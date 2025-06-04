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
$navigationConfig = [
    [
        'label' => 'Dashboard',
        'url' => 'Dashboard', 
        'icon' => 'fas fa-tachometer-alt me-1',
    ],
    [
        'label' => 'Open Office',
        'icon' => 'fas fa-door-open me-1',
        'id' => 'openOfficeDropdown',
        'base_path' => 'OpenOffice', // MODIFIED: Changed to OpenOffice (uppercase O)
        'children' => [
            [
                'label' => 'Manage Rooms',
                'url' => 'OpenOffice/rooms', // MODIFIED: Changed to OpenOffice (uppercase O)
                'capability' => 'VIEW_ROOMS'
            ],
            [
                'label' => 'Room Reservations (Admin)',
                'url' => 'OpenOffice/roomreservations', // MODIFIED: Changed to OpenOffice (uppercase O)
                'capability' => 'VIEW_ALL_ROOM_RESERVATIONS'
            ],
            [
                'label' => 'My Room Reservations',
                'url' => 'OpenOffice/myreservations', // MODIFIED: Changed to OpenOffice (uppercase O)
            ],
            [
                'type' => 'divider' 
            ],
            [
                'label' => 'Manage Vehicles',
                'url' => 'vehicle', 
                'capability' => 'VIEW_VEHICLES'
            ],
            [
                'label' => 'Vehicle Reservations (Admin)',
                'url' => 'VehicleRequest/index', 
                'capability' => 'VIEW_ALL_VEHICLE_RESERVATIONS'
            ],
            [
                'label' => 'My Vehicle Reservations',
                'url' => 'VehicleRequest/myrequests', 
            ],
        ]
    ],
    [
        'label' => 'IT Department',
        'icon' => 'fas fa-desktop me-1',
        'id' => 'itDepartmentDropdown',
        'base_path' => 'It', // Assuming ItController.php
        'capability' => 'MANAGE_IT_REQUESTS', 
        'children' => [
            [
                'label' => 'Requests',
                'url' => 'It/requests', 
            ],
        ]
    ],
    [
        'label' => 'Rap', 
        'icon' => 'fas fa-calendar-alt me-1',
        'id' => 'rapDropdown',
        'base_path' => 'Rap',  // Assuming RapController.php
        'capability' => 'MANAGE_RAP_CALENDAR', 
        'children' => [
            [
                'label' => 'Calendar Of Activities',
                'url' => 'Rap/calendar', 
            ],
        ]
    ],
    [
        'label' => 'SES', 
        'icon' => 'fas fa-chart-bar me-1',
        'id' => 'sesDropdown',
        'base_path' => 'Ses', // Assuming SesController.php
        'capability' => 'MANAGE_SES_DATA', 
        'children' => [
            [
                'label' => 'SES Data',
                'url' => 'Ses/data', 
            ],
        ]
    ],
];

function renderNavigationItems($items, $currentPath, $basePathStripped, $isDropdown = false) {
    $html = '';
    $hasVisibleChild = false; 
    foreach ($items as $item) {
        if (isset($item['capability']) && !userHasCapability($item['capability'])) {
            continue; 
        }
        $itemVisible = true; 
        if (isset($item['children'])) {
            $childHtml = renderNavigationItems($item['children'], $currentPath, $basePathStripped, true);
            if (empty(trim(strip_tags($childHtml)))) { 
                if (!isset($item['capability'])) {
                    $itemVisible = false;
                }
            }
            if ($itemVisible) $hasVisibleChild = true;
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
            $childContent = renderNavigationItems($item['children'], $currentPath, $basePathStripped, true);
            if (!empty(trim(strip_tags($childContent)))) { 
                $dropdownActiveClass = '';
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
        } else { 
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
            padding-top: 70px; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        .breadcrumb { 
            background-color: #e9ecef; 
            padding: 0.75rem 1rem; 
            border-radius: 0.25rem; 
            margin-bottom: 1rem; 
        }
        .main-content { 
            min-height: calc(100vh - 70px - 70px - 3rem); 
            background-color: #fff; 
            padding: 1.5rem;
            border-radius: 0.25rem;
        }
        footer { 
            line-height: 1; 
            background-color: #343a40; 
            color: #f8f9fa; 
        }
        .navbar-nav .dropdown-menu { 
            min-width: 220px; 
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
        }
        .navbar-brand img {
            max-height: 30px; 
            margin-right: 0.5rem;
        }
        .fc-event-main-frame { white-space: normal; overflow-wrap: break-word; display: block; }
        .fc-event-title { white-space: normal !important; overflow-wrap: break-word; display: block; padding: 2px 0; }
        .fc-daygrid-event { white-space: normal; overflow: visible !important; }
        .fc-daygrid-event .fc-event-main { overflow: visible; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0.3em 0.8em; }
        .dataTables_length, .dataTables_filter { margin-bottom: 1em; }
        .table th, .table td { vertical-align: middle; } 
        .card {
            border: 1px solid rgba(0,0,0,.125); 
        }
        .card-header {
            font-weight: 500;
        }
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
    if (isset($breadcrumbs) && is_array($breadcrumbs) && !empty($breadcrumbs)) {
        echo generateBreadcrumbs($breadcrumbs);
    }
    ?>
    <div class="main-content">
