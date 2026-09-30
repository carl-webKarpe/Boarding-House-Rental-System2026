<?php

declare(strict_types=1);

/**
 * Database queries for boarding houses, rooms, photos, reviews and favorites.
 * All queries use prepared statements.
 */

/** SQL condition for rooms the public is allowed to see. */
const PUBLIC_ROOM_CONDITION = "r.status <> 'hidden' AND bh.status = 'active' AND u.approval_status = 'approved' AND u.is_active = 1";

const ROOM_SELECT = "
    SELECT r.*, bh.name AS house_name, bh.address, bh.barangay, bh.city, bh.province,
           bh.nearby_school, bh.distance_to_school, bh.gender_policy, bh.landlord_id, bh.latitude, bh.longitude,
           (SELECT p.path FROM room_photos p WHERE p.room_id = r.id ORDER BY p.sort_order, p.id LIMIT 1) AS cover_photo,
           (SELECT ROUND(AVG(rv.rating), 1) FROM reviews rv WHERE rv.room_id = r.id) AS avg_rating,
           (SELECT COUNT(*) FROM reviews rv WHERE rv.room_id = r.id) AS review_count
    FROM rooms r
    JOIN boarding_houses bh ON bh.id = r.boarding_house_id
    JOIN users u ON u.id = bh.landlord_id";

/**
 * Search visible rooms.
 * Filters: q, type, min_price, max_price, amenity, gender, available (bool), sort.
 */
function searchRooms(array $filters, int $limit = 60, int $offset = 0): array {
    $where = [PUBLIC_ROOM_CONDITION];
    $params = [];

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(r.name LIKE :q1 OR bh.name LIKE :q2 OR bh.barangay LIKE :q3 OR bh.city LIKE :q4 OR bh.nearby_school LIKE :q5 OR bh.address LIKE :q6)';
        for ($i = 1; $i <= 6; $i++) {
            $params[':q' . $i] = '%' . $q . '%';
        }
    }

    $type = (string) ($filters['type'] ?? '');
    if (isset(ROOM_TYPES[$type])) {
        $where[] = 'r.room_type = :type';
        $params[':type'] = $type;
    }

    if (($filters['min_price'] ?? '') !== '' && is_numeric($filters['min_price'])) {
        $where[] = 'r.monthly_rent >= :min_price';
        $params[':min_price'] = (float) $filters['min_price'];
    }
    if (($filters['max_price'] ?? '') !== '' && is_numeric($filters['max_price'])) {
        $where[] = 'r.monthly_rent <= :max_price';
        $params[':max_price'] = (float) $filters['max_price'];
    }

    $amenity = trim((string) ($filters['amenity'] ?? ''));
    if ($amenity !== '') {
        $where[] = 'r.amenities LIKE :amenity';
        $params[':amenity'] = '%' . $amenity . '%';
    }

    $gender = (string) ($filters['gender'] ?? '');
    if (in_array($gender, ['male', 'female'], true)) {
        $where[] = "bh.gender_policy IN ('any', :gender)";
        $params[':gender'] = $gender;
    }

    if (!empty($filters['available'])) {
        $where[] = "r.status = 'available' AND r.available_slots > 0";
    }

    $order = match ((string) ($filters['sort'] ?? '')) {
        'price_asc' => 'r.monthly_rent ASC',
        'price_desc' => 'r.monthly_rent DESC',
        'rating' => 'avg_rating IS NULL, avg_rating DESC',
        default => 'r.created_at DESC, r.id DESC',
    };

    $sql = ROOM_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order
        . ' LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset);
    $stmt = getDb()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Load one room. Hidden rooms are only returned to their landlord or admins.
 */
function findRoom(int $roomId): ?array {
    $extraColumns = ', u.first_name AS landlord_first_name, u.last_name AS landlord_last_name, u.username AS landlord_username,
           u.email AS landlord_email, u.phone AS landlord_phone, u.approval_status AS landlord_status, u.is_active AS landlord_active,
           bh.description AS house_description, bh.house_rules, bh.contact_phone, bh.status AS house_status
    FROM rooms r';
    $sql = str_replace('FROM rooms r', $extraColumns, ROOM_SELECT) . ' WHERE r.id = :id LIMIT 1';
    $stmt = getDb()->prepare($sql);
    $stmt->execute([':id' => $roomId]);
    return $stmt->fetch() ?: null;
}

