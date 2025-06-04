<?php
// This view is used by VehicleRequestController::create()
// Expected variables:
// - $pageTitle (string)
// - $vehicle (array) - Details of the vehicle being booked
// - $breadcrumbs (array)
// - $errors (array) - Validation errors
// - $reservation_date (string) - Submitted date
// - $reservation_time_slots (array) - Submitted time slots
// - $reservation_purpose (string) - Submitted purpose
// - $destination (string) - Submitted destination
// - $approved_reservations_data_for_js (array) - Approved reservations for this vehicle

$formAction = BASE_URL . 'VehicleRequest/create/' . ($vehicle['object_id'] ?? '');

// Define base time slots (e.g., 8 AM to 5 PM, hourly)
// This should ideally match how room time slots are generated for consistency
$baseTimeSlots = [];
for ($hour = 8; $hour < 17; $hour++) { // 8 AM to 4 PM start times, for 1-hour slots ending at 5 PM
    $startTime = sprintf("%02d:00", $hour);
    $endTimeHour = $hour + 1;
    $endTime = sprintf("%02d:00", $endTimeHour);
    
    // Format for display (e.g., "8:00 AM - 9:00 AM")
    $displayStartTime = date("g:i A", strtotime($startTime));
    $displayEndTime = date("g:i A", strtotime($endTimeHour . ":00")); // Ensure correct end time for display
    $displaySlot = $displayStartTime . ' - ' . $displayEndTime;
    
    $valueSlot = $startTime . '-' . $endTime; // Value for the checkbox
    $baseTimeSlots[$valueSlot] = $displaySlot;
}

require_once __DIR__ . '/../layouts/header.php';

// Pass PHP variables to JavaScript
$jsVehicleId = $vehicle['object_id'] ?? 0;
echo "<script>const PHP_VEHICLE_ID = " . intval($jsVehicleId) . ";</script>";
echo "<script>const BASE_AJAX_URL = '" . BASE_URL . "';</script>"; // For AJAX calls if any JS needs it
// Note: The queue info AJAX endpoint for vehicles might need to be different or adapted.
// For now, we'll assume a similar structure to room queue info if implemented.

