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
    const dateInput = document.getElementById('reservation_date');
    const timeSlotCheckboxes = document.querySelectorAll('.time-slot-checkbox');
    const timeSlotsContainer = document.getElementById('time_slots_container');
    const queueInfoEl = document.getElementById('queueInfo');
    
    // Use the globally defined constants from PHP
    const roomId = (typeof PHP_ROOM_ID !== 'undefined') ? PHP_ROOM_ID : 0;
    const appBaseUrl = (typeof APP_BASE_URL !== 'undefined') ? APP_BASE_URL : '/'; // Provide a fallback
    const ajaxControllerPath = (typeof AJAX_CONTROLLER_PATH !== 'undefined') ? AJAX_CONTROLLER_PATH : 'OpenOffice/';


    const approvedReservations = <?php 
        echo json_encode(
            $approved_reservations_data_for_js ?? [], 
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        ); 
    ?>;
    
    const baseTimeSlotData = Array.from(timeSlotCheckboxes).map(checkbox => ({
        value: checkbox.value,
        label: checkbox.nextElementSibling.textContent.trim(),
        originalChecked: checkbox.checked 
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

    async function fetchQueueInfoForSelectedSlots(selectedDate, selectedSlots) {
        queueInfoEl.textContent = 'Checking availability...';
        if (!roomId || !selectedDate || selectedSlots.length === 0) {
            queueInfoEl.textContent = ''; 
            return;
        }
        
        // Construct URL using the JS constants defined by PHP
        const url = `${appBaseUrl}${ajaxControllerPath}getMultipleSlotsQueueInfo`; 
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    roomId: roomId, 
                    date: selectedDate,
                    slots: selectedSlots
                })
            });

            if (!response.ok) {
                let errorText = `HTTP error! status: ${response.status}`;
                try {
                    const errorData = await response.json();
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
            console.error('Error fetching queue info:', error);
            queueInfoEl.textContent = 'Could not retrieve queue information: ' + error.message;
            queueInfoEl.className = 'text-warning small mt-2 mb-0';
        }
    }

    function updateAvailableTimeSlots() {
        queueInfoEl.textContent = ''; 
        const selectedDateStr = dateInput.value; 
        const now = new Date(); 
        
        const todaysApprovedBookings = approvedReservations.filter(booking => {
            return typeof booking.start === 'string' && booking.start.substring(0, 10) === selectedDateStr;
        });

        timeSlotCheckboxes.forEach(checkbox => {
            const optData = baseTimeSlotData.find(d => d.value === checkbox.value);
            if (!optData) return;

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

            checkbox.disabled = isBooked || isPast;
            const label = checkbox.nextElementSibling;
            if (isBooked) {
                label.textContent = `${optData.label} (Booked)`;
                checkbox.checked = false; 
            } else if (isPast) {
                label.textContent = `${optData.label} (Past)`;
                checkbox.checked = false; 
            } else {
                label.textContent = optData.label;
                checkbox.checked = optData.originalChecked && !checkbox.disabled;
            }
        });
        
        const selectedSlots = Array.from(timeSlotCheckboxes)
                                .filter(cb => cb.checked && !cb.disabled)
                                .map(cb => cb.value);
        if (selectedSlots.length > 0 && dateInput.value) {
            fetchQueueInfoForSelectedSlots(dateInput.value, selectedSlots);
        } else {
            queueInfoEl.textContent = ''; 
        }
    }

    if (dateInput && timeSlotsContainer) {
        dateInput.addEventListener('change', updateAvailableTimeSlots);
        
        timeSlotsContainer.addEventListener('change', function(event) {
            if (event.target.classList.contains('time-slot-checkbox')) {
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
        updateAvailableTimeSlots(); 
    }
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
