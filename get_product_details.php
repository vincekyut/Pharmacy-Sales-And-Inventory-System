<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit();
}

$product_id = mysqli_real_escape_string($conn, $_GET['id']);


$query = "SELECT p.*, pb.unit_price as last_unit_price, pb.selling_price as last_selling_price 
          FROM product_list p 
          LEFT JOIN product_batches pb ON p.product_id = pb.product_id 
          WHERE p.product_id = ? 
          ORDER BY pb.date_added DESC 
          LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    $product = mysqli_fetch_assoc($result);
    
   
    $unit_price = $product['last_unit_price'] ?? $product['unit_price'];
    $selling_price = $product['last_selling_price'] ?? $product['selling_price'];
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'product_name' => $product['product_name'],
        'unit_price' => $unit_price,
        'selling_price' => $selling_price,
        'measure' => $product['prod_measure']
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Product not found']);
}
?>
