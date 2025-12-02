<?php
require_once '../config/db_connect.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$database = new Database();
$db = $database->getConnection();

if (isset($_GET['product_id'])) {
    // Fetch by product ID and return base64 encoded data
    $productId = $_GET['product_id'];

    $stmt = $db->prepare("SELECT image_data FROM vendor_products WHERE id = :product_id LIMIT 1");
    $stmt->bindParam(':product_id', $productId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['image_data'])) {
        $base64Image = base64_encode($row['image_data']);
        echo json_encode([
            'success' => true,
            'image_data' => $base64Image
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Image not found'
        ]);
    }
} elseif (isset($_GET['file'])) {
    // Legacy: Fetch by file parameter and return raw binary for backward compatibility
    $fileName = $_GET['file'];

    $stmt = $db->prepare("SELECT image_data FROM vendor_products WHERE image_url LIKE :file LIMIT 1");
    $search = "%$fileName%";
    $stmt->bindParam(':file', $search);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['image_data'])) {
        header("Content-Type: image/jpeg");
        echo $row["image_data"];
    } else {
        http_response_code(404);
        echo "Image not found";
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing product_id or file parameter'
    ]);
}
