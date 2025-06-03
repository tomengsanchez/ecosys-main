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
// - $approved_reservations_data_for_js (array) - Raw PHP array of approved reservation start/end times

$formAction = BASE_URL . 'Openoffice/createreservation/' . ($room['object_id'] ?? '');

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

$jsRoomId = $room['object_id'] ?? 0;
echo "<script>const PHP_ROOM_ID = " . intval($jsRoomId) . ";</script>";
echo "<script>const BASE_AJAX_URL = '" . BASE_URL . "';</script>"; 

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
                // Session messages display
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
                        <label for="reservation_time_slot" class="form-label">Time Slot <span class="text-danger">*</span></label>
                        <select name="reservation_time_slot" id="reservation_time_slot" 
                                class="form-select <?php echo (!empty($errors['time_slot_err'])) ? 'is-invalid' : ''; ?>" required>
                            <option value="">-- Select a Time Slot --</option>
                            <?php 
                            foreach ($baseTimeSlots as $value => $display): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (isset($reservation_time_slot) && $reservation_time_slot == $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($display); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['time_slot_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['time_slot_err']); ?></div>
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
                        Reservations are for 1-hour slots.
                    </p>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Submit Reservation Request</button>
                        <a href="<?php echo BASE_URL . 'Openoffice/rooms'; ?>" class="btn btn-secondary">Cancel</a>
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
    const queueInfoEl = document.getElementById('queueInfo');
    const roomId = typeof PHP_ROOM_ID !== 'undefined' ? PHP_ROOM_ID : 0;
    const ajaxBaseUrl = typeof BASE_AJAX_URL !== 'undefined' ? BASE_AJAX_URL : '/';

    // Directly assign the PHP array, encoded by PHP, to the JavaScript variable.
    // JSON_HEX_* options are used for security to prevent breaking out of JS context.
    const approvedReservations = <?php 
        echo json_encode(
            $approved_reservations_data_for_js ?? [], 
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        ); 
    ?>;
    
    console.log('[DEBUG] Approved Reservations:', approvedReservations); // For debugging

    const baseTimeSlotOptions = Array.from(timeSlotSelect.options).map(opt => ({
        value: opt.value,
        text: opt.text,
        originalText: opt.text
    }));

    function isSlotBooked(slotStartTime, slotEndTime, approvedBookings) {
        for (const booking of approvedBookings) {
            const bookingStart = new Date(booking.start);
            const bookingEnd = new Date(booking.end);
            if (slotStartTime < bookingEnd && slotEndTime > bookingStart) {
                return true; 
            }
        }
        return false; 
    }

    async function fetchQueueInfo(selectedDate, timeSlotValue) {
        queueInfoEl.textContent = 'Checking availability...';
        if (!roomId || !selectedDate || !timeSlotValue) {
            queueInfoEl.textContent = ''; 
            return;
        }
        
        const url = `${ajaxBaseUrl}Openoffice/getSlotQueueInfo?roomId=${roomId}&date=${selectedDate}&slot=${timeSlotValue}`;

        try {
            const response = await fetch(url);
            if (!response.ok) {
                // Try to get more detailed error from response if possible
                let errorText = `HTTP error! status: ${response.status}`;
                try {
                    const errorData = await response.json(); // if server sends JSON error
                    if (errorData && errorData.error) {
                        errorText = errorData.error;
                    }
                } catch (e) { /* ignore if response is not JSON */ }
                throw new Error(errorText);
            }
            const data = await response.json();

            if (data.error) {
                queueInfoEl.textContent = `Note: ${data.error}`;
                queueInfoEl.className = 'text-danger small mt-2 mb-0';
            } else {
                const count = data.pendingCount;
                if (count > 0) {
                    queueInfoEl.textContent = `There are ${count} other pending request(s) for this slot. You would be #${count + 1} in the queue.`;
                } else {
                    queueInfoEl.textContent = 'This slot currently has no other pending requests.';
                }
                queueInfoEl.className = 'text-info small mt-2 mb-0';
            }
        } catch (error) {
            console.error('Error fetching queue info:', error);
            queueInfoEl.textContent = 'Could not retrieve queue information: ' + error.message;
            queueInfoEl.className = 'text-warning small mt-2 mb-0';
        }
    }

    function updateAvailableTimeSlots() {
        queueInfoEl.textContent = ''; 
        if (!dateInput.value) {
            timeSlotSelect.innerHTML = ''; 
            baseTimeSlotOptions.forEach((optData) => {
                const option = new Option(optData.text, optData.value);
                if (optData.value === "") { option.selected = true; } 
                else { option.disabled = true; }
                timeSlotSelect.add(option);
            });
            return;
        }

        const selectedDateStr = dateInput.value; 
        const now = new Date(); 
        now.setHours(0,0,0,0); 

        const todaysApprovedBookings = approvedReservations.filter(booking => {
            return booking.start.substring(0, 10) === selectedDateStr;
        });

        timeSlotSelect.innerHTML = ''; 
        let firstAvailableSlotValue = null;

        baseTimeSlotOptions.forEach((optData) => {
            const option = new Option(optData.originalText, optData.value); 
            if (optData.value === "") { 
                option.selected = (timeSlotSelect.value === "" || !timeSlotSelect.value);
                timeSlotSelect.add(option);
                return; 
            }

            const timeParts = optData.value.split('-'); 
            const slotStartHourMin = timeParts[0]; 
            const slotEndHourMin = timeParts[1];   

            const slotFullStartTime = new Date(`${selectedDateStr}T${slotStartHourMin}:00`);
            const slotFullEndTime = new Date(`${selectedDateStr}T${slotEndHourMin}:00`);
            
            let isBooked = false;
            let isPast = false;
            const currentTimeForSlotCheck = new Date(); 

            if (slotFullEndTime <= currentTimeForSlotCheck) { 
                isPast = true;
            }
            
            if (!isPast) {
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
                if (firstAvailableSlotValue === null) { 
                    firstAvailableSlotValue = optData.value;
                }
            }
            
            if (timeSlotSelect.dataset.selectedValue === optData.value && !option.disabled) {
                option.selected = true;
            }
            timeSlotSelect.add(option);
        });
        
        if(timeSlotSelect.selectedIndex === -1 || timeSlotSelect.options[timeSlotSelect.selectedIndex].disabled || timeSlotSelect.value === ""){
            if (firstAvailableSlotValue) {
                timeSlotSelect.value = firstAvailableSlotValue;
            } else {
                 timeSlotSelect.value = ""; 
            }
        }
        
        if (timeSlotSelect.value !== "" && !timeSlotSelect.options[timeSlotSelect.selectedIndex].disabled) {
            fetchQueueInfo(selectedDateStr, timeSlotSelect.value);
        }
    }

    if (dateInput) {
        dateInput.addEventListener('change', function() {
            timeSlotSelect.dataset.selectedValue = timeSlotSelect.value; 
            updateAvailableTimeSlots();
        });
        timeSlotSelect.addEventListener('change', function() {
            if (this.value !== "" && !this.options[this.selectedIndex].disabled && dateInput.value) {
                fetchQueueInfo(dateInput.value, this.value);
            } else {
                queueInfoEl.textContent = ''; 
            }
        });
        updateAvailableTimeSlots(); 
    }
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