?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7 col-xl-6">
        <div class="card shadow-sm mt-4 mb-5">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="fas fa-car me-2"></i>Request Vehicle: <?php echo htmlspecialchars($vehicle['object_title'] ?? 'N/A'); ?></h4>
            </div>
            <div class="card-body p-4">
                <p class="card-text">
                    <strong>Plate Number:</strong> <?php echo htmlspecialchars($vehicle['meta']['vehicle_plate_number'] ?? 'N/A'); ?><br>
                    <strong>Make & Model:</strong> <?php echo htmlspecialchars(($vehicle['meta']['vehicle_make'] ?? '') . ' ' . ($vehicle['meta']['vehicle_model'] ?? '')); ?><br>
                    <strong>Capacity:</strong> <?php echo htmlspecialchars($vehicle['meta']['vehicle_capacity'] ?? 'N/A'); ?> people<br>
                    <?php if (!empty($vehicle['object_content'])): ?>
                        <strong>Description:</strong> <?php echo nl2br(htmlspecialchars($vehicle['object_content'])); ?><br>
                    <?php endif; ?>
                     <?php if (!empty($vehicle['meta']['vehicle_type'])): ?>
                        <strong>Type:</strong> <?php echo htmlspecialchars($vehicle['meta']['vehicle_type']); ?><br>
                    <?php endif; ?>
                </p>
                <hr>
                <h5 class="mb-3">Reservation Details</h5>

                <?php
                // Display form errors or session messages
                if (!empty($errors['form_err'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($errors['form_err']) . '</div>';
                }
                if (isset($_SESSION['message'])) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    unset($_SESSION['message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
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
                            // Retrieve selected time slots for repopulation if form was submitted with errors
                            $submittedTimeSlots = $reservation_time_slots ?? [];
                            foreach ($baseTimeSlots as $value => $display):
                                $isChecked = in_array($value, $submittedTimeSlots) ? 'checked' : '';
                            ?>
                                <div class="col">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input time-slot-checkbox" type="checkbox" 
                                               name="reservation_time_slots[]" id="time_slot_<?php echo htmlspecialchars(str_replace([':', '-'], '_', $value)); // Make ID more robust ?>" 
                                               value="<?php echo htmlspecialchars($value); ?>" <?php echo $isChecked; ?>>
                                        <label class="form-check-label" for="time_slot_<?php echo htmlspecialchars(str_replace([':', '-'], '_', $value)); ?>">
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
                        <label for="destination" class="form-label">Destination <span class="text-danger">*</span></label>
                        <input type="text" name="destination" id="destination" 
                                  class="form-control <?php echo (!empty($errors['destination_err'])) ? 'is-invalid' : ''; ?>"
                                  value="<?php echo htmlspecialchars($destination ?? ''); ?>" required>
                        <?php if (!empty($errors['destination_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['destination_err']); ?></div>
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
                        Your vehicle request will be submitted for approval.
                        Reservations are for 1-hour slots. You can select multiple consecutive or non-consecutive slots.
                    </p>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Submit Vehicle Request</button>
                        <a href="<?php echo BASE_URL . 'vehicle'; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('reservation_date');
    const timeSlotCheckboxes = document.querySelectorAll('.time-slot-checkbox');
    const timeSlotsContainer = document.getElementById('time_slots_container'); // For event delegation
    const queueInfoEl = document.getElementById('queueInfo');
    const vehicleId = typeof PHP_VEHICLE_ID !== 'undefined' ? PHP_VEHICLE_ID : 0;
    const ajaxBaseUrl = typeof BASE_AJAX_URL !== 'undefined' ? BASE_AJAX_URL : '/';

    // This comes from PHP, listing already approved reservations for the current vehicle
    const approvedReservations = <?php 
        echo json_encode(
            $approved_reservations_data_for_js ?? [], 
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        ); 
    ?>;
    
    // Store original state of checkboxes (value, label, and if it was checked on page load due to form repopulation)
    const baseTimeSlotData = Array.from(timeSlotCheckboxes).map(checkbox => ({
        value: checkbox.value,
        label: checkbox.nextElementSibling.textContent.trim(),
        originalChecked: checkbox.checked 
    }));

    function isSlotBooked(slotStartTime, slotEndTime, approvedBookings) {
        for (const booking of approvedBookings) {
            const bookingStart = new Date(booking.start);
            const bookingEnd = new Date(booking.end);
            // Check for overlap: (SlotStart < BookingEnd) and (SlotEnd > BookingStart)
            if (slotStartTime < bookingEnd && slotEndTime > bookingStart) {
                return true; 
            }
        }
        return false; 
    }

    // Placeholder for fetching queue info. This would be similar to the room reservation.
    // You'll need an AJAX endpoint in VehicleRequestController: e.g., getVehicleSlotsQueueInfo
    async function fetchQueueInfoForSelectedSlots(selectedDate, selectedSlots) {
        queueInfoEl.textContent = 'Checking availability...';
        if (!vehicleId || !selectedDate || selectedSlots.length === 0) {
            queueInfoEl.textContent = ''; 
            return;
        }
        
        // const url = `${ajaxBaseUrl}VehicleRequest/getVehicleSlotsQueueInfo`; // Example endpoint
        // For now, we'll just clear the message, as the endpoint isn't fully implemented.
        // console.log("Would fetch queue info for vehicle:", vehicleId, "Date:", selectedDate, "Slots:", selectedSlots);
        // queueInfoEl.textContent = 'Queue information check not yet implemented for vehicles.';
        // queueInfoEl.className = 'text-muted small mt-2 mb-0';
        // return;


        // Actual AJAX call (similar to room booking)
        // This assumes an endpoint like 'VehicleRequest/getMultipleSlotsQueueInfo' exists
        // and works like the one for rooms.
        const url = `${ajaxBaseUrl}VehicleRequest/getMultipleSlotsQueueInfo`; // Hypothetical endpoint
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vehicleId: vehicleId, date: selectedDate, slots: selectedSlots })
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();

            if (data.error) {
                queueInfoEl.textContent = `Note: ${data.error}`;
                queueInfoEl.className = 'text-danger small mt-2 mb-0';
            } else {
                let totalPending = 0;
                let infoMessages = [];
                for (const slot of selectedSlots) {
                    if (data.pendingCounts && data.pendingCounts[slot] !== undefined) {
                        const count = data.pendingCounts[slot];
                        totalPending += count;
                        if (count > 0) {
                            const slotLabel = baseTimeSlotData.find(d => d.value === slot)?.label || slot;
                            infoMessages.push(`${slotLabel}: ${count} pending request(s)`);
                        }
                    }
                }
                if (totalPending > 0) {
                    queueInfoEl.innerHTML = `There are pending requests for your selected slots:<br>${infoMessages.join('<br>')}<br>You would be in queue for these slots.`;
                } else {
                    queueInfoEl.textContent = 'Selected slots currently have no other pending requests.';
                }
                queueInfoEl.className = 'text-info small mt-2 mb-0';
            }
        } catch (error) {
            console.error('Error fetching vehicle queue info:', error);
            queueInfoEl.textContent = 'Could not retrieve queue information for vehicle.';
            queueInfoEl.className = 'text-warning small mt-2 mb-0';
        }
    }


    function updateAvailableTimeSlots() {
        queueInfoEl.textContent = ''; 
        const selectedDateStr = dateInput.value; 
        const now = new Date(); 
        // No need to set hours to 0 for 'now' if comparing full datetime of slots

        // Filter approvedReservations for the selected date
        const todaysApprovedBookings = approvedReservations.filter(booking => {
            // Make sure booking.start is defined and is a string
            return typeof booking.start === 'string' && booking.start.substring(0, 10) === selectedDateStr;
        });

        timeSlotCheckboxes.forEach(checkbox => {
            const optData = baseTimeSlotData.find(d => d.value === checkbox.value);
            if (!optData) return; // Should not happen if baseTimeSlotData is correct

            const timeParts = optData.value.split('-'); 
            const slotStartHourMin = timeParts[0]; 
            const slotEndHourMin = timeParts[1];   

            const slotFullStartTime = new Date(`${selectedDateStr}T${slotStartHourMin}:00`);
            const slotFullEndTime = new Date(`${selectedDateStr}T${slotEndHourMin}:00`);
            
            let isBooked = false;
            let isPast = false;
            const currentTimeForSlotCheck = new Date(); 

            // A slot is in the past if its END time is before or at the current time
            if (slotFullEndTime <= currentTimeForSlotCheck) { 
                isPast = true;
            }
            
            if (!isPast) { // Only check for booking conflicts if the slot is not in the past
                 isBooked = isSlotBooked(slotFullStartTime, slotFullEndTime, todaysApprovedBookings);
            }

            checkbox.disabled = isBooked || isPast;
            const label = checkbox.nextElementSibling; // Get the label element
            if (isBooked) {
                label.textContent = `${optData.label} (Booked)`;
                checkbox.checked = false; 
            } else if (isPast) {
                label.textContent = `${optData.label} (Past)`;
                checkbox.checked = false; 
            } else {
                label.textContent = optData.label;
                // Restore original checked state from form repopulation if not disabled
                checkbox.checked = optData.originalChecked && !checkbox.disabled;
            }
        });
        
        // After updating slot availability, fetch queue info for currently selected & enabled slots
        const selectedSlots = Array.from(timeSlotCheckboxes)
                                .filter(cb => cb.checked && !cb.disabled)
                                .map(cb => cb.value);
        if (selectedSlots.length > 0 && dateInput.value) {
            fetchQueueInfoForSelectedSlots(dateInput.value, selectedSlots);
        } else {
            queueInfoEl.textContent = ''; // Clear queue info if no slots are selected or date is missing
        }
    }

    if (dateInput && timeSlotsContainer) {
        dateInput.addEventListener('change', updateAvailableTimeSlots);
        
        // Use event delegation on the container for checkbox changes
        timeSlotsContainer.addEventListener('change', function(event) {
            if (event.target.classList.contains('time-slot-checkbox')) {
                // Update originalChecked state for the changed checkbox based on its current state
                const changedOptData = baseTimeSlotData.find(d => d.value === event.target.value);
                if (changedOptData) {
                    changedOptData.originalChecked = event.target.checked;
                }

                const selectedSlots = Array.from(timeSlotCheckboxes)
                                        .filter(cb => cb.checked && !cb.disabled)
                                        .map(cb => cb.value);
                if (selectedSlots.length > 0 && dateInput.value) {
                    fetchQueueInfoForSelectedSlots(dateInput.value, selectedSlots);
                } else {
                    queueInfoEl.textContent = ''; 
                }
            }
        });
        updateAvailableTimeSlots(); // Initial call to set up slots based on default date
    }
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
