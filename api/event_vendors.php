<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: fetch vendor status ---
if ($method === 'GET' && isset($_GET['event_id']) && isset($_GET['vendor_id'])) {
    $query = "SELECT status, event_receipt_url 
              FROM event_vendors 
              WHERE event_id = :event_id AND vendor_id = :vendor_id
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":event_id", $_GET['event_id']);
    $stmt->bindParam(":vendor_id", $_GET['vendor_id']);
    $stmt->execute();

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            "success" => true,
            "vendor_status" => $row['status'],
            "receipt_url" => $row['event_receipt_url']
        ]);
    } else {
        // No record found = vendor has not applied yet (not an error)
        echo json_encode([
            "success" => true,
            "vendor_status" => null,
            "receipt_url" => null
        ]);
    }
    exit;
}

// --- POST: apply or upload receipt ---
if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    error_log("POST /event_vendors.php received: " . json_encode($data));

    $event_id = $data['event_id'] ?? null;
    $vendor_id = $data['vendor_id'] ?? null;
    $status = $data['status'] ?? "applied";
    $receipt_url = $data['event_receipt_url'] ?? null;

    if (!$event_id || !$vendor_id) {
        echo json_encode([
            "success" => false,
            "error" => "Missing event_id or vendor_id"
        ]);
        exit;
    }

    try {
        if ($receipt_url) {
            // ✅ Upload/update receipt for existing record
            $stmt = $db->prepare("
                UPDATE event_vendors
                SET event_receipt_url = :receipt_url, status = :status
                WHERE event_id = :event_id AND vendor_id = :vendor_id
            ");
            $stmt->execute([
                ":receipt_url" => $receipt_url,
                ":status" => $status,
                ":event_id" => $event_id,
                ":vendor_id" => $vendor_id
            ]);

            echo json_encode([
                "success" => true,
                "message" => "Receipt uploaded/updated"
            ]);
        } else {
            // ✅ Insert new record or update status if already exists
            $stmt = $db->prepare("
                INSERT INTO event_vendors (event_id, vendor_id, status)
                VALUES (:event_id, :vendor_id, :status)
                ON CONFLICT (event_id, vendor_id) DO NOTHING
            ");
            $stmt->execute([
                ":event_id" => $event_id,
                ":vendor_id" => $vendor_id,
                ":status" => $status
            ]);

            echo json_encode([
                "success" => true,
                "message" => "Application recorded/updated"
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
    exit;
}

// --- Fallback ---
echo json_encode([
    "success" => false,
    "error" => "Invalid request method"
]);
