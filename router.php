<?php
/**
 * Router for PHP's built-in web server (replaces Apache/XAMPP for local work).
 * Start it with:  php -S localhost:8000 router.php
 */

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if ($path === '/' || $path === '/index.php') {
    header('Location: /html/index.html');
    return true;
}

// Never serve private folders or config files to the browser.
$blocked = '#^/(storage|security|includes|database|\.git)(/|$)|/\.env|\.sql$|\.md$|^/router\.php$#i';
if (preg_match($blocked, $path) || str_contains($path, '..')) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

return false; // let the built-in server handle the file normally
