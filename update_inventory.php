<?php
/**
 * Update inventory and product information after a sale
 * 
 * @param mysqli $conn Database connection
 * @param int $product_id Product ID
 * @param int $quantity_sold Quantity sold
 * @return bool Success status
 */
function updateInventoryAfterSale($conn, $product_id, $quantity_sold) {
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {

        $batch_query = "SELECT * FROM product_batches 
                        WHERE product_id = ? AND quantity > 0 
                        ORDER BY expiry_date ASC";
        $batch_stmt = mysqli_prepare($conn, $batch_query);
        mysqli_stmt_bind_param($batch_stmt, "i", $product_id);
        mysqli_stmt_execute($batch_stmt);
        $batch_result = mysqli_stmt_get_result($batch_stmt);
        
        $batches = [];
        while ($row = mysqli_fetch_assoc($batch_result)) {
            $batches[] = $row;
        }
        
        if (empty($batches)) {

            mysqli_rollback($conn);
            return false;
        }
        

        $current_batch = $batches[0];
        $remaining_quantity = $quantity_sold;
        $current_batch_depleted = false;
        

        foreach ($batches as $batch) {
            if ($remaining_quantity <= 0) {
                break;
            }
            
            $batch_id = $batch['batch_ID'];
            $batch_quantity = $batch['quantity'];
            
            if ($batch_quantity <= $remaining_quantity) {

                $new_quantity = 0;
                $remaining_quantity -= $batch_quantity;
                
                if ($batch_id == $current_batch['batch_id']) {
                    $current_batch_depleted = true;
                }
            } else {

                $new_quantity = $batch_quantity - $remaining_quantity;
                $remaining_quantity = 0;
            }

            $update_batch_query = "UPDATE product_batches SET quantity = ? WHERE batch_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_batch_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $new_quantity, $batch_id);
            mysqli_stmt_execute($update_stmt);
        }
        

        $update_inventory_query = "UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_inventory_query);
        mysqli_stmt_bind_param($update_stmt, "ii", $quantity_sold, $product_id);
        mysqli_stmt_execute($update_stmt);
        
    
        if ($current_batch_depleted) {

            $next_batch_query = "SELECT * FROM product_batches 
                                WHERE product_id = ? AND quantity > 0 
                                ORDER BY expiry_date ASC LIMIT 1";
            $next_batch_stmt = mysqli_prepare($conn, $next_batch_query);
            mysqli_stmt_bind_param($next_batch_stmt, "i", $product_id);
            mysqli_stmt_execute($next_batch_stmt);
            $next_batch_result = mysqli_stmt_get_result($next_batch_stmt);
            $next_batch = mysqli_fetch_assoc($next_batch_result);
            
            if ($next_batch) {

                logBatchUpdate(
                    $conn, 
                    $product_id, 
                    $current_batch['batch_id'], 
                    $next_batch['batch_id'], 
                    $current_batch['selling_price'], 
                    $next_batch['selling_price'], 
                    $current_batch['expiry_date'], 
                    $next_batch['expiry_date']
                );
                

                $update_product_query = "UPDATE product_list 
                                        SET unit_price = ?, selling_price = ?, prod_expiry = ? 
                                        WHERE product_id = ?";
                $update_product_stmt = mysqli_prepare($conn, $update_product_query);
                mysqli_stmt_bind_param(
                    $update_product_stmt, 
                    "ddsi", 
                    $next_batch['unit_price'], 
                    $next_batch['selling_price'], 
                    $next_batch['expiry_date'], 
                    $product_id
                );
                mysqli_stmt_execute($update_product_stmt);
            }
        }
        

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {

        mysqli_rollback($conn);
        error_log("Error updating inventory: " . $e->getMessage());
        return false;
    }
}
?>