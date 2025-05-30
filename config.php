<?php
/**
 * Database Configuration and Session Management
 *
 * This file defines the database connection constants, establishes a PDO connection,
 * and starts the session.
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database */
define('DB_NAME', 'mainsystem');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', ''); // It's highly recommended to use a strong password in production

/** MySQL hostname */
define('DB_HOST', 'localhost'); // Or your specific host IP/name

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**
 * Base URL of the application
 * Example: http://localhost/mainsystem/
 * Make sure it ends with a slash /
 */
define('BASE_URL', '/mainsystem/'); // Adjust this to your actual base URL

/**
 * PDO Database Connection
 */
$pdo = null;
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (\PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Could not connect to the database. Please check your configuration. Error: " . $e->getMessage());
}

/**
 * Session Management
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper function for redirecting
 */
function redirect($url) {
    header("Location: " . BASE_URL . ltrim($url, '/'));
    exit;
}

/**
 * Helper function to check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Helper function to generate Bootstrap breadcrumbs.
 *
 * @param array $breadcrumbs An array of breadcrumb items.
 * Each item should be an associative array with 'label' and optionally 'url'.
 * The last item's URL is ignored as it's the active page.
 * @return string HTML for the breadcrumbs.
 */
function generateBreadcrumbs($breadcrumbs = []) {
    if (empty($breadcrumbs)) {
        return '';
    }

    $html = '<nav aria-label="breadcrumb" class="mt-3 mb-3">';
    $html .= '<ol class="breadcrumb">';

    $count = count($breadcrumbs);
    foreach ($breadcrumbs as $index => $crumb) {
        $isActive = ($index === $count - 1);
        $label = htmlspecialchars($crumb['label']);

        if ($isActive) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
        } else {
            $url = isset($crumb['url']) ? BASE_URL . ltrim($crumb['url'], '/') : '#';
            $html .= '<li class="breadcrumb-item"><a href="' . $url . '">' . $label . '</a></li>';
        }
    }

    $html .= '</ol>';
    $html .= '</nav>';
    return $html;
}

?>
