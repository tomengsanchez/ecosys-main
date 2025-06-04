<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mainsystem/');
}
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePathStripped = rtrim(BASE_URL, '/');

// Helper function to check if a nav link is active
function isActive($linkPath, $currentPath, $basePathStripped) {
    $fullLinkPath = $basePathStripped . '/' . ltrim($linkPath, '/');
    // Special case for the root/dashboard
    if (($linkPath === 'Dashboard' || $linkPath === '') && ($currentPath === $basePathStripped . '/' || $currentPath === $basePathStripped || $currentPath === $basePathStripped . '/Dashboard' || $currentPath === $basePathStripped . '/dashboard')) {
        return true;
    }
    if ($linkPath !== '' && !empty($fullLinkPath) && strpos($currentPath, $fullLinkPath) === 0) {
        // Check if it's an exact match or followed by a query string or slash
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

// Navigation Configuration
// Note: URLs should generally match the controller/method structure.
// The router in index.php typically converts 'ControllerName/actionName' to 'ControllerNameController' and 'actionName'.
// So, 'Openoffice/rooms' routes to OpenofficeController's rooms() method.
// 'Vehicle/index' or just 'Vehicle' would route to VehicleController's index() method.
$navigationConfig = [
    [
        'label' => 'Dashboard',
        'url' => 'Dashboard', // Or just '' for the base dashboard
        'icon' => 'fas fa-tachometer-alt me-1',
    ],
    [
        'label' => 'Open Office',
        'icon' => 'fas fa-door-open me-1',
        'id' => 'openOfficeDropdown',
        'base_path' => 'Openoffice', // Base path for highlighting the parent dropdown
        'children' => [
            [
                'label' => 'Manage Rooms',
                'url' => 'Openoffice/rooms', 
                'capability' => 'VIEW_ROOMS'
            ],
            [
                'label' => 'Room Reservations (Admin)',
                'url' => 'Openoffice/roomreservations', 
                'capability' => 'VIEW_ALL_ROOM_RESERVATIONS'
            ],
            [
                'label' => 'My Room Reservations',
                'url' => 'Openoffice/myreservations', 
                // No specific capability, all logged-in users can see their own
            ],
            [
                'type' => 'divider' // Visual separator in dropdown
            ],
            [
                'label' => 'Manage Vehicles',
                'url' => 'vehicle', // Routes to VehicleController's index() method
                'capability' => 'VIEW_VEHICLES'
            ],
            // --- NEW: Vehicle Reservation Links ---
            [
                'label' => 'Vehicle Reservations (Admin)',
                'url' => 'VehicleRequest/index', // Points to the new VehicleRequestController
                'capability' => 'VIEW_ALL_VEHICLE_RESERVATIONS'
            ],
            [
                'label' => 'My Vehicle Reservations',
                'url' => 'VehicleRequest/myrequests', // Points to the new VehicleRequestController
                // No specific capability, all logged-in users can see their own
            ],
            // --- END NEW ---
        ]
    ],
    [
        'label' => 'IT Department',
        'icon' => 'fas fa-desktop me-1',
        'id' => 'itDepartmentDropdown',
        'base_path' => 'It', 
        'capability' => 'MANAGE_IT_REQUESTS', // Example capability
        'children' => [
            [
                'label' => 'Requests',
                'url' => 'It/requests', // Example URL
            ],
            // Add more IT department links here
        ]
    ],
    [
        'label' => 'Rap', // Assuming this is another module
        'icon' => 'fas fa-calendar-alt me-1',
        'id' => 'rapDropdown',
        'base_path' => 'Rap', 
        'capability' => 'MANAGE_RAP_CALENDAR', // Example capability
        'children' => [
            [
                'label' => 'Calendar Of Activities',
                'url' => 'Rap/calendar', // Example URL
            ],
        ]
    ],
    [
        'label' => 'SES', // Assuming this is another module
        'icon' => 'fas fa-chart-bar me-1',
        'id' => 'sesDropdown',
        'base_path' => 'Ses', 
        'capability' => 'MANAGE_SES_DATA', // Example capability
        'children' => [
            [
                'label' => 'SES Data',
                'url' => 'Ses/data', // Example URL
            ],
        ]
    ],
    // Add other top-level navigation items or dropdowns here
];

function renderNavigationItems($items, $currentPath, $basePathStripped, $isDropdown = false) {
    $html = '';
    $hasVisibleChild = false; // Track if any child item is visible for a dropdown
    foreach ($items as $item) {
        // Check for capability first
        if (isset($item['capability']) && !userHasCapability($item['capability'])) {
            continue; // Skip this item if user lacks capability
        }

        $itemVisible = true; // Assume visible unless children are all hidden

        // If it's a dropdown, check if any of its children are visible
        if (isset($item['children'])) {
            $childHtml = renderNavigationItems($item['children'], $currentPath, $basePathStripped, true);
            if (empty(trim(strip_tags($childHtml)))) { // Check if rendered child HTML is effectively empty
                 // If the parent dropdown itself doesn't have a capability requirement,
                 // but all its children are hidden due to their own capabilities,
                 // then hide the parent dropdown too.
                if (!isset($item['capability'])) {
                    $itemVisible = false;
                }
            }
            if ($itemVisible) $hasVisibleChild = true;
        } else {
            // For non-dropdown items, visibility is already determined by its own capability check above
            if (isset($item['capability']) && !userHasCapability($item['capability'])) {
                 $itemVisible = false;
            }
            if ($itemVisible) $hasVisibleChild = true;
        }
        
        if (!$itemVisible) {
            continue; // Skip rendering this item if it's not visible
        }


        if (isset($item['type']) && $item['type'] === 'divider') {
            $html .= '<li><hr class="dropdown-divider"></li>';
        } elseif (isset($item['children'])) {
            // Re-render children only if the parent is visible
            $childContent = renderNavigationItems($item['children'], $currentPath, $basePathStripped, true);
            if (!empty(trim(strip_tags($childContent)))) { // Ensure there's actual content
                // Determine active state for dropdown based on its base_path or children's URLs
                $dropdownActiveClass = '';
                // Corrected: Check if $item['base_path'] is set before using it.
                // Also, ensure the check for children's active state is robust.
                if (isset($item['base_path']) && isDropdownSectionActive($item['base_path'], $currentPath, $basePathStripped)) {
                    $dropdownActiveClass = 'active';
                } else { 
                    foreach ($item['children'] as $child) {
                        if (isset($child['url']) && isActive($child['url'], $currentPath, $basePathStripped)) {
                            $dropdownActiveClass = 'active';
                            break;
                        }
                    }
                }

                $html .= '<li class="nav-item dropdown">';
                $html .= '<a class="nav-link dropdown-toggle ' . $dropdownActiveClass . '" href="#" id="' . htmlspecialchars($item['id']) . '" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
                if (isset($item['icon'])) { $html .= '<i class="' . htmlspecialchars($item['icon']) . '"></i>'; }
                $html .= htmlspecialchars($item['label']);
                $html .= '</a>';
                $html .= '<ul class="dropdown-menu" aria-labelledby="' . htmlspecialchars($item['id']) . '">';
                $html .= $childContent;
                $html .= '</ul></li>';
                if (!$isDropdown) $hasVisibleChild = true; 
            }
        } else { // Regular link item
            $activeClass = isActive($item['url'], $currentPath, $basePathStripped) ? 'active' : '';
            $itemClass = $isDropdown ? 'dropdown-item' : 'nav-link';
            
            $html .= $isDropdown ? '<li>' : '<li class="nav-item">';
            $html .= '<a class="' . $itemClass . ' ' . $activeClass . '" href="' . BASE_URL . ltrim($item['url'], '/') . '">';
            if (isset($item['icon']) && !$isDropdown) { 
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
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js'></script>
    <style>
        body { 
            padding-top: 70px; /* Adjust for fixed navbar height */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Example font */
        }
        .breadcrumb { 
            background-color: #e9ecef; /* Lighter breadcrumb background */
            padding: 0.75rem 1rem; 
            border-radius: 0.25rem; 
            margin-bottom: 1rem; 
        }
        .main-content { 
            min-height: calc(100vh - 70px - 70px - 3rem); /* Navbar height - Footer height - some margin */
            background-color: #fff; /* White background for content area */
            padding: 1.5rem;
            border-radius: 0.25rem;
            /* box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); */ /* Optional subtle shadow */
        }
        footer { 
            line-height: 1; 
            background-color: #343a40; /* Dark footer */
            color: #f8f9fa; /* Light text for footer */
        }
        .navbar-nav .dropdown-menu { 
            min-width: 220px; /* Wider dropdown */
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
        }
        .navbar-brand img {
            max-height: 30px; /* Adjust logo size if you add one */
            margin-right: 0.5rem;
        }
        /* FullCalendar event text wrapping */
        .fc-event-main-frame { white-space: normal; overflow-wrap: break-word; display: block; }
        .fc-event-title { white-space: normal !important; overflow-wrap: break-word; display: block; padding: 2px 0; }
        .fc-daygrid-event { white-space: normal; overflow: visible !important; }
        .fc-daygrid-event .fc-event-main { overflow: visible; }
        
        /* DataTables styling adjustments */
        .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0.3em 0.8em; }
        .dataTables_length, .dataTables_filter { margin-bottom: 1em; }
        .table th, .table td { vertical-align: middle; } /* Better alignment in table cells */

        /* Card styling improvements */
        .card {
            border: 1px solid rgba(0,0,0,.125); /* Standard border */
        }
        .card-header {
            font-weight: 500;
        }
        /* Alert styling */
        .alert {
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>">
            <i class="fas fa-cogs me-2"></i>Mainsystem
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php 
                if (isLoggedIn()) {
                    echo renderNavigationItems($navigationConfig, $currentPath, $basePathStripped);
                }
                ?>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                 <?php if (isLoggedIn()): ?>
                    <?php 
                    // Admin dropdown - only show if user has ACCESS_ADMIN_PANEL capability
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
                    <?php endif; // End ACCESS_ADMIN_PANEL check ?>

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
                <?php else: // Not logged in ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('auth/login', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'auth/login'; ?>">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                <?php endif; // End isLoggedIn check ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5"> 
    <?php
    // Generate breadcrumbs if $breadcrumbs variable is set and is an array
    if (isset($breadcrumbs) && is_array($breadcrumbs) && !empty($breadcrumbs)) {
        echo generateBreadcrumbs($breadcrumbs);
    }
    ?>
    <div class="main-content">
