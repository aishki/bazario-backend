<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if ($data->action == "login") {
    if (empty($data->email) || empty($data->password)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Email and password are required"]);
        exit;
    }

    try {
        $sql = "SELECT u.id, u.email, u.password_hash, u.role, u.username,
                       v.id as vendor_id, v.business_name, v.description, v.business_category, v.logo_url, v.verified, v.social_links,
                       vc.phone_number,
                       c.first_name, c.last_name
                FROM users u 
                LEFT JOIN vendors v ON u.id = v.id 
                LEFT JOIN vendor_contacts vc ON u.id = vc.id
                LEFT JOIN customers c ON u.id = c.id
                WHERE u.email = :email";
        $stmt = $database->executeQuery($sql, [':email' => $data->email]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($data->password, $user['password_hash'])) {
                $token = bin2hex(random_bytes(32));
                $response = [
                    "status" => "success",
                    "message" => "Login successful",
                    "user_id" => $user['id'],
                    "email" => $user['email'],
                    "username" => $user['username'],
                    "role" => $user['role'],
                    "token" => $token
                ];

                if ($user['role'] === 'customer' && !empty($user['first_name'])) {
                    $response['first_name'] = $user['first_name'];
                    $response['last_name'] = $user['last_name'];
                }

                if ($user['role'] === 'vendor' && !empty($user['vendor_id'])) {
                    $response['vendor_id'] = $user['vendor_id'];
                    $response['business_name'] = $user['business_name'];
                    $response['description'] = $user['description'];
                    $response['business_category'] = $user['business_category'];
                    $response['logo_url'] = $user['logo_url'];
                    $response['verified'] = $user['verified'];

                    if (!empty($user['phone_number'])) {
                        $response['phone_number'] = $user['phone_number'];
                    }

                    if (!empty($user['social_links'])) {
                        $response['social_links'] = json_decode($user['social_links'], true);
                    }
                }
                echo json_encode($response);
            } else {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
            }
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "User not found"]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} elseif ($data->action == "register") {
    if (empty($data->email) || empty($data->password) || empty($data->first_name) || empty($data->last_name) || empty($data->username)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Email, password, first name, last name, and username are required"]);
        exit;
    }

    try {
        $check_sql = "SELECT email, username FROM users WHERE email = :email OR username = :username";
        $stmt = $database->executeQuery($check_sql, [':email' => $data->email, ':username' => $data->username]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['email'] === $data->email) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "Email already exists"]);
                exit;
            }
            if ($existing['username'] === $data->username) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "Username already exists"]);
                exit;
            }
        }

        $password_hash = password_hash($data->password, PASSWORD_DEFAULT, ['cost' => 10]);
        $role = isset($data->role) ? strtolower($data->role) : 'customer';
        $now = date("Y-m-d H:i:s");

        $db->beginTransaction();

        // Insert into users table
        $user_sql = "INSERT INTO users (email, username, password_hash, role, created_at) 
                     VALUES (:email, :username, :password_hash, :role, :created_at) 
                     RETURNING id";
        $stmt = $database->executeQuery($user_sql, [
            ':email' => $data->email,
            ':username' => $data->username,
            ':password_hash' => $password_hash,
            ':role' => $role,
            ':created_at' => $now
        ]);
        $user_id = $stmt->fetchColumn();

        if ($user_id) {
            $response = [
                "status" => "success",
                "message" => "Registration successful",
                "user_id" => $user_id,
                "role" => $role
            ];

            try {
                if ($role === 'customer') {
                    // Insert into customers table
                    $customer_sql = "INSERT INTO customers (id, first_name, middle_name, last_name, suffix, phone_number, address, city, postal_code, created_at) 
                                   VALUES (:id, :first_name, :middle_name, :last_name, :suffix, :phone_number, :address, :city, :postal_code, :created_at)";
                    $database->executeQuery($customer_sql, [
                        ':id' => $user_id,
                        ':first_name' => $data->first_name,
                        ':middle_name' => $data->middle_name ?? null,
                        ':last_name' => $data->last_name,
                        ':suffix' => $data->suffix ?? null,
                        ':phone_number' => $data->phone ?? null,
                        ':address' => $data->address ?? null,
                        ':city' => $data->city ?? null,
                        ':postal_code' => $data->postal_code ?? null,
                        ':created_at' => $now
                    ]);

                    $cart_sql = "INSERT INTO cart (customer_id, created_at) VALUES (:customer_id, :created_at)";
                    $database->executeQuery($cart_sql, [
                        ':customer_id' => $user_id,
                        ':created_at' => $now
                    ]);
                } elseif ($role === 'vendor') {
                    // Insert into vendors table
                    $business_name = !empty($data->business_name) ? $data->business_name : "New Business";
                    $vendor_sql = "INSERT INTO vendors (id, business_name, description, business_category, created_at) 
                                 VALUES (:id, :business_name, :description, :business_category, :created_at)";
                    $database->executeQuery($vendor_sql, [
                        ':id' => $user_id,
                        ':business_name' => $business_name,
                        ':description' => $data->business_description ?? null,
                        ':business_category' => $data->business_category ?? null,
                        ':created_at' => $now
                    ]);

                    // Insert into vendor_contacts table
                    $contact_sql = "INSERT INTO vendor_contacts (id, first_name, middle_name, last_name, suffix, phone_number, email, position, created_at) 
                                  VALUES (:id, :first_name, :middle_name, :last_name, :suffix, :phone_number, :email, :position, :created_at)";
                    $database->executeQuery($contact_sql, [
                        ':id' => $user_id,
                        ':first_name' => $data->first_name,
                        ':middle_name' => $data->middle_name ?? null,
                        ':last_name' => $data->last_name,
                        ':suffix' => $data->suffix ?? null,
                        ':phone_number' => $data->phone ?? null,
                        ':email' => $data->email,
                        ':position' => !empty($data->position) ? $data->position : "Owner",
                        ':created_at' => $now
                    ]);

                    $response['vendor_id'] = $user_id;
                    $response['business_name'] = $business_name;
                }

                $db->commit();
            } catch (Exception $e) {
                $db->rollback();
                error_log("Secondary profile creation failed for user $user_id: " . $e->getMessage());
                throw $e;
            }

            echo json_encode($response);
        } else {
            $db->rollback();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Registration failed"]);
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}
