<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mainsystem/');
}
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePathStripped = rtrim(BASE_URL, '/');

// Helper function to check if a nav link is active
function isActive($linkPath, $currentPath, $basePathStripped) {
    $fullLinkPath = $basePathStripped . '/' . ltrim($linkPath, '/');
    // Check for exact match or match with trailing slash for base URL
    if ($linkPath === '' && ($currentPath === $basePathStripped . '/' || $currentPath === $basePathStripped)) {
        return true;
    }
    // Ensure $fullLinkPath is not empty before using it in strpos
    if ($linkPath !== '' && !empty($fullLinkPath) && strpos($currentPath, $fullLinkPath) === 0) {
        // Check if it's an exact match or if currentPath is a sub-path (e.g., for dropdown sections)
        if ($currentPath === $fullLinkPath || 
            strpos($currentPath, $fullLinkPath . '?') === 0 || 
            strpos($currentPath, $fullLinkPath . '/') === 0) {
            return true;
        }
    }
    return false;
}

// Helper function to check if a dropdown section is active
function isDropdownSectionActive($sectionPrefix, $currentPath, $basePathStripped) {
    $fullSectionPrefix = $basePathStripped . '/' . ltrim($sectionPrefix, '/');
    if (!empty($fullSectionPrefix) && strpos($currentPath, $fullSectionPrefix) === 0) {
        return true;
    }
    return false;
}

// --- Navigation Configuration Array ---
$navigationConfig = [
    [
        'label' => 'Dashboard',
        'url' => 'dashboard',
        'icon' => 'fas fa-tachometer-alt me-1',
        // No capability means visible to all logged-in users
    ],
    [
        'label' => 'Open Office',
        'icon' => 'fas fa-door-open me-1',
        'id' => 'openOfficeDropdown',
        'base_path' => 'openoffice', // For isDropdownSectionActive
        // Visibility of this dropdown depends on its children's capabilities
        'children' => [
            [
                'label' => 'Manage Rooms',
                'url' => 'openoffice/rooms',
                'capability' => 'VIEW_ROOMS'
            ],
            [
                'label' => 'Room Reservations (Admin)',
                'url' => 'openoffice/roomreservations',
                'capability' => 'VIEW_ALL_ROOM_RESERVATIONS'
            ],
            [
                'label' => 'My Reservations',
                'url' => 'openoffice/myreservations',
                // No capability, visible to all logged-in users
            ],
            // Example for future items (currently commented out in original)
            // [ 'type' => 'divider' ],
            // [ 'label' => 'Vehicle Reservation', 'url' => 'openoffice/vehicles', 'capability' => 'MANAGE_VEHICLE_RESERVATIONS' ],
            // [ 'label' => 'Service Request', 'url' => 'openoffice/services', 'capability' => 'MANAGE_SERVICE_REQUESTS' ],
        ]
    ],
    [
        'label' => 'IT Department',
        'icon' => 'fas fa-desktop me-1',
        'id' => 'itDepartmentDropdown',
        'base_path' => 'it',
        'capability' => 'MANAGE_IT_REQUESTS', // Dropdown itself requires this
        'children' => [
            [
                'label' => 'Requests',
                'url' => 'it/requests',
                // Inherits capability from parent if not specified, or can be more granular
            ],
        ]
    ],
    [
        'label' => 'Rap',
        'icon' => 'fas fa-calendar-alt me-1',
        'id' => 'rapDropdown',
        'base_path' => 'rap',
        'capability' => 'MANAGE_RAP_CALENDAR',
        'children' => [
            [
                'label' => 'Calendar Of Activities',
                'url' => 'rap/calendar',
            ],
        ]
    ],
    [
        'label' => 'SES',
        'icon' => 'fas fa-chart-bar me-1',
        'id' => 'sesDropdown',
        'base_path' => 'ses',
        'capability' => 'MANAGE_SES_DATA',
        'children' => [
            [
                'label' => 'SES Data',
                'url' => 'ses/data',
            ],
        ]
    ],
];

