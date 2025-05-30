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
 *
 * Establishes a PDO connection object.
 * You can use $pdo in other parts of your application to interact with the database.
 */
$pdo = null;
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (\PDOException $e) {
    // In a production environment, you might want to log this error and display a generic message.
    // For development, throwing the exception can be helpful for debugging.
    error_log("Database Connection Error: " . $e->getMessage());
    // You could die here or handle it more gracefully depending on your application's needs.
    die("Could not connect to the database. Please check your configuration. Error: " . $e->getMessage());
}

/**
 * Session Management
 *
 * Starts or resumes a session. This should be called before any output is sent to the browser.
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

?>
