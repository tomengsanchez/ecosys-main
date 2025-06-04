<?php
// This view is used by VehicleRequestController::myrequests()
// Expected variables:
// - $pageTitle (string)
// - $breadcrumbs (array)
// - $reservation_statuses (array) - (Optional, if needed for client-side logic, though status is rendered server-side)

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="vehicle-my-reservations-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'My Vehicle Reservations'); ?></h1>
        <a href="<?php echo BASE_URL . 'vehicle'; ?>" class="btn btn-primary"> <?php // Link to vehicle list to make a new request ?>
            <i class="fas fa-car-side me-1"></i> Make a New Vehicle Request
        </a>
    </div>

    <?php
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

    <div class="table-responsive">
        <table class="table table-striped table-hover" id="myVehicleReservationsTable"> 
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Vehicle</th>
                    <th>Purpose</th>
                    <th>Destination</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Requested On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php // Data will be loaded by DataTables via AJAX ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>

<script>
// Ensure jQuery and DataTables are loaded (typically in footer.php)
function initializeMyVehicleReservationsLogic() {
    $('#myVehicleReservationsTable').DataTable({
        "processing": true, 
        "serverSide": false, // Using client-side processing for this view based on full dataset from AJAX.
                             // For very large datasets, server-side processing would be better.
        "ajax": {
            "url": "<?php echo BASE_URL . 'VehicleRequest/ajaxGetMyVehicleReservations'; ?>", // Correct AJAX endpoint
            "type": "GET", 
            "dataSrc": "data", // Key in JSON response holding the array of data
            "error": function(xhr, error, thrown) {
                console.error("DataTables AJAX error for My Vehicle Reservations: ", error, thrown);
                $('#myVehicleReservationsTable tbody').html(
                    '<tr><td colspan="9" class="text-center text-danger">' +
                    'Error loading your vehicle reservations. Please try again later.' +
                    '</td></tr>'
                );
            }
        },
        "columns": [
            { "data": "id" },
            { "data": "vehicle_name" }, // Ensure this key matches the JSON from controller
            { "data": "purpose" },
            { "data": "destination" }, // New column for destination
            { "data": "start_time" },
            { "data": "end_time" },
            { "data": "requested_on" },
            { "data": "status" }, // This will be the HTML badge from the controller
            { 
                "data": "actions", // HTML for actions from the controller
                "orderable": false,
                "searchable": false
            }
        ],
        "order": [[ 6, "desc" ]], // Default sort by 'Requested On' descending
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        // "language": { // Optional: customize language strings
        //     "emptyTable": "You have no vehicle reservations.",
        //     "zeroRecords": "No matching vehicle reservations found."
        // }
    });

    // Event delegation for action buttons (if any were to be handled via JS, e.g., non-navigation clicks)
    // For simple link-based cancels, the controller handles it directly.
    // If you had AJAX based cancel:
    // $('#myVehicleReservationsTable tbody').on('click', '.cancel-vehicle-request-btn', function(e) {
    //     e.preventDefault();
    //     const reservationId = $(this).data('id');
    //     if (confirm('Are you sure you want to cancel this vehicle request?')) {
    //         // Perform AJAX call to VehicleRequest/cancel/{id}
    //         // On success, reload DataTables: $('#myVehicleReservationsTable').DataTable().ajax.reload();
    //     }
    // });
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && $.fn.DataTable) {
        initializeMyVehicleReservationsLogic();
    } else {
        console.error("jQuery or DataTables not loaded. 'My Vehicle Reservations' table cannot be initialized.");
        // Fallback or wait for jQuery/DataTables
        let dtCheckInterval = setInterval(function() {
            if (window.jQuery && $.fn.DataTable) {
                clearInterval(dtCheckInterval);
                initializeMyVehicleReservationsLogic();
            }
        }, 100);
    }
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
