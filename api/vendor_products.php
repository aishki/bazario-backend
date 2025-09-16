<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['vendor_id'])) {
            $vendor_id = $_GET['vendor_id'];

            $query = "SELECT id, vendor_id, name, description, image_url, is_featured, created_at
                      FROM vendor_products
                      WHERE vendor_id = :vendor_id
                      ORDER BY created_at DESC";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':vendor_id', $vendor_id);
            $stmt->execute();

            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "products" => $products
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "vendor_id is required"
            ]);
        }
        break;

    default:
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        break;
}
