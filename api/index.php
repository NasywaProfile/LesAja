<?php
// Set working directory to the root of the project so relative imports function correctly
chdir(__DIR__ . '/../');

// Get the request path
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize trailing slashes
if ($uri !== '/' && substr($uri, -1) === '/') {
    $uri = rtrim($uri, '/');
}

// Map root to landing.php
if ($uri === '/' || $uri === '' || $uri === '/index.php' || $uri === '/index') {
    $_SERVER['PHP_SELF'] = '/landing.php';
    $_SERVER['SCRIPT_NAME'] = '/landing.php';
    require 'landing.php';
    exit;
}

// Strip leading slash for path checks
$file = ltrim($uri, '/');

// Check if file exists exactly as a php file
if (file_exists($file) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['PHP_SELF'] = $uri;
    $_SERVER['SCRIPT_NAME'] = $uri;
    require $file;
    exit;
}

// Check if file exists with .php extension appended
if (file_exists($file . '.php') && is_file($file . '.php')) {
    $_SERVER['PHP_SELF'] = $uri . '.php';
    $_SERVER['SCRIPT_NAME'] = $uri . '.php';
    require $file . '.php';
    exit;
}

// If file is not found, return 404
http_response_code(404);
echo "404 Not Found";
?>
