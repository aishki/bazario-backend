<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include '../config/db_connect.php'; // adjust path

$category = isset($_GET['category']) ? $_GET['category'] : 'all';

try {
    if ($category === 'all') {
        $query = "SELECT * FROM products ORDER BY created_at DESC";
        $stmt = $pdo->prepare($query);
    } else {
        $query = "SELECT * FROM products WHERE category = :category ORDER BY created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':category', $category);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($products);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
