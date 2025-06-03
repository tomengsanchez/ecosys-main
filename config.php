<?php
// sleep(3);
/**
 * Database Configuration, Session Management, Role/Capability, and Email Definitions
 */

// ** MySQL settings ** //
define('DB_NAME', 'maindb2');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('BASE_URL', '/mainsystem/'); 

// Default Timezone
date_default_timezone_set('Asia/Manila'); 

// Default Time Format if not set in options
define('DEFAULT_TIME_FORMAT', 'Y-m-d H:i');

// --- Email Configuration Defaults ---
define('DEFAULT_SITE_EMAIL_FROM', 'noreply@example.com'); 
define('DEFAULT_ADMIN_EMAIL_NOTIFICATIONS', 'admin@example.com'); 
define('DEFAULT_EMAIL_NOTIFICATIONS_ENABLED', 'on'); 


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

spl_autoload_register(function ($className) {
    $modelPath = __DIR__ . '/app/models/' . $className . '.php';
    if (file_exists($modelPath)) {
        require_once $modelPath;
    }
});


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

function get_site_time_format() {
    global $pdo;
    static $siteTimeFormat = null;

    if ($siteTimeFormat !== null) {
        return $siteTimeFormat;
    }

    if (!class_exists('OptionModel')) {
        $modelPath = __DIR__ . '/app/models/OptionModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { 
            error_log("OptionModel class not found in get_site_time_format(). Using default.");
            $siteTimeFormat = DEFAULT_TIME_FORMAT;
            return $siteTimeFormat;
        }
    }
    
    $optionModel = new OptionModel($pdo);
    $formatFromDb = $optionModel->getOption('site_time_format');

    if ($formatFromDb) {
        $siteTimeFormat = $formatFromDb;
    } else {
        $siteTimeFormat = DEFAULT_TIME_FORMAT;
    }
    return $siteTimeFormat;
}

function format_datetime_for_display($datetimeString, $customFormat = null) {
    if (empty($datetimeString) || $datetimeString === '0000-00-00 00:00:00') {
        return 'N/A'; 
    }
    try {
        $formatToUse = $customFormat ?: get_site_time_format();
        $date = new DateTime($datetimeString); 
        return $date->format($formatToUse);
    } catch (Exception $e) {
        error_log("Error formatting date '{$datetimeString}': " . $e->getMessage());
        return htmlspecialchars($datetimeString); 
    }
}

function send_system_email($to, $subject, $message, $additional_headers = null) {
    global $pdo;

    if (!class_exists('OptionModel')) {
        $modelPath = __DIR__ . '/app/models/OptionModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { 
            error_log("OptionModel class not found in send_system_email(). Email not sent.");
            return false;
        }
    }
    $optionModel = new OptionModel($pdo);

    $notificationsEnabled = $optionModel->getOption('site_email_notifications_enabled', DEFAULT_EMAIL_NOTIFICATIONS_ENABLED);
    if (strtolower($notificationsEnabled) !== 'on') {
        error_log("Email notifications are disabled. Email to {$to} with subject '{$subject}' not sent.");
        return false; 
    }

    $siteName = $optionModel->getOption('site_name', 'Mainsystem'); 
    $fromEmail = $optionModel->getOption('site_email_from', DEFAULT_SITE_EMAIL_FROM);

    $headers = "From: {$siteName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n"; 

    if (is_array($additional_headers)) {
        foreach ($additional_headers as $header) {
            $headers .= $header . "\r\n";
        }
    }

    $fullMessage = $message . "\r\n\r\n--\r\nThis is an automated message from " . $siteName . ".\r\n" . BASE_URL;
    $subjectWithSiteName = "[{$siteName}] " . $subject;

    if (mail($to, $subjectWithSiteName, $fullMessage, $headers)) {
        error_log("Email successfully sent to {$to} with subject '{$subjectWithSiteName}'.");
        return true;
    } else {
        error_log("Failed to send email to {$to} with subject '{$subjectWithSiteName}'. Check mail server configuration.");
        return false;
    }
}


