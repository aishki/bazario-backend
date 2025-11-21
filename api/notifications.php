<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';
$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    // GET - Fetch notifications
    case 'GET':
        if (!isset($_GET['user_id'])) {
            echo json_encode(["success" => false, "message" => "user_id required"]);
            exit;
        }

        $user_id = $_GET['user_id'];
        $unread_only = isset($_GET['unread']) && $_GET['unread'] === 'true';

        if ($unread_only) {
            $query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :uid AND read = false";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':uid', $user_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "unread_count" => $result['unread_count']]);
        } else {
            $query = "SELECT *
                FROM notifications
                WHERE user_id = :uid
                ORDER BY created_at DESC
                LIMIT 50";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':uid', $user_id);
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "notifications" => $notifications]);
        }
        break;

    // PUT - Mark notification as read
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->notification_id)) {
            echo json_encode(["success" => false, "message" => "notification_id required"]);
            exit;
        }

        $query = "UPDATE notifications SET read = true WHERE id = :nid";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nid', $data->notification_id);
        $stmt->execute();

        echo json_encode(["success" => true, "message" => "Notification marked as read"]);
        break;

    default:
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        break;
}
