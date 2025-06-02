<?php
// pageTitle, rooms array, and breadcrumbs are passed from OpenOfficeController's rooms() method

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="openoffice-rooms-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Rooms'); ?></h1>
        <a href="<?php echo BASE_URL . 'openoffice/addRoom'; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Room
        </a>
    </div>

    <?php
    // Display any session messages (using 'admin_message' for consistency)
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
    if (isset($_SESSION['error_message'])) { // For general errors not fitting admin_message pattern
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['error_message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['error_message']); 
    }
    ?>

    <?php if (!empty($rooms) && is_array($rooms)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Room Name</th>
                        <th>Capacity</th>
                        <th>Location</th>
                        <th>Equipment</th>
                        <th>Status</th>
                        <th>Last Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($room['object_id']); ?></td>
                            <td><?php echo htmlspecialchars($room['object_title']); ?></td>
                            <td><?php echo htmlspecialchars($room['meta']['room_capacity'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($room['meta']['room_location'] ?? 'N/A'); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($room['meta']['room_equipment'] ?? 'N/A')); ?></td>
                            <td>
                                <?php 
                                $status = $room['object_status'] ?? 'unknown';
                                $statusLabel = ucfirst($status);
                                $badgeClass = 'bg-secondary';
                                if ($status === 'available') $badgeClass = 'bg-success';
                                elseif ($status === 'unavailable') $badgeClass = 'bg-warning text-dark';
                                elseif ($status === 'maintenance') $badgeClass = 'bg-danger';
                                echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span>';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($room['object_modified']))); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL . 'openoffice/editRoom/' . htmlspecialchars($room['object_id']); ?>" class="btn btn-sm btn-primary me-1" title="Edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="<?php echo BASE_URL . 'openoffice/deleteRoom/' . htmlspecialchars($room['object_id']); ?>" 
                                   class="btn btn-sm btn-danger" title="Delete"
                                   onclick="return confirm('Are you sure you want to delete the room &quot;<?php echo htmlspecialchars(addslashes($room['object_title'])); ?>&quot;? This action cannot be undone.');">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No rooms found. You can add one using the button above.</div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
