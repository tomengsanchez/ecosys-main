<?php

/**
 * VehicleModel
 *
 * Handles database operations specific to 'vehicle' objects.
 * Extends BaseObjectModel for generic object functionalities.
 */
class VehicleModel extends BaseObjectModel {

    /**
     * Constructor
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo); // Call parent constructor
    }

    /**
     * Get a specific vehicle by its ID.
     *
     * @param int $vehicleId The ID of the vehicle.
     * @return array|false The vehicle data (object and meta), or false if not found or not a vehicle.
     */
    public function getVehicleById($vehicleId) {
        $vehicle = parent::getObjectById($vehicleId);
        // Ensure the fetched object is indeed a vehicle
        if ($vehicle && isset($vehicle['object_type']) && $vehicle['object_type'] === 'vehicle') {
            return $vehicle;
        }
        return false;
    }

    /**
     * Get all vehicles.
     *
     * @param array $args Optional arguments for ordering, limit, offset, metadata inclusion, etc.
     * 'include_meta' defaults to true.
     * @return array|false An array of vehicle objects (each with a 'meta' sub-array) or false on failure.
     */
    public function getAllVehicles(array $args = []) {
        // Default arguments for vehicles, can be overridden by $args
        $defaults = [
            'orderby' => 'o.object_title', // Default order by vehicle name (title)
            'orderdir' => 'ASC',
            'include_meta' => true
        ];
        $finalArgs = array_merge($defaults, $args);
        
        return parent::getObjectsByType('vehicle', $finalArgs);
    }

    /**
     * Create a new vehicle.
     *
     * @param array $data Associative array containing vehicle data.
     * Required: 
     * 'object_author' (int) - User ID of the creator.
     * 'object_title' (string) - The name or identifier of the vehicle (e.g., "Toyota HiAce Plate ABC 123").
     * Optional:
     * 'object_content' (string) - Description or notes about the vehicle.
     * 'object_status' (string) - e.g., 'available', 'unavailable', 'maintenance'. Defaults to 'available'.
     * 'meta_fields' (array) - Associative array for custom fields like:
     * 'vehicle_plate_number' (string)
     * 'vehicle_make' (string) - e.g., Toyota, Honda
     * 'vehicle_model' (string) - e.g., HiAce, Civic
     * 'vehicle_year' (int)
     * 'vehicle_capacity' (int) - Number of passengers
     * 'vehicle_type' (string) - e.g., Van, Sedan, SUV, Truck
     * 'vehicle_fuel_type' (string) - e.g., Gasoline, Diesel
     * 'vehicle_current_driver_id' (int) - User ID of the currently assigned driver (if any)
     * 'vehicle_notes' (string) - Additional notes for the vehicle
     * @return int|false The ID of the newly created vehicle object, or false on failure.
     */
    public function createVehicle(array $data) {
        // Ensure required fields for creating any object are present
        if (empty($data['object_author']) || empty($data['object_title'])) {
            error_log("VehicleModel::createVehicle: Missing required fields (author or title).");
            return false;
        }
        // Set the object_type specifically for vehicles
        $data['object_type'] = 'vehicle';
        
        // Set default status if not provided
        if (!isset($data['object_status'])) {
            $data['object_status'] = 'available'; // Default status for new vehicles
        }

        return parent::createObject($data);
    }

    /**
     * Update an existing vehicle.
     *
     * @param int $vehicleId The ID of the vehicle object to update.
     * @param array $data Associative array of data to update. Can include 'object_title', 
     * 'object_content', 'object_status', and 'meta_fields'.
     * 'object_type' cannot be changed via this method.
     * @return bool True on success, false on failure.
     */
    public function updateVehicle($vehicleId, array $data) {
        // Ensure we are not trying to change the object_type via this method
        if (isset($data['object_type']) && $data['object_type'] !== 'vehicle') {
            error_log("VehicleModel::updateVehicle: Attempt to change object_type is not allowed for vehicle ID {$vehicleId}.");
            unset($data['object_type']); // Or return false to indicate error
        }
        return parent::updateObject($vehicleId, $data);
    }

    /**
     * Delete a vehicle.
     * This will also delete associated metadata via parent::deleteObject.
     * Business logic like checking for existing reservations should be handled in the controller.
     *
     * @param int $vehicleId The ID of the vehicle object to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteVehicle($vehicleId) {
        // Optional: Before deleting, ensure it's actually a vehicle.
        // The controller should ideally do this check before calling deleteVehicle.
        // $vehicle = $this->getVehicleById($vehicleId);
        // if (!$vehicle) {
        //     error_log("VehicleModel::deleteVehicle: Vehicle ID {$vehicleId} not found or is not a vehicle.");
        //     return false; 
        // }
        return parent::deleteObject($vehicleId);
    }

    // --- Additional Vehicle-Specific Methods (Examples) ---

    /**
     * Find vehicles by plate number.
     * This would typically search a meta field.
     *
     * @param string $plateNumber
     * @return array|false Array of vehicle objects or false.
     */
    public function findVehiclesByPlateNumber($plateNumber) {
        // This is a more complex query involving objectmeta.
        // BaseObjectModel's getObjectsByConditions might need enhancement to support meta queries directly,
        // or we build a custom query here.
        try {
            $sql = "SELECT o.* FROM objects o
                    JOIN objectmeta om ON o.object_id = om.object_id
                    WHERE o.object_type = :object_type 
                      AND om.meta_key = :meta_key 
                      AND om.meta_value = :meta_value";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':object_type' => 'vehicle',
                ':meta_key' => 'vehicle_plate_number', // Assuming this is your meta key
                ':meta_value' => $plateNumber
            ]);
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($vehicles) {
                foreach ($vehicles as $key => $vehicle) {
                    $vehicles[$key]['meta'] = $this->getAllObjectMeta($vehicle['object_id']);
                }
            }
            return $vehicles;
        } catch (PDOException $e) {
            error_log("Error in VehicleModel::findVehiclesByPlateNumber(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get vehicles by their status (e.g., 'available', 'maintenance').
     *
     * @param string|array $status Single status string or array of statuses.
     * @param array $args Optional arguments for ordering, limit, etc.
     * @return array|false
     */
    public function getVehiclesByStatus($status, array $args = []) {
        $conditions = ['o.object_status' => $status]; // 'o.' prefix for BaseObjectModel
        return $this->getObjectsByConditions('vehicle', $conditions, $args);
    }
}
