<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$offset = max(0, (int) ($_GET['offset'] ?? 0));
$limit = max(1, min(20, (int) ($_GET['limit'] ?? 6)));

try {
    // Fetch one extra row to know whether more results exist.
    $rows = searchRooms($_GET, $limit + 1, $offset);
} catch (Throwable $e) {
    writeLog('Room API failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['rooms' => [], 'hasMore' => false]);
    exit;
}

$hasMore = count($rows) > $limit;
$rows = array_slice($rows, 0, $limit);

echo json_encode([
    'rooms' => array_map(static fn (array $room): array => [
        'id' => (int) $room['id'],
        'houseId' => (int) $room['boarding_house_id'],
        'title' => $room['name'],
        'house' => $room['house_name'],
        'rent' => (float) $room['monthly_rent'],
        'type' => roomTypeLabel($room['room_type']),
        'barangay' => $room['barangay'],
        'city' => $room['city'],
        'school' => $room['nearby_school'],
        'distance' => $room['distance_to_school'],
        'rating' => $room['avg_rating'] !== null ? (float) $room['avg_rating'] : null,
        'image' => photoUrl($room['cover_photo']),  // relative to html/ and php/ pages
        'availableSlots' => (int) $room['available_slots'],
        'amenities' => amenityList($room['amenities']),
        'latitude' => $room['latitude'] !== null ? (float) $room['latitude'] : null,
        'longitude' => $room['longitude'] !== null ? (float) $room['longitude'] : null,
        'url' => '../php/room.php?id=' . (int) $room['id'],
    ], $rows),
    'hasMore' => $hasMore,
]);
