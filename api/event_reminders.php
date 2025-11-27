<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : null;

try {
    if ($method === 'POST' && $action === 'check_and_create_reminders') {
        // Check for upcoming events and create reminders
        // This endpoint should be called periodically (e.g., by a cron job)

        // Get all vendors who joined events
        $query = "SELECT DISTINCT 
                    ve.vendor_id,
                    e.id as event_id,
                    e.name,
                    e.schedule_start,
                    NOW() AT TIME ZONE 'UTC' as current_time
                  FROM event_vendors ve
                  INNER JOIN events e ON ve.event_id = e.id
                  WHERE e.schedule_start > NOW() AT TIME ZONE 'UTC'";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reminders_created = 0;

        foreach ($events as $event) {
            $vendor_id = $event['vendor_id'];
            $event_id = $event['event_id'];
            $event_name = $event['name'];
            $schedule_start = new DateTime($event['schedule_start']);
            $current_time = new DateTime($event['current_time']);
            $interval = $current_time->diff($schedule_start);

            // Check for 1 month reminder
            if ($interval->days == 30) {
                createReminder(
                    $db,
                    $vendor_id,
                    $event_id,
                    '1_month',
                    "Event Reminder: '{$event_name}' starts in 1 month!"
                );
                $reminders_created++;
            }

            // Check for 1 week reminder
            if ($interval->days == 7) {
                createReminder(
                    $db,
                    $vendor_id,
                    $event_id,
                    '1_week',
                    "Event Reminder: '{$event_name}' starts in 1 week!"
                );
                $reminders_created++;
            }

            // Check for 1 day reminder
            if ($interval->days == 1) {
                createReminder(
                    $db,
                    $vendor_id,
                    $event_id,
                    '1_day',
                    "Event Reminder: '{$event_name}' starts tomorrow!"
                );
                $reminders_created++;
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Reminder check completed",
            "reminders_created" => $reminders_created
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Invalid request"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

function createReminder($db, $vendor_id, $event_id, $reminder_type, $message)
{
    try {
        // Check if reminder already exists
        $check_query = "SELECT id FROM event_reminders 
                       WHERE vendor_id = :vendor_id 
                       AND event_id = :event_id 
                       AND reminder_type = :reminder_type";

        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':vendor_id', $vendor_id);
        $check_stmt->bindParam(':event_id', $event_id);
        $check_stmt->bindParam(':reminder_type', $reminder_type);
        $check_stmt->execute();

        if ($check_stmt->rowCount() == 0) {
            // Create notification
            $notif_query = "INSERT INTO notifications (user_id, message, type, created_at) 
                           VALUES (:user_id, :message, 'event_reminder', NOW())";

            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $vendor_id);
            $notif_stmt->bindParam(':message', $message);
            $notif_stmt->execute();

            // Create reminder record
            $reminder_query = "INSERT INTO event_reminders (vendor_id, event_id, reminder_type, sent_at) 
                             VALUES (:vendor_id, :event_id, :reminder_type, NOW())";

            $reminder_stmt = $db->prepare($reminder_query);
            $reminder_stmt->bindParam(':vendor_id', $vendor_id);
            $reminder_stmt->bindParam(':event_id', $event_id);
            $reminder_stmt->bindParam(':reminder_type', $reminder_type);
            $reminder_stmt->execute();

            return true;
        }

        return false;
    } catch (Exception $e) {
        error_log("Error creating reminder: " . $e->getMessage());
        return false;
    }
}
