<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
ini_set('memory_limit', '256M');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once '../config/db_connect.php';

$database = new Database();
$db = $database->getConnection();

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_REQUEST['action'] ?? null;

try {
    if ($action === 'upload') {
        // Get raw input and decode JSON
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        // Log for debugging
        error_log("Upload input: " . print_r($input, true));
        error_log("Binary length: " . strlen($binaryData));

        if ($input === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            exit;
        }

        // Validate required fields
        if (!isset($input['file_data'], $input['storage_type'], $input['record_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $fileData = $input['file_data'];
        $storageType = $input['storage_type'];
        $recordId = $input['record_id'];
        $fileName = $input['file_name'] ?? 'file';

        // Decode base64 safely
        $binaryData = base64_decode($fileData, true);
        if ($binaryData === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid base64 file data']);
            exit;
        }

        // Determine table & column based on storage type
        switch ($storageType) {
            case 'vendor_logo':
                $query = "UPDATE vendors SET logo_data = :file_data WHERE id = :record_id";
                break;
            case 'vendor_doc':
                $query = "UPDATE vendor_documents SET file_data = :file_data WHERE id = :record_id";
                break;
            case 'payment_receipt':
                $query = "UPDATE order_payments SET receipt_data = :file_data WHERE id = :record_id";
                break;
            case 'product_image':
                $query = "UPDATE vendor_products SET image_data = :file_data WHERE id = :record_id";
                break;
            case 'event_receipt':
                $query = "UPDATE event_vendors SET receipt_data = :file_data WHERE id = :record_id";
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid storage type']);
                exit;
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':file_data', $binaryData, PDO::PARAM_LOB);
        $stmt->bindParam(':record_id', $recordId);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);
        exit;
    } elseif ($action === 'download') {
        $recordId = $_GET['record_id'] ?? null;
        $storageType = $_GET['storage_type'] ?? null;

        if (!$recordId || !$storageType) {
            echo json_encode(['success' => false, 'message' => 'Missing record_id or storage_type']);
            exit;
        }

        switch ($storageType) {
            case 'vendor_logo':
                $query = "SELECT logo_data FROM vendors WHERE id = :record_id";
                break;
            case 'vendor_doc':
                $query = "SELECT file_data FROM vendor_documents WHERE id = :record_id";
                break;
            case 'payment_receipt':
                $query = "SELECT receipt_data FROM order_payments WHERE id = :record_id";
                break;
            case 'product_image':
                $query = "SELECT image_data FROM vendor_products WHERE id = :record_id";
                break;
            case 'event_receipt':
                $query = "SELECT receipt_data FROM event_vendors WHERE id = :record_id";
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid storage type']);
                exit;
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':record_id', $recordId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row[array_keys($row)[0]])) {
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }

        $fileData = base64_encode($row[array_keys($row)[0]]);
        echo json_encode(['success' => true, 'file_data' => $fileData]);
        exit;
    } elseif ($action === 'delete') {
        $recordId = $_POST['record_id'] ?? null;
        $storageType = $_POST['storage_type'] ?? null;

        if (!$recordId || !$storageType) {
            echo json_encode(['success' => false, 'message' => 'Missing record_id or storage_type']);
            exit;
        }

        switch ($storageType) {
            case 'vendor_logo':
                $query = "UPDATE vendors SET logo_data = NULL WHERE id = :record_id";
                break;
            case 'vendor_doc':
                $query = "UPDATE vendor_documents SET file_data = NULL WHERE id = :record_id";
                break;
            case 'payment_receipt':
                $query = "UPDATE order_payments SET receipt_data = NULL WHERE id = :record_id";
                break;
            case 'product_image':
                $query = "UPDATE vendor_products SET image_data = NULL WHERE id = :record_id";
                break;
            case 'event_receipt':
                $query = "UPDATE event_vendors SET receipt_data = NULL WHERE id = :record_id";
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid storage type']);
                exit;
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':record_id', $recordId);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}
