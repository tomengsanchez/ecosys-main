<?php
// This view is used by OpenOfficeController::roomreservations()
// Expected variables:
// - $pageTitle (string)
// - $breadcrumbs (array)
// - $reservation_statuses (array) - May still be useful for reference or if any client-side logic needs it.

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="openoffice-reservations-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Room Reservations'); ?></h1>
        </div>

    <?php
    // Session messages display
    if (isset($_SESSION['admin_message'])) {
        $alertType = (strpos(strtolower($_SESSION['admin_message']), 'error') === false && 
                      strpos(strtolower($_SESSION['admin_message']), 'fail') === false && 
                      strpos(strtolower($_SESSION['admin_message']), 'cannot') === false) ? 'success' : 'danger';
        echo '<div class="alert alert-' . $alertType . ' alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['admin_message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['admin_message']); 
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
        <table class="table table-striped table-hover" id="allReservationsTable"> <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Room</th>
                    <th>User</th>
                    <th>Purpose</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Requested On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>

<script>
$(document).ready(function() {
    $('#allReservationsTable').DataTable({
        "processing": true, // Optional: show a processing indicator
        "serverSide": false, // For now, client-side processing of the full dataset.
                             // Change to true for full server-side processing.
        "ajax": {
            "url": "<?php echo BASE_URL . 'OpenOffice/ajaxGetAllReservations'; ?>", // Ensure controller name matches
            "type": "GET",
            "dataSrc": "data" // Key in JSON response holding the array of data
        },
        "columns": [
            { "data": "id" },
            { "data": "room" },
            { "data": "user" }, // Added User column
            { "data": "purpose" },
            { "data": "start_time" },
            { "data": "end_time" },
            { "data": "requested_on" },
            { "data": "status" },
            { 
                "data": "actions",
                "orderable": false,
                "searchable": false
            }
        ],
        "order": [[ 6, "desc" ]] // Default sort by 'Requested On' (index 6) descending
    });
});
</script>
