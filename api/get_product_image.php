<?php
require_once '../config/db_connect.php';

header("Access-Control-Allow-Origin: *");
header("Cache-Control: max-age=31536000, public");

$database = new Database();
$db = $database->getConnection();

if (isset($_GET['product_id'])) {
    $productId = $_GET['product_id'];

    $stmt = $db->prepare("SELECT image_data FROM vendor_products WHERE id = :product_id LIMIT 1");
    $stmt->bindParam(':product_id', $productId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['image_data'])) {
        header("Content-Type: image/jpeg");
        header("Content-Length: " . strlen($row['image_data']));
        echo $row['image_data'];
        exit;
    }

    http_response_code(404);
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Image not found"]);
    exit;
} else {
    http_response_code(400);
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Missing product_id parameter"]);
}
