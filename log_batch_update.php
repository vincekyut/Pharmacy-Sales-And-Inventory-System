<?php
/**
 * Log batch depletion and product information update
 * 
 * @param mysqli $conn Database connection
 * @param int $product_id Product ID
 * @param int $old_batch_id Old batch ID
 * @param int $new_batch_id New batch ID
 * @param float $old_price Old selling price
 * @param float $new_price New selling price
 * @param string $old_expiry Old expiry date
 * @param string $new_expiry New expiry date
 */
function logBatchUpdate($conn, $product_id, $old_batch_id, $new_batch_id, $old_price, $new_price, $old_expiry, $new_expiry) {

    $product_query = "SELECT product_name FROM product_list WHERE product_id = ?";
    $stmt = mysqli_prepare($conn, $product_query);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    $product_name = $product ? $product['product_name'] : "Product #$product_id";

    $log_query = "INSERT INTO batch_update_logs 
                 (product_id, old_batch_id, new_batch_id, old_price, new_price, old_expiry, new_expiry, update_date) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $log_query);
    mysqli_stmt_bind_param($stmt, "iiiddss", $product_id, $old_batch_id, $new_batch_id, $old_price, $new_price, $old_expiry, $new_expiry);
    mysqli_stmt_execute($stmt);
    

    $notification_query = "INSERT INTO notifications 
                          (user_id, message, is_read, created_at) 
                          VALUES (?, ?, 0, NOW())";
    

    $admin_query = "SELECT user_id FROM users WHERE role = 'admin'";
    $admin_result = mysqli_query($conn, $admin_query);
    
    $message = "Batch #$old_batch_id for $product_name has been depleted. Price updated from ₱" . number_format($old_price, 2) . 
               " to ₱" . number_format($new_price, 2) . ". Expiry date changed from " . 
               date('Y-m-d', strtotime($old_expiry)) . " to " . date('Y-m-d', strtotime($new_expiry)) . ".";

    while ($admin = mysqli_fetch_assoc($admin_result)) {
        $stmt = mysqli_prepare($conn, $notification_query);
        mysqli_stmt_bind_param($stmt, "is", $admin['user_id'], $message);
        mysqli_stmt_execute($stmt);
    }
    

    error_log("BATCH UPDATE: $message");
}
?>