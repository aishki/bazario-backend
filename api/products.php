<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include '../config/db_connect.php';

$category = isset($_GET['category']) ? $_GET['category'] : 'all';

try {
    // ✅ Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();

    if ($category === 'all') {
        $query = "SELECT * FROM products ORDER BY created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    } else {
        $query = "SELECT * FROM products WHERE category = :category ORDER BY created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':category', $category);
        $stmt->execute();
    }

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($products);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
