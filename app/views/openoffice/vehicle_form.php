<?php
// This view is used by VehicleController's add() and edit() methods.
// Expected variables from controller:
// - $pageTitle (string)
// - $breadcrumbs (array)
// - $errors (array) - Validation errors
// - $vehicle_statuses (array) - e.g., ['available' => 'Available', ...]
// - $vehicle_types (array) - e.g., ['Van' => 'Van', ...]
// - $fuel_types (array) - e.g., ['Gasoline' => 'Gasoline', ...]
//
// For editing, additionally expected:
// - $vehicle_id (int)
// - $original_vehicle_data (array) - The current vehicle data from DB
//
// For form repopulation on validation error or initial load:
// - $object_title (string)
// - $object_content (string)
// - $object_status (string)
// - $meta_fields (array) containing:
//   - vehicle_plate_number
//   - vehicle_make
//   - vehicle_model
//   - vehicle_year
//   - vehicle_capacity
//   - vehicle_type
//   - vehicle_fuel_type
//   - vehicle_notes

$isEditing = isset($vehicle_id) && !empty($vehicle_id);
$formAction = $isEditing ? BASE_URL . 'vehicle/edit/' . $vehicle_id : BASE_URL . 'vehicle/add';

// Populate form fields with existing data if editing, or from POST data if validation failed, or defaults
$currentTitle = $object_title ?? ($original_vehicle_data['object_title'] ?? '');
$currentContent = $object_content ?? ($original_vehicle_data['object_content'] ?? ''); // General description
$currentStatus = $object_status ?? ($original_vehicle_data['object_status'] ?? 'available');

