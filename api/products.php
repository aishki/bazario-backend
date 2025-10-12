<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db_connect.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $category = isset($_GET['category']) ? $_GET['category'] : 'all';

    // Build the base query with JOINs
    $baseQuery = "
    SELECT 
        p.id,
        p.business_partner_id,
        p.added_by,
        p.name,
        p.description,
        p.price,
        p.image_url,
        p.stock,
        p.created_at,
        p.category,
        v.business_name AS business_partner_name,
        v.logo_url AS business_logo
    FROM products p
    LEFT JOIN business_partners bp ON p.business_partner_id = bp.id
    LEFT JOIN vendors v ON bp.vendor_id = v.id
    ";

    // If 'all', fetch all; otherwise filter by category
    if ($category === 'all') {
        $query = $baseQuery . " ORDER BY p.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    } else {
        $query = $baseQuery . " WHERE p.category = :category ORDER BY p.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':category', $category);
        $stmt->execute();
    }

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "products" => $products
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
