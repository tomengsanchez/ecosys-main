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
// - $approved_reservations_json (string) - JSON string of approved reservation start/end times for this room

// Determine the form action URL
$formAction = BASE_URL . 'openoffice/createreservation/' . ($room['object_id'] ?? '');

// Define available time slots (1-hour intervals from 8 AM to 5 PM)
// This array defines the base structure of the slots. JS will enable/disable them.
$baseTimeSlots = [];
for ($hour = 8; $hour < 17; $hour++) { // 8 AM to 4 PM start times, for slots ending by 5 PM
    $startTime = sprintf("%02d:00", $hour);
    $endTimeHour = $hour + 1;
    $endTime = sprintf("%02d:00", $endTimeHour);
    
    $displayStartTime = date("g:i A", strtotime($startTime));
    $displayEndTime = date("g:i A", strtotime($endTimeHour . ":00")); // Use $endTimeHour for correct AM/PM
    $displaySlot = $displayStartTime . ' - ' . $displayEndTime;

    $valueSlot = $startTime . '-' . $endTime;
    $baseTimeSlots[$valueSlot] = $displaySlot;
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
                            <?php 
                            // PHP loop to create the base options. JavaScript will manage their state.
                            foreach ($baseTimeSlots as $value => $display): ?>
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
                        Reservations are for 1-hour slots. Available slots are updated based on the selected date.
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('reservation_date');
    const timeSlotSelect = document.getElementById('reservation_time_slot');
    
    // Parse the approved reservations data passed from PHP
    // Ensure this variable name matches what's passed from the controller
    const approvedReservations = JSON.parse(<?php echo $approved_reservations_json ?? '[]'; ?>);
    
    // Store the original options from the select dropdown
    const baseTimeSlotOptions = Array.from(timeSlotSelect.options).map(opt => ({
        value: opt.value,
        text: opt.text,
        originalText: opt.text // Keep original text for resetting
    }));

    function isSlotBooked(slotStartTime, slotEndTime, approvedBookings) {
        for (const booking of approvedBookings) {
            const bookingStart = new Date(booking.start);
            const bookingEnd = new Date(booking.end);

            // Check for overlap:
            // (SlotStart < BookingEnd) and (SlotEnd > BookingStart)
            if (slotStartTime < bookingEnd && slotEndTime > bookingStart) {
                return true; // Conflict found
            }
        }
        return false; // No conflict
    }

    function updateAvailableTimeSlots() {
        if (!dateInput.value) {
            // If no date is selected, reset to default and disable all but the placeholder
            timeSlotSelect.innerHTML = ''; // Clear existing options
            baseTimeSlotOptions.forEach((optData, index) => {
                const option = new Option(optData.text, optData.value);
                if (optData.value === "") { // Placeholder
                    option.selected = true;
                } else {
                    option.disabled = true;
                }
                timeSlotSelect.add(option);
            });
            return;
        }

        const selectedDateStr = dateInput.value; // YYYY-MM-DD
        const now = new Date(); // Current date and time for comparison

        // Filter approved reservations for the selected date only
        const todaysApprovedBookings = approvedReservations.filter(booking => {
            const bookingStartDate = booking.start.substring(0, 10); // Extract YYYY-MM-DD part
            return bookingStartDate === selectedDateStr;
        });

        timeSlotSelect.innerHTML = ''; // Clear existing options before repopulating

        baseTimeSlotOptions.forEach((optData, index) => {
            const option = new Option(optData.originalText, optData.value); // Use originalText for display
            
            if (optData.value === "") { // Placeholder option
                option.selected = (timeSlotSelect.value === "" || !timeSlotSelect.value);
                timeSlotSelect.add(option);
                return; // Continue to next iteration
            }

            const timeParts = optData.value.split('-'); // e.g., "08:00-09:00"
            const slotStartHourMin = timeParts[0]; // "08:00"
            const slotEndHourMin = timeParts[1];   // "09:00"

            const slotFullStartTime = new Date(`${selectedDateStr}T${slotStartHourMin}:00`);
            const slotFullEndTime = new Date(`${selectedDateStr}T${slotEndHourMin}:00`);
            
            let isBooked = false;
            let isPast = false;

            // Check if the slot is in the past
            // Compare end of slot with current time to allow booking slots that start now but haven't ended
            if (slotFullEndTime <= now) { 
                isPast = true;
            }
            
            // Check if the slot is booked
            if (!isPast) { // Only check for booking if not already past
                 isBooked = isSlotBooked(slotFullStartTime, slotFullEndTime, todaysApprovedBookings);
            }

            if (isBooked) {
                option.disabled = true;
                option.text = `${optData.originalText} (Booked)`;
            } else if (isPast) {
                option.disabled = true;
                option.text = `${optData.originalText} (Past)`;
            } else {
                option.disabled = false;
            }
            
            // Preserve selection if it was previously selected and still valid
            if (timeSlotSelect.dataset.selectedValue === optData.value && !option.disabled) {
                option.selected = true;
            }

            timeSlotSelect.add(option);
        });
        // After repopulating, if no option is selected (e.g. previous selection became invalid), select placeholder
        if(timeSlotSelect.selectedIndex === -1 || timeSlotSelect.options[timeSlotSelect.selectedIndex].disabled){
            timeSlotSelect.value = "";
        }
    }

    if (dateInput) {
        dateInput.addEventListener('change', function() {
            timeSlotSelect.dataset.selectedValue = timeSlotSelect.value; // Store current selection before update
            updateAvailableTimeSlots();
        });
        // Initial call to populate time slots based on the default date
        updateAvailableTimeSlots(); 
    }
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