$currentPlateNumber = $meta_fields['vehicle_plate_number'] ?? ($original_vehicle_data['meta']['vehicle_plate_number'] ?? '');
$currentMake = $meta_fields['vehicle_make'] ?? ($original_vehicle_data['meta']['vehicle_make'] ?? '');
$currentModel = $meta_fields['vehicle_model'] ?? ($original_vehicle_data['meta']['vehicle_model'] ?? '');
$currentYear = $meta_fields['vehicle_year'] ?? ($original_vehicle_data['meta']['vehicle_year'] ?? '');
$currentCapacity = $meta_fields['vehicle_capacity'] ?? ($original_vehicle_data['meta']['vehicle_capacity'] ?? 0);
$currentVehicleType = $meta_fields['vehicle_type'] ?? ($original_vehicle_data['meta']['vehicle_type'] ?? '');
$currentFuelType = $meta_fields['vehicle_fuel_type'] ?? ($original_vehicle_data['meta']['vehicle_fuel_type'] ?? '');
$currentVehicleNotes = $meta_fields['vehicle_notes'] ?? ($original_vehicle_data['meta']['vehicle_notes'] ?? ''); // Specific notes

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7 col-xl-6">
        <div class="card shadow-sm mt-4 mb-5">
            <div class="card-body p-4">
                <h1 class="card-title text-center mb-4"><?php echo htmlspecialchars($pageTitle); ?></h1>

                <?php
                // Display general form errors if any
                if (!empty($errors['form_err'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($errors['form_err']) . '</div>';
                }
                ?>

                <form action="<?php echo $formAction; ?>" method="POST">
                    
                    <div class="mb-3">
                        <label for="object_title" class="form-label">Vehicle Name/Identifier <span class="text-danger">*</span></label>
                        <input type="text" name="object_title" id="object_title" class="form-control <?php echo (!empty($errors['object_title_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($currentTitle); ?>" required placeholder="e.g., Toyota HiAce Commuter - Red">
                        <small class="form-text text-muted">A descriptive name, could include make, model, or a unique identifier.</small>
                        <?php if (!empty($errors['object_title_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['object_title_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="vehicle_plate_number" class="form-label">Plate Number <span class="text-danger">*</span></label>
                        <input type="text" name="vehicle_plate_number" id="vehicle_plate_number" class="form-control <?php echo (!empty($errors['vehicle_plate_number_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($currentPlateNumber); ?>" required placeholder="e.g., ABC 1234">
                        <?php if (!empty($errors['vehicle_plate_number_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['vehicle_plate_number_err']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    <h5 class="mb-3">Vehicle Specifications</h5>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vehicle_make" class="form-label">Make</label>
                            <input type="text" name="vehicle_make" id="vehicle_make" class="form-control <?php echo (!empty($errors['vehicle_make_err'])) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo htmlspecialchars($currentMake); ?>" placeholder="e.g., Toyota">
                            <?php if (!empty($errors['vehicle_make_err'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['vehicle_make_err']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="vehicle_model" class="form-label">Model</label>
                            <input type="text" name="vehicle_model" id="vehicle_model" class="form-control <?php echo (!empty($errors['vehicle_model_err'])) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo htmlspecialchars($currentModel); ?>" placeholder="e.g., HiAce Commuter">
                            <?php if (!empty($errors['vehicle_model_err'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['vehicle_model_err']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vehicle_year" class="form-label">Year Manufactured</label>
                            <input type="number" name="vehicle_year" id="vehicle_year" class="form-control <?php echo (!empty($errors['vehicle_year_err'])) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo htmlspecialchars($currentYear); ?>" placeholder="e.g., 2023" min="1900" max="<?php echo date('Y') + 1; ?>">
                            <?php if (!empty($errors['vehicle_year_err'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['vehicle_year_err']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="vehicle_capacity" class="form-label">Passenger Capacity</label>
                            <input type="number" name="vehicle_capacity" id="vehicle_capacity" class="form-control <?php echo (!empty($errors['vehicle_capacity_err'])) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo htmlspecialchars($currentCapacity); ?>" min="0" placeholder="e.g., 12">
                            <?php if (!empty($errors['vehicle_capacity_err'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['vehicle_capacity_err']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vehicle_type" class="form-label">Vehicle Type</label>
                            <select name="vehicle_type" id="vehicle_type" class="form-select <?php echo (!empty($errors['vehicle_type_err'])) ? 'is-invalid' : ''; ?>">
                                <option value="">-- Select Type --</option>
                                <?php foreach ($vehicle_types as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($currentVehicleType == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['vehicle_type_err'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['vehicle_type_err']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="vehicle_fuel_type" class="form-label">Fuel Type</label>
                            <select name="vehicle_fuel_type" id="vehicle_fuel_type" class="form-select <?php echo (!empty($errors['vehicle_fuel_type_err'])) ? 'is-invalid' : ''; ?>">
                                <option value="">-- Select Fuel Type --</option>
                                <?php foreach ($fuel_types as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($currentFuelType == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['vehicle_fuel_type_err'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['vehicle_fuel_type_err']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr>
                    <h5 class="mb-3">Status & Notes</h5>

                    <div class="mb-3">
                        <label for="object_status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="object_status" id="object_status" class="form-select <?php echo (!empty($errors['object_status_err'])) ? 'is-invalid' : ''; ?>" required>
                            <?php foreach ($vehicle_statuses as $statusValue => $statusLabel): ?>
                                <option value="<?php echo htmlspecialchars($statusValue); ?>" <?php echo ($currentStatus == $statusValue) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($statusLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['object_status_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['object_status_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="object_content" class="form-label">General Description/Notes</label>
                        <textarea name="object_content" id="object_content" class="form-control <?php echo (!empty($errors['object_content_err'])) ? 'is-invalid' : ''; ?>"
                                  rows="3" placeholder="Any general notes about the vehicle, its history, or common use cases."><?php echo htmlspecialchars($currentContent); ?></textarea>
                        <?php if (!empty($errors['object_content_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['object_content_err']); ?></div>
                        <?php endif; ?>
                    </div>

                     <div class="mb-3">
                        <label for="vehicle_notes" class="form-label">Specific Vehicle Notes (Internal)</label>
                        <textarea name="vehicle_notes" id="vehicle_notes" class="form-control <?php echo (!empty($errors['vehicle_notes_err'])) ? 'is-invalid' : ''; ?>"
                                  rows="3" placeholder="e.g., Known issues, maintenance schedule, specific instructions for drivers."><?php echo htmlspecialchars($currentVehicleNotes); ?></textarea>
                        <?php if (!empty($errors['vehicle_notes_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['vehicle_notes_err']); ?></div>
                        <?php endif; ?>
                    </div>


                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <?php echo $isEditing ? 'Update Vehicle' : 'Add Vehicle'; ?>
                        </button>
                        <a href="<?php echo BASE_URL . 'vehicle'; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
