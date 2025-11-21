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
    // GET - Fetch orders (with items)
    // -------------------------------------------------------------
    case 'GET':
        if (!isset($_GET['customer_id'])) {
            echo json_encode(["success" => false, "message" => "customer_id required"]);
            exit;
        }

        $customer_id = $_GET['customer_id'];
        $query = "SELECT * FROM orders WHERE customer_id = :cid ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cid', $customer_id);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$order) {
            // 🧩 Fetch items and include vendor (business) name
            $items_stmt = $db->prepare("
                SELECT 
                    oi.id AS order_item_id,
                    oi.product_id,
                    oi.quantity,
                    oi.price,
                    p.name,
                    p.image_url,
                    v.business_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                LEFT JOIN business_partners bp ON p.business_partner_id = bp.id
                LEFT JOIN vendors v ON bp.vendor_id = v.id
                WHERE oi.order_id = :oid
        ");
            $items_stmt->bindParam(':oid', $order['id']);
            $items_stmt->execute();
            $all_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            // 🧩 Group items by business_name
            $grouped = [];
            foreach ($all_items as $item) {
                $biz = $item['business_name'] ?? 'Unknown Vendor';
                if (!isset($grouped[$biz])) {
                    $grouped[$biz] = [
                        "business_name" => $biz,
                        "items" => []
                    ];
                }
                $grouped[$biz]["items"][] = [
                    "order_item_id" => $item['order_item_id'],
                    "product_id" => $item['product_id'],
                    "quantity" => $item['quantity'],
                    "price" => $item['price'],
                    "name" => $item['name'],
                    "image_url" => $item['image_url']
                ];
            }

            // Replace items list with grouped version
            $order['shops'] = array_values($grouped);
            unset($order['items']);
        }

        echo json_encode(["success" => true, "orders" => $orders]);
        break;


    // -------------------------------------------------------------
    // POST - Place a new order (move items from cart)
    // -------------------------------------------------------------
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->customer_id)) {
            echo json_encode(["success" => false, "message" => "customer_id required"]);
            exit;
        }

        try {
            $db->beginTransaction();

            // Get cart id
            $cart_stmt = $db->prepare("SELECT id FROM cart WHERE customer_id = :cid LIMIT 1");
            $cart_stmt->bindParam(':cid', $data->customer_id);
            $cart_stmt->execute();
            $cart = $cart_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cart) throw new Exception("Cart not found");

            $cart_id = $cart['id'];

            // ✅ Only include selected cart_item_ids (if provided)
            $item_ids = isset($data->cart_item_ids) && is_array($data->cart_item_ids)
                ? $data->cart_item_ids
                : [];

            if (empty($item_ids)) throw new Exception("No items selected");

            // ✅ Use placeholders for prepared statement
            $placeholders = implode(',', array_fill(0, count($item_ids), '?'));

            // Fetch only selected items
            $items_stmt = $db->prepare("
                SELECT ci.*, p.price 
                FROM cart_items ci 
                JOIN products p ON ci.product_id = p.id 
                WHERE ci.cart_id = ? AND ci.id IN ($placeholders)
        ");
            $params = array_merge([$cart_id], $item_ids);
            $items_stmt->execute($params);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) throw new Exception("No valid cart items found");

            // Compute subtotal from items
            $total = 0;
            foreach ($items as $it) {
                $total += $it['price'] * $it['quantity'];
            }

            // Add delivery fee if provided
            $delivery_fee = isset($data->delivery_fee) ? floatval($data->delivery_fee) : 0;
            $total += $delivery_fee;

            // Create order
            $status = isset($data->status) ? $data->status : 'paid'; // Default status to 'paid'

            $order_stmt = $db->prepare("
                INSERT INTO orders (customer_id, total_amount, status, delivery_fee) 
                VALUES (:cid, :total, :status, :delivery_fee)
                RETURNING id
            ");
            $order_stmt->bindParam(':cid', $data->customer_id);
            $order_stmt->bindParam(':total', $total);
            $order_stmt->bindParam(':status', $status);
            $order_stmt->bindParam(':delivery_fee', $delivery_fee);
            $order_stmt->execute();

            // Fetch the new order ID
            $order_row = $order_stmt->fetch(PDO::FETCH_ASSOC);
            $order_id = $order_row['id'];

            $short_id = substr($order_id, 0, 8);
            $initial_message = "Your order (ID: " . $short_id . ") is pending approval, Bazario will review your payment so just sit back and relax!";

            $notif_stmt = $db->prepare("
                INSERT INTO notifications (user_id, order_id, message, type, read, created_at)
                VALUES (:uid, :oid, :message, 'order_update', false, NOW())
            ");
            $notif_stmt->bindParam(':uid', $data->customer_id);
            $notif_stmt->bindParam(':oid', $order_id);
            $notif_stmt->bindParam(':message', $initial_message);
            $notif_stmt->execute();

            // Insert items
            $insert_item = $db->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES (:oid, :pid, :q, :p)
            ");
            foreach ($items as $it) {
                $insert_item->bindParam(':oid', $order_id);
                $insert_item->bindParam(':pid', $it['product_id']);
                $insert_item->bindParam(':q', $it['quantity']);
                $insert_item->bindParam(':p', $it['price']);
                $insert_item->execute();
            }

            // Clear only those selected items
            $delete_placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            $clear_cart = $db->prepare("
                DELETE FROM cart_items 
                WHERE cart_id = ? AND id IN ($delete_placeholders)
        ");
            $clear_cart->execute($params);

            $db->commit();
            echo json_encode(["success" => true, "message" => "Order placed successfully", "order_id" => $order_id]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // PUT - Update order status
    // -------------------------------------------------------------
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->order_id) || !isset($data->status)) {
            echo json_encode(["success" => false, "message" => "order_id and status required"]);
            exit;
        }

        $update = $db->prepare("UPDATE orders SET status = :s WHERE id = :id");
        $update->bindParam(':s', $data->status);
        $update->bindParam(':id', $data->order_id);
        $update->execute();

        echo json_encode(["success" => true, "message" => "Order status updated"]);
        break;

    // -------------------------------------------------------------
    // DELETE - Cancel or remove order
    // -------------------------------------------------------------
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->order_id)) {
            echo json_encode(["success" => false, "message" => "order_id required"]);
            exit;
        }

        $del = $db->prepare("DELETE FROM orders WHERE id = :id");
        $del->bindParam(':id', $data->order_id);
        $del->execute();

        echo json_encode(["success" => true, "message" => "Order deleted"]);
        break;

    default:
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        break;
}
