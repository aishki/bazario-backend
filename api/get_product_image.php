<?php
require_once '../config/db_connect.php';

header("Access-Control-Allow-Origin: *");

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['file'])) {
    http_response_code(400);
    exit("Missing file parameter");
}

$fileName = $_GET['file'];

$stmt = $db->prepare("SELECT image_data FROM vendor_products WHERE image_url = :file LIMIT 1");
$stmt->bindParam(':file', $fileName);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && !empty($row['image_data'])) {
    header("Content-Type: image/jpeg");
    echo $row["image_data"];
} else {
    http_response_code(404);
    echo "Image not found";
}
