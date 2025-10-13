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

    // -----------------------------------------------------------------
    // GET: Fetch all customers or a single customer by ID
    // -----------------------------------------------------------------
    case 'GET':
        if (isset($_GET['customer_id'])) {
            $customer_id = $_GET['customer_id'];

            $query = "SELECT 
                        u.id,
                        u.username,
                        u.email,
                        c.id AS customer_id,
                        c.profile_url,
                        c.first_name,
                        c.middle_name,
                        c.last_name,
                        c.suffix,
                        c.phone_number,
                        c.address,
                        c.city,
                        c.postal_code,
                        c.latitude,
                        c.longitude,
                        c.created_at
                      FROM customers c
                      JOIN users u ON c.id = u.id
                      WHERE c.id = :customer_id";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode([
                    "success" => true,
                    "customer" => $customer
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "Customer not found"
                ]);
            }
        } else {
            // Get all customers
            $query = "SELECT 
                        u.id,
                        u.username,
                        u.email,
                        c.first_name,
                        c.middle_name,
                        c.last_name,
                        c.suffix,
                        c.profile_url,
                        c.phone_number,
                        c.address,
                        c.city,
                        c.postal_code,
                        c.latitude,
                        c.longitude,
                        c.created_at
                      FROM customers c
                      JOIN users u ON c.id = u.id
                      ORDER BY c.created_at DESC";

            $stmt = $db->prepare($query);
            $stmt->execute();
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "customers" => $customers
            ]);
        }
        break;


    // -----------------------------------------------------------------
    // POST: Create a new customer (alr in auth)
    // -----------------------------------------------------------------
    case 'POST':
        // $data = json_decode(file_get_contents("php://input"));

        // if (
        //     !isset($data->email) ||
        //     !isset($data->username) ||
        //     !isset($data->password)
        // ) {
        //     echo json_encode([
        //         "success" => false,
        //         "message" => "Missing required fields: email, username, or password"
        //     ]);
        //     exit;
        // }

        // try {
        //     $db->beginTransaction();

        //     // Hash password
        //     $password_hash = password_hash($data->password, PASSWORD_BCRYPT);

        //     // Insert into users
        //     $user_query = "INSERT INTO users (username, email, password_hash, role)
        //                    VALUES (:username, :email, :password_hash, 'customer')
        //                    RETURNING id";

        //     $user_stmt = $db->prepare($user_query);
        //     $user_stmt->bindParam(':username', $data->username);
        //     $user_stmt->bindParam(':email', $data->email);
        //     $user_stmt->bindParam(':password_hash', $password_hash);
        //     $user_stmt->execute();
        //     $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
        //     $user_id = $user_row['id'];

        //     // Insert into customers
        //     $cust_query = "INSERT INTO customers (
        //                         id, first_name, middle_name, last_name, suffix,
        //                         phone_number, address, city, postal_code
        //                    ) VALUES (
        //                         :id, :first_name, :middle_name, :last_name, :suffix,
        //                         :phone_number, :address, :city, :postal_code
        //                    )";

        //     $cust_stmt = $db->prepare($cust_query);
        //     $cust_stmt->bindParam(':id', $user_id);
        //     $cust_stmt->bindParam(':first_name', $data->first_name);
        //     $cust_stmt->bindParam(':middle_name', $data->middle_name);
        //     $cust_stmt->bindParam(':last_name', $data->last_name);
        //     $cust_stmt->bindParam(':suffix', $data->suffix);
        //     $cust_stmt->bindParam(':phone_number', $data->phone_number);
        //     $cust_stmt->bindParam(':address', $data->address);
        //     $cust_stmt->bindParam(':city', $data->city);
        //     $cust_stmt->bindParam(':postal_code', $data->postal_code);
        //     $cust_stmt->execute();

        //     $db->commit();

        //     echo json_encode([
        //         "success" => true,
        //         "message" => "Customer created successfully",
        //         "customer_id" => $user_id
        //     ]);
        // } catch (Exception $e) {
        //     $db->rollback();
        //     echo json_encode([
        //         "success" => false,
        //         "message" => "Error creating customer: " . $e->getMessage()
        //     ]);
        // }
        break;


    // -----------------------------------------------------------------
    // PUT: Update customer profile
    // -----------------------------------------------------------------
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->customer_id)) {
            echo json_encode([
                "success" => false,
                "message" => "Customer ID is required"
            ]);
            exit;
        }

        try {
            // --- Update CUSTOMER TABLE ---
            $update_fields = [];
            $params = [":customer_id" => $data->customer_id];

            $fields = [
                "profile_url",
                "first_name",
                "middle_name",
                "last_name",
                "suffix",
                "phone_number",
                "address",
                "city",
                "postal_code",
                "latitude",
                "longitude"
            ];

            foreach ($fields as $field) {
                if (isset($data->$field)) {
                    $update_fields[] = "$field = :$field";
                    $params[":$field"] = $data->$field;
                }
            }

            if (!empty($update_fields)) {
                $query = "UPDATE customers SET " . implode(", ", $update_fields) . " WHERE id = :customer_id";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
            }

            // --- Update USERS TABLE (for username/email) ---
            if (isset($data->username) || isset($data->email)) {
                $userUpdateFields = [];
                $userParams = [":id" => $data->customer_id];

                if (isset($data->username)) {
                    $userUpdateFields[] = "username = :username";
                    $userParams[":username"] = $data->username;
                }
                if (isset($data->email)) {
                    $userUpdateFields[] = "email = :email";
                    $userParams[":email"] = $data->email;
                }

                if (!empty($userUpdateFields)) {
                    $userQuery = "UPDATE users SET " . implode(", ", $userUpdateFields) . " WHERE id = :id";
                    $userStmt = $db->prepare($userQuery);
                    $userStmt->execute($userParams);
                }
            }

            echo json_encode([
                "success" => true,
                "message" => "Customer updated successfully"
            ]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => "Error updating customer: " . $e->getMessage()
            ]);
        }
        break;



    // -----------------------------------------------------------------
    // DELETE: Delete a customer + linked user
    // -----------------------------------------------------------------
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->customer_id)) {
            echo json_encode([
                "success" => false,
                "message" => "Customer ID is required"
            ]);
            exit;
        }

        try {
            $db->beginTransaction();

            // Delete from customers
            $del_cust = $db->prepare("DELETE FROM customers WHERE id = :id");
            $del_cust->bindParam(':id', $data->customer_id);
            $del_cust->execute();

            // Delete from users
            $del_user = $db->prepare("DELETE FROM users WHERE id = :id");
            $del_user->bindParam(':id', $data->customer_id);
            $del_user->execute();

            $db->commit();

            echo json_encode([
                "success" => true,
                "message" => "Customer deleted successfully"
            ]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode([
                "success" => false,
                "message" => "Error deleting customer: " . $e->getMessage()
            ]);
        }
        break;

    default:
        echo json_encode([
            "success" => false,
            "message" => "Method not allowed"
        ]);
        break;
}
