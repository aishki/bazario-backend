<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';
$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // GET - Fetch announcements
    case 'GET':
        $target = isset($_GET['target']) ? $_GET['target'] : 'all';
        $vendorId = isset($_GET['vendor_id']) ? $_GET['vendor_id'] : null;

        // Fetch announcements with admin username and likes count
        $query = "
            SELECT a.*, u.username AS admin_name,
                   COALESCE(l.likes_count, 0) AS likes,
                   CASE WHEN al.user_id IS NOT NULL THEN true ELSE false END AS is_liked
            FROM announcements a
            LEFT JOIN users u ON a.posted_by = u.id
            LEFT JOIN (
                SELECT announcement_id, COUNT(*) AS likes_count
                FROM announcement_likes
                GROUP BY announcement_id
            ) l ON a.id = l.announcement_id
            LEFT JOIN announcement_likes al 
                ON a.id = al.announcement_id AND al.user_id = :vendorId
            WHERE a.target_audience = :target OR a.target_audience = 'all'
            ORDER BY a.created_at DESC
            LIMIT 50
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':target', $target);
        $stmt->bindParam(':vendorId', $vendorId);
        $stmt->execute();
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "announcements" => $announcements]);
        break;

    // POST - Like an announcement
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->announcement_id) || !isset($data->user_id) || !isset($data->action)) {
            echo json_encode(["success" => false, "message" => "announcement_id, user_id, and action required"]);
            exit;
        }

        $announcement_id = $data->announcement_id;
        $user_id = $data->user_id;
        $action = $data->action;

        if ($action === 'like') {
            // Prevent duplicate likes
            $checkQuery = "SELECT * FROM announcement_likes WHERE announcement_id = :aid AND user_id = :uid";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':aid', $announcement_id);
            $checkStmt->bindParam(':uid', $user_id);
            $checkStmt->execute();

            if ($checkStmt->rowCount() === 0) {
                $insertQuery = "INSERT INTO announcement_likes (announcement_id, user_id) VALUES (:aid, :uid)";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindParam(':aid', $announcement_id);
                $insertStmt->bindParam(':uid', $user_id);
                $insertStmt->execute();
            }

            echo json_encode(["success" => true, "message" => "Announcement liked"]);
        } elseif ($action === 'unlike') {
            // Remove like
            $deleteQuery = "DELETE FROM announcement_likes WHERE announcement_id = :aid AND user_id = :uid";
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bindParam(':aid', $announcement_id);
            $deleteStmt->bindParam(':uid', $user_id);
            $deleteStmt->execute();

            echo json_encode(["success" => true, "message" => "Announcement unliked"]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid action"]);
        }

        break;


    default:
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        break;
}
