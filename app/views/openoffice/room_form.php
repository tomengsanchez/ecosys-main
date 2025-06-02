<?php
// pageTitle, errors, breadcrumbs, room_statuses
// and potentially: room_id, object_title, object_content, object_status, meta_fields (for editing)
// are passed from OpenOfficeController's addRoom() or editRoom() methods.

$isEditing = isset($room_id) && !empty($room_id);
$formAction = $isEditing ? BASE_URL . 'openoffice/editRoom/' . $room_id : BASE_URL . 'openoffice/addRoom';

// Populate form fields with existing data if editing, or from POST data if validation failed
$currentTitle = $object_title ?? ($original_room_data['object_title'] ?? '');
$currentContent = $object_content ?? ($original_room_data['object_content'] ?? '');
$currentStatus = $object_status ?? ($original_room_data['object_status'] ?? 'available');
$currentCapacity = $meta_fields['room_capacity'] ?? ($original_room_data['meta']['room_capacity'] ?? 0);
$currentLocation = $meta_fields['room_location'] ?? ($original_room_data['meta']['room_location'] ?? '');
$currentEquipment = $meta_fields['room_equipment'] ?? ($original_room_data['meta']['room_equipment'] ?? '');


// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7 col-xl-6">
        <div class="card shadow-sm mt-4 mb-5">
            <div class="card-body p-4">
                <h1 class="card-title text-center mb-4"><?php echo htmlspecialchars($pageTitle ?? ($isEditing ? 'Edit Room' : 'Add New Room')); ?></h1>

                <?php
                if (!empty($errors['form_err'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($errors['form_err']) . '</div>';
                }
                ?>

                <form action="<?php echo $formAction; ?>" method="POST">
                    
                    <div class="mb-3">
                        <label for="object_title" class="form-label">Room Name <span class="text-danger">*</span></label>
                        <input type="text" name="object_title" id="object_title" class="form-control <?php echo (!empty($errors['object_title_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($currentTitle); ?>" required>
                        <?php if (!empty($errors['object_title_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['object_title_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="object_content" class="form-label">Description</label>
                        <textarea name="object_content" id="object_content" class="form-control <?php echo (!empty($errors['object_content_err'])) ? 'is-invalid' : ''; ?>"
                                  rows="3"><?php echo htmlspecialchars($currentContent); ?></textarea>
                        <?php if (!empty($errors['object_content_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['object_content_err']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    <h5 class="mb-3">Room Details</h5>

                    <div class="mb-3">
                        <label for="room_capacity" class="form-label">Capacity</label>
                        <input type="number" name="room_capacity" id="room_capacity" class="form-control <?php echo (!empty($errors['room_capacity_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($currentCapacity); ?>" min="0">
                        <?php if (!empty($errors['room_capacity_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['room_capacity_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="room_location" class="form-label">Location</label>
                        <input type="text" name="room_location" id="room_location" class="form-control <?php echo (!empty($errors['room_location_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($currentLocation); ?>">
                        <?php if (!empty($errors['room_location_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['room_location_err']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="room_equipment" class="form-label">Equipment</label>
                        <textarea name="room_equipment" id="room_equipment" class="form-control <?php echo (!empty($errors['room_equipment_err'])) ? 'is-invalid' : ''; ?>"
                                  rows="3" placeholder="e.g., Projector, Whiteboard, Conference Phone"><?php echo htmlspecialchars($currentEquipment); ?></textarea>
                        <small class="form-text text-muted">List equipment, one item per line or comma-separated.</small>
                        <?php if (!empty($errors['room_equipment_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['room_equipment_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="object_status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="object_status" id="object_status" class="form-select <?php echo (!empty($errors['object_status_err'])) ? 'is-invalid' : ''; ?>" required>
                            <?php foreach ($room_statuses as $statusValue => $statusLabel): ?>
                                <option value="<?php echo htmlspecialchars($statusValue); ?>" <?php echo ($currentStatus == $statusValue) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($statusLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['object_status_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['object_status_err']); ?></div>
                        <?php endif; ?>
                    </div>


                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <?php echo $isEditing ? 'Update Room' : 'Create Room'; ?>
                        </button>
                        <a href="<?php echo BASE_URL . 'openoffice/rooms'; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
