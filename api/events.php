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
        // Events that the vendor already joined
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
                    e.slots_taken,
                    ve.status AS vendor_status,
                    ve.event_receipt_url
                  FROM events e
                  INNER JOIN event_vendors ve 
                          ON e.id = ve.event_id
                  WHERE ve.vendor_id = :vendor_id
                  ORDER BY e.schedule_start ASC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":vendor_id", $vendorId);
    } elseif ($type === "random") {
        // ✅ Random 3 events only
        $query = "SELECT 
                    e.id,
                    e.created_by,
                    e.name,
                    e.description,
                    e.venue,
                    e.poster_url,
                    e.schedule_start,
                    e.schedule_end,
                    e.created_at
                  FROM events e
                  ORDER BY RANDOM()
                  LIMIT 3";
        $stmt = $db->prepare($query);
        $stmt->execute();

        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["success" => true, "events" => $events]);
        exit;
    } else {
        // Base SELECT (always these columns)
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
                    e.slots_taken";

        // Add vendor columns only if vendorId is passed
        if ($vendorId) {
            $query .= ",
                    ve.status AS vendor_status,
                    ve.event_receipt_url";
        }

        $query .= "
                FROM events e
                " . ($vendorId ? "LEFT JOIN event_vendors ve ON e.id = ve.event_id AND ve.vendor_id = :vendor_id " : "") . "
                WHERE 1=1 ";

        if ($type === "upcoming") {
            $query .= "AND e.schedule_start >= NOW() ";
        } elseif ($type === "past") {
            $query .= "AND e.schedule_end < NOW() ";
        }

        $query .= "ORDER BY e.schedule_start ASC";

        $stmt = $db->prepare($query);

        if ($vendorId) {
            $stmt->bindParam(":vendor_id", $vendorId);
        }
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
