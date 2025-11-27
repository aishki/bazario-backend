<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $vendorId = isset($_GET['vendor_id']) ? $_GET['vendor_id'] : null;
        if (!$vendorId) {
            echo json_encode(["success" => false, "message" => "vendor_id is required"]);
            exit;
        }

        $query = "SELECT 
                    r.id,
                    r.vendor_id,
                    r.event_id,
                    r.reminder_type,
                    r.reminder_datetime,
                    r.is_notified,
                    r.created_at,
                    e.name as event_name
                  FROM vendor_event_reminders r
                  LEFT JOIN events e ON r.event_id = e.id
                  WHERE r.vendor_id = :vendor_id
                  ORDER BY r.reminder_datetime ASC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':vendor_id', $vendorId);
        $stmt->execute();
        $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "reminders" => $reminders]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $action = isset($data['action']) ? $data['action'] : null;

        if ($action === 'create') {
            $vendorId = $data['vendor_id'];
            $eventId = $data['event_id'];
            $reminderDatetime = $data['reminder_datetime'];
            $reminderType = $data['reminder_type'];

            $query = "INSERT INTO vendor_event_reminders 
                     (vendor_id, event_id, reminder_type, reminder_datetime) 
                     VALUES (:vendor_id, :event_id, :reminder_type, :reminder_datetime)";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':vendor_id', $vendorId);
            $stmt->bindParam(':event_id', $eventId);
            $stmt->bindParam(':reminder_type', $reminderType);
            $stmt->bindParam(':reminder_datetime', $reminderDatetime);
            $stmt->execute();

            echo json_encode(["success" => true, "message" => "Reminder created"]);
        } elseif ($action === 'create_automatic') {
            $vendorId = $data['vendor_id'];
            $eventId = $data['event_id'];
            $eventStart = new DateTime($data['event_start']);

            $reminderTimes = [
                '1_month' => '- 1 month',
                '1_week' => '- 1 week',
                '1_hour' => '- 1 hour'
            ];

            foreach ($reminderTimes as $type => $interval) {
                $reminderTime = clone $eventStart;
                $reminderTime->modify($interval);

                $query = "INSERT INTO vendor_event_reminders 
                         (vendor_id, event_id, reminder_type, reminder_datetime) 
                         VALUES (:vendor_id, :event_id, :reminder_type, :reminder_datetime)
                         ON CONFLICT DO NOTHING";

                $stmt = $db->prepare($query);
                $stmt->bindParam(':vendor_id', $vendorId);
                $stmt->bindParam(':event_id', $eventId);
                $stmt->bindParam(':reminder_type', $type);
                $stmt->bindParam(':reminder_datetime', $reminderTime->format('Y-m-d H:i:s'));
                $stmt->execute();
            }

            echo json_encode(["success" => true, "message" => "Automatic reminders created"]);
        } elseif ($action === 'delete') {
            $reminderId = isset($data['id']) ? $data['id'] : null;
            if (!$reminderId) {
                echo json_encode(["success" => false, "message" => "id is required"]);
                exit;
            }

            $query = "DELETE FROM vendor_event_reminders WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $reminderId);
            $stmt->execute();

            echo json_encode(["success" => true, "message" => "Reminder deleted"]);
        } elseif ($action === 'mark_notified') {
            $reminderId = isset($data['id']) ? $data['id'] : null;
            if (!$reminderId) {
                echo json_encode(["success" => false, "message" => "id is required"]);
                exit;
            }

            $query = "UPDATE vendor_event_reminders SET is_notified = true WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $reminderId);
            $stmt->execute();

            echo json_encode(["success" => true, "message" => "Reminder marked as notified"]);
        }
    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents("php://input"), true);
        $reminderId = $data['id'];

        $query = "DELETE FROM vendor_event_reminders WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $reminderId);
        $stmt->execute();

        echo json_encode(["success" => true, "message" => "Reminder deleted"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
