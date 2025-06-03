<?php

/**
 * RoomModel
 *
 * Handles database operations specific to 'room' objects.
 * Extends BaseObjectModel for generic object functionalities.
 */
class RoomModel extends BaseObjectModel {

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo); // Call parent constructor
    }

    /**
     * Get a specific room by its ID.
     *
     * @param int $roomId The ID of the room.
     * @return array|false The room data, or false if not found or not a room.
     */
    public function getRoomById($roomId) {
        $room = parent::getObjectById($roomId);
        if ($room && $room['object_type'] === 'room') {
            return $room;
        }
        return false;
    }

    /**
     * Get all rooms.
     *
     * @param array $args Optional arguments for ordering, limit, etc.
     * @return array|false An array of room objects or false on failure.
     */
    public function getAllRooms(array $args = []) {
        // Default ordering for rooms
        $args['orderby'] = $args['orderby'] ?? 'object_title';
        $args['orderdir'] = $args['orderdir'] ?? 'ASC';
        return parent::getObjectsByType('room', $args);
    }

    /**
     * Create a new room.
     *
     * @param array $data Associative array containing room data.
     * Required: 'object_author', 'object_title'.
     * Optional: 'object_content', 'object_excerpt', 'object_status', 'meta_fields'.
     * @return int|false The ID of the newly created room, or false on failure.
     */
    public function createRoom(array $data) {
        if (empty($data['object_author']) || empty($data['object_title'])) {
            error_log("RoomModel::createRoom: Missing required fields (author or title).");
            return false;
        }
        $data['object_type'] = 'room'; // Ensure object type is set to 'room'
        return parent::createObject($data);
    }

    /**
     * Update an existing room.
     *
     * @param int $roomId The ID of the room to update.
     * @param array $data Associative array of data to update.
     * @return bool True on success, false on failure.
     */
    public function updateRoom($roomId, array $data) {
        // Ensure we are not trying to change the object_type via this method
        if (isset($data['object_type']) && $data['object_type'] !== 'room') {
            error_log("RoomModel::updateRoom: Attempt to change object_type is not allowed.");
            unset($data['object_type']); // Or return false
        }
        return parent::updateObject($roomId, $data);
    }

    /**
     * Delete a room.
     * This will also delete associated metadata via parent::deleteObject.
     * Note: Business logic like checking for existing reservations should be in the controller.
     *
     * @param int $roomId The ID of the room to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteRoom($roomId) {
        // Before deleting, ensure it's actually a room (optional, as controller might do this)
        // $room = $this->getRoomById($roomId);
        // if (!$room) return false; // Not a room or doesn't exist

        return parent::deleteObject($roomId);
    }

    // Add any other room-specific methods here, e.g.:
    // - getRoomsByCapacity($minCapacity)
    // - findRoomsByEquipment(array $equipmentList)
}
