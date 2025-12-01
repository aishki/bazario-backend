<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../config/db_connect.php';

$database = new Database();
$db = $database->getConnection();

$action = $_REQUEST['action'] ?? null;

try {
    if ($action === 'upload') {
        // Parse input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['file_data'], $input['storage_type'], $input['record_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $fileData = $input['file_data'];
        $storageType = $input['storage_type'];
        $recordId = $input['record_id'];
        $fileName = $input['file_name'] ?? 'file';

        // Decode base64
        $binaryData = base64_decode($fileData);

        // Route to appropriate table based on storage type
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
    } elseif ($action === 'download') {
        $recordId = $_GET['record_id'] ?? null;
        $storageType = $_GET['storage_type'] ?? null;

        if (!$recordId || !$storageType) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        // Route to appropriate table
        switch ($storageType) {
            case 'vendor_logo':
                $query = "SELECT logo_data FROM vendors WHERE id = :record_id";
                $column = 'logo_data';
                break;
            case 'vendor_doc':
                $query = "SELECT file_data FROM vendor_documents WHERE id = :record_id";
                $column = 'file_data';
                break;
            case 'payment_receipt':
                $query = "SELECT receipt_data FROM order_payments WHERE id = :record_id";
                $column = 'receipt_data';
                break;
            case 'product_image':
                $query = "SELECT image_data FROM vendor_products WHERE id = :record_id";
                $column = 'image_data';
                break;
            case 'event_receipt':
                $query = "SELECT receipt_data FROM event_vendors WHERE id = :record_id";
                $column = 'receipt_data';
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid storage type']);
                exit;
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':record_id', $recordId);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $fileData = $row[$column];

            if ($fileData) {
                $base64Data = base64_encode($fileData);
                echo json_encode(['success' => true, 'file_data' => $base64Data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No file found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
    } elseif ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        $recordId = $input['record_id'] ?? null;
        $storageType = $input['storage_type'] ?? null;

        if (!$recordId || !$storageType) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        // Route to appropriate table
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

        echo json_encode(['success' => true, 'message' => 'File deleted']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
