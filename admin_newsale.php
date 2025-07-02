<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
include 'db_connect.php';
include 'update_inventory.php';
include 'log_batch_update.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}


$invoice_query = "SELECT MAX(sale_id) as last_id FROM sale_item";
$invoice_result = mysqli_query($conn, $invoice_query);
$invoice_data = mysqli_fetch_assoc($invoice_result);
$next_invoice = $invoice_data['last_id'] ? $invoice_data['last_id'] + 1 : 1;
$invoice_number = sprintf('INV-%03d', $next_invoice);


if(isset($_POST['complete_sale']) && isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $total_amount = $_POST['total_amount'];
    $cash_payment = $_POST['cash_payment'];
    $discount_type = isset($_POST['discount_type']) ? $_POST['discount_type'] : 'none';
    

    if ($cash_payment < $total_amount) {
        $error_message = "Cash payment is less than the total amount. Please enter a valid amount.";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Validate inventory before proceeding
            $inventory_error = false;
            $error_products = [];
            
            foreach($_SESSION['cart'] as $item) {
                // Check current inventory level
                $inventory_query = "SELECT quantity FROM inventory WHERE product_id = ?";
                $stmt = mysqli_prepare($conn, $inventory_query);
                mysqli_stmt_bind_param($stmt, "i", $item['id']);
                mysqli_stmt_execute($stmt);
                $inventory_result = mysqli_stmt_get_result($stmt);
                $inventory_data = mysqli_fetch_assoc($inventory_result);
                
                if (!$inventory_data || $inventory_data['quantity'] < $item['quantity']) {
                    $inventory_error = true;
                    
                    // Get product name for error message
                    $product_query = "SELECT product_name FROM product_list WHERE product_id = ?";
                    $stmt = mysqli_prepare($conn, $product_query);
                    mysqli_stmt_bind_param($stmt, "i", $item['id']);
                    mysqli_stmt_execute($stmt);
                    $product_result = mysqli_stmt_get_result($stmt);
                    $product_data = mysqli_fetch_assoc($product_result);
                    
                    $error_products[] = $product_data['product_name'] . " (Available: " . 
                                       ($inventory_data ? $inventory_data['quantity'] : 0) . 
                                       ", Requested: " . $item['quantity'] . ")";
                }
            }
            
            if ($inventory_error) {
                throw new Exception("Insufficient inventory for: " . implode(", ", $error_products));
            }
            
            // Insert into sale table
            $sale_query = "INSERT INTO sale (invoice_number, cash_payment, total_amount, discount_type, cashier_id, sale_date) 
                          VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($conn, $sale_query);
            mysqli_stmt_bind_param($stmt, "sddsi", $invoice_number, $cash_payment, $total_amount, $discount_type, $_SESSION["user_id"]);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to create sale record: " . mysqli_error($conn));
            }
            
            $sale_id = mysqli_insert_id($conn);
            
            // Insert each item into sale_item table and update inventory
            foreach($_SESSION['cart'] as $item) {
                $item_query = "INSERT INTO sale_item (sale_id, product_id, quantity, price, subtotal) 
                              VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $item_query);
                $subtotal = $item['quantity'] * $item['price'];
                mysqli_stmt_bind_param($stmt, "iiddd", $sale_id, $item['id'], $item['quantity'], $item['price'], $subtotal);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to add item to sale: " . mysqli_error($conn));
                }
                
                // Use the new function to update inventory and product information
                $update_success = updateInventoryAfterSale($conn, $item['id'], $item['quantity']);
                
                if (!$update_success) {
                    throw new Exception("Failed to update inventory for product ID: " . $item['id']);
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Store receipt data in session for printing
            $_SESSION['receipt_data'] = [
                'invoice_number' => $invoice_number,
                'sale_id' => $sale_id,
                'items' => $_SESSION['cart'],
                'subtotal' => calculateSubtotal($_SESSION['cart']),
                'discount_type' => $discount_type,
                'discount_amount' => calculateDiscount(calculateSubtotal($_SESSION['cart']), $discount_type),
                'total_amount' => $total_amount,
                'cash_payment' => $cash_payment,
                'change' => $cash_payment - $total_amount,
                'sale_date' => date('Y-m-d H:i:s')
            ];
            
            // Clear cart
            $_SESSION['cart'] = array(); // Change from unset to empty array
            
            // Redirect to receipt page
            header("Location: receipt.php?id=" . $sale_id);
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error_message = "Transaction failed: " . $e->getMessage();
        }
    }
}

