<?php
// This view is used by OpenOfficeController::myreservations()
// Expected variables:
// - $pageTitle (string)
// - $breadcrumbs (array)
// - $reservation_statuses (array) - This might still be useful if any client-side logic needs it,
//                                   though status rendering is now done server-side for the table.

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="openoffice-my-reservations-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'My Room Reservations'); ?></h1>
        <a href="<?php echo BASE_URL . 'OpenOffice/rooms'; ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Make a New Reservation
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
        <table class="table table-striped table-hover" id="myReservationsTable"> <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Room</th>
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
    $('#myReservationsTable').DataTable({
        "processing": true, // Optional: show a processing indicator
        "serverSide": false, // For now, we're doing client-side processing of the full dataset.
                             // Change to true if implementing full server-side processing (pagination, search, sort)
        "ajax": {
            "url": "<?php echo BASE_URL . 'OpenOffice/ajaxGetUserReservations'; ?>",
            "type": "GET", // Or "POST" if your endpoint expects that
            "dataSrc": "data" // The key in the JSON response that holds the array of data
        },
        "columns": [
            { "data": "id" },
            { "data": "room" },
            { "data": "purpose" },
            { "data": "start_time" },
            { "data": "end_time" },
            { "data": "requested_on" },
            { "data": "status" },
            { 
                "data": "actions",
                "orderable": false, // Actions column is usually not sortable
                "searchable": false // Actions column is usually not searchable
            }
        ],
        // Optional: You can add default ordering, language options, etc.
        "order": [[ 5, "desc" ]] // Default sort by 'Requested On' descending
    });
});
</script>
