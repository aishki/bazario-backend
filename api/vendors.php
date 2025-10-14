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

            // Get complete vendor information including contacts and social links
            $query = "SELECT 
                        v.id AS vendor_id,
                        v.business_name,
                        v.description,
                        v.logo_url,
                        v.social_links,
                        v.verified,
                        v.business_category,
                        v.created_at,
                        vc.first_name,
                        vc.middle_name,
                        vc.last_name,
                        vc.suffix,
                        vc.phone_number,
                        vc.email AS contact_email,
                        vc.position
                    FROM vendors v
                    LEFT JOIN vendor_contacts vc ON v.id = vc.id
                    WHERE v.id = :vendor_id";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':vendor_id', $vendor_id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

                // Parse social_links JSON if it exists
                if ($vendor['social_links']) {
                    $vendor['social_links'] = json_decode($vendor['social_links'], true);
                }

                // Nest contact info
                $vendor['contact'] = [
                    "first_name"   => $vendor['first_name'],
                    "last_name"    => $vendor['last_name'],
                    "suffix"       => $vendor['suffix'],
                    "phone_number" => $vendor['phone_number'],
                    "email"        => $vendor['contact_email'],
                    "position"     => $vendor['position'],
                ];

                // Remove flat contact fields
                unset(
                    $vendor['first_name'],
                    $vendor['last_name'],
                    $vendor['suffix'],
                    $vendor['phone_number'],
                    $vendor['contact_email'],
                    $vendor['position']
                );

                echo json_encode([
                    "success" => true,
                    "vendor"  => $vendor
                ]);
            }
        } else {
            // ✅ Unified: Get all vendors with full contact info
            $query = "SELECT 
                        v.id AS vendor_id, 
                        v.business_name, 
                        v.description, 
                        v.logo_url, 
                        v.social_links,
                        v.verified, 
                        v.business_category, 
                        v.created_at,
                        vc.first_name,
                        vc.middle_name,
                        vc.last_name,
                        vc.suffix,
                        vc.phone_number,
                        vc.email AS contact_email,
                        vc.position
                      FROM vendors v
                      LEFT JOIN vendor_contacts vc ON v.id = vc.id
                      ORDER BY v.created_at DESC";

            $stmt = $db->prepare($query);
            $stmt->execute();

            $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format each vendor with nested contact & parsed social_links
            foreach ($vendors as &$vendor) {
                if ($vendor['social_links']) {
                    $vendor['social_links'] = json_decode($vendor['social_links'], true);
                }

                $vendor['contact'] = [
                    "first_name"   => $vendor['first_name'],
                    "last_name"    => $vendor['last_name'],
                    "suffix"       => $vendor['suffix'],
                    "phone_number" => $vendor['phone_number'],
                    "email"        => $vendor['contact_email'],
                    "position"     => $vendor['position'],
                ];

                unset(
                    $vendor['first_name'],
                    $vendor['last_name'],
                    $vendor['suffix'],
                    $vendor['phone_number'],
                    $vendor['contact_email'],
                    $vendor['position']
                );
            }

            echo json_encode([
                "success" => true,
                "vendors" => $vendors
            ]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));

        if (isset($data->vendor_id)) {
            try {
                $db->beginTransaction();

                // Fetch existing vendor first
                $check_query = "SELECT * FROM vendors WHERE id = :vendor_id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':vendor_id', $data->vendor_id);
                $check_stmt->execute();
                $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    echo json_encode([
                        "success" => false,
                        "message" => "Vendor not found"
                    ]);
                    exit;
                }

                // Build dynamic update query for vendors
                $update_fields = [];
                $params = [":vendor_id" => $data->vendor_id];

                if (isset($data->business_name)) {
                    $update_fields[] = "business_name = :business_name";
                    $params[":business_name"] = $data->business_name;
                }
                if (isset($data->description)) {
                    $update_fields[] = "description = :description";
                    $params[":description"] = $data->description;
                }
                if (isset($data->logo_url)) {
                    $update_fields[] = "logo_url = :logo_url";
                    $params[":logo_url"] = $data->logo_url;
                }
                if (isset($data->social_links)) {
                    $update_fields[] = "social_links = :social_links";
                    $params[":social_links"] = json_encode($data->social_links);
                }
                if (isset($data->business_category)) {
                    $update_fields[] = "business_category = :business_category";
                    $params[":business_category"] = $data->business_category;
                }

                if (!empty($update_fields)) {
                    $query = "UPDATE vendors SET " . implode(", ", $update_fields) . " WHERE id = :vendor_id";
                    $stmt = $db->prepare($query);
                    $stmt->execute($params);
                }

                // Handle vendor_contacts - support both 'contact', 'contact_info', and 'phone_number' formats
                if (isset($data->contact) || isset($data->contact_info) || isset($data->phone_number)) {
                    // Check if contact record exists
                    $check_query = "SELECT id FROM vendor_contacts WHERE id = :vendor_id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':vendor_id', $data->vendor_id);
                    $check_stmt->execute();

                    $contact_data = null;
                    if (isset($data->contact)) {
                        $contact_data = $data->contact;
                    } elseif (isset($data->contact_info)) {
                        $contact_data = $data->contact_info;
                    }

                    if ($check_stmt->rowCount() > 0) {
                        $contact_query = "UPDATE vendor_contacts SET 
                                            first_name = :first_name,
                                            middle_name = :middle_name,
                                            last_name = :last_name,
                                            suffix = :suffix,
                                            phone_number = :phone_number,
                                            email = :email,
                                            position = :position
                                          WHERE id = :vendor_id";
                    } else {
                        $contact_query = "INSERT INTO vendor_contacts 
                                            (id, first_name, middle_name, last_name, suffix, phone_number, email, position, created_at) 
                                          VALUES 
                                            (:vendor_id, :first_name, :middle_name, :last_name, :suffix, :phone_number, :email, :position, NOW())";
                    }

                    $contact_stmt = $db->prepare($contact_query);
                    $contact_stmt->bindParam(':vendor_id', $data->vendor_id);

                    if ($contact_data) {
                        $first_name = $contact_data->first_name ?? null;
                        $middle_name = $contact_data->middle_name ?? null;
                        $last_name = $contact_data->last_name ?? null;
                        $suffix = $contact_data->suffix ?? null;
                        $phone_number = $contact_data->phone_number ?? null;
                        $email = $contact_data->email ?? null;
                        $position = $contact_data->position ?? null;

                        $contact_stmt->bindParam(':first_name', $first_name);
                        $contact_stmt->bindParam(':middle_name', $middle_name);
                        $contact_stmt->bindParam(':last_name', $last_name);
                        $contact_stmt->bindParam(':suffix', $suffix);
                        $contact_stmt->bindParam(':phone_number', $phone_number);
                        $contact_stmt->bindParam(':email', $email);
                        $contact_stmt->bindParam(':position', $position);
                    } else {
                        $phone_number = $data->phone_number ?? null;
                        $null = null;
                        $contact_stmt->bindParam(':first_name', $null);
                        $contact_stmt->bindParam(':middle_name', $null);
                        $contact_stmt->bindParam(':last_name', $null);
                        $contact_stmt->bindParam(':suffix', $null);
                        $contact_stmt->bindParam(':phone_number', $phone_number);
                        $contact_stmt->bindParam(':email', $null);
                        $contact_stmt->bindParam(':position', $null);
                    }

                    $contact_stmt->execute();
                }

                $db->commit();

                echo json_encode([
                    "success" => true,
                    "message" => "Vendor information updated successfully"
                ]);
            } catch (Exception $e) {
                $db->rollback();
                error_log("Vendor update error: " . $e->getMessage());
                error_log("Vendor update data: " . json_encode($data));
                echo json_encode([
                    "success" => false,
                    "message" => "Error updating vendor: " . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Vendor ID is required"
            ]);
        }
        break;

    case 'POST':
        // echo json_encode(array("message" => "Vendor creation endpoint - to be implemented"));
        break;

    default:
        echo json_encode(["message" => "Method not allowed"]);
        break;
}