// Helper function to calculate subtotal
function calculateSubtotal($cart) {
    $subtotal = 0;
    foreach($cart as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    return $subtotal;
}

// Helper function to calculate discount
function calculateDiscount($subtotal, $discount_type) {
    $discount_amount = 0;
    if($discount_type == 'pwd' || $discount_type == 'senior') {
        $discount_amount = $subtotal * 0.20; // 20% discount
    }
    return $discount_amount;
}

// Clear cart
if(isset($_GET['clear_cart'])) {
    $_SESSION['cart'] = array(); // Re-initialize cart as empty array
    header("Location: admin_newsale.php"); // Redirect to refresh state
    exit();
}

// Always initialize cart as an array if it doesn't exist or is not an array
if(!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Add product to cart
if(isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    if(!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = array();
    }
    $product_query = "SELECT p.*, i.quantity as inventory_quantity,
                     (SELECT batch_id FROM product_batches 
                      WHERE product_id = p.product_id AND quantity > 0 
                      ORDER BY expiry_date ASC LIMIT 1) as active_batch_id
                     FROM product_list p 
                     LEFT JOIN inventory i ON p.product_id = i.product_id 
                     WHERE p.product_id = ?";
    $stmt = mysqli_prepare($conn, $product_query);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    if($product) {
        $available_quantity = isset($product['inventory_quantity']) ? $product['inventory_quantity'] : 0;
        if($available_quantity >= $quantity) {
            $found = false;
            foreach($_SESSION['cart'] as $key => $item) {
                if((int)$item['id'] === $product_id) {
                    $_SESSION['cart'][$key]['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                $_SESSION['cart'][] = array(
                    'id' => $product_id,
                    'name' => $product['product_name'],
                    'price' => $product['selling_price'],
                    'quantity' => $quantity,
                    'measure' => $product['prod_measure'],
                    'batch_id' => $product['active_batch_id']
                );
            }
            $success_message = "Product added to cart.";
            header("Location: admin_newsale.php");
            exit();
        } else {
            $error_message = "Insufficient stock. Available: " . $available_quantity;
        }
    }
}

// Remove item from cart using POST for robust removal
if(isset($_POST['remove_from_cart']) && isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $remove_id = (int)$_POST['remove_from_cart'];
    $_SESSION['cart'] = array_values(array_filter(
        $_SESSION['cart'],
        function($item) use ($remove_id) {
            return (int)$item['id'] !== $remove_id;
        }
    ));

}

// Search products
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$products = array();

if(!empty($search_term)) {
    $search_query = "SELECT p.*, i.quantity as inventory_quantity,
                    (SELECT batch_id FROM product_batches 
                     WHERE product_id = p.product_id AND quantity > 0 
                     ORDER BY expiry_date ASC LIMIT 1) as active_batch_id
                    FROM product_list p 
                    LEFT JOIN inventory i ON p.product_id = i.product_id 
                    WHERE p.product_name LIKE ? AND i.quantity > 0";
    $stmt = mysqli_prepare($conn, $search_query);
    $search_param = "%" . $search_term . "%";
    mysqli_stmt_bind_param($stmt, "s", $search_param);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    // Get all products if no search term
    $search_query = "SELECT p.*, i.quantity as inventory_quantity,
                    (SELECT batch_id FROM product_batches 
                     WHERE product_id = p.product_id AND quantity > 0 
                     ORDER BY expiry_date ASC LIMIT 1) as active_batch_id
                    FROM product_list p 
                    LEFT JOIN inventory i ON p.product_id = i.product_id 
                    WHERE i.quantity > 0";
    $stmt = mysqli_prepare($conn, $search_query);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

while($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale - Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Sale page specific styles */
        .sale-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }

        .search-section, .cart-section {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .search-section h3, .cart-section h3 {
            margin-bottom: 20px;
            color: var(--text-color);
            font-size: 18px;
            font-weight: 600;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .search-btn {
            background-color: rgb(0, 183, 255);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background-color: #0075fc;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .product-image {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            background-color: white;
        }

        .product-image img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }

        .product-details {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }


        .product-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--text-color);
            line-height: 1.3;
            height: 42px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
        }

        .product-price {
            font-weight: 700;
            font-size: 18px;
            color: #0075fc;
            margin-bottom: 5px;
        }

        .product-stock {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .product-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: auto;
        }

        .quantity-input {
            width: 60px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }

        .add-btn {
            flex: 1;
            background-color: rgb(0, 183, 255);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 15px;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .add-btn:hover {
            background-color: #0075fc;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .cart-table th, .cart-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .cart-table th {
            font-weight: 600;
            color: #777;
            font-size: 14px;
        }

        .cart-table .product-measure {
            font-size: 12px;
            color: #777;
        }

        .remove-btn {
            color: #dc3545;
            background: none;
            border: none;
            border-radius: 0;
            width: 24px;
            height: 24px;
            font-size: 20px;
            font-weight: bold;
            line-height: 24px;
            text-align: center;
            cursor: pointer;
            transition: color 0.2s;
            margin-left: 8px;
            padding: 0;
            box-shadow: none;
        }

        .remove-btn:hover, .remove-btn:focus {
            color: #c82333;
            background: none;
            outline: none;
            box-shadow: none;
        }

        .cart-table td:last-child {
            white-space: nowrap;
            text-align: right;
            font-weight: 600;
            font-size: 16px;
            color: #0075fc;
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-summary {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #ddd;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .summary-row.total {
            font-size: 18px;
            font-weight: 600;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .checkout-form {
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #777;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .checkout-btn {
            width: 100%;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 12px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .checkout-btn:hover {
            background-color: #218838;
        }

        .clear-cart-btn {
            display: block;
            text-align: center;
            color: #dc3545;
            text-decoration: none;
            padding: 8px;
            transition: all 0.3s ease;
        }

        .clear-cart-btn:hover {
            color: #c82333;
        }

        .empty-cart {
            text-align: center;
            padding: 30px 0;
            color: #777;
        }

        .empty-cart i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        .invoice-number {
            font-size: 14px;
            color: #777;
            font-weight: normal;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .batch-indicator {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 5px;
            background-color: #e3f2fd;
            color: #0d47a1;
        }

        .product-name-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
        }

        @media (max-width: 992px) {
            .sale-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .product-image {
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="logo-container">
                <div class="logo" id="logoContainer">
                    <img src="logo/log.png" alt="Logo" id="logoImage">
                </div>
                <button id="toggle-sidebar" class="toggle-sidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <ul class="menu">
              <hr>
              <br>
              <li data-title="Dashboard"><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
              <li data-title="Users"><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
              <li data-title="Inventory"><a href="inventory.php"><i class="fas fa-boxes"></i> <span>Inventory</span></a></li>
              <li class="active" data-title="Sales"><a href="admin_newsale.php"><i class="fas fa-shopping-cart"></i> <span>New Sales</span></a></li>
              <li data-title="Sales History"><a href="admin_sales-history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
              <li data-title="Product"><a href="product_category.php"><i class="fas fa-tags"></i> <span>Product Category</span></a></li>
              <li data-title="Product"><a href="product_dashboard.php"><i class="fas fa-pills"></i> <span>Product</span></a></li>
              <li data-title="Receive"><a href="receive_product.php"><i class="fas fa-truck-loading"></i> <span>Receive Product</span></a></li>
              <li data-title="Batches"><a href="batch_management.php"><i class="fas fa-layer-group"></i> <span>Batch Management</span></a></li>              
              <li data-title="Suppliers"><a href="suppliers.php"><i class="fas fa-truck"></i> <span>Suppliers</span></a></li>
              <li data-title="Settings"><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            </ul>
            
            <div class="logout">
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> 
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <div class="main-content" id="main-content">
            <div class="header">
                <div class="page-title">
                    <h2>New Sale</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <?php if(isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="sale-container">
                <div class="search-section">
                    <h3>Search Products</h3>
                    
                    <form action="" method="GET" class="search-form">
                        <input type="text" name="search" class="search-input" placeholder="Search by product name..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                    </form>
                    
                    <div class="product-grid">
                        <?php if(empty($products)): ?>
                            <p>No products found. Try a different search term.</p>
                        <?php else: ?>
                            <?php foreach($products as $product): 
                                // Get the active batch information
                                $batch_query = "SELECT * FROM product_batches 
                                               WHERE batch_id = ? AND quantity > 0";
                                $batch_stmt = mysqli_prepare($conn, $batch_query);
                                mysqli_stmt_bind_param($batch_stmt, "i", $product['active_batch_id']);
                                mysqli_stmt_execute($batch_stmt);
                                $batch_result = mysqli_stmt_get_result($batch_stmt);
                                $active_batch = mysqli_fetch_assoc($batch_result);
                                
                                // Get expiry date
                                $expiry_date = $active_batch ? $active_batch['expiry_date'] : $product['prod_expiry'];
                                
                                // Calculate days until expiry
                                $today = new DateTime();
                                $expiry = new DateTime($expiry_date);
                                $interval = $today->diff($expiry);
                                $days_until_expiry = $interval->days;
                                $is_expired = $today > $expiry;
                            ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <?php if(!empty($product['image_path']) && file_exists($product['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                        <?php else: ?>
                                            <img src="placeholder.jpg" alt="Product Image">
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-details">
                                        <h4 class="product-name">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                            <?php if($is_expired): ?>
                                                <span class="batch-indicator" style="background-color: #f8d7da; color: #721c24;">Expired</span>
                                            <?php elseif($days_until_expiry < 30): ?>
                                                <span class="batch-indicator" style="background-color: #fff3cd; color: #856404;">Expiring Soon</span>
                                            <?php endif; ?>
                                        </h4>
                                        <div class="product-price">₱<?php echo number_format($product['selling_price'], 2); ?></div>
                                        <div class="product-stock">
                                            Stock: <?php echo $product['inventory_quantity']; ?>
                                            <span style="font-size: 12px; color: #666;">
                                                (Expires: <?php echo date('Y-m-d', strtotime($expiry_date)); ?>)
                                            </span>
                                        </div>
                                        
                                        <form action="" method="POST" class="product-actions">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <input type="number" name="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['inventory_quantity']; ?>" required>
                                            <button type="submit" name="add_to_cart" class="add-btn" <?php echo $is_expired ? 'disabled style="background-color: #ccc;"' : ''; ?>>
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="cart-section">
                    <h3>Shopping Cart <span class="invoice-number">(<?php echo $invoice_number; ?>)</span></h3>
                    
                    <?php if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $subtotal = 0;
                                foreach($_SESSION['cart'] as $item): 
                                    $item_total = $item['price'] * $item['quantity'];
                                    $subtotal += $item_total;
                                ?>
                                <tr>
                                    <td>
                                        <div class="product-name-container">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                            <form action="" method="POST" style="display:inline;">
                                                <input type="hidden" name="remove_from_cart" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="remove-btn" title="Remove item">×</button>
                                            </form>
                                        </div>
                                        <div class="product-measure"><?php echo htmlspecialchars($item['measure']); ?></div>
                                    </td>
                                    <div class="product-detail">
                                        <td style="font-size:13px; max-width:70px; white-space:nowrap; text-align:right;">₱<?php echo number_format($item['price'], 2); ?></td>
                                        <td style="font-size:13px;"><?php echo $item['quantity']; ?></td>
                                        <td style="font-size:13px;max-width:90px; white-space:nowrap; text-align:right;">₱<?php echo number_format($item_total, 2); ?></td>
                                    </div>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php
                        // Calculate discount
                        $discount_rate = 0;
                        $discount_amount = 0;
                        $discount_type = isset($_POST['discount_type']) ? $_POST['discount_type'] : 'none';
                        
                        if($discount_type == 'pwd') {
                            $discount_rate = 0.20; // 20% discount for PWD
                            $discount_amount = $subtotal * $discount_rate;
                        } elseif($discount_type == 'senior') {
                            $discount_rate = 0.20; // 20% discount for Senior Citizens
                            $discount_amount = $subtotal * $discount_rate;
                        }
                        
                        $total = $subtotal - $discount_amount;
                        ?>
                        
                        <div class="cart-summary">
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span>₱<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Discount:</span>
                                <span>₱<?php echo number_format($discount_amount, 2); ?></span>
                            </div>
                            <div class="summary-row total">
                                <span>Total:</span>
                                <span>₱<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                        
                        <form action="" method="POST" class="checkout-form" id="checkout-form">
                            <div class="form-group">
                                <label for="cash_payment">Cash Payment</label>
                                <input type="number" step="0.01" name="cash_payment" id="cash_payment" class="form-control" placeholder="Enter cash amount" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="change">Change</label>
                                <input type="text" id="change" class="form-control" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label for="discount_type">Discount Type</label>
                                <select name="discount_type" id="discount_type" class="form-control">
                                    <option value="none">No Discount</option>
                                    <option value="pwd">PWD (20%)</option>
                                    <option value="senior">Senior Citizen (20%)</option>
                                </select>
                            </div>
                            
                            <input type="hidden" name="total_amount" value="<?php echo $total; ?>">
                            <button type="submit" name="complete_sale" class="checkout-btn" id="complete-sale-btn"><i class="fas fa-check-circle"></i> Complete Sale</button>
                        </form>
                        
                        <a href="?clear_cart=1" class="clear-cart-btn"><i class="fas fa-trash"></i> Clear Cart</a>
                    <?php else: ?>
                        <div class="empty-cart">
                            <i class="fas fa-shopping-cart"></i>
                            <p>Your cart is empty.</p>
                            <p>Search for products to add to your cart.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toggleBtn = document.getElementById("toggle-sidebar");
            const sidebar = document.getElementById("sidebar");
            const mainContent = document.getElementById("main-content");
            
            // Check if there's a saved state in localStorage
            const isCollapsed = localStorage.getItem("sidebarCollapsed") === "true";
            
            // Apply saved state on page load
            if (isCollapsed) {
                sidebar.classList.add("collapsed");
                mainContent.classList.add("expanded");
            }
            
            // Toggle sidebar when button is clicked
            toggleBtn.addEventListener("click", function() {
                sidebar.classList.toggle("collapsed");
                mainContent.classList.toggle("expanded");
                
                // Save state to localStorage
                localStorage.setItem("sidebarCollapsed", sidebar.classList.contains("collapsed"));
            });
            
            // Add tooltips for mobile view
            const menuItems = document.querySelectorAll('.menu li');
            menuItems.forEach(item => {
                const link = item.querySelector('a');
                const text = link.querySelector('span').textContent.trim();
                item.setAttribute('data-title', text);
            });
            
            // Update total when discount type changes
            const discountSelect = document.getElementById('discount_type');
            if(discountSelect) {
                discountSelect.addEventListener('change', function() {
                    const subtotal = <?php echo isset($subtotal) ? $subtotal : 0; ?>;
                    let discount = 0;
                    
                    if(this.value === 'pwd' || this.value === 'senior') {
                        discount = subtotal * 0.20; // 20% discount
                    }
                    
                    const total = subtotal - discount;
                    
                    document.querySelector('.summary-row:nth-child(2) span:last-child').textContent = '₱' + discount.toFixed(2);
                    document.querySelector('.summary-row.total span:last-child').textContent = '₱' + total.toFixed(2);
                    document.querySelector('input[name="total_amount"]').value = total;
                    
                    // Recalculate change if cash payment is entered
                    calculateChange();
                });
            }
            
            // Calculate change when cash payment is entered
            const cashPaymentInput = document.getElementById('cash_payment');
            const changeDisplay = document.getElementById('change');
            const checkoutForm = document.getElementById('checkout-form');
            const completeSaleBtn = document.getElementById('complete-sale-btn');
            
            function calculateChange() {
                if(cashPaymentInput && changeDisplay) {
                    const cashAmount = parseFloat(cashPaymentInput.value) || 0;
                    const totalAmount = <?php echo isset($total) ? $total : 0; ?>;
                    
                    if(cashAmount >= totalAmount) {
                        const change = cashAmount - totalAmount;
                        changeDisplay.value = '₱' + change.toFixed(2);
                        changeDisplay.style.color = '#28a745';
                        completeSaleBtn.disabled = false;
                    } else {
                        changeDisplay.value = 'Insufficient amount';
                        changeDisplay.style.color = '#dc3545';
                        completeSaleBtn.disabled = true;
                    }
                }
            }
            
            if(cashPaymentInput) {
                cashPaymentInput.addEventListener('input', calculateChange);
            }
            
            // Form validation before submission
            if(checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    const cashAmount = parseFloat(cashPaymentInput.value) || 0;
                    const totalAmount = <?php echo isset($total) ? $total : 0; ?>;
                    
                    if(cashAmount < totalAmount) {
                        e.preventDefault();
                        alert('Cash payment must be greater than or equal to the total amount.');
                        return false;
                    }
                    
                    // Check for any error messages displayed
                    const errorAlert = document.querySelector('.alert-danger');
                    if(errorAlert && errorAlert.style.display !== 'none') {
                        e.preventDefault();
                        alert('Please resolve the errors before completing the sale.');
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            if(alerts.length > 0) {
                setTimeout(function() {
                    alerts.forEach(function(alert) {
                        alert.style.transition = 'opacity 1s';
                        alert.style.opacity = '0';
                        setTimeout(function() {
                            alert.style.display = 'none';
                        }, 1000);
                    });
                }, 5000);
            }

            // Refresh page after clearing cart
            const clearCartBtn = document.querySelector('.clear-cart-btn');
            if(clearCartBtn) {
                clearCartBtn.addEventListener('click', function(e) {
                    // Add a small delay to ensure the server processes the clear request
                    setTimeout(function() {
                        window.location.reload();
                    }, 100);
                });
            }
        });
    </script>
</body>
</html>