// --- Function to Render Navigation Items ---
function renderNavigationItems($items, $currentPath, $basePathStripped, $isDropdown = false) {
    $html = '';
    $hasVisibleChild = false;

    foreach ($items as $item) {
        // Check capability for the item itself
        if (isset($item['capability']) && !userHasCapability($item['capability'])) {
            continue; // Skip this item if user doesn't have capability
        }

        $itemVisible = true; // Assume visible unless children logic says otherwise for dropdowns

        if (isset($item['children'])) {
            // For a dropdown parent, check if any child is visible
            $childHtml = renderNavigationItems($item['children'], $currentPath, $basePathStripped, true);
            if (empty(trim($childHtml))) { // No visible children
                 // If dropdown parent has its own capability, it might still be shown if that capability is met.
                 // If no capability on parent, and no children, then hide.
                if (!isset($item['capability'])) { // If parent has no capability of its own and no visible children, hide it
                    $itemVisible = false;
                }
            }
            if ($itemVisible) $hasVisibleChild = true; // Mark that at least one child (or parent itself) is visible
        } else {
             if (isset($item['capability']) && !userHasCapability($item['capability'])) {
                $itemVisible = false;
            }
            if ($itemVisible) $hasVisibleChild = true;
        }
        
        if (!$itemVisible) {
            continue;
        }


        if (isset($item['type']) && $item['type'] === 'divider') {
            $html .= '<li><hr class="dropdown-divider"></li>';
        } elseif (isset($item['children'])) {
            // Render dropdown parent
            $childContent = renderNavigationItems($item['children'], $currentPath, $basePathStripped, true);
            if (!empty(trim($childContent))) { // Only render dropdown if it has visible children
                $activeClass = isDropdownSectionActive($item['base_path'] ?? $item['url'] ?? '', $currentPath, $basePathStripped) ? 'active' : '';
                $html .= '<li class="nav-item dropdown">';
                $html .= '<a class="nav-link dropdown-toggle ' . $activeClass . '" href="#" id="' . htmlspecialchars($item['id']) . '" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
                if (isset($item['icon'])) {
                    $html .= '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
                }
                $html .= htmlspecialchars($item['label']);
                $html .= '</a>';
                $html .= '<ul class="dropdown-menu" aria-labelledby="' . htmlspecialchars($item['id']) . '">';
                $html .= $childContent;
                $html .= '</ul></li>';
                 if (!$isDropdown) $hasVisibleChild = true;
            }
        } else {
            // Render single link item
            $activeClass = isActive($item['url'], $currentPath, $basePathStripped) ? 'active' : '';
            $itemClass = $isDropdown ? 'dropdown-item' : 'nav-link';
            $html .= $isDropdown ? '<li>' : '<li class="nav-item">';
            $html .= '<a class="' . $itemClass . ' ' . $activeClass . '" href="' . BASE_URL . ltrim($item['url'], '/') . '">';
            if (isset($item['icon']) && !$isDropdown) { // Icons usually for top-level nav-links
                $html .= '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
            }
            $html .= htmlspecialchars($item['label']);
            $html .= '</a></li>';
            if (!$isDropdown) $hasVisibleChild = true;
        }
    }
    return $isDropdown ? $html : ($hasVisibleChild ? $html : '');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'My Application'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" xintegrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js'></script>
    <style>
        body { padding-top: 70px; /* Adjusted for potentially taller navbar */ }
        .breadcrumb { background-color: #f8f9fa; padding: 0.75rem 1rem; border-radius: 0.25rem; margin-bottom: 1rem; }
        .main-content { min-height: calc(100vh - 70px - 70px - 3rem); /* Full viewport - navbar - footer - margins */ }
        footer { line-height: 1; }
        .navbar-nav .dropdown-menu { min-width: 200px; } /* Ensure dropdowns have enough width */

        /* FullCalendar text wrapping fixes */
        .fc-event-main-frame { /* Container for event content */
            white-space: normal; /* Allow text to wrap */
            overflow-wrap: break-word; /* Break long words if necessary */
            display: block; /* Ensure it can take up vertical space */
        }
        .fc-event-title { /* The event title itself */
            white-space: normal !important; /* Ensure wrapping, override other styles */
            overflow-wrap: break-word;
            display: block; /* Ensure it takes up block space for wrapping */
            padding: 2px 0; /* Add a little padding if needed */
        }
        .fc-daygrid-event { /* General daygrid event container */
             white-space: normal; /* Allow wrapping for the whole event block */
             overflow: visible !important; /* Ensure content isn't clipped */
             /* Adjust height if necessary, or let it grow */
        }
        .fc-daygrid-event .fc-event-main {
            overflow: visible; /* Ensure inner content is also visible */
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>"><i class="fas fa-cogs me-2"></i>Mainsystem</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php 
                if (isLoggedIn()) {
                    // Render the dynamic navigation items
                    echo renderNavigationItems($navigationConfig, $currentPath, $basePathStripped);
                }
                ?>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                 <?php if (isLoggedIn()): ?>
                    <?php 
                    // Admin dropdown remains hardcoded for now, can be integrated later
                    if (userHasCapability('ACCESS_ADMIN_PANEL')): 
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('admin', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-shield me-1"></i>Admin
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item <?php echo isActive('admin', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/users', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/departments', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/siteSettings', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/roleAccessSettings', $currentPath, $basePathStripped) &&
                                                                    !isDropdownSectionActive('admin/listRoles', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/dtr', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/assets', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin'; ?>">Admin Dashboard</a></li>
                                
                                <?php if (userHasCapability('MANAGE_USERS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/users', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/users'; ?>">Employees</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_DEPARTMENTS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/departments', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/departments'; ?>">Departments</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_ROLES')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/listRoles', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/listRoles'; ?>">Manage Roles</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_ROLES_PERMISSIONS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/roleAccessSettings', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/roleAccessSettings'; ?>">Role Permissions</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_SITE_SETTINGS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/siteSettings', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/siteSettings'; ?>">Site Settings</a></li>
                                <?php endif; ?>
                                
                                <?php if (userHasCapability('MANAGE_DTR') || userHasCapability('MANAGE_ASSETS')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_DTR')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/dtr', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/dtr'; ?>">DTR</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_ASSETS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/assets', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/assets'; ?>">Assets</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userProfileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userProfileDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-edit me-1"></i>Profile (Soon)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL . 'auth/logout'; ?>"><i class="fas fa-sign-out-alt me-1"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('auth/login', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'auth/login'; ?>">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5"> 
    <?php
    if (isset($breadcrumbs) && is_array($breadcrumbs)) {
        echo generateBreadcrumbs($breadcrumbs);
    }
    ?>
    <div class="main-content">
