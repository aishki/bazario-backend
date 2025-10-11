<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db_connect.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $category = isset($_GET['category']) ? $_GET['category'] : 'all';

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
