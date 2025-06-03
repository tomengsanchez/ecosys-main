<?php
// pageTitle, welcomeMessage are passed from DashboardController.
// Calendar events are now loaded via AJAX.

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>
<style>
    /* Basic styling for the calendar loading overlay */
    #calendarLoadingOverlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(255, 255, 255, 0.75); /* Semi-transparent white */
        z-index: 1000; /* Ensure it's on top of the calendar */
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.25rem; /* Match card border radius */
    }
    .calendar-relative-container {
        position: relative; /* Needed for absolute positioning of the overlay */
    }
</style>

<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h2>
    </div>

    <?php if (isset($welcomeMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($welcomeMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <p>This is your main dashboard area. You are successfully logged in!</p>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Room Reservation Calendar</h5>
        </div>
        <div class="card-body calendar-relative-container"> {/* Added class for positioning context */}
            <div id="calendarLoadingOverlay" style="display: none;">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading events...</span>
                </div>
            </div>
            <div id="reservationCalendar" style="max-height: 650px;"></div>
        </div>
        <div class="card-footer bg-light">
            <small class="text-muted">
                <span style="color: #5cb85c; font-weight: bold;">■</span> Approved &nbsp;&nbsp;
                <span style="color: #f0ad4e; font-weight: bold;">■</span> Pending
            </small>
        </div>
    </div>
    <h4 class="mt-5">Quick Links:</h4>
    <ul>
        <li>Manage your profile (if a profile page is created).</li>
        <li>View site statistics.</li>
        <li>Access administrative tools (if applicable).</li>
        <li>Create or manage content (if this is a CMS).</li>
    </ul>


    <h4 class="mt-5">Session Information (for demonstration):</h4>
    <pre class="bg-light p-3 border rounded small">
<?php
// Display some session information for debugging/demonstration
if (isset($_SESSION)) {
    print_r($_SESSION);
}
?>
    </pre>

    <p class="mt-4">
        <a href="<?php echo BASE_URL . 'auth/logout'; ?>" class="btn btn-danger"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
    </p>
</div>

<script>
// Wait for the DOM to be fully loaded, then check for jQuery, then initialize FullCalendar
document.addEventListener('DOMContentLoaded', function() {
    let jqueryCheckInterval = setInterval(function() {
        if (window.jQuery && typeof FullCalendar !== 'undefined') { // Also check if FullCalendar is loaded
            clearInterval(jqueryCheckInterval);
            // Now that jQuery and FullCalendar are loaded, initialize the calendar.
            // We can use $(document).ready or just call the init function directly.
            initializeDashboardCalendar();
        }
    }, 100); // Check every 100ms
});

function initializeDashboardCalendar() {
    var calendarEl = document.getElementById('reservationCalendar');
    var loadingOverlayEl = document.getElementById('calendarLoadingOverlay'); // Get the overlay element

    if (calendarEl && loadingOverlayEl) { // Check if overlay element also exists
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth', 
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            events: '<?php echo BASE_URL . "dashboard/ajaxCalendarEvents"; ?>', 
            
            loading: function(isLoading) {
                if (isLoading) {
                    loadingOverlayEl.style.display = 'flex'; // Show the overlay
                    console.log('Calendar is loading events...');
                } else {
                    loadingOverlayEl.style.display = 'none'; // Hide the overlay
                    console.log('Calendar events loaded.');
                }
            },
            eventDidMount: function(info) {
                var tooltipContent = `
                    <strong>${info.event.extendedProps.roomName || info.event.title}</strong><br>
                    Booked by: ${info.event.extendedProps.userName || 'N/A'}<br>
                    Status: ${info.event.extendedProps.status || 'N/A'}<br>
                    Purpose: ${info.event.extendedProps.purpose || 'N/A'}<br>
                    <hr class='my-1'>
                    Starts: ${info.event.extendedProps.formattedStartTime || 'N/A'}<br>
                    Ends: ${info.event.extendedProps.formattedEndTime || 'N/A'}
                `;
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    var tooltip = new bootstrap.Tooltip(info.el, {
                        title: tooltipContent,
                        placement: 'top',
                        trigger: 'hover',
                        container: 'body',
                        html: true,
                        sanitize: false 
                    });
                } else {
                    info.el.setAttribute('title', 
                        (info.event.extendedProps.roomName || info.event.title) +
                        `\nBooked by: ${info.event.extendedProps.userName || 'N/A'}` +
                        `\nStatus: ${info.event.extendedProps.status || 'N/A'}` +
                        `\nPurpose: ${info.event.extendedProps.purpose || 'N/A'}` +
                        `\nStarts: ${info.event.extendedProps.formattedStartTime || 'N/A'}` +
                        `\nEnds: ${info.event.extendedProps.formattedEndTime || 'N/A'}`
                    );
                }
            },
            eventClick: function(info) {
                // Example: alert event details or redirect
                // alert('Event: ' + info.event.title + '\nReservation ID: ' + info.event.extendedProps.reservation_id);
                // window.location.href = '<?php echo BASE_URL . "openoffice/viewreservation/"; ?>' + info.event.extendedProps.reservation_id;
                info.jsEvent.preventDefault(); 
            }
        });
        calendar.render();
    } else {
        if (!calendarEl) console.error("Calendar element #reservationCalendar not found.");
        if (!loadingOverlayEl) console.error("Calendar loading overlay element #calendarLoadingOverlay not found.");
    }
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
