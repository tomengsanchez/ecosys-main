<?php
// This view is used by OpenOfficeController::roomreservations()
// Expected variables:
// - $pageTitle (string)
// - $reservations (array) - List of reservation objects, enhanced with room_name and user_display_name
// - $breadcrumbs (array)
// - $reservation_statuses (array) - Key-value pairs of status codes and labels

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="openoffice-reservations-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Room Reservations'); ?></h1>
        </div>

    <?php
    // Display any session messages (using 'admin_message' for consistency from controller)
    if (isset($_SESSION['admin_message'])) {
        $alertType = (strpos(strtolower($_SESSION['admin_message']), 'error') === false && 
                      strpos(strtolower($_SESSION['admin_message']), 'fail') === false && 
                      strpos(strtolower($_SESSION['admin_message']), 'cannot') === false) ? 'success' : 'danger';
        echo '<div class="alert alert-' . $alertType . ' alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['admin_message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['admin_message']); 
    }
    if (isset($_SESSION['error_message'])) { // For general errors
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['error_message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['error_message']); 
    }
    ?>

    <?php if (!empty($reservations) && is_array($reservations)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Room</th>
                        <th>User</th>
                        <th>Purpose</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Requested On</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reservation['object_id']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['room_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($reservation['user_display_name'] ?? 'N/A'); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($reservation['object_content'] ?? 'N/A')); // Purpose ?></td>
                            <td><?php echo htmlspecialchars(isset($reservation['meta']['reservation_start_datetime']) ? date('Y-m-d H:i', strtotime($reservation['meta']['reservation_start_datetime'])) : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($reservation['meta']['reservation_end_datetime']) ? date('Y-m-d H:i', strtotime($reservation['meta']['reservation_end_datetime'])) : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($reservation['object_date']))); ?></td>
                            <td>
                                <?php 
                                $statusKey = $reservation['object_status'] ?? 'unknown';
                                $statusLabel = $reservation_statuses[$statusKey] ?? ucfirst($statusKey);
                                $badgeClass = 'bg-secondary'; // Default
                                if ($statusKey === 'pending') $badgeClass = 'bg-warning text-dark';
                                elseif ($statusKey === 'approved') $badgeClass = 'bg-success';
                                elseif ($statusKey === 'denied') $badgeClass = 'bg-danger';
                                elseif ($statusKey === 'cancelled') $badgeClass = 'bg-info text-dark';
                                echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php if ($reservation['object_status'] === 'pending'): ?>
                                    <a href="<?php echo BASE_URL . 'openoffice/approvereservation/' . htmlspecialchars($reservation['object_id']); ?>" 
                                       class="btn btn-sm btn-success mb-1" title="Approve"
                                       onclick="return confirm('Are you sure you want to approve this reservation?');">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="<?php echo BASE_URL . 'openoffice/denyreservation/' . htmlspecialchars($reservation['object_id']); ?>" 
                                       class="btn btn-sm btn-danger mb-1" title="Deny"
                                       onclick="return confirm('Are you sure you want to deny this reservation?');">
                                        <i class="fas fa-times"></i> Deny
                                    </a>
                                <?php elseif ($reservation['object_status'] === 'approved'): ?>
                                    <a href="<?php echo BASE_URL . 'openoffice/denyreservation/' . htmlspecialchars($reservation['object_id']); ?>" 
                                       class="btn btn-sm btn-warning text-dark mb-1" title="Revoke Approval (Deny)"
                                       onclick="return confirm('Are you sure you want to revoke approval and deny this reservation?');">
                                        <i class="fas fa-undo"></i> Revoke
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">No actions</span>
                                <?php endif; ?>
                                <!-- 
                                <a href="<?php echo BASE_URL . 'openoffice/deletereservation/' . htmlspecialchars($reservation['object_id']); ?>" 
                                   class="btn btn-sm btn-outline-danger mt-1" title="Delete Record"
                                   onclick="return confirm('Are you sure you want to permanently delete this reservation record? This cannot be undone.');">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                                 -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No room reservations found.</div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
