<?php
// This view is used by OpenOfficeController::createreservation()
// Expected variables:
// - $pageTitle (string)
// - $room (array) - Details of the room being booked
// - $breadcrumbs (array)
// - $errors (array) - Validation errors
// - $reservation_date (string) - Submitted date (for repopulating form)
// - $reservation_time_slots (array) - Submitted time slot array
// - $reservation_purpose (string) - Submitted purpose
// - $approved_reservations_data_for_js (array) - Raw PHP array of approved reservation start/end times

$formAction = BASE_URL . 'OpenOffice/createreservation/' . ($room['object_id'] ?? ''); // Ensure OpenOffice casing

$baseTimeSlots = [];
for ($hour = 8; $hour < 17; $hour++) { 
    $startTime = sprintf("%02d:00", $hour);
    $endTimeHour = $hour + 1;
    $endTime = sprintf("%02d:00", $endTimeHour);
    $displayStartTime = date("g:i A", strtotime($startTime));
    $displayEndTime = date("g:i A", strtotime($endTimeHour . ":00"));
    $displaySlot = $displayStartTime . ' - ' . $displayEndTime;
    $valueSlot = $startTime . '-' . $endTime;
    $baseTimeSlots[$valueSlot] = $displaySlot;
}

require_once __DIR__ . '/../layouts/header.php';

// Define JavaScript constants from PHP variables
// These will be outputted directly into a <script> tag by PHP before the main script block.
$js_vars_to_define = [
    'PHP_ROOM_ID' => intval($room['object_id'] ?? 0),
    'APP_BASE_URL' => BASE_URL, // Use a more specific name
    'AJAX_CONTROLLER_PATH' => 'OpenOffice/' // Specific to this view's AJAX calls
];
echo "<script>";
foreach ($js_vars_to_define as $key => $value) {
    // Ensure strings are properly quoted for JavaScript
    echo "const " . $key . " = " . (is_string($value) ? "'" . addslashes($value) . "'" : $value) . ";\n";
}
echo "</script>";

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
                // Error and session message display
                if (!empty($errors['form_err'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($errors['form_err']) . '</div>';
                }
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
                        <label class="form-label">Time Slots <span class="text-danger">*</span></label>
                        <div id="time_slots_container" class="row row-cols-2 row-cols-md-3 g-2">
                            <?php 
                            $submittedTimeSlots = $reservation_time_slots ?? [];
                            foreach ($baseTimeSlots as $value => $display):
                                $isChecked = (is_array($submittedTimeSlots) && in_array($value, $submittedTimeSlots)) ? 'checked' : '';
                                $checkboxId = "time_slot_" . htmlspecialchars(str_replace([':', '-'], '_', $value));
                            ?>
                                <div class="col">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input time-slot-checkbox" type="checkbox" 
                                               name="reservation_time_slots[]" id="<?php echo $checkboxId; ?>" 
                                               value="<?php echo htmlspecialchars($value); ?>" <?php echo $isChecked; ?>>
                                        <label class="form-check-label" for="<?php echo $checkboxId; ?>">
                                            <?php echo htmlspecialchars($display); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($errors['time_slot_err'])): ?>
                            <div class="text-danger small mt-1"><?php echo htmlspecialchars($errors['time_slot_err']); ?></div>
                        <?php endif; ?>
                        <p id="queueInfo" class="text-info small mt-2 mb-0"></p> 
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
                        Your reservation request will be submitted for approval.
                        Reservations are for 1-hour slots. You can select multiple consecutive or non-consecutive slots.
                    </p>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Submit Reservation Request</button>
                        <a href="<?php echo BASE_URL . 'OpenOffice/rooms'; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Note: PHP_ROOM_ID, APP_BASE_URL, and AJAX_CONTROLLER_PATH are now defined globally 
// by the PHP echo block before this script tag.

document.addEventListener('DOMContentLoaded', function() {
    // Ensure PHP_ROOM_ID, APP_BASE_URL, AJAX_CONTROLLER_PATH are defined by PHP above.
    // These are now globally available due to the <script> block generated by PHP.

    const approvedRoomReservations = <?php
        echo json_encode(
            $approved_reservations_data_for_js ?? [], 
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        ); 
    ?>;

    initializeReservationForm({
        dateInputId: 'reservation_date',
        timeSlotsContainerId: 'time_slots_container',
        queueInfoElId: 'queueInfo',
        itemId: (typeof PHP_ROOM_ID !== 'undefined') ? PHP_ROOM_ID : 0,
        appBaseUrl: (typeof APP_BASE_URL !== 'undefined') ? APP_BASE_URL : '/',
        ajaxControllerPath: (typeof AJAX_CONTROLLER_PATH !== 'undefined') ? AJAX_CONTROLLER_PATH : 'OpenOffice/',
        itemTypeKey: 'roomId', // Key to use in AJAX payload for the item's ID
        approvedReservationsData: approvedRoomReservations,
        baseTimeSlotsSelector: '.time-slot-checkbox'
    });
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
