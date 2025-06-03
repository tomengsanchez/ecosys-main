<?php
// This view is used by OpenOfficeController::rooms()
// Expected variables:
// - $pageTitle (string)
// - $breadcrumbs (array)
// Room data is now loaded via AJAX by DataTables

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="openoffice-rooms-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Rooms'); ?></h1>
        <?php if (userHasCapability('CREATE_ROOMS')): ?>
        <a href="<?php echo BASE_URL . 'OpenOffice/addRoom'; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Room
        </a>
        <?php endif; ?>
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
        <table class="table table-striped table-hover" id="roomsTable"> <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Room Name</th>
                    <th>Capacity</th>
                    <th>Location</th>
                    <th>Equipment</th>
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
    $('#roomsTable').DataTable({
        "processing": true, 
        "serverSide": false, // Client-side processing for now
        "ajax": {
            "url": "<?php echo BASE_URL . 'OpenOffice/ajaxGetRooms'; ?>", // Ensure controller name casing is correct
            "type": "GET",
            "dataSrc": "data" 
        },
        "columns": [
            // Conditionally show 'id' and 'modified' based on capability is tricky with pure client-side DataTables
            // if the data structure from AJAX is fixed. For simplicity, always include them in JS columns.
            // Visibility can be controlled by CSS or by not including them in the PHP AJAX response if not permitted.
            // For now, we assume if VIEW_ROOMS is granted, these are okay to show.
            // A more complex setup would involve different AJAX endpoints or column definitions based on role.
            { "data": "id" },
            { "data": "name" },
            { "data": "capacity" },
            { "data": "location" },
            { "data": "equipment" },
            { "data": "status" },
            { "data": "modified" },
            { 
                "data": "actions",
                "orderable": false,
                "searchable": false
            }
        ],
        "order": [[ 1, "asc" ]], // Default sort by Room Name ascending
        // Example of how to conditionally hide columns if needed (more complex with server-side data)
        // "columnDefs": [
        //     {
        //         "targets": [0, 6], // Column indexes for ID and Last Modified
        //         "visible": <?php echo (userHasCapability('EDIT_ROOMS') || userHasCapability('DELETE_ROOMS')) ? 'true' : 'false'; ?>
        //     }
        // ]
    });
});
</script>
