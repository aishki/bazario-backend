<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';
$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // -------------------------------------------------------------
    // GET - Retrieve cart + items for a customer
    // -------------------------------------------------------------
    case 'GET':
        if (!isset($_GET['customer_id'])) {
            echo json_encode(["success" => false, "message" => "customer_id required"]);
            exit;
        }

        $customer_id = $_GET['customer_id'];

        // Get or create cart
        $cart_query = "SELECT * FROM cart WHERE customer_id = :customer_id LIMIT 1";
        $cart_stmt = $db->prepare($cart_query);
        $cart_stmt->bindParam(':customer_id', $customer_id);
        $cart_stmt->execute();
        $cart = $cart_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cart) {
            // Create empty cart
            $new_cart = $db->prepare("INSERT INTO cart (customer_id) VALUES (:customer_id) RETURNING id, created_at");
            $new_cart->bindParam(':customer_id', $customer_id);
            $new_cart->execute();
            $cart = $new_cart->fetch(PDO::FETCH_ASSOC);
        }

        // Fetch items
        $item_query = "SELECT 
                         ci.id AS cart_item_id,
                         ci.quantity,
                         p.id AS product_id,
                         p.name,
                         p.price,
                         p.image_url,
                         v.business_name
                       FROM cart_items ci
                       JOIN products p ON ci.product_id = p.id
                       LEFT JOIN business_partners bp ON p.business_partner_id = bp.id
                       LEFT JOIN vendors v ON bp.vendor_id = v.id
                       WHERE ci.cart_id = :cart_id
";
        $item_stmt = $db->prepare($item_query);
        $item_stmt->bindParam(':cart_id', $cart['id']);
        $item_stmt->execute();
        $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "cart" => [
                "id" => $cart['id'],
                "created_at" => $cart['created_at'],
                "items" => $items
            ]
        ]);
        break;

    // -------------------------------------------------------------
    // POST - Add an item to the cart
    // -------------------------------------------------------------
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->customer_id) || !isset($data->product_id) || !isset($data->quantity)) {
            echo json_encode(["success" => false, "message" => "Missing customer_id, product_id or quantity"]);
            exit;
        }

        try {
            // Find or create cart
            $cart_stmt = $db->prepare("SELECT id FROM cart WHERE customer_id = :cid LIMIT 1");
            $cart_stmt->bindParam(':cid', $data->customer_id);
            $cart_stmt->execute();
            $cart = $cart_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cart) {
                $new_cart = $db->prepare("INSERT INTO cart (customer_id) VALUES (:cid) RETURNING id");
                $new_cart->bindParam(':cid', $data->customer_id);
                $new_cart->execute();
                $cart = $new_cart->fetch(PDO::FETCH_ASSOC);
            }

            $cart_id = $cart['id'];

            // Check if product already exists in cart
            $check = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = :cid AND product_id = :pid");
            $check->bindParam(':cid', $cart_id);
            $check->bindParam(':pid', $data->product_id);
            $check->execute();

            if ($check->rowCount() > 0) {
                // Update quantity
                $existing = $check->fetch(PDO::FETCH_ASSOC);
                $new_qty = $existing['quantity'] + $data->quantity;
                $update = $db->prepare("UPDATE cart_items SET quantity = :q WHERE id = :id");
                $update->bindParam(':q', $new_qty);
                $update->bindParam(':id', $existing['id']);
                $update->execute();
            } else {
                // Insert new item
                $insert = $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity)
                                        VALUES (:cart_id, :product_id, :quantity)");
                $insert->bindParam(':cart_id', $cart_id);
                $insert->bindParam(':product_id', $data->product_id);
                $insert->bindParam(':quantity', $data->quantity);
                $insert->execute();
            }

            echo json_encode(["success" => true, "message" => "Item added to cart"]);
        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // PUT - Update item quantity or remove item
    // -------------------------------------------------------------
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->cart_item_id) || !isset($data->quantity)) {
            echo json_encode(["success" => false, "message" => "cart_item_id and quantity required"]);
            exit;
        }

        try {
            if ($data->quantity <= 0) {
                $del = $db->prepare("DELETE FROM cart_items WHERE id = :id");
                $del->bindParam(':id', $data->cart_item_id);
                $del->execute();
                echo json_encode(["success" => true, "message" => "Item removed"]);
            } else {
                $update = $db->prepare("UPDATE cart_items SET quantity = :q WHERE id = :id");
                $update->bindParam(':q', $data->quantity);
                $update->bindParam(':id', $data->cart_item_id);
                $update->execute();
                echo json_encode(["success" => true, "message" => "Quantity updated"]);
            }
        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // DELETE - Clear the entire cart OR delete specific items
    // -------------------------------------------------------------
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->customer_id)) {
            echo json_encode(["success" => false, "message" => "customer_id required"]);
            exit;
        }

        try {
            if (!empty($data->cart_item_ids)) {
                // 🧩 Delete only selected cart items
                $placeholders = implode(',', array_fill(0, count($data->cart_item_ids), '?'));
                $sql = "DELETE FROM cart_items WHERE id IN ($placeholders)";
                $stmt = $db->prepare($sql);
                $stmt->execute($data->cart_item_ids);

                echo json_encode([
                    "success" => true,
                    "message" => "Selected cart items deleted successfully"
                ]);
            } else {
                // 🧹 Clear entire cart if no specific IDs provided
                $stmt = $db->prepare("DELETE FROM cart_items WHERE cart_id = (SELECT id FROM cart WHERE customer_id = :cid)");
                $stmt->bindParam(':cid', $data->customer_id);
                $stmt->execute();

                echo json_encode([
                    "success" => true,
                    "message" => "Entire cart cleared successfully"
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        break;


    default:
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        break;
}
