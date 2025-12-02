<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/api/error.log');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        // ===================== GET =====================
        case 'GET':
            if (!isset($_GET['vendor_id'])) {
                echo json_encode(["success" => false, "message" => "vendor_id is required"]);
                exit;
            }
            $vendor_id = $_GET['vendor_id'];

            // ----- TOP PRODUCTS -----
            if (isset($_GET['top_products']) && $_GET['top_products'] === 'true') {
                $query = "
                    SELECT p.id, p.vendor_id, p.name, p.description, p.image_url, p.is_featured,
                           COALESCE(SUM(s.quantity), 0) AS total_sold
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
                echo json_encode(["success" => true, "products" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                exit;
            }

            // ----- REGULAR PRODUCT LIST -----
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
                    AND (vp.description = p.description OR 
                        (vp.description IS NULL AND p.description IS NULL))
                WHERE vp.vendor_id = :vendor_id
                ORDER BY vp.created_at DESC
            ";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':vendor_id', $vendor_id);
            $stmt->execute();
            echo json_encode(["success" => true, "products" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ===================== POST =====================
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['vendor_id']) || !isset($data['name'])) {
                echo json_encode(["success" => false, "message" => "vendor_id and name are required"]);
                exit;
            }

            $vendor_id = $data['vendor_id'];
            $name = $data['name'];
            $description = $data['description'] ?? null;
            $is_featured = $data['isFeatured'] ?? false;

            // ===== IMAGE HANDLING (DB ONLY) =====
            $imageUrl = null;
            $imageData = null;

            if (!empty($data['imageData'])) {
                $imageData = base64_decode($data['imageData']);
                if ($imageData === false) {
                    echo json_encode(["success" => false, "message" => "Invalid image data"]);
                    exit;
                }
                $imageUrl = 'product_' . uniqid() . '.jpg';
            }

            // ===== CHECK MAX PRODUCTS =====
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM vendor_products WHERE vendor_id = :vendor_id");
            $checkStmt->bindParam(':vendor_id', $vendor_id);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() >= 10) {
                echo json_encode(["success" => false, "message" => "Limit is 10 products"]);
                exit;
            }

            // ===== INSERT PRODUCT =====
            $query = "
                INSERT INTO vendor_products
                (vendor_id, name, description, image_url, image_data, is_featured, created_at)
                VALUES (:vendor_id, :name, :description, :image_url, :image_data, :is_featured, NOW())
                RETURNING id, vendor_id, name, description, image_url, is_featured, created_at
            ";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':vendor_id', $vendor_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':image_url', $imageUrl);
            $stmt->bindParam(':image_data', $imageData, PDO::PARAM_LOB);
            $stmt->bindParam(':is_featured', $is_featured, PDO::PARAM_BOOL);

            $stmt->execute();
            echo json_encode(["success" => true, "product" => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        // ===================== PUT =====================
        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['id'])) {
                echo json_encode(["success" => false, "message" => "Product ID required"]);
                exit;
            }

            $query = "
                UPDATE vendor_products
                SET name = :name, description = :description, image_url = :image_url, is_featured = :is_featured
                WHERE id = :id
            ";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':id' => $data['id'],
                ':name' => $data['name'],
                ':description' => $data['description'],
                ':image_url' => $data['image_url'],
                ':is_featured' => $data['is_featured']
            ]);

            echo json_encode(["success" => true, "message" => "Product updated"]);
            break;

        // ===================== DELETE =====================
        case 'DELETE':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['id'])) {
                echo json_encode(["success" => false, "message" => "Product ID required"]);
                exit;
            }
            $stmt = $db->prepare("DELETE FROM vendor_products WHERE id = :id");
            $stmt->execute([':id' => $data['id']]);
            echo json_encode(["success" => true, "message" => "Product deleted"]);
            break;

        default:
            echo json_encode(["success" => false, "message" => "Invalid method"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
