<?php
require_once '../config/db_connect.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit("Product ID required");
}

$id = $_GET['id'];

$stmt = $db->prepare("SELECT image_data FROM vendor_products WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && !empty($row['image_data'])) {
    header("Content-Type: image/jpeg");
    echo $row["image_data"];
} else {
    http_response_code(404);
}
