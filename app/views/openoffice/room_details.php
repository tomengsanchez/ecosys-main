<?php include_once VIEWS_DIR . 'layouts/header.php'; ?>

<div class="container">
    <h1>Room Details: <span id="room-name"></span></h1>

    <div class="card mb-4">
        <div class="card-header">
            Room Information
        </div>
        <div class="card-body">
            <p><strong>Capacity:</strong> <span id="room-capacity"></span></p>
            <p><strong>Description:</strong> <span id="room-description"></span></p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Filter Reservations by Date
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="reservation-date">Select Date:</label>
                <input type="date" class="form-control" id="reservation-date">
            </div>
            <button class="btn btn-primary mt-3" id="filter-reservations-btn">Filter</button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Pending Reservations
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Reservation ID</th>
                        <th>User</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Purpose</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pending-reservations-table-body">
                    <!-- Pending reservations will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Approved Reservations
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Reservation ID</th>
                        <th>User</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody id="approved-reservations-table-body">
                    <!-- Approved reservations will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once VIEWS_DIR . 'layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roomId = <?php echo json_encode($room['object_id'] ?? null); ?>; // Corrected to use object_id
    const reservationDateInput = document.getElementById('reservation-date');
    const filterButton = document.getElementById('filter-reservations-btn');

    // Set default date to today
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
    const day = String(today.getDate()).padStart(2, '0');
    reservationDateInput.value = `${year}-${month}-${day}`;

    if (!roomId) {
        console.error('Room ID not found.');
        return;
    }

    // Function to fetch and display room details and reservations
    function fetchRoomDetails(selectedDate = null) {
        // Use BASE_URL to ensure correct path
        let url = `<?php echo BASE_URL; ?>openoffice/roomDetailsData/${roomId}`;
        if (selectedDate) {
            url += `?date=${selectedDate}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.room) {
                    document.getElementById('room-name').textContent = data.room.name;
                    document.getElementById('room-capacity').textContent = data.room.capacity;
                    document.getElementById('room-description').textContent = data.room.description;
                }

                const pendingTableBody = document.getElementById('pending-reservations-table-body');
                pendingTableBody.innerHTML = '';
                if (data.pendingReservations && data.pendingReservations.length > 0) {
                    data.pendingReservations.forEach(reservation => {
                        const row = `
                            <tr>
                                <td>${reservation.object_id}</td>
                                <td>${reservation.user_name}</td>
                                <td>${reservation.start_time}</td>
                                <td>${reservation.end_time}</td>
                                <td>${reservation.purpose}</td>
                            </tr>
                        `;
                        pendingTableBody.insertAdjacentHTML('beforeend', row);
                    });

                    // Removed approve/reject buttons and their event listeners
                } else {
                    pendingTableBody.innerHTML = '<tr><td colspan="5">No pending reservations.</td></tr>';
                }

                const approvedTableBody = document.getElementById('approved-reservations-table-body');
                approvedTableBody.innerHTML = '';
                if (data.approvedReservations && data.approvedReservations.length > 0) {
                    data.approvedReservations.forEach(reservation => {
                        const row = `
                            <tr>
                                <td>${reservation.object_id}</td>
                                <td>${reservation.user_name}</td>
                                <td>${reservation.start_time}</td>
                                <td>${reservation.end_time}</td>
                                <td>${reservation.purpose}</td>
                            </tr>
                        `;
                        approvedTableBody.insertAdjacentHTML('beforeend', row);
                    });
                } else {
                    approvedTableBody.innerHTML = '<tr><td colspan="5">No approved reservations.</td></tr>';
                }
            })
            .catch(error => console.error('Error fetching room details:', error));
    }

    // Function to update reservation status (kept for other potential uses, though buttons removed)
    function updateReservationStatus(reservationId, status) {
        fetch(`<?php echo BASE_URL; ?>openoffice/updateReservationStatus`, { // Adjust API endpoint as needed
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ reservation_id: reservationId, status: status }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Reservation ${reservationId} ${status} successfully.`);
                fetchRoomDetails(reservationDateInput.value); // Refresh data with current filter
            } else {
                alert(`Failed to ${status} reservation ${reservationId}.`);
            }
        })
        .catch(error => console.error('Error updating reservation status:', error));
    }

    // Event listener for filter button
    filterButton.addEventListener('click', function() {
        fetchRoomDetails(reservationDateInput.value);
    });

    fetchRoomDetails(reservationDateInput.value); // Initial fetch with today's date
});
</script>
