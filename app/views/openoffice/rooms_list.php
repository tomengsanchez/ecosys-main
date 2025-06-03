<?php
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="openoffice-rooms-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Rooms'); ?></h1>
        <?php if (userHasCapability('CREATE_ROOMS')): // Changed from MANAGE_ROOMS for consistency with controller logic ?>
        <a href="<?php echo BASE_URL . 'OpenOffice/addRoom'; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Room
        </a>
        <?php endif; ?>
    </div>

    <?php
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
    // ... (other session messages as before) ...
    ?>

    <?php if (!empty($rooms) && is_array($rooms)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable-l" id="roomsTable"> <thead class="table-dark">
                    <tr>
                        <?php if (userHasCapability('EDIT_ROOMS') || userHasCapability('DELETE_ROOMS')): // Show ID if admin has edit/delete rights for rooms ?>
                            <th>ID</th>
                        <?php endif; ?>
                        <th>Room Name</th>
                        <th>Capacity</th>
                        <th>Location</th>
                        <th>Equipment</th>
                        <th>Status</th>
                        <?php if (userHasCapability('EDIT_ROOMS') || userHasCapability('DELETE_ROOMS')): ?>
                            <th>Last Modified</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <?php if (userHasCapability('EDIT_ROOMS') || userHasCapability('DELETE_ROOMS')): ?>
                                <td><?php echo htmlspecialchars($room['object_id']); ?></td>
                            <?php endif; ?>
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
                            <?php if (userHasCapability('EDIT_ROOMS') || userHasCapability('DELETE_ROOMS')): ?>
                                <td><?php echo htmlspecialchars(format_datetime_for_display($room['object_modified'])); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if ($room['object_status'] === 'available' && userHasCapability('CREATE_ROOM_RESERVATIONS')): ?>
                                    <a href="<?php echo BASE_URL . 'OpenOffice/createreservation/' . htmlspecialchars($room['object_id']); ?>" class="btn btn-sm btn-info me-1 mb-1" title="Book this room">
                                        <i class="fas fa-calendar-plus"></i> Book
                                    </a>
                                <?php endif; ?>

                                <?php if (userHasCapability('EDIT_ROOMS')): ?>
                                    <a href="<?php echo BASE_URL . 'OpenOffice/editRoom/' . htmlspecialchars($room['object_id']); ?>" class="btn btn-sm btn-primary me-1 mb-1" title="Edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                <?php endif; ?>
                                <?php if (userHasCapability('DELETE_ROOMS')): ?>
                                    <a href="<?php echo BASE_URL . 'OpenOffice/deleteRoom/' . htmlspecialchars($room['object_id']); ?>" 
                                       class="btn btn-sm btn-danger mb-1" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete the room &quot;<?php echo htmlspecialchars(addslashes($room['object_title'])); ?>&quot;? This action cannot be undone.');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No rooms found. <?php if (userHasCapability('CREATE_ROOMS')): ?>You can add one using the button above.<?php endif; ?></div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