// --- Role and Capability Management ---
define('CAPABILITIES', [
    // General Admin
    'ACCESS_ADMIN_PANEL' => 'Access Admin Panel',
    'MANAGE_USERS' => 'Manage Users (Add, Edit, Delete)',
    'MANAGE_ROLES' => 'Manage Roles (Add, Edit, Delete)', 
    'MANAGE_ROLES_PERMISSIONS' => 'Manage Roles & Permissions',
    'MANAGE_DEPARTMENTS' => 'Manage Departments',
    'MANAGE_SITE_SETTINGS' => 'Manage Site Settings',
    'VIEW_REPORTS' => 'View Reports (Example)',
    
    // Open Office - Rooms (Physical Entities)
    'MANAGE_ROOMS' => 'Manage All Aspects of Rooms (Legacy/Super)',
    'VIEW_ROOMS'   => 'View Rooms List',
    'CREATE_ROOMS' => 'Create New Rooms',
    'EDIT_ROOMS'   => 'Edit Existing Rooms',
    'DELETE_ROOMS' => 'Delete Rooms',
    
    // Open Office - Room Reservations
    'CREATE_ROOM_RESERVATIONS' => 'Create Own Room Reservations',
    'EDIT_OWN_ROOM_RESERVATIONS' => 'Edit Own Pending Room Reservations', 
    'CANCEL_OWN_ROOM_RESERVATIONS' => 'Cancel Own Pending Room Reservations',
    'VIEW_ALL_ROOM_RESERVATIONS' => 'View All Room Reservations', 
    'APPROVE_DENY_ROOM_RESERVATIONS' => 'Approve/Deny Room Reservations', 
    'EDIT_ANY_ROOM_RESERVATION' => 'Edit Any Room Reservation', 
    'DELETE_ANY_ROOM_RESERVATION' => 'Delete Any Room Reservation Record', 

    // Open Office - Vehicles
    'VIEW_VEHICLES'   => 'View Vehicles List',
    'CREATE_VEHICLES' => 'Create New Vehicles',
    'EDIT_VEHICLES'   => 'Edit Existing Vehicles',
    'DELETE_VEHICLES' => 'Delete Vehicles',
    // Add more vehicle-related capabilities if needed (e.g., for reservations)
    // 'CREATE_VEHICLE_RESERVATIONS' => 'Create Own Vehicle Reservations',
    // 'VIEW_ALL_VEHICLE_RESERVATIONS' => 'View All Vehicle Reservations',
    // 'APPROVE_DENY_VEHICLE_RESERVATIONS' => 'Approve/Deny Vehicle Reservations',

    // Other Modules (examples, can be expanded)
    'MANAGE_IT_REQUESTS' => 'Manage IT Requests',
    'MANAGE_RAP_CALENDAR' => 'Manage Rap Calendar',
    'MANAGE_SES_DATA' => 'Manage SES Data',
    'MANAGE_DTR' => 'Manage DTR Records',
    'MANAGE_ASSETS' => 'Manage Assets',
]);

function getDefinedRoles() {
    global $pdo; 
    static $rolesCache = null; 

    if ($rolesCache !== null) {
        return $rolesCache;
    }

    if (!class_exists('RoleModel')) {
        $modelPath = __DIR__ . '/app/models/RoleModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { error_log("RoleModel class not found in getDefinedRoles()."); return []; }
    }
    
    $roleModel = new RoleModel($pdo);
    $dbRoles = $roleModel->getAllRoles('role_key', 'ASC'); 
    
    $formattedRoles = [];
    if ($dbRoles) {
        foreach ($dbRoles as $role) {
            $formattedRoles[$role['role_key']] = $role['role_name'];
        }
    }
    $rolesCache = $formattedRoles;
    return $rolesCache;
}

function userHasCapability($capability) {
    global $pdo; 

    if (!isLoggedIn()) {
        return false;
    }
    $userRole = $_SESSION['user_role'] ?? 'user'; 

    if (!class_exists('RolePermissionModel')) {
        $modelPath = __DIR__ . '/app/models/RolePermissionModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { error_log("RolePermissionModel class not found in userHasCapability()."); return false; }
    }
    
    if (!class_exists('RoleModel')) {
        $modelPathRole = __DIR__ . '/app/models/RoleModel.php';
        if (file_exists($modelPathRole)) require_once $modelPathRole;
    }
    
    if (class_exists('RolePermissionModel')) {
        $rolePermissionModel = new RolePermissionModel($pdo);
        // Specific capability check
        return $rolePermissionModel->roleHasCapability($userRole, $capability);
    }
    
    error_log("RolePermissionModel class does not exist after attempting to load for userHasCapability().");
    return false; 
}

?>
