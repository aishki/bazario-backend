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

            // Check if we want top products
            if (isset($_GET['top_products']) && $_GET['top_products'] == 'true') {

                // Get products sold by this vendor, summing quantity
                $query = "
                    SELECT p.id, p.vendor_id, p.name, p.description, p.image_url, p.is_featured,
                        COALESCE(SUM(s.quantity),0) as total_sold
                    FROM products p
                    LEFT JOIN sale_items s ON s.product_id = p.id
                    LEFT JOIN business_partners b ON p.business_partner_id = b.id
                    WHERE p.vendor_id = :vendor_id OR b.vendor_id = :vendor_id
                    GROUP BY p.id
                    ORDER BY total_sold DESC
                    LIMIT 3
                ";

                $stmt = $db->prepare($query);
                $stmt->bindParam(':vendor_id', $vendor_id);
                $stmt->execute();

                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    "success" => true,
                    "products" => $products
                ]);
                exit;
            }

            // Regular products for vendor (PRODUCT LIST)
            $query = "
                SELECT 
                    vp.id,
                    vp.vendor_id,
                    vp.name,
                    vp.description,
                    vp.image_url,
                    vp.is_featured,
                    vp.created_at,
                    p.price,
                    p.stock
                FROM vendor_products vp
                LEFT JOIN products p 
                    ON vp.name = p.name 
                    AND (vp.description = p.description OR (vp.description IS NULL AND p.description IS NULL))
                WHERE vp.vendor_id = :vendor_id
                ORDER BY vp.created_at DESC
            ";


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


    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['vendor_id']) || !isset($data['name'])) {
            echo json_encode([
                "success" => false,
                "message" => "vendor_id and name are required"
            ]);
            exit;
        }

        // Check if vendor already has 5 products
        $checkQuery = "SELECT COUNT(*) as product_count FROM vendor_products WHERE vendor_id = :vendor_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':vendor_id', $data['vendor_id']);
        $checkStmt->execute();
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($result['product_count'] >= 10) {
            echo json_encode([
                "success" => false,
                "message" => "You can only add up to 10 products.",
            ]);
            exit;
        }

        $query = "INSERT INTO vendor_products (vendor_id, name, description, image_url, is_featured)
                  VALUES (:vendor_id, :name, :description, :image_url, :is_featured)
                  RETURNING id, vendor_id, name, description, image_url, is_featured, created_at";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':vendor_id', $data['vendor_id']);
        $stmt->bindParam(':name', $data['name']);
        $description = $data['description'] ?? null;
        $stmt->bindParam(':description', $description);
        $image_url = $data['image_url'] ?? null;
        $stmt->bindParam(':image_url', $image_url);
        $is_featured = $data['is_featured'] ?? false;
        $stmt->bindParam(':is_featured', $is_featured);

        if ($stmt->execute()) {
            $newProduct = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                "success" => true,
                "message" => "Product created successfully",
                "product" => $newProduct
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Failed to create product"
            ]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['id'])) {
            echo json_encode([
                "success" => false,
                "message" => "Product ID is required"
            ]);
            exit;
        }

        $query = "UPDATE vendor_products
                  SET name = :name, description = :description, image_url = :image_url, is_featured = :is_featured
                  WHERE id = :id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data['id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':image_url', $data['image_url']);
        $stmt->bindParam(':is_featured', $data['is_featured']);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Product updated successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update product"]);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['id'])) {
            echo json_encode([
                "success" => false,
                "message" => "Product ID is required"
            ]);
            exit;
        }

        $query = "DELETE FROM vendor_products WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data['id']);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Product deleted successfully"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Failed to delete product"
            ]);
        }
        break;

    default:
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        break;
}
