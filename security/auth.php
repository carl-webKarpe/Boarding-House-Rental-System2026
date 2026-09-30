<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/audit_log.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/upload_security.php';

function loginUser(string $email, string $password, bool $rememberMe = false): array {
    startSecureSession();

    if (!checkRateLimit('login', getClientIp(), 10, 15)) {
        writeLog('Rate limit exceeded for login from ' . getClientIp(), 'WARN');
        return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
    }

    $email = sanitizeEmail($email);
    $validation = validateEmailValue($email);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }

    if (trim($password) === '') {
        return ['success' => false, 'message' => 'Password is required.'];
    }

    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, role, approval_status, is_active, failed_login_attempts, locked_until FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $validation['value']]);
        $user = $stmt->fetch();

        if (!$user) {
            recordFailedLogin($email);
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Account is temporarily locked. Please try again later.'];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            recordFailedLogin($email);
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        if ((int) $user['is_active'] !== 1) {
            return ['success' => false, 'message' => 'This account has been deactivated. Please contact the administrator.'];
        }

        if ($user['approval_status'] === 'rejected') {
            return ['success' => false, 'message' => 'Your registration was not approved. Please contact the administrator.'];
        }

        $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['email'] = (string) $user['email'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['is_logged_in'] = true;

        // "Remember me" only pre-fills the email on the client; clear the old
        // insecure cookie that stored the email in plain base64.
        setcookie('remember_me', '', time() - 3600, '/', '', isHttps(), true);

        auditLog('login', 'User logged in', (int) $user['id']);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'username' => (string) $user['username'],
            'role' => (string) $user['role'],
            'redirect' => homePathForRole((string) $user['role']),
        ];
    } catch (Throwable $e) {
        writeLog('Login error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Login failed.'];
    }
}

/**
 * Registers a tenant or landlord with their profile details and documents.
 *
 * @param array $data  form fields
 * @param array $files uploaded files keyed by document type, e.g. ['government_id' => $_FILES['idFile']]
 */
