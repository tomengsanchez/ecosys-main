<?php
/**
 * Script to populate the database with 100 sample room reservations.
 * Place this file in the root directory of your 'mainsystem' application
 * (alongside config.php and index.php) and run it via browser or CLI.
 *
 * WARNING: Make sure to backup your database before running any seeding script.
 * This script assumes your 'objects' table has 'object_id' as AUTO_INCREMENT.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the main configuration file to get $pdo and other settings
require_once 'config.php';

if (!$pdo) {
    die("Failed to connect to the database. Please check your config.php and database server.");
}

echo "<pre>"; // For better browser output formatting

// --- Configuration for Seeding ---
$numberOfReservationsToCreate = 10000;
$sampleUserId = 1; // Assuming user_id 1 (admin) exists
$sampleRoomId = 1; // Assuming room object_id 1 (e.g., 'Conference Room Alpha') exists
$sampleRoomName = "Conference Room Alpha"; // For generating titles

// Possible statuses for reservations
$possibleStatuses = ['pending', 'approved', 'denied', 'cancelled'];

// Helper function to generate a random date within the next month
function getRandomFutureDateTime($daysForwardMax = 30, $hourMin = 8, $hourMax = 16) {
    $days = rand(1, $daysForwardMax); // 1 to X days in the future
    $hour = rand($hourMin, $hourMax); // Random hour between, e.g., 8 AM and 4 PM (for start time)
    
    $date = new DateTime();
    $date->modify("+{$days} days");
    $date->setTime($hour, 0, 0); // Set to the hour, 0 minutes, 0 seconds
    return $date;
}

echo "Starting to populate {$numberOfReservationsToCreate} room reservations...\n\n";

$reservationsCreated = 0;
for ($i = 1; $i <= $numberOfReservationsToCreate; $i++) {
    $reservationStatus = $possibleStatuses[array_rand($possibleStatuses)];
    
    // Generate start and end times (1-hour slots)
    $startDateTime = getRandomFutureDateTime();
    $endDateTime = clone $startDateTime;
    $endDateTime->modify('+1 hour');

    $startDateTimeStr = $startDateTime->format('Y-m-d H:i:s');
    $endDateTimeStr = $endDateTime->format('Y-m-d H:i:s');
    $dateForTitle = $startDateTime->format('Y-m-d H:i');

    $purpose = "Sample Reservation #{$i}: Team meeting for project discussion.";
    if ($reservationStatus === 'approved') {
        $purpose = "Sample Reservation #{$i}: Approved client presentation.";
    } elseif ($reservationStatus === 'pending') {
        $purpose = "Sample Reservation #{$i}: Tentative internal workshop booking.";
    }

    $objectTitle = "Reservation for {$sampleRoomName} by User {$sampleUserId} - {$dateForTitle} (ID: {$i})";
    // Generate a somewhat unique slug
    $objectName = "reservation-room{$sampleRoomId}-user{$sampleUserId}-" . $startDateTime->format('YmdHis') . "-{$i}";

    try {
        $pdo->beginTransaction();

        // 1. Insert into 'objects' table
        $sqlObject = "INSERT INTO `objects` (
            `object_author`, `object_date`, `object_date_gmt`, `object_content`, 
            `object_title`, `object_excerpt`, `object_status`, `comment_status`, 
            `ping_status`, `object_password`, `object_name`, `object_modified`, 
            `object_modified_gmt`, `object_parent`, `guid`, `menu_order`, 
            `object_type`, `object_mime_type`
        ) VALUES (
            :object_author, NOW(), NOW(), :object_content, 
            :object_title, '', :object_status, 'closed', 
            'closed', '', :object_name, NOW(), 
            NOW(), :object_parent, '', 0, 
            'reservation', ''
        )";

        $stmtObject = $pdo->prepare($sqlObject);
        $stmtObject->execute([
            ':object_author' => $sampleUserId,
            ':object_content' => $purpose,
            ':object_title' => $objectTitle,
            ':object_status' => $reservationStatus,
            ':object_name' => $objectName,
            ':object_parent' => $sampleRoomId
        ]);

        $lastReservationObjectId = $pdo->lastInsertId();

        if (!$lastReservationObjectId) {
            throw new Exception("Failed to insert object and get lastInsertId.");
        }

        // 2. Insert into 'objectmeta' table
        $metaFields = [
            'reservation_start_datetime' => $startDateTimeStr,
            'reservation_end_datetime' => $endDateTimeStr,
            'reservation_user_id' => $sampleUserId
        ];

        $sqlMeta = "INSERT INTO `objectmeta` (`object_id`, `meta_key`, `meta_value`) VALUES (:object_id, :meta_key, :meta_value)";
        $stmtMeta = $pdo->prepare($sqlMeta);

        foreach ($metaFields as $key => $value) {
            $stmtMeta->execute([
                ':object_id' => $lastReservationObjectId,
                ':meta_key' => $key,
                ':meta_value' => $value
            ]);
        }

        $pdo->commit();
        echo "Successfully created Reservation ID: {$lastReservationObjectId} (Title: {$objectTitle})\n";
        $reservationsCreated++;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Error creating reservation #{$i}: " . $e->getMessage() . "\n";
        // Optionally, break or continue on error
        // break; 
    }
    // Small delay to prevent hitting any potential server limits if running very fast (optional)
    // usleep(50000); // 50 milliseconds
}

echo "\n-------------------------------------\n";
echo "Finished populating reservations.\n";
echo "Total reservations attempted: {$numberOfReservationsToCreate}\n";
echo "Total reservations successfully created: {$reservationsCreated}\n";
echo "</pre>";

?>
