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
// Define BASE_URL for JavaScript
const BASE_URL = '<?php echo BASE_URL; ?>';

// Helper function to escape HTML, reusable
function escapeHtml(unsafe) {
    if (unsafe === null || typeof unsafe === 'undefined') return '';
    return String(unsafe)
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

$(document).ready(function() {
    $('#vehiclesTable').DataTable({
        "processing": true, // Show processing indicator
        "serverSide": true, // Enable server-side processing
        "ajax": {
            "url": BASE_URL + 'vehicle/ajaxGetVehicles', // Use JS BASE_URL here
            "type": "POST", // Method type for the AJAX request
            "dataType": "json", // Expected data type from server
            "dataSrc": function (json) { // Custom dataSrc to process actions column
                if (json.data) {
                    json.data.forEach(function(vehicle) {
                        let actionsHtml = '';
                        // Edit button
                        if (<?php echo json_encode(userHasCapability('EDIT_VEHICLES')); ?>) {
                            actionsHtml += '<a href="' + BASE_URL + 'vehicle/edit/' + escapeHtml(String(vehicle.object_id)) + '" class="btn btn-sm btn-primary me-1" title="Edit"><i class="fas fa-edit"></i></a>';
                        }
                        // Delete button
                        if (<?php echo json_encode(userHasCapability('DELETE_VEHICLES')); ?>) {
                            actionsHtml += ' <a href="' + BASE_URL + 'vehicle/delete/' + escapeHtml(String(vehicle.object_id)) + '" \
                                               class="btn btn-sm btn-danger" title="Delete"\
                                               onclick="return confirm(\'Are you sure you want to delete this vehicle: ' + escapeHtml(String(vehicle.vehicle_name).replace(/'/g, "\\'")) + '?\');">\
                                                <i class="fas fa-trash-alt"></i>\
                                            </a>';
                        }
                        // Book Vehicle Button
                        // Assuming 'status_raw' is sent from AJAX to check vehicle availability
                        if (<?php echo json_encode(userHasCapability('CREATE_VEHICLE_RESERVATIONS')); ?> && vehicle.status_raw === 'available') { 
                             actionsHtml += ' <a href="' + BASE_URL + 'VehicleRequest/create/' + escapeHtml(String(vehicle.object_id)) + '" class="btn btn-sm btn-success ms-1" title="Book Vehicle"><i class="fas fa-calendar-plus"></i></a>';
                        }
                        vehicle.actions = actionsHtml; // Set the processed actions HTML
                    });
                }
                return json.data;
            },
            "error": function(xhr, error, thrown) { // Enhanced error handling
                console.error("DataTables AJAX error: ", error, thrown);
                console.error("Response Text: ", xhr.responseText);
                $('#vehiclesTable tbody').html(
                    '<tr><td colspan="8" class="text-center text-danger">' +
                    'Error loading data. Please check console or try again later. ' +
                    '<a href="http://datatables.net/tn/7" target="_blank">More info</a>.' +
                    '</td></tr>'
                );
            }
        },
        "columns": [
            { "data": "object_id", "name": "object_id" }, 
            { "data": "vehicle_name", "name": "vehicle_name" },
            { "data": "plate_number", "name": "plate_number" },
            { "data": "make_model", "name": "make_model", "orderable": false }, 
            { "data": "capacity", "name": "capacity" },
            { 
                "data": "status", // This is the HTML badge
                "name": "status_raw", // Use a raw status field for sorting if available
                "orderable": true // Allow sorting by status if raw status is provided
            },
            { "data": "last_modified", "name": "last_modified" },
            { 
                "data": "actions",
                "orderable": false, 
                "searchable": false 
            }
        ],
        "order": [[0, "desc"]], 
        "pageLength": 10, 
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]], 
    });
});
</script>
