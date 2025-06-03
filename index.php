<?php
// Main entry point of the application

// Include configuration
require_once 'config.php';

// Autoload controllers and models (simple autoloader)
spl_autoload_register(function ($className) {
    $paths = [
        'app/controllers/',
        'app/models/'
    ];
    foreach ($paths as $path) {
        $file = __DIR__ . '/' . $path . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// --- Basic Routing ---
// Get the requested path from the URL
$requestPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$basePath = trim(BASE_URL, '/'); // BASE_URL is defined in config.php

// Remove base path from request path if present
if (!empty($basePath) && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}
$requestPath = trim($requestPath, '/');

$segments = $requestPath ? explode('/', $requestPath) : [];

// --- Determine Controller and Action ---
$controllerName = '';
$actionName = '';
$params = [];

if (empty($segments)) {
    // No segments in URL (e.g., accessing the root like /mainsystem/)
    if (isLoggedIn()) {
        $controllerName = 'DashboardController';
        $actionName = 'index';
    } else {
        $controllerName = 'AuthController';
        $actionName = 'login';
    }
} else {
    // At least one segment (controller specified)
    $controllerName = ucfirst(strtolower($segments[0])) . 'Controller';
    // If only controller is specified, default action is 'index'
    // If controller and action are specified, use them
    $actionName = !empty($segments[1]) ? strtolower($segments[1]) : 'index';

    // Collect remaining segments as parameters
    if (count($segments) > 2) {
        $params = array_slice($segments, 2);
    }
}


// --- Controller Instantiation and Action Execution ---
$controllerFile = __DIR__ . '/app/controllers/' . $controllerName . '.php';

if (file_exists($controllerFile)) {
    // Autoloader should have loaded it if class name matches file name
    if (class_exists($controllerName)) {
        $controller = new $controllerName($pdo); // Pass PDO to controller constructor
        if (method_exists($controller, $actionName)) {
            // Call the action, passing any parameters
            call_user_func_array([$controller, $actionName], $params);
        } else {
            // Action not found in the controller
            error_log("Action {$actionName} not found in controller {$controllerName}");
            header("HTTP/1.0 404 Not Found");
            echo "<h1>404 Not Found</h1><p>The page you requested could not be found (action missing).</p>";
            echo "<p>Controller: {$controllerName}, Action: {$actionName}</p>";
        }
    } else {
        // Controller class not found within the file (e.g., naming mismatch)
        error_log("Controller class {$controllerName} not found in file {$controllerFile}");
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller class missing).</p>";
        echo "<p>Controller: {$controllerName}</p>";
    }
} else {
    // Controller file itself does not exist
    // This block handles truly missing controller files or provides fallbacks.

    // If not logged in and trying to access anything other than auth actions, redirect to login.
    // This is a broad catch-all. Specific checks for 'auth/login' or 'auth/processlogin'
    // might be needed if you want to allow access to those even if AuthController file was hypothetically missing.
    if (!isLoggedIn() && strtolower($controllerName) !== 'authcontroller') {
        redirect('auth/login');
    } else {
        error_log("Controller file not found: {$controllerFile}");
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller file missing).</p>";
        echo "<p>Requested Path: {$requestPath}</p>";
        echo "<p>Attempted Controller: {$controllerName}</p>";
    }
}

?>
