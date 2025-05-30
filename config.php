<?php
/**
 * Database Configuration, Session Management, and Role/Capability Definitions
 */

// ** MySQL settings ** //
define('DB_NAME', 'mainsystem');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('BASE_URL', '/mainsystem/'); 

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

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function redirect($url) {
    header("Location: " . BASE_URL . ltrim($url, '/'));
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function generateBreadcrumbs($breadcrumbs = []) {
    if (empty($breadcrumbs)) return '';
    $html = '<nav aria-label="breadcrumb" class="mt-3 mb-3"><ol class="breadcrumb">';
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
    $html .= '</ol></nav>';
    return $html;
}

// --- Role and Capability Management ---

/**
 * Define all available capabilities in the system.
 * These are granular permissions for specific actions.
 */
define('CAPABILITIES', [
    'ACCESS_ADMIN_PANEL' => 'Access Admin Panel',
    'MANAGE_USERS' => 'Manage Users (Add, Edit, Delete)',
    'MANAGE_ROLES_PERMISSIONS' => 'Manage Roles & Permissions', // For this new settings page
    'MANAGE_DEPARTMENTS' => 'Manage Departments',
    'MANAGE_SITE_SETTINGS' => 'Manage Site Settings',
    'VIEW_REPORTS' => 'View Reports (Example)',
    // Add capabilities for your other modules like Open Office, IT, Rap, SES
    'MANAGE_OPEN_OFFICE_RESERVATIONS' => 'Manage Open Office Reservations',
    'MANAGE_IT_REQUESTS' => 'Manage IT Requests',
    'MANAGE_RAP_CALENDAR' => 'Manage Rap Calendar',
    'MANAGE_SES_DATA' => 'Manage SES Data'
]);

/**
 * Define roles and their assigned capabilities.
 * For now, this is hardcoded. A more advanced system might store this in the database.
 */
define('ROLE_CAPABILITIES', [
    'admin' => [ // Super admin has all defined capabilities
        'ACCESS_ADMIN_PANEL',
        'MANAGE_USERS',
        'MANAGE_ROLES_PERMISSIONS',
        'MANAGE_DEPARTMENTS',
        'MANAGE_SITE_SETTINGS',
        'VIEW_REPORTS',
        'MANAGE_OPEN_OFFICE_RESERVATIONS',
        'MANAGE_IT_REQUESTS',
        'MANAGE_RAP_CALENDAR',
        'MANAGE_SES_DATA'
    ],
    'editor' => [ // Example editor role
        'ACCESS_ADMIN_PANEL', // May need access to parts of admin
        'VIEW_REPORTS',
        'MANAGE_RAP_CALENDAR' // Example: Editor can manage calendar
    ],
    'user' => [ // Standard user
        // Add capabilities specific to standard users if any, e.g., 'SUBMIT_SERVICE_REQUEST'
    ]
]);

/**
 * Get all defined roles.
 * @return array
 */
function getDefinedRoles() {
    return array_keys(ROLE_CAPABILITIES);
}


/**
 * Check if the currently logged-in user has a specific capability.
 *
 * @param string $capability The capability key (e.g., 'MANAGE_USERS').
 * @return bool True if the user has the capability, false otherwise.
 */
function userHasCapability($capability) {
    if (!isLoggedIn()) {
        return false;
    }
    $userRole = $_SESSION['user_role'] ?? 'user'; // Default to 'user' if role not set

    if (isset(ROLE_CAPABILITIES[$userRole]) && in_array($capability, ROLE_CAPABILITIES[$userRole])) {
        return true;
    }
    return false;
}

?>