function canViewRoom(array $room): bool {
    $role = currentRole();
    if (isAdminRole($role) || (int) $room['landlord_id'] === currentUserId()) {
        return true;
    }
    return $room['status'] !== 'hidden'
        && $room['house_status'] === 'active'
        && $room['landlord_status'] === 'approved'
        && (int) $room['landlord_active'] === 1;
}

function roomPhotos(int $roomId): array {
    $stmt = getDb()->prepare('SELECT id, path FROM room_photos WHERE room_id = :id ORDER BY sort_order, id');
    $stmt->execute([':id' => $roomId]);
    return $stmt->fetchAll();
}

function roomReviews(int $roomId): array {
    $stmt = getDb()->prepare('SELECT rv.*, u.username, u.first_name, u.last_name FROM reviews rv JOIN users u ON u.id = rv.tenant_id WHERE rv.room_id = :id ORDER BY rv.created_at DESC');
    $stmt->execute([':id' => $roomId]);
    return $stmt->fetchAll();
}

function favoriteRoomIds(?int $userId): array {
    if (!$userId) {
        return [];
    }
    $stmt = getDb()->prepare('SELECT room_id FROM favorites WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function favoriteRooms(int $userId): array {
    $stmt = getDb()->prepare(ROOM_SELECT . ' JOIN favorites f ON f.room_id = r.id WHERE f.user_id = :id AND ' . PUBLIC_ROOM_CONDITION . ' ORDER BY f.created_at DESC');
    $stmt->execute([':id' => $userId]);
    return $stmt->fetchAll();
}

/* ---------------------------------------------------------------------
 * Landlord-owned data (every query checks ownership)
 * ------------------------------------------------------------------- */

function landlordHouses(int $landlordId): array {
    $stmt = getDb()->prepare("
        SELECT bh.*,
               (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = bh.id) AS room_count,
               (SELECT COALESCE(SUM(r.available_slots), 0) FROM rooms r WHERE r.boarding_house_id = bh.id AND r.status = 'available') AS open_slots
        FROM boarding_houses bh WHERE bh.landlord_id = :id ORDER BY bh.created_at DESC");
    $stmt->execute([':id' => $landlordId]);
    return $stmt->fetchAll();
}

function landlordHouse(int $houseId, int $landlordId): ?array {
    $stmt = getDb()->prepare('SELECT * FROM boarding_houses WHERE id = :id AND landlord_id = :landlord LIMIT 1');
    $stmt->execute([':id' => $houseId, ':landlord' => $landlordId]);
    return $stmt->fetch() ?: null;
}

function houseRooms(int $houseId): array {
    $stmt = getDb()->prepare("
        SELECT r.*,
               (SELECT p.path FROM room_photos p WHERE p.room_id = r.id ORDER BY p.sort_order, p.id LIMIT 1) AS cover_photo,
               (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id AND b.status = 'pending') AS pending_bookings
        FROM rooms r WHERE r.boarding_house_id = :id ORDER BY r.created_at DESC");
    $stmt->execute([':id' => $houseId]);
    return $stmt->fetchAll();
}

function landlordRoom(int $roomId, int $landlordId): ?array {
    $stmt = getDb()->prepare('SELECT r.*, bh.name AS house_name FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE r.id = :id AND bh.landlord_id = :landlord LIMIT 1');
    $stmt->execute([':id' => $roomId, ':landlord' => $landlordId]);
    return $stmt->fetch() ?: null;
}

function landlordIsApproved(int $landlordId): bool {
    $stmt = getDb()->prepare("SELECT approval_status FROM users WHERE id = :id");
    $stmt->execute([':id' => $landlordId]);
    return $stmt->fetchColumn() === 'approved';
}
