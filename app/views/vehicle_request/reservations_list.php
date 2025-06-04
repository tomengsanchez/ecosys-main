<?php
// This view is used by VehicleRequestController::index()
// Expected variables:
// - $pageTitle (string)
// - $breadcrumbs (array)
// - $reservation_statuses (array) - Key-value pairs for filter dropdown

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="vehicle-reservations-admin-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Vehicle Reservations'); ?></h1>
        <?php // Optional: Add button to create a new vehicle request if needed from this page ?>
    </div>

    <div id="ajaxMessages"></div> <?php // For displaying success/error messages from AJAX actions ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="searchTerm" class="form-label">Search Term:</label>
                    <input type="text" class="form-control form-control-sm" id="searchTerm" placeholder="ID, Purpose, Vehicle, User...">
                </div>
                <div class="col-md-3">
                    <label for="filterStatus" class="form-label">Status:</label>
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">All Statuses</option>
                        <?php if (isset($reservation_statuses) && is_array($reservation_statuses)): ?>
                            <?php foreach ($reservation_statuses as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="showEntries" class="form-label">Show:</label>
                    <select class="form-select form-select-sm" id="showEntries">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="applyFilters" class="btn btn-primary btn-sm w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Vehicle</th>
                    <th>User</th>
                    <th>Purpose</th>
                    <th>Destination</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Requested On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="reservationsTableBody">
                <tr><td colspan="10" class="text-center">Loading vehicle reservations...</td></tr>
            </tbody>
        </table>
    </div>
    <div id="paginationControls" class="d-flex justify-content-between align-items-center mt-3">
        <span id="paginationInfo"></span>
        <nav id="paginationNav"></nav>
    </div>
</div>

<script>
// Ensure jQuery is loaded before this script runs. Typically handled by footer.php.
function initializeVehicleReservationsAdminLogic() {
    let currentPage = 1;
    let currentLimit = parseInt($('#showEntries').val(), 10);
    let currentSearchTerm = '';
    let currentFilterStatus = '';
    const reservationStatuses = <?php echo json_encode($reservation_statuses ?? []); ?>;

    function escapeHtml(unsafe) {
        if (unsafe === null || typeof unsafe === 'undefined') return '';
        return String(unsafe)
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    function nl2br(str) {
        if (typeof str === 'undefined' || str === null) return '';
        return String(str).replace(/(\r\n|\n\r|\r|\n)/g, '<br>');
    }

    function fetchVehicleReservations(page = 1) {
        currentPage = page;
        currentLimit = parseInt($('#showEntries').val(), 10);
        currentSearchTerm = $('#searchTerm').val().trim();
        currentFilterStatus = $('#filterStatus').val();

        $('#reservationsTableBody').html('<tr><td colspan="10" class="text-center"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Loading reservations...</td></tr>');
        $('#paginationInfo').html('');
        $('#paginationNav').html('');

        $.ajax({
            url: '<?php echo BASE_URL . "VehicleRequest/ajaxGetAllVehicleReservations"; ?>', // Correct AJAX endpoint
            type: 'POST',
            dataType: 'json',
            data: {
                page: currentPage,
                limit: currentLimit,
                searchTerm: currentSearchTerm,
                filterStatus: currentFilterStatus
            },
            success: function(response) {
                const tableBody = $('#reservationsTableBody');
                tableBody.empty();

                if (response.data && response.data.length > 0) {
                    response.data.forEach(function(reservation) {
                        let actionsHtml = '';
                        if (reservation.object_status === 'pending' && <?php echo json_encode(userHasCapability('APPROVE_DENY_VEHICLE_RESERVATIONS')); ?>) {
                            actionsHtml = `
                                <a href="#" class="btn btn-sm btn-success mb-1 action-btn" data-action="approve" data-id="${escapeHtml(String(reservation.object_id))}" title="Approve">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="#" class="btn btn-sm btn-danger mb-1 action-btn" data-action="deny" data-id="${escapeHtml(String(reservation.object_id))}" title="Deny">
                                    <i class="fas fa-times"></i> Deny
                                </a>`;
                        } else if (reservation.object_status === 'approved' && <?php echo json_encode(userHasCapability('APPROVE_DENY_VEHICLE_RESERVATIONS')); ?>) {
                            actionsHtml = `
                                <a href="#" class="btn btn-sm btn-warning text-dark mb-1 action-btn" data-action="deny" data-id="${escapeHtml(String(reservation.object_id))}" title="Revoke Approval (Deny)">
                                    <i class="fas fa-undo"></i> Revoke
                                </a>`;
                        } else {
                            actionsHtml = '<span class="text-muted small">No actions</span>';
                        }
                         // Add delete action if user has capability
                        if (<?php echo json_encode(userHasCapability('DELETE_ANY_VEHICLE_RESERVATION')); ?>) {
                             actionsHtml += ` <a href="#" class="btn btn-sm btn-outline-danger mb-1 action-btn" data-action="delete" data-id="${escapeHtml(String(reservation.object_id))}" title="Delete Record">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>`;
                        }


                        let statusKey = reservation.object_status || 'unknown';
                        let statusLabel = reservationStatuses[statusKey] || statusKey.charAt(0).toUpperCase() + statusKey.slice(1);
                        let badgeClass = 'bg-secondary';
                        if (statusKey === 'pending') badgeClass = 'bg-warning text-dark';
                        else if (statusKey === 'approved') badgeClass = 'bg-success';
                        else if (statusKey === 'denied') badgeClass = 'bg-danger';
                        else if (statusKey === 'cancelled') badgeClass = 'bg-info text-dark';
                        let statusHtml = `<span class="badge ${badgeClass}">${escapeHtml(statusLabel)}</span>`;

                        let row = `<tr>
                            <td>${escapeHtml(String(reservation.object_id))}</td>
                            <td>${escapeHtml(reservation.vehicle_name || 'N/A')}</td>
                            <td>${escapeHtml(reservation.user_display_name || 'N/A')}</td>
                            <td>${nl2br(escapeHtml(reservation.object_content || 'N/A'))}</td>
                            <td>${escapeHtml(reservation.destination || 'N/A')}</td>
                            <td>${escapeHtml(reservation.formatted_start_datetime || '')}</td>
                            <td>${escapeHtml(reservation.formatted_end_datetime || '')}</td>
                            <td>${escapeHtml(reservation.formatted_object_date || '')}</td>
                            <td>${statusHtml}</td>
                            <td>${actionsHtml}</td>
                        </tr>`;
                        tableBody.append(row);
                    });
                } else {
                    tableBody.html('<tr><td colspan="10" class="text-center">No vehicle reservations found.</td></tr>');
                }
                renderPagination(response.pagination);
            },
            error: function(xhr, status, error) {
                $('#reservationsTableBody').html('<tr><td colspan="10" class="text-center text-danger">Error loading vehicle reservations.</td></tr>');
                console.error("AJAX Error:", status, error, xhr.responseText);
            }
        });
    }

    function renderPagination(pagination) {
        if (!pagination || pagination.totalPages <= 0) {
            $('#paginationInfo').html('Showing 0 to 0 of 0 entries');
            $('#paginationNav').html('');
            return;
        }
        const { currentPage, totalPages, limit, totalRecords } = pagination;
        const startRecord = totalRecords > 0 ? (currentPage - 1) * limit + 1 : 0;
        const endRecord = Math.min(currentPage * limit, totalRecords);
        $('#paginationInfo').html(`Showing ${startRecord} to ${endRecord} of ${totalRecords} entries`);

        let paginationHtml = '<ul class="pagination pagination-sm mb-0">';
        paginationHtml += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a></li>`;
        
        const maxPagesToShow = 5; 
        let startPage, endPage;
        if (totalPages <= maxPagesToShow) { startPage = 1; endPage = totalPages; } 
        else {
            if (currentPage <= Math.ceil(maxPagesToShow / 2)) { startPage = 1; endPage = maxPagesToShow; } 
            else if (currentPage + Math.floor(maxPagesToShow / 2) >= totalPages) { startPage = totalPages - maxPagesToShow + 1; endPage = totalPages; } 
            else { startPage = currentPage - Math.floor(maxPagesToShow / 2); endPage = currentPage + Math.floor(maxPagesToShow / 2); }
        }
        if (startPage > 1) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }
        paginationHtml += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a></li>`;
        paginationHtml += '</ul>';
        $('#paginationNav').html(paginationHtml);
    }

    $('#applyFilters, #showEntries').on('click change', function() { fetchVehicleReservations(1); });
    let searchTimeout;
    $('#searchTerm').on('keyup', function() { clearTimeout(searchTimeout); searchTimeout = setTimeout(function() { fetchVehicleReservations(1); }, 500); });
    $('#paginationNav').on('click', 'a.page-link', function(e) { e.preventDefault(); const page = $(this).data('page'); if (page) fetchVehicleReservations(page); });

    $('#reservationsTableBody').on('click', '.action-btn', function(e) {
        e.preventDefault();
        const action = $(this).data('action');
        const reservationId = $(this).data('id');
        let actionUrl = '';
        let confirmationMessage = '';

        if (action === 'approve') {
            actionUrl = `<?php echo BASE_URL . 'VehicleRequest/approve/'; ?>${reservationId}`;
            confirmationMessage = `Are you sure you want to APPROVE vehicle reservation ID ${reservationId}?`;
        } else if (action === 'deny') { // Covers both deny and revoke
            actionUrl = `<?php echo BASE_URL . 'VehicleRequest/deny/'; ?>${reservationId}`;
            confirmationMessage = `Are you sure you want to DENY/REVOKE vehicle reservation ID ${reservationId}?`;
        } else if (action === 'delete') {
            actionUrl = `<?php echo BASE_URL . 'VehicleRequest/deleteAnyReservation/'; ?>${reservationId}`; // Assuming this method exists
            confirmationMessage = `Are you sure you want to DELETE vehicle reservation record ID ${reservationId}? This cannot be undone.`;
        } else {
            return;
        }

        if (confirm(confirmationMessage)) {
            $.ajax({
                url: actionUrl,
                type: 'GET', // Or POST if your controller actions expect it
                dataType: 'json',
                beforeSend: function() { $(e.target).closest('td').html('<div class="spinner-border spinner-border-sm" role="status"></div>'); },
                success: function(response) {
                    displayAjaxMessage(response.message || 'Action processed.', response.success ? 'success' : 'danger');
                    fetchVehicleReservations(currentPage);
                },
                error: function(xhr) {
                    let errorMsg = 'Error processing action.';
                    try { const errResponse = JSON.parse(xhr.responseText); if (errResponse && errResponse.message) errorMsg = errResponse.message; } catch (e) {}
                    displayAjaxMessage(errorMsg, 'danger');
                    fetchVehicleReservations(currentPage);
                }
            });
        }
    });
    
    function displayAjaxMessage(message, type = 'info') {
        const messageId = 'ajax-msg-' + Date.now();
        $('#ajaxMessages').html(`<div id="${messageId}" class="alert alert-${type} alert-dismissible fade show" role="alert">${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`);
        setTimeout(() => { $('#' + messageId).fadeOut(500, function() { $(this).remove(); }); }, 5000);
    }

    fetchVehicleReservations(); // Initial load
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery) {
        $(document).ready(initializeVehicleReservationsAdminLogic);
    } else {
        console.error("jQuery not loaded. Vehicle reservations admin logic cannot run.");
    }
});
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
