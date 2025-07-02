<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $query = "SELECT * FROM category_list WHERE category_id = $id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $category = mysqli_fetch_assoc($result);
        header('Content-Type: application/json');
        echo json_encode($category);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Category not found']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid ID']);
}
?>