<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../config/db_connect.php';

$db = new Database();
$pdo = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

if ($method === 'POST' && isset($data['action']) && $data['action'] === 'add_payment') {
    $order_id = $data['order_id'] ?? null;
    $reference_number = $data['reference_number'] ?? null;
    $receipt_url = $data['receipt_url'] ?? null;

    if (!$order_id || !$reference_number) {
        echo json_encode([
            "success" => false,
            "message" => "Missing required fields."
        ]);
        exit;
    }

    try {
        $query = "INSERT INTO order_payments (order_id, reference_number, receipt_url, status)
                  VALUES (:order_id, :reference_number, :receipt_url, 'under_review')";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ":order_id" => $order_id,
            ":reference_number" => $reference_number,
            ":receipt_url" => $receipt_url
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Payment record created successfully."
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ]);
    }
    exit;
}

if ($method === 'GET' && isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    $query = "SELECT * FROM order_payments WHERE order_id = :order_id ORDER BY uploaded_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([":order_id" => $order_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "payments" => $payments]);
    exit;
}

echo json_encode(["success" => false, "message" => "Invalid request."]);
