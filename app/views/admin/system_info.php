<?php
// pageTitle, breadcrumbs, and systemInfo are passed from SystemInfoController

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-system-info-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'System Information'); ?></h1>
    </div>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($systemInfo) && is_array($systemInfo)): ?>
        <div class="row">
            <!-- Server & PHP Information -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-server me-2"></i>Server & PHP Information
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            PHP Version:
                            <span class="badge bg-info rounded-pill"><?php echo htmlspecialchars($systemInfo['php_version'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Server Software:
                            <span><?php echo htmlspecialchars($systemInfo['server_software'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            PHP Memory Limit:
                            <span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($systemInfo['php_memory_limit'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Current Memory Usage:
                            <span class="badge bg-light text-dark rounded-pill"><?php echo htmlspecialchars($systemInfo['current_memory_usage'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Peak Memory Usage:
                            <span class="badge bg-light text-dark rounded-pill"><?php echo htmlspecialchars($systemInfo['peak_memory_usage'] ?? 'N/A'); ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Database Information -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-database me-2"></i>Database Information
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            DB Server Version:
                            <span class="badge bg-info rounded-pill"><?php echo htmlspecialchars($systemInfo['db_server_version'] ?? 'N/A'); ?></span>
                        </li>
                         <li class="list-group-item d-flex justify-content-between align-items-center">
                            DB Driver:
                            <span><?php echo htmlspecialchars($systemInfo['db_driver_name'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            DB Connection Status:
                            <span><?php echo htmlspecialchars($systemInfo['db_connection_status'] ?? 'N/A'); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Disk Space Information -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-hdd me-2"></i>Disk Space (Current Partition)
                    </div>
                     <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Space:
                            <span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($systemInfo['disk_total_space'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Free Space:
                            <span class="badge bg-success rounded-pill"><?php echo htmlspecialchars($systemInfo['disk_free_space'] ?? 'N/A'); ?></span>
                        </li>
                        <?php if (isset($systemInfo['disk_used_space'])): ?>
                        <li class="list-group-item">
                            Usage:
                            <div class="progress mt-1" style="height: 20px;">
                                <div class="progress-bar <?php 
                                    $usagePercent = floatval(rtrim($systemInfo['disk_usage_percentage'] ?? '0', '%'));
                                    if ($usagePercent > 90) echo 'bg-danger'; 
                                    elseif ($usagePercent > 75) echo 'bg-warning'; 
                                    else echo 'bg-success'; 
                                ?>" role="progressbar" style="width: <?php echo htmlspecialchars($systemInfo['disk_usage_percentage'] ?? '0%'); ?>;" 
                                     aria-valuenow="<?php echo $usagePercent; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo htmlspecialchars($systemInfo['disk_usage_percentage'] ?? '0%'); ?> Used
                                    (<?php echo htmlspecialchars($systemInfo['disk_used_space'] ?? 'N/A'); ?>)
                                </div>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Application Statistics -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-info text-white">
                       <i class="fas fa-cogs me-2"></i>Application Statistics
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Users:
                            <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($systemInfo['total_users'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Rooms:
                            <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($systemInfo['total_rooms'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Vehicles:
                            <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($systemInfo['total_vehicles'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Room Reservations (Pending):
                            <span class="badge bg-warning text-dark rounded-pill"><?php echo htmlspecialchars($systemInfo['total_room_reservations_pending'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Room Reservations (Approved):
                            <span class="badge bg-success rounded-pill"><?php echo htmlspecialchars($systemInfo['total_room_reservations_approved'] ?? 'N/A'); ?></span>
                        </li>
                         <li class="list-group-item d-flex justify-content-between align-items-center">
                            Vehicle Requests (Pending):
                            <span class="badge bg-warning text-dark rounded-pill"><?php echo htmlspecialchars($systemInfo['total_vehicle_reservations_pending'] ?? 'N/A'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Vehicle Requests (Approved):
                            <span class="badge bg-success rounded-pill"><?php echo htmlspecialchars($systemInfo['total_vehicle_reservations_approved'] ?? 'N/A'); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h5>PHP Info (Select Details)</h5>
            <div class="card shadow-sm">
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <pre style="font-size: 0.8rem; white-space: pre-wrap; word-break: break-all;">
<?php
// Display select phpinfo() sections for security and relevance.
// Be very careful about what you expose from phpinfo().
// This is a basic example. You might want to parse phpinfo output more carefully.

ob_start();
phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES | INFO_ENVIRONMENT | INFO_VARIABLES);
$phpinfoOutput = ob_get_clean();

// Remove body, html, head tags and doctype
$phpinfoOutput = preg_replace('/^<!DOCTYPE.+?>/is', '', $phpinfoOutput);
$phpinfoOutput = preg_replace('/<html.+?<\/head>/is', '', $phpinfoOutput);
$phpinfoOutput = preg_replace('/<\/body><\/html>$/is', '', $phpinfoOutput);
// Attempt to make tables Bootstrap friendly (basic)
$phpinfoOutput = str_replace('<table', '<table class="table table-sm table-bordered table-striped"', $phpinfoOutput);
$phpinfoOutput = str_replace('border="0" cellpadding="3" width="600"', '', $phpinfoOutput); // remove old table attributes
$phpinfoOutput = str_replace('class="e"', 'class="table-light"', $phpinfoOutput);
$phpinfoOutput = str_replace('class="v"', '', $phpinfoOutput);
$phpinfoOutput = str_replace('class="h"', 'class="table-dark text-white"', $phpinfoOutput);


// Further sanitization might be needed depending on what you want to show.
// Avoid showing sensitive configuration details.
echo $phpinfoOutput;
?>
                    </pre>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-warning">System information could not be loaded.</div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
