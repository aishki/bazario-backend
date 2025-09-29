<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // --- FETCH DOCUMENTS ---
        $vendorId = isset($_GET['vendor_id']) ? $_GET['vendor_id'] : null;
        if (!$vendorId) {
            echo json_encode(["success" => false, "message" => "vendor_id is required"]);
            exit;
        }

        $query = "SELECT 
                    vd.id,
                    vd.vendor_id,
                    vd.doc_type,
                    vd.file_url,
                    vd.extra_info,
                    vd.status,
                    vd.uploaded_at,
                    vd.reviewed_at,
                    u.username AS reviewed_by
                  FROM vendor_documents vd
                  LEFT JOIN users u ON vd.reviewed_by = u.id
                  WHERE vd.vendor_id = :vendor_id
                  ORDER BY vd.uploaded_at DESC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":vendor_id", $vendorId);
        $stmt->execute();
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "documents" => $docs]);
    } elseif ($method === 'POST') {
        // --- CREATE NEW DOCUMENT ---
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || !isset($data['vendor_id'], $data['doc_type'], $data['file_url'])) {
            echo json_encode(["success" => false, "message" => "Missing required fields"]);
            exit;
        }

        $query = "INSERT INTO vendor_documents (vendor_id, doc_type, file_url, status) 
                  VALUES (:vendor_id, :doc_type, :file_url, 'pending')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":vendor_id", $data['vendor_id']);
        $stmt->bindParam(":doc_type", $data['doc_type']);
        $stmt->bindParam(":file_url", $data['file_url']);
        $stmt->execute();

        echo json_encode(["success" => true, "message" => "Document uploaded"]);
    } elseif ($method === 'PUT') {
        // --- UPDATE DOCUMENT (replace file, update status, etc.) ---
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || !isset($data['id'])) {
            echo json_encode(["success" => false, "message" => "Document id is required"]);
            exit;
        }

        // Build update fields dynamically
        $fields = [];
        if (isset($data['file_url'])) $fields[] = "file_url = :file_url";
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $fields[] = "reviewed_at = NOW()";
            if (isset($data['reviewed_by'])) $fields[] = "reviewed_by = :reviewed_by";
        }

        if (empty($fields)) {
            echo json_encode(["success" => false, "message" => "No fields to update"]);
            exit;
        }

        $query = "UPDATE vendor_documents SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $data['id']);
        if (isset($data['file_url'])) $stmt->bindParam(":file_url", $data['file_url']);
        if (isset($data['status'])) $stmt->bindParam(":status", $data['status']);
        if (isset($data['reviewed_by'])) $stmt->bindParam(":reviewed_by", $data['reviewed_by']);
        $stmt->execute();

        // --- Create notification if status changes ---
        if (isset($data['status']) && isset($data['vendor_id'])) {
            $notifMsg = "Your document has been " . $data['status'];
            $notifQuery = "INSERT INTO notifications (message, user_id, type) 
                           VALUES (:message, :user_id, 'verification')";
            $notifStmt = $db->prepare($notifQuery);
            $notifStmt->bindParam(":message", $notifMsg);
            $notifStmt->bindParam(":user_id", $data['vendor_id']);
            $notifStmt->execute();
        }

        // --- Check if vendor has 4 approved docs ---
        if (isset($data['vendor_id'])) {
            $checkQuery = "SELECT COUNT(*) as approved_count 
                           FROM vendor_documents 
                           WHERE vendor_id = :vendor_id AND status = 'approved'";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(":vendor_id", $data['vendor_id']);
            $checkStmt->execute();
            $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['approved_count'] >= 4) {
                // Insert notification
                $successMsg = "🎉 Congratulations! You've successfully accomplished all four documents! You are now qualified for verification!";
                $notifQuery = "INSERT INTO notifications (message, user_id, type) 
                               VALUES (:message, :user_id, 'verification')";
                $notifStmt = $db->prepare($notifQuery);
                $notifStmt->bindParam(":message", $successMsg);
                $notifStmt->bindParam(":user_id", $data['vendor_id']);
                $notifStmt->execute();

                // Insert verification request
                $verifQuery = "INSERT INTO vendor_verification_requests (vendor_id, status) 
                               VALUES (:vendor_id, 'pending')";
                $verifStmt = $db->prepare($verifQuery);
                $verifStmt->bindParam(":vendor_id", $data['vendor_id']);
                $verifStmt->execute();
            }
        }

        echo json_encode(["success" => true, "message" => "Document updated"]);
    } elseif ($method === 'DELETE') {
        // --- DELETE DOCUMENT ---
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || !isset($data['id'])) {
            echo json_encode(["success" => false, "message" => "Document id is required"]);
            exit;
        }

        $query = "DELETE FROM vendor_documents WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $data['id']);
        $stmt->execute();

        echo json_encode(["success" => true, "message" => "Document deleted"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
