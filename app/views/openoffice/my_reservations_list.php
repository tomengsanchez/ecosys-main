<?php
// This view is used by OpenOfficeController::myreservations()
// Expected variables:
// - $pageTitle (string)
// - $reservations (array) - List of the current user's reservation objects, enhanced with room_name
// - $breadcrumbs (array)
// - $reservation_statuses (array) - Key-value pairs of status codes and labels

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="openoffice-my-reservations-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'My Room Reservations'); ?></h1>
        <a href="<?php echo BASE_URL . 'openoffice/rooms'; ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Make a New Reservation
        </a>
    </div>

    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error_message'])) {
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
                            <td><?php echo nl2br(htmlspecialchars($reservation['object_content'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_start_datetime'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(format_datetime_for_display($reservation['meta']['reservation_end_datetime'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(format_datetime_for_display($reservation['object_date'])); ?></td>
                            <td>
                                <?php 
                                $statusKey = $reservation['object_status'] ?? 'unknown';
                                $statusLabel = $reservation_statuses[$statusKey] ?? ucfirst($statusKey);
                                $badgeClass = 'bg-secondary'; 
                                if ($statusKey === 'pending') $badgeClass = 'bg-warning text-dark';
                                elseif ($statusKey === 'approved') $badgeClass = 'bg-success';
                                elseif ($statusKey === 'denied') $badgeClass = 'bg-danger';
                                elseif ($statusKey === 'cancelled') $badgeClass = 'bg-info text-dark';
                                echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php if ($reservation['object_status'] === 'pending'): ?>
                                    <a href="<?php echo BASE_URL . 'openoffice/cancelreservation/' . htmlspecialchars($reservation['object_id']); ?>" 
                                       class="btn btn-sm btn-warning text-dark" title="Cancel Request"
                                       onclick="return confirm('Are you sure you want to cancel this reservation request?');">
                                        <i class="fas fa-ban"></i> Cancel
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">No actions</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">You have not made any room reservations yet. <a href="<?php echo BASE_URL . 'openoffice/rooms'; ?>" class="alert-link">Click here to find a room to book.</a></div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
