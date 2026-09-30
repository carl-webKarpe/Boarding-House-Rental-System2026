<?php

declare(strict_types=1);

const ROLE_SUPER_ADMIN = 'super_admin';
const ROLE_ADMIN = 'admin';
const ROLE_STAFF = 'staff';
const ROLE_LANDLORD = 'landlord';
const ROLE_TENANT = 'tenant';
const ROLE_USER = 'user'; // legacy accounts; treated as tenants

const ADMIN_ROLES = [ROLE_SUPER_ADMIN, ROLE_ADMIN];

function roleHierarchy(string $role): array {
    return match ($role) {
        ROLE_SUPER_ADMIN => [ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF, ROLE_USER],
        ROLE_ADMIN => [ROLE_ADMIN, ROLE_STAFF, ROLE_USER],
        ROLE_STAFF => [ROLE_STAFF, ROLE_USER],
        ROLE_LANDLORD => [ROLE_LANDLORD],
        default => [ROLE_TENANT, ROLE_USER],
    };
}

function userCan(string $requiredRole, ?string $userRole = null): bool {
    $role = $userRole ?? ($_SESSION['role'] ?? ROLE_USER);
    $allowed = roleHierarchy($role);
    return in_array($requiredRole, $allowed, true);
}

function isAdminRole(string $role): bool {
    return in_array($role, ADMIN_ROLES, true);
}

function isTenantRole(string $role): bool {
    return in_array($role, [ROLE_TENANT, ROLE_USER], true);
}

/** Page each role lands on after logging in (relative to a first-level folder). */
function homePathForRole(string $role): string {
    if (isAdminRole($role)) {
        return '../admin/adminpanel.php';
    }
    if ($role === ROLE_LANDLORD) {
        return '../landlord/dashboard.php';
    }
    return '../php/browse-rooms.php';
}