function registerUser(array $data, array $files = []): array {
    startSecureSession();
    $role = in_array($data['role'] ?? '', ['tenant', 'landlord'], true) ? $data['role'] : 'tenant';

    $emailValidation = validateEmailValue($data['email'] ?? '');
    if (!$emailValidation['valid']) {
        return ['success' => false, 'message' => $emailValidation['message']];
    }

    $rawUsername = trim((string) ($data['username'] ?? ''));
    if ($rawUsername !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $rawUsername)) {
        return ['success' => false, 'message' => 'Username may only contain letters, numbers, and underscores.'];
    }
    $usernameValidation = validateUsernameValue($rawUsername);
    if (!$usernameValidation['valid']) {
        return ['success' => false, 'message' => $usernameValidation['message']];
    }

    $passwordValidation = validatePasswordValue($data['password'] ?? '');
    if (!$passwordValidation['valid']) {
        return ['success' => false, 'message' => $passwordValidation['message']];
    }

    if (($data['password'] ?? '') !== ($data['confirmPassword'] ?? '')) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    $profile = validateProfileFields($data);
    if (isset($profile['error'])) {
        return ['success' => false, 'message' => $profile['error']];
    }

    $landlordProfile = null;
    if ($role === 'landlord') {
        $landlordProfile = [];
        foreach (['propertyName' => 'Boarding house name', 'businessAddress' => 'Business address', 'barangay' => 'Barangay', 'municipality' => 'Municipality/city', 'province' => 'Province'] as $key => $label) {
            $value = mb_substr(trim((string) ($data[$key] ?? '')), 0, 150);
            if ($value === '') {
                return ['success' => false, 'message' => $label . ' is required.'];
            }
            $landlordProfile[$key] = $value;
        }
    }

    $requiredDocs = $role === 'landlord' ? ['government_id', 'selfie'] : ['id'];
    foreach ($requiredDocs as $docKey) {
        if (empty($files[$docKey]) || ($files[$docKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'message' => 'Please upload all required documents.'];
        }
    }
    if ($role === 'tenant') {
        $idType = (string) ($data['idType'] ?? '');
        if (!in_array($idType, ['student_id', 'government_id'], true)) {
            return ['success' => false, 'message' => 'Please select which ID you are uploading.'];
        }
        // Store the tenant's ID under the type they picked.
        $files = [$idType => $files['id']];
    }

    $storedFiles = [];
    $pdo = getDb();
    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1');
        $stmt->execute([':email' => $emailValidation['value'], ':username' => $usernameValidation['value']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email or username already exists.'];
        }

        foreach ($files as $docType => $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $upload = storeUploadedFile($file, BH_DOCUMENT_DIR, DOCUMENT_MIME_TYPES);
            if (!$upload['success']) {
                throw new InvalidArgumentException($upload['message']);
            }
            $storedFiles[$docType] = $upload;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, first_name, middle_name, last_name, gender, birth_date, phone, address, approval_status, is_active, created_at)
            VALUES (:username, :email, :password_hash, :role, :first_name, :middle_name, :last_name, :gender, :birth_date, :phone, :address, :approval_status, 1, NOW())');
        $stmt->execute([
            ':username' => $usernameValidation['value'],
            ':email' => $emailValidation['value'],
            ':password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            ':role' => $role,
            ':first_name' => $profile['first_name'],
            ':middle_name' => $profile['middle_name'],
            ':last_name' => $profile['last_name'],
            ':gender' => $profile['gender'],
            ':birth_date' => $profile['birth_date'],
            ':phone' => $profile['phone'],
            ':address' => $profile['address'],
            ':approval_status' => $role === 'landlord' ? 'pending' : 'approved',
        ]);
        $userId = (int) $pdo->lastInsertId();

        if ($landlordProfile !== null) {
            $pdo->prepare('INSERT INTO landlord_profiles (user_id, property_name, business_address, barangay, municipality, province) VALUES (:user_id, :property_name, :business_address, :barangay, :municipality, :province)')
                ->execute([
                    ':user_id' => $userId,
                    ':property_name' => $landlordProfile['propertyName'],
                    ':business_address' => $landlordProfile['businessAddress'],
                    ':barangay' => $landlordProfile['barangay'],
                    ':municipality' => $landlordProfile['municipality'],
                    ':province' => $landlordProfile['province'],
                ]);
        }

        $docStmt = $pdo->prepare('INSERT INTO user_documents (user_id, doc_type, file_name, original_name, mime_type) VALUES (:user_id, :doc_type, :file_name, :original_name, :mime_type)');
        foreach ($storedFiles as $docType => $upload) {
            $docStmt->execute([
                ':user_id' => $userId,
                ':doc_type' => $docType,
                ':file_name' => $upload['file_name'],
                ':original_name' => $upload['original_name'],
                ':mime_type' => $upload['mime'],
            ]);
        }

        $pdo->commit();
        auditLog('create', ucfirst($role) . ' registered', $userId);

        $message = $role === 'landlord'
            ? 'Registration successful. Your landlord account is waiting for administrator approval.'
            : 'Registration successful. You can now log in.';
        return ['success' => true, 'message' => $message];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($storedFiles as $upload) {
            @unlink(BH_DOCUMENT_DIR . '/' . $upload['file_name']);
        }
        if ($e instanceof InvalidArgumentException) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        writeLog('Registration error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

/** Validates the personal details shared by tenant and landlord registration. */
function validateProfileFields(array $data): array {
    $firstName = mb_substr(trim((string) ($data['firstName'] ?? '')), 0, 80);
    $middleName = mb_substr(trim((string) ($data['middleName'] ?? '')), 0, 80);
    $lastName = mb_substr(trim((string) ($data['lastName'] ?? '')), 0, 80);
    if ($firstName === '' || $lastName === '') {
        return ['error' => 'First and last name are required.'];
    }

    $gender = (string) ($data['gender'] ?? '');
    if (!in_array($gender, ['male', 'female', 'prefer_not_to_say'], true)) {
        return ['error' => 'Please select your gender.'];
    }

    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['dob'] ?? ''));
    if (!$birthDate) {
        return ['error' => 'Please enter a valid date of birth.'];
    }
    $age = $birthDate->diff(new DateTimeImmutable('today'))->y;
    if ($birthDate > new DateTimeImmutable('today') || $age < 16 || $age > 120) {
        return ['error' => 'You must be at least 16 years old to register.'];
    }

    $phoneValidation = validatePhoneValue($data['mobileNumber'] ?? $data['contactNumber'] ?? '');
    if (!$phoneValidation['valid']) {
        return ['error' => $phoneValidation['message']];
    }

    $address = mb_substr(trim((string) ($data['currentAddress'] ?? $data['homeAddress'] ?? '')), 0, 255);
    if ($address === '') {
        return ['error' => 'Address is required.'];
    }

    return [
        'first_name' => $firstName,
        'middle_name' => $middleName !== '' ? $middleName : null,
        'last_name' => $lastName,
        'gender' => $gender,
        'birth_date' => $birthDate->format('Y-m-d'),
        'phone' => $phoneValidation['value'],
        'address' => $address,
    ];
}

function changePassword(int $userId, string $newPassword): bool {
    $passwordValidation = validatePasswordValue($newPassword);
    if (!$passwordValidation['valid']) {
        return false;
    }

    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
        auditLog('password_change', 'Password changed', $userId);
        return true;
    } catch (Throwable $e) {
        writeLog('Password update failed: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}
