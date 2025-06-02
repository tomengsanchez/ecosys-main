<?php
// pageTitle, welcomeMessage, and calendarEvents (JSON string) are passed from DashboardController.

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

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
        <div class="card-body">
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
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('reservationCalendar');
    if (calendarEl) {
        var calendarEvents = <?php echo $calendarEvents ?? '[]'; ?>; // Get events from PHP

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth', // Default view
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            events: calendarEvents,
            editable: false, // Set to true if you want drag-and-drop editing
            selectable: true, // Allows clicking on dates/times
            eventDidMount: function(info) {
                // Tooltip for event details
                var tooltipContent = `
                    <strong>${info.event.extendedProps.roomName || info.event.title}</strong><br>
                    Booked by: ${info.event.extendedProps.userName || 'N/A'}<br>
                    Status: ${info.event.extendedProps.status || 'N/A'}<br>
                    Purpose: ${info.event.extendedProps.purpose || 'N/A'}
                `;
                var tooltip = new bootstrap.Tooltip(info.el, {
                    title: tooltipContent,
                    placement: 'top',
                    trigger: 'hover',
                    container: 'body',
                    html: true
                });
            },
            // Example: handle date click to go to reservation form (more advanced)
            // dateClick: function(info) {
            //    alert('Clicked on: ' + info.dateStr);
            //    alert('Coordinates: ' + info.jsEvent.pageX + ',' + info.jsEvent.pageY);
            //    alert('Current view: ' + info.view.type);
            //    // Potentially redirect to a reservation form pre-filled with this date
            // }
        });
        calendar.render();
    } else {
        console.error("Calendar element #reservationCalendar not found.");
    }
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
