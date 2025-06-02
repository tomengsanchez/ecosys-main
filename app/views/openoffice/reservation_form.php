<?php
// This view is used by OpenOfficeController::createreservation()
// Expected variables:
// - $pageTitle (string)
// - $room (array) - Details of the room being booked
// - $breadcrumbs (array)
// - $errors (array) - Validation errors
// - $reservation_date (string) - Submitted date (for repopulating form)
// - $reservation_time_slot (string) - Submitted time slot
// - $reservation_purpose (string) - Submitted purpose

// Determine the form action URL
$formAction = BASE_URL . 'openoffice/createreservation/' . ($room['object_id'] ?? '');

// Define available time slots (1-hour intervals from 8 AM to 5 PM)
$timeSlots = [];
for ($hour = 8; $hour < 17; $hour++) { // 8 AM to 4 PM start times, for slots ending by 5 PM
    $startTime = sprintf("%02d:00", $hour);
    $endTimeHour = $hour + 1;
    $endTime = sprintf("%02d:00", $endTimeHour);
    
    // Format for display (e.g., "8:00 AM - 9:00 AM")
    $displayStartTime = date("g:i A", strtotime($startTime));
    $displayEndTime = date("g:i A", strtotime($endTime));
    $displaySlot = $displayStartTime . ' - ' . $displayEndTime;

    // Value for the option (e.g., "08:00-09:00")
    $valueSlot = $startTime . '-' . $endTime;
    $timeSlots[$valueSlot] = $displaySlot;
}

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7 col-xl-6">
        <div class="card shadow-sm mt-4 mb-5">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Book Room: <?php echo htmlspecialchars($room['object_title'] ?? 'N/A'); ?></h4>
            </div>
            <div class="card-body p-4">
                <p class="card-text">
                    <strong>Location:</strong> <?php echo htmlspecialchars($room['meta']['room_location'] ?? 'N/A'); ?><br>
                    <strong>Capacity:</strong> <?php echo htmlspecialchars($room['meta']['room_capacity'] ?? 'N/A'); ?> people<br>
                    <?php if (!empty($room['meta']['room_equipment'])): ?>
                        <strong>Equipment:</strong> <?php echo nl2br(htmlspecialchars($room['meta']['room_equipment'])); ?>
                    <?php endif; ?>
                </p>
                <hr>
                <h5 class="mb-3">Reservation Details</h5>

                <?php
                if (!empty($errors['form_err'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($errors['form_err']) . '</div>';
                }
                if (isset($_SESSION['message'])) {
                    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['message']) . '</div>';
                    unset($_SESSION['message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                    unset($_SESSION['error_message']);
                }
                ?>

                <form action="<?php echo $formAction; ?>" method="POST">
                    
                    <div class="mb-3">
                        <label for="reservation_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="reservation_date" id="reservation_date" 
                               class="form-control <?php echo (!empty($errors['date_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($reservation_date ?? date('Y-m-d')); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                        <?php if (!empty($errors['date_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['date_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="reservation_time_slot" class="form-label">Time Slot <span class="text-danger">*</span></label>
                        <select name="reservation_time_slot" id="reservation_time_slot" 
                                class="form-select <?php echo (!empty($errors['time_slot_err'])) ? 'is-invalid' : ''; ?>" required>
                            <option value="">-- Select a Time Slot --</option>
                            <?php foreach ($timeSlots as $value => $display): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (isset($reservation_time_slot) && $reservation_time_slot == $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($display); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['time_slot_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['time_slot_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="reservation_purpose" class="form-label">Purpose of Reservation <span class="text-danger">*</span></label>
                        <textarea name="reservation_purpose" id="reservation_purpose" 
                                  class="form-control <?php echo (!empty($errors['purpose_err'])) ? 'is-invalid' : ''; ?>"
                                  rows="3" required><?php echo htmlspecialchars($reservation_purpose ?? ''); ?></textarea>
                        <?php if (!empty($errors['purpose_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['purpose_err']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <p class="text-muted small">
                        Your reservation request will be submitted for approval. You can view the status of your requests under "My Reservations".
                        Reservations are for 1-hour slots.
                    </p>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Submit Reservation Request</button>
                        <a href="<?php echo BASE_URL . 'openoffice/rooms'; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// No client-side JavaScript needed for this specific time slot implementation, 
// as the slots are predefined. The previous JS for adjusting datetime-local is removed.
// Server-side validation will be crucial.
?>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
