/**
 * reservation_form_helper.js
 *
 * Provides reusable JavaScript logic for reservation forms (rooms, vehicles, etc.)
 * to handle time slot availability, display, and queue information fetching.
 */

function initializeReservationForm(config) {
    const dateInput = document.getElementById(config.dateInputId);
    const timeSlotsContainer = document.getElementById(config.timeSlotsContainerId);
    const queueInfoEl = document.getElementById(config.queueInfoElId);
    const timeSlotCheckboxes = timeSlotsContainer.querySelectorAll(config.baseTimeSlotsSelector);

    if (!dateInput || !timeSlotsContainer || !queueInfoEl || timeSlotCheckboxes.length === 0) {
        console.error('Reservation form helper: Missing one or more required DOM elements.');
        return;
    }

    const itemId = config.itemId;
    const appBaseUrl = config.appBaseUrl;
    const ajaxControllerPath = config.ajaxControllerPath;
    const itemTypeKey = config.itemTypeKey; // e.g., 'roomId' or 'vehicleId'
    const approvedReservations = config.approvedReservationsData || [];

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
        if (!itemId || !selectedDate || selectedSlots.length === 0) {
            queueInfoEl.textContent = '';
            return;
        }

        const url = `${appBaseUrl}${ajaxControllerPath}getMultipleSlotsQueueInfo`;
        const payload = {
            date: selectedDate,
            slots: selectedSlots
        };
        payload[itemTypeKey] = itemId; // Dynamically set roomId or vehicleId

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest' // Important for some server-side AJAX checks
                },
                body: JSON.stringify(payload)
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
            } else if (data.pendingCounts) {
                let totalPending = 0;
                let infoMessages = [];
                for (const slot of selectedSlots) {
                    if (data.pendingCounts[slot] !== undefined) {
                        const count = parseInt(data.pendingCounts[slot], 10);
                        if (!isNaN(count) && count > 0) {
                            totalPending += count;
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
            } else {
                queueInfoEl.textContent = 'Queue information not available or an unexpected response was received.';
                queueInfoEl.className = 'text-warning small mt-2 mb-0';
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
                // Restore original checked state only if not disabled
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

    dateInput.addEventListener('change', updateAvailableTimeSlots);
    timeSlotsContainer.addEventListener('change', function(event) {
        if (event.target.matches(config.baseTimeSlotsSelector)) {
            const changedOptData = baseTimeSlotData.find(d => d.value === event.target.value);
            if (changedOptData) {
                changedOptData.originalChecked = event.target.checked;
            }
            updateAvailableTimeSlots(); // Re-evaluate all slots and fetch queue info
        }
    });

    updateAvailableTimeSlots(); // Initial call
}