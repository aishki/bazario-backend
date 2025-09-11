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
                        v.id, v.business_name, v.description, v.logo_url, 
                        v.social_links, v.verified, v.business_category, v.created_at,
                        vc.first_name, vc.middle_name, vc.last_name, vc.suffix,
                        vc.phone_number, vc.email as contact_email, vc.position
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

                echo json_encode(array(
                    "success" => true,
                    "vendor" => $vendor
                ));
            } else {
                echo json_encode(array(
                    "success" => false,
                    "message" => "Vendor not found"
                ));
            }
        } else {
            // Get all vendors (for listing)
            $query = "SELECT v.id, v.business_name, v.description, v.logo_url, 
                             v.verified, v.business_category, v.created_at
                      FROM vendors v 
                      ORDER BY v.created_at DESC";

            $stmt = $db->prepare($query);
            $stmt->execute();

            $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(array(
                "success" => true,
                "vendors" => $vendors
            ));
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));

        if (isset($data->vendor_id)) {
            try {
                $db->beginTransaction();

                // Update vendors table
                $query = "UPDATE vendors SET 
                            business_name = :business_name,
                            description = :description,
                            logo_url = :logo_url,
                            social_links = :social_links,
                            business_category = :business_category
                          WHERE id = :vendor_id";

                $stmt = $db->prepare($query);
                $stmt->bindParam(':vendor_id', $data->vendor_id);
                $stmt->bindParam(':business_name', $data->business_name);
                $stmt->bindParam(':description', $data->description);
                $stmt->bindParam(':logo_url', $data->logo_url);
                $stmt->bindParam(':social_links', json_encode($data->social_links));
                $stmt->bindParam(':business_category', $data->business_category);

                $stmt->execute();

                // Update vendor_contacts table if contact info provided
                if (isset($data->contact_info)) {
                    $contact_query = "UPDATE vendor_contacts SET 
                                        first_name = :first_name,
                                        middle_name = :middle_name,
                                        last_name = :last_name,
                                        suffix = :suffix,
                                        phone_number = :phone_number,
                                        email = :email,
                                        position = :position
                                      WHERE id = :vendor_id";

                    $contact_stmt = $db->prepare($contact_query);
                    $contact_stmt->bindParam(':vendor_id', $data->vendor_id);
                    $contact_stmt->bindParam(':first_name', $data->contact_info->first_name);
                    $contact_stmt->bindParam(':middle_name', $data->contact_info->middle_name);
                    $contact_stmt->bindParam(':last_name', $data->contact_info->last_name);
                    $contact_stmt->bindParam(':suffix', $data->contact_info->suffix);
                    $contact_stmt->bindParam(':phone_number', $data->contact_info->phone_number);
                    $contact_stmt->bindParam(':email', $data->contact_info->email);
                    $contact_stmt->bindParam(':position', $data->contact_info->position);

                    $contact_stmt->execute();
                }

                $db->commit();

                echo json_encode(array(
                    "success" => true,
                    "message" => "Vendor information updated successfully"
                ));
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(array(
                    "success" => false,
                    "message" => "Error updating vendor: " . $e->getMessage()
                ));
            }
        } else {
            echo json_encode(array(
                "success" => false,
                "message" => "Vendor ID is required"
            ));
        }
        break;

    case 'POST':
        echo json_encode(array("message" => "Vendor creation endpoint - to be implemented"));
        break;

    default:
        echo json_encode(array("message" => "Method not allowed"));
        break;
}
