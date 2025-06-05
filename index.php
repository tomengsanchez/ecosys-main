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
        $controllerName = 'DashboardController'; // Default for logged-in users
        $actionName = 'index';
    } else {
        $controllerName = 'AuthController'; // Default for logged-out users
        $actionName = 'login';
    }
} else {
    // At least one segment (controller specified)
    // Ensure the first letter of the segment is capitalized for the controller name.
    $controllerName = ucfirst($segments[0]) . 'Controller'; 
    
    $actionName = !empty($segments[1]) ? $segments[1] : 'index'; // Preserve original case for action

    // Collect remaining segments as parameters
    if (count($segments) > 2) {
        $params = array_slice($segments, 2);
    }
}

// --- Controller Instantiation and Action Execution ---
$controllerFile = __DIR__ . '/app/controllers/' . $controllerName . '.php';

if (file_exists($controllerFile)) {
    // Autoloader should handle the require_once for $controllerFile

    if (class_exists($controllerName)) {
        $controller = new $controllerName($pdo); 
        
        $resolvedActionName = '';
        $actionExists = false;

        // Check for method with original case and then lowercase
        if (method_exists($controller, $actionName)) {
            $resolvedActionName = $actionName;
            $actionExists = true;
        } else {
            $lowercaseActionName = strtolower($actionName);
            if (method_exists($controller, $lowercaseActionName)) { 
                $resolvedActionName = $lowercaseActionName;
                $actionExists = true;
            }
        }

        if ($actionExists) {
            call_user_func_array([$controller, $resolvedActionName], $params);
        } else {
            error_log("Action '{$actionName}' (and variants) not found in controller '{$controllerName}'. Requested Path: '{$requestPath}'");
            header("HTTP/1.0 404 Not Found");
            echo "<h1>404 Not Found</h1><p>The page you requested could not be found (action method missing).</p>";
            // Optionally, load a dedicated 404 view
            // include __DIR__ . '/app/views/errors/404.php';
        }
    } else {
        error_log("Controller class '{$controllerName}' NOT found in file '{$controllerFile}' after checking existence. Requested Path: '{$requestPath}'");
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller class missing).</p>";
        // Optionally, load a dedicated 404 view
    }
} else {
    error_log("Controller file not found: '{$controllerFile}'. Requested Path: '{$requestPath}'");
    if (!isLoggedIn() && strtolower($controllerName) !== 'authcontroller') {
        redirect('auth/login');
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller file missing).</p>";
        // Optionally, load a dedicated 404 view
    }
}

?>
