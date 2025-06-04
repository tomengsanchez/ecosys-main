<?php
// This view is used by VehicleController::index()
// Expected variables from controller:
// - $pageTitle (string)
// - $breadcrumbs (array)
// - $vehicle_statuses (array) - For potential filter dropdowns, though not used in this basic setup

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="openoffice-vehicles-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Vehicles'); ?></h1>
        <?php if (userHasCapability('CREATE_VEHICLES')): ?>
        <a href="<?php echo BASE_URL . 'vehicle/add'; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Vehicle
        </a>
        <?php endif; ?>
    </div>

    <?php
    // Session messages display for success/error feedback
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
        <table class="table table-striped table-hover" id="vehiclesTable" style="width:100%;">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Vehicle Name/Identifier</th>
                    <th>Plate Number</th>
                    <th>Make & Model</th>
                    <th>Capacity</th>
                    <th>Status</th>
                    <th>Last Modified</th>
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
    $('#vehiclesTable').DataTable({
        "processing": true, // Show processing indicator
        "serverSide": true, // Enable server-side processing
        "ajax": {
            "url": "<?php echo BASE_URL . 'vehicle/ajaxGetVehicles'; ?>", // Endpoint in VehicleController
            "type": "POST", // Method type for the AJAX request
            "dataType": "json", // Expected data type from server
            "error": function(xhr, error, thrown) { // Enhanced error handling
                console.error("DataTables AJAX error: ", error, thrown);
                console.error("Response Text: ", xhr.responseText);
                // Display a user-friendly error in the table
                $('#vehiclesTable tbody').html(
                    '<tr><td colspan="8" class="text-center text-danger">' +
                    'Error loading data. Please check console or try again later. ' +
                    '<a href="http://datatables.net/tn/7" target="_blank">More info</a>.' +
                    '</td></tr>'
                );
            }
        },
        "columns": [
            // 'data' attributes should match the keys in the JSON response from ajaxGetVehicles
            { "data": "object_id", "name": "object_id" }, // 'name' is used by server-side for ordering if different from 'data'
            { "data": "vehicle_name", "name": "vehicle_name" },
            { "data": "plate_number", "name": "plate_number" },
            { "data": "make_model", "name": "make_model", "orderable": false }, // Example: Make & Model might not be directly sortable if combined
            { "data": "capacity", "name": "capacity" },
            { "data": "status", "name": "status" },
            { "data": "last_modified", "name": "last_modified" },
            { 
                "data": "actions",
                "orderable": false, // Actions column is usually not sortable
                "searchable": false // Actions column is usually not searchable
            }
        ],
        "order": [[0, "desc"]], // Default sort by ID descending
        "pageLength": 10, // Default number of rows per page
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]], // Options for number of rows per page
        // "language": { // Optional: customize language strings
        //     "processing": "Processing... <i class='fas fa-spinner fa-spin'></i>",
        //     "search": "Search Vehicles:"
        // }
    });
});
</script>
