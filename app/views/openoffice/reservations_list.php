<?php
// This view is used by OpenOfficeController::roomreservations()
// Expected variables:
// - $pageTitle (string)
// - $breadcrumbs (array)
// - $reservation_statuses (array) - Key-value pairs of status codes and labels for the filter dropdown

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="openoffice-reservations-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Room Reservations'); ?></h1>
    </div>

    <div id="ajaxMessages"></div> <?php // For displaying success/error messages from AJAX actions ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="searchTerm" class="form-label">Search Term:</label>
                    <input type="text" class="form-control form-control-sm" id="searchTerm" placeholder="ID, Purpose, Title...">
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
                        <option value="10">10</option>
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
            <tbody id="reservationsTableBody">
                <tr><td colspan="9" class="text-center">Loading reservations...</td></tr>
            </tbody>
        </table>
    </div>
    <div id="paginationControls" class="d-flex justify-content-between align-items-center mt-3">
        <span id="paginationInfo"></span>
        <nav id="paginationNav"></nav>
    </div>
</div>

<script>
function initializeReservationsLogic() {
    // All jQuery dependent code will go inside this function
    let currentPage = 1;
    let currentLimit = parseInt($('#showEntries').val(), 10);
    let currentSearchTerm = '';
    let currentFilterStatus = '';
    // Store php-generated reservation_statuses for use in JS rendering status badges
    const reservationStatuses = <?php echo json_encode($reservation_statuses ?? []); ?>;


    function fetchReservations(page = 1) {
        currentPage = page;
        currentLimit = parseInt($('#showEntries').val(), 10);
        currentSearchTerm = $('#searchTerm').val().trim();
        currentFilterStatus = $('#filterStatus').val();

        $('#reservationsTableBody').html('<tr><td colspan="9" class="text-center"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Loading reservations...</td></tr>');
        $('#paginationInfo').html('');
        $('#paginationNav').html('');


        $.ajax({
            url: '<?php echo BASE_URL . "openoffice/ajaxRoomReservationsData"; ?>',
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
                tableBody.empty(); // Clear previous rows

                if (response.data && response.data.length > 0) {
                    response.data.forEach(function(reservation) {
                        let actionsHtml = '';
                        // Ensure userHasCapability is available or links are shown/hidden by controller if preferred
                        // For simplicity, actions are generated based on status here.
                        // A more robust solution might involve the AJAX response indicating which actions are permissible.
                        if (reservation.object_status === 'pending') {
                            actionsHtml = `
                                <a href="<?php echo BASE_URL . 'openoffice/approvereservation/'; ?>${escapeHtml(String(reservation.object_id))}"
                                   class="btn btn-sm btn-success mb-1 action-btn" data-action="approve" data-id="${escapeHtml(String(reservation.object_id))}" title="Approve">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="<?php echo BASE_URL . 'openoffice/denyreservation/'; ?>${escapeHtml(String(reservation.object_id))}"
                                   class="btn btn-sm btn-danger mb-1 action-btn" data-action="deny" data-id="${escapeHtml(String(reservation.object_id))}" title="Deny">
                                    <i class="fas fa-times"></i> Deny
                                </a>`;
                        } else if (reservation.object_status === 'approved') {
                            actionsHtml = `
                                <a href="<?php echo BASE_URL . 'openoffice/denyreservation/'; ?>${escapeHtml(String(reservation.object_id))}"
                                   class="btn btn-sm btn-warning text-dark mb-1 action-btn" data-action="revoke" data-id="${escapeHtml(String(reservation.object_id))}" title="Revoke Approval (Deny)">
                                    <i class="fas fa-undo"></i> Revoke
                                </a>`;
                        } else {
                            actionsHtml = '<span class="text-muted small">No actions</span>';
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
                            <td>${escapeHtml(reservation.room_name || 'N/A')}</td>
                            <td>${escapeHtml(reservation.user_display_name || 'N/A')}</td>
                            <td>${nl2br(escapeHtml(reservation.object_content || 'N/A'))}</td>
                            <td>${escapeHtml(reservation.formatted_start_datetime || '')}</td>
                            <td>${escapeHtml(reservation.formatted_end_datetime || '')}</td>
                            <td>${escapeHtml(reservation.formatted_object_date || '')}</td>
                            <td>${statusHtml}</td>
                            <td>${actionsHtml}</td>
                        </tr>`;
                        tableBody.append(row);
                    });
                } else {
                    tableBody.html('<tr><td colspan="9" class="text-center">No reservations found matching your criteria.</td></tr>');
                }
                renderPagination(response.pagination);
            },
            error: function(xhr, status, error) {
                $('#reservationsTableBody').html('<tr><td colspan="9" class="text-center text-danger">Error loading reservations. Please try again.</td></tr>');
                console.error("AJAX Error:", status, error, xhr.responseText);
            }
        });
    }

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
        if (typeof str === 'undefined' || str === null) {
            return '';
        }
        // Ensure the input is a string before calling replace
        str = String(str);
        return str.replace(/(\r\n|\n\r|\r|\n)/g, '<br>');
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
        // Prev button
        paginationHtml += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                              <a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a>
                           </li>`;
        
        // Page numbers
        const maxPagesToShow = 5; // Max number of page links to show
        let startPage, endPage;

        if (totalPages <= maxPagesToShow) {
            startPage = 1;
            endPage = totalPages;
        } else {
            if (currentPage <= Math.ceil(maxPagesToShow / 2)) {
                startPage = 1;
                endPage = maxPagesToShow;
            } else if (currentPage + Math.floor(maxPagesToShow / 2) >= totalPages) {
                startPage = totalPages - maxPagesToShow + 1;
                endPage = totalPages;
            } else {
                startPage = currentPage - Math.floor(maxPagesToShow / 2);
                endPage = currentPage + Math.floor(maxPagesToShow / 2);
            }
        }
        
        if (startPage > 1) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                                  <a class="page-link" href="#" data-page="${i}">${i}</a>
                               </li>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }

        // Next button
        paginationHtml += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                              <a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a>
                           </li>`;
        paginationHtml += '</ul>';
        $('#paginationNav').html(paginationHtml);
    }

    // --- Event Handlers ---
    $('#applyFilters').on('click', function() {
        fetchReservations(1); // Reset to page 1 on new filter application
    });
    $('#showEntries').on('change', function() {
        fetchReservations(1); // Reset to page 1
    });
     // Debounce search input
    let searchTimeout;
    $('#searchTerm').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            fetchReservations(1);
        }, 500); // 500ms delay
    });

    $('#paginationNav').on('click', 'a.page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && !$(this).closest('.page-item').hasClass('disabled') && !$(this).closest('.page-item').hasClass('active')) {
            fetchReservations(page);
        }
    });

    $('#reservationsTableBody').on('click', '.action-btn', function(e) {
        e.preventDefault();
        const actionUrl = $(this).attr('href');
        const actionType = $(this).data('action');
        const reservationId = $(this).data('id');
        const confirmationMessage = `Are you sure you want to ${actionType} this reservation (ID: ${escapeHtml(String(reservationId))})?`;

        if (confirm(confirmationMessage)) {
            $.ajax({
                url: actionUrl,
                type: 'GET', // Assuming GET for these actions as per typical link behavior
                             // Change to POST if your controller actions expect POST for modifications
                dataType: 'json', // Expecting JSON response from controller for actions
                beforeSend: function() {
                    // Optional: show a loading indicator specific to the action
                    $(e.target).closest('td').html('<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Processing...</span></div>');
                },
                success: function(response) { // Expecting { success: true/false, message: "..." }
                    if (response && response.message) {
                        displayAjaxMessage(response.message, response.success ? 'success' : 'danger');
                    } else {
                        displayAjaxMessage('Action processed. Refreshing list...', 'info');
                    }
                    fetchReservations(currentPage); // Refresh current page
                },
                error: function(xhr, status, error) {
                    console.error("Action Error:", status, error, xhr.responseText);
                    let errorMsg = 'Error processing action for reservation ' + escapeHtml(String(reservationId)) + '.';
                    try {
                        const errResponse = JSON.parse(xhr.responseText);
                        if (errResponse && errResponse.message) {
                            errorMsg += ' ' + errResponse.message;
                        }
                    } catch (e) {
                        // If responseText is not JSON, use the generic error
                        errorMsg += ' ' + error;
                    }
                    displayAjaxMessage(errorMsg, 'danger');
                    fetchReservations(currentPage); // Refresh even on error to show original state
                }
            });
        }
    });
    
    function displayAjaxMessage(message, type = 'info') {
        const messageId = 'ajax-msg-' + Date.now();
        const alertHtml = `<div id="${messageId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        
        $('#ajaxMessages').append(alertHtml);
        
        // Auto-dismiss
        setTimeout(function() {
            $('#' + messageId).fadeOut(500, function() { $(this).remove(); });
        }, 5000); // Message disappears after 5 seconds
    }

    // Initial load
    fetchReservations();
}

// Wait for the DOM to be fully loaded, then check for jQuery, then initialize
document.addEventListener('DOMContentLoaded', function() {
    let jqueryCheckInterval = setInterval(function() {
        if (window.jQuery) {
            clearInterval(jqueryCheckInterval);
            // Now that jQuery is loaded, execute the main function by calling $(document).ready()
            // which will in turn call our initializeReservationsLogic function.
            $(document).ready(initializeReservationsLogic);
        }
    }, 100); // Check every 100ms
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
