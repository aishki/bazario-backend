<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db_connect.php';
$database = new Database();
$db = $database->getConnection();

$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$vendorId = isset($_GET['vendor_id']) ? $_GET['vendor_id'] : null;

try {
    if ($type === "joined" && $vendorId) {
        // Use event_vendors join for joined tab
        $query = "SELECT 
                    e.id,
                    e.name,
                    e.venue,
                    e.schedule_start,
                    e.schedule_end,
                    e.max_slots,
                    e.slots_taken
                  FROM events e
                  INNER JOIN event_vendors ve ON e.id = ve.event_id
                  WHERE ve.vendor_id = :vendor_id
                  ORDER BY e.schedule_start ASC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":vendor_id", $vendorId);
    } else {
        // Use events table only for upcoming/past/all
        $query = "SELECT 
                    e.id,
                    e.created_by,
                    e.name,
                    e.description,
                    e.venue,
                    e.poster_url,
                    e.schedule_start,
                    e.schedule_end,
                    e.created_at,
                    e.requirements,
                    e.booth_fee,
                    e.max_slots,
                    e.slots_taken
                FROM events e
                WHERE 1=1 ";


        if ($type === "upcoming") {
            $query .= "AND e.schedule_start >= NOW() ";
        } elseif ($type === "past") {
            $query .= "AND e.schedule_end < NOW() ";
        }

        $query .= "ORDER BY e.schedule_start ASC";
        $stmt = $db->prepare($query);
    }

    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "events" => $events
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
