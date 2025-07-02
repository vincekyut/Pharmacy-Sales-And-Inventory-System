<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $unit_price = mysqli_real_escape_string($conn, $_POST['unit_price']);
    $selling_price = mysqli_real_escape_string($conn, $_POST['selling_price']);
    $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date']);
    $receive_date = mysqli_real_escape_string($conn, $_POST['receive_date']);
    
    $total_price = $quantity * $unit_price;
    
    mysqli_begin_transaction($conn);
    
    try {
        $batch_insert = "INSERT INTO product_batches (product_id, batch_number, quantity, unit_price, selling_price, expiry_date, supplier_id) 
                         VALUES (?, 0, ?, ?, ?, ?, ?)";
        $batch_insert_stmt = mysqli_prepare($conn, $batch_insert);
        mysqli_stmt_bind_param($batch_insert_stmt, "iddssi", $product_id, $quantity, $unit_price, $selling_price, $expiry_date, $supplier_id);
        mysqli_stmt_execute($batch_insert_stmt);
        $new_batch_id = mysqli_insert_id($conn);
        
        $get_batches = "SELECT batch_id FROM product_batches WHERE product_id = ? AND quantity > 0 ORDER BY expiry_date ASC";
        $get_batches_stmt = mysqli_prepare($conn, $get_batches);
        mysqli_stmt_bind_param($get_batches_stmt, "i", $product_id);
        mysqli_stmt_execute($get_batches_stmt);
        $batches_result = mysqli_stmt_get_result($get_batches_stmt);
        
        $batch_number = 1;
        $new_batch_number = 0;
        
        while ($batch = mysqli_fetch_assoc($batches_result)) {
            $update_batch = "UPDATE product_batches SET batch_number = ? WHERE batch_id = ?";
            $update_batch_stmt = mysqli_prepare($conn, $update_batch);
            mysqli_stmt_bind_param($update_batch_stmt, "ii", $batch_number, $batch['batch_id']);
            mysqli_stmt_execute($update_batch_stmt);
            
            if ($batch['batch_id'] == $new_batch_id) {
                $new_batch_number = $batch_number;
            }
            
            $batch_number++;
        }
        
        $receive_query = "INSERT INTO receive_product (product_id, supplier_id, quantity, unit_price, selling_price, total_price, receive_date, batch_number) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $receive_stmt = mysqli_prepare($conn, $receive_query);
        mysqli_stmt_bind_param($receive_stmt, "iiddddsi", $product_id, $supplier_id, $quantity, $unit_price, $selling_price, $total_price, $receive_date, $new_batch_number);
        mysqli_stmt_execute($receive_stmt);
        $receive_id = mysqli_insert_id($conn);
        
        $invoice_number = "INV-" . str_pad($receive_id, 6, "0", STR_PAD_LEFT);
        
        $update_invoice = "UPDATE receive_product SET invoice_number = ? WHERE id = ?";
        $update_invoice_stmt = mysqli_prepare($conn, $update_invoice);
        mysqli_stmt_bind_param($update_invoice_stmt, "si", $invoice_number, $receive_id);
        mysqli_stmt_execute($update_invoice_stmt);
        
        $check_inventory = "SELECT * FROM inventory WHERE product_id = ?";
        $check_inv_stmt = mysqli_prepare($conn, $check_inventory);
        mysqli_stmt_bind_param($check_inv_stmt, "i", $product_id);
        mysqli_stmt_execute($check_inv_stmt);
        $check_inv_result = mysqli_stmt_get_result($check_inv_stmt);

        $total_query = "SELECT SUM(quantity) as total FROM product_batches WHERE product_id = ?";
        $total_stmt = mysqli_prepare($conn, $total_query);
        mysqli_stmt_bind_param($total_stmt, "i", $product_id);
        mysqli_stmt_execute($total_stmt);
        $total_result = mysqli_stmt_get_result($total_stmt);
        $total_row = mysqli_fetch_assoc($total_result);
        $total_quantity = $total_row['total'];

        if (mysqli_num_rows($check_inv_result) > 0) {
            $update_inventory = "UPDATE inventory SET quantity = ? WHERE product_id = ?";
            $update_inv_stmt = mysqli_prepare($conn, $update_inventory);
            mysqli_stmt_bind_param($update_inv_stmt, "ii", $total_quantity, $product_id);
            mysqli_stmt_execute($update_inv_stmt);
        } else {
            $insert_inventory = "INSERT INTO inventory (product_id, quantity) VALUES (?, ?)";
            $insert_inv_stmt = mysqli_prepare($conn, $insert_inventory);
            mysqli_stmt_bind_param($insert_inv_stmt, "ii", $product_id, $total_quantity);
            mysqli_stmt_execute($insert_inv_stmt);
        }
        
        $check_query = "SELECT * FROM product_batches WHERE product_id = ? AND quantity > 0 AND batch_number = 1";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "i", $product_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $active_batch = mysqli_fetch_assoc($check_result);
            
            $update_product = "UPDATE product_list SET unit_price = ?, selling_price = ?, prod_expiry = ? WHERE product_id = ?";
            $update_prod_stmt = mysqli_prepare($conn, $update_product);
            mysqli_stmt_bind_param($update_prod_stmt, "ddsi", $active_batch['unit_price'], $active_batch['selling_price'], $active_batch['expiry_date'], $product_id);
            mysqli_stmt_execute($update_prod_stmt);
        }
        
        $product_query = "SELECT product_name FROM product_list WHERE product_id = ?";
        $product_stmt = mysqli_prepare($conn, $product_query);
        mysqli_stmt_bind_param($product_stmt, "i", $product_id);
        mysqli_stmt_execute($product_stmt);
        $product_result = mysqli_stmt_get_result($product_stmt);
        $product_row = mysqli_fetch_assoc($product_result);
        $product_name = $product_row['product_name'];
        
        $supplier_query = "SELECT supplier_name FROM supplier_list WHERE supplier_id = ?";
        $supplier_stmt = mysqli_prepare($conn, $supplier_query);
        mysqli_stmt_bind_param($supplier_stmt, "i", $supplier_id);
        mysqli_stmt_execute($supplier_stmt);
        $supplier_result = mysqli_stmt_get_result($supplier_stmt);
        $supplier_row = mysqli_fetch_assoc($supplier_result);
        $supplier_name = $supplier_row['supplier_name'];
        
        $user_name = isset($_SESSION["full_name"]) ? $_SESSION["full_name"] : 'Unknown User';
        $activity_type_str = "{$user_name} received {$quantity} units of {$product_name} from {$supplier_name} with Invoice #{$invoice_number}. Batch #{$new_batch_number}";
        $activity_type = "Product Receive";
        $log_query = "INSERT INTO activity_log (user_id, activity_type, description, timestamp) VALUES (?, ?, ?, NOW())";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "iss", $_SESSION["user_id"], $activity_type, $activity_type_str);
        mysqli_stmt_execute($log_stmt);
        
        mysqli_commit($conn);
        
        $_SESSION['success_message'] = "Product received successfully! Added as Batch #" . $new_batch_number;
        header("Location: receive_product.php");
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        
        $_SESSION['error_message'] = "Error receiving product: " . $e->getMessage();
        header("Location: receive_product.php");
        exit();
    }
}

$products_query = "SELECT * FROM product_list ORDER BY product_name";
$products_result = mysqli_query($conn, $products_query);

$suppliers_query = "SELECT * FROM supplier_list ORDER BY supplier_name";
$suppliers_result = mysqli_query($conn, $suppliers_query);

$history_query = "SELECT rp.*, p.product_name, s.supplier_name 
                 FROM receive_product rp 
                 JOIN product_list p ON rp.product_id = p.product_id 
                 JOIN supplier_list s ON rp.supplier_id = s.supplier_id 
                 ORDER BY rp.receive_date DESC, rp.id DESC 
                 LIMIT 10";
$history_result = mysqli_query($conn, $history_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Product - MediCare Pharmacy</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");

        :root {
            --primary-color: rgb(0, 183, 255);
            --secondary-color: #0075fc;
            --accent-color: #d8e9a8;
            --text-color: #333;
            --light-color: #f9f9f9;
            --dark-color: #191919;
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        body {
            background-color: var(--light-color);
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--secondary-color);
            color: white;
            padding: 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
            padding: 20px 10px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            padding: 10px 0;
            margin-bottom: 30px;
            position: relative;
        }

        .logo {
            width: 60px;
            height: 60px;
            background-color: transparent;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 10px;
            overflow: hidden;
            transition: opacity 0.3s ease, width 0.3s ease, height 0.3s ease;
        }

        .sidebar.collapsed .logo {
            opacity: 0;
            width: 0;
            height: 0;
            margin-right: 0;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar h1 {
            color: white;
            font-size: 20px;
            font-weight: 600;
            transition: opacity 0.3s ease, width 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed h1 {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .menu {
            list-style: none;
            margin-top: 20px;
        }

        .menu li {
            margin-bottom: 5px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .menu li.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .menu li:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .menu li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .menu li a i {
            margin-right: 10px;
            font-size: 18px;
            transition: margin 0.3s ease;
            min-width: 20px;
            text-align: center;
        }

        .sidebar.collapsed .menu li a i {
            margin-right: 0;
        }

        .menu li a span {
            transition: opacity 0.3s ease, width 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed .menu li a span {
            opacity: 0;
            width: 0;
            display: none;
        }

        .sidebar.collapsed .menu li a {
            justify-content: center;
            padding: 12px;
        }

        .logout {
            margin-top: auto;
            padding: 15px 0;
        }

        .logout a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .logout a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .logout a i {
            margin-right: 10px;
            font-size: 18px;
            transition: margin 0.3s ease;
            min-width: 20px;
            text-align: center;
        }

        .sidebar.collapsed .logout a i {
            margin-right: 0;
        }

        .logout a span {
            transition: opacity 0.3s ease, width 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed .logout a span {
            opacity: 0;
            width: 0;
            display: none;
        }

        .sidebar.collapsed .logout a {
            justify-content: center;
            padding: 12px;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s ease;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        .toggle-sidebar {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.3s ease;
        }

        .sidebar.collapsed .toggle-sidebar {
            right: 10px;
            left: auto;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .toggle-sidebar:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .toggle-sidebar i {
            transition: transform 0.3s ease;
        }

        .sidebar.collapsed .toggle-sidebar i {
            transform: rotate(180deg);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .page-title h2 {
            color: var(--text-color);
            font-size: 24px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            margin-right: 15px;
            font-weight: 500;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 20px;
        }

        .card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
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

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th,
        .history-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .history-table th {
            font-weight: 600;
            color: #777;
            background-color: #f9f9f9;
        }

        .history-table tbody tr:hover {
            background-color: #f9f9f9;
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

        @media (max-width: 768px) {
            .sidebar {
                width: var(--sidebar-collapsed-width);
                padding: 20px 10px;
            }
            
            .sidebar h1,
            .menu li a span,
            .logout a span {
                opacity: 0;
                width: 0;
                display: none;
            }
            
            .menu li a,
            .logout a {
                justify-content: center;
                padding: 12px;
            }
            
            .menu li a i,
            .logout a i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .main-content {
                margin-left: var(--sidebar-collapsed-width);
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
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
                <li data-title="Sales"><a href="admin_newsale.php"><i class="fas fa-shopping-cart"></i> <span>New Sales</span></a></li>
                <li data-title="Sales History"><a href="admin_sales-history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
                <li data-title="Product"><a href="product_category.php"><i class="fas fa-tags"></i> <span>Product Category</span></a></li>
                <li data-title="Product"><a href="product_dashboard.php"><i class="fas fa-pills"></i> <span>Product</span></a></li>
                <li class="active" data-title="Receive"><a href="receive_product.php"><i class="fas fa-truck-loading"></i> <span>Receive Product</span></a></li>
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
                    <h2>Receive Product</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
                ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
                ?>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h3 class="card-title">Receive New Product</h3>
                <form action="" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_id">Product</label>
                            <select id="product_id" name="product_id" class="form-control" required>
                                <option value="">Select Product</option>
                                <?php while($product = mysqli_fetch_assoc($products_result)): ?>
                                    <option value="<?php echo $product['product_id']; ?>">
                                        <?php echo htmlspecialchars($product['product_name']); ?> (<?php echo htmlspecialchars($product['prod_measure']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="supplier_id">Supplier</label>
                            <select id="supplier_id" name="supplier_id" class="form-control" required>
                                <option value="">Select Supplier</option>
                                <?php while($supplier = mysqli_fetch_assoc($suppliers_result)): ?>
                                    <option value="<?php echo $supplier['supplier_id']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="unit_price">Unit Price</label>
                            <input type="number" id="unit_price" name="unit_price" step="0.01" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="selling_price">Selling Price</label>
                            <input type="number" id="selling_price" name="selling_price" step="0.01" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry_date">Expiry Date</label>
                            <input type="date" id="expiry_date" name="expiry_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="receive_date">Receive Date</label>
                            <input type="date" id="receive_date" name="receive_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Receive Product
                    </button>
                </form>
            </div>
            
            <div class="card">
                <h3 class="card-title">Recent Receive History</h3>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Selling Price</th>
                            <th>Total Price</th>
                            <th>Invoice #</th>
                            <th>Receive Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($history_result) > 0): ?>
                            <?php while($history = mysqli_fetch_assoc($history_result)): ?>
                                <tr>
                                    <td><?php echo $history['id']; ?></td>
                                    <td><?php echo htmlspecialchars($history['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($history['supplier_name']); ?></td>
                                    <td><?php echo $history['quantity']; ?></td>
                                    <td>₱<?php echo number_format($history['unit_price'], 2); ?></td>
                                    <td>₱<?php echo number_format($history['selling_price'], 2); ?></td>
                                    <td>₱<?php echo number_format($history['total_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($history['invoice_number']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($history['receive_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center;">No recent history</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toggleBtn = document.getElementById("toggle-sidebar");
            const sidebar = document.getElementById("sidebar");
            const mainContent = document.getElementById("main-content");
            
            const isCollapsed = localStorage.getItem("sidebarCollapsed") === "true";
            
            if (isCollapsed) {
                sidebar.classList.add("collapsed");
                mainContent.classList.add("expanded");
            }
            
            toggleBtn.addEventListener("click", function() {
                sidebar.classList.toggle("collapsed");
                mainContent.classList.toggle("expanded");
                
                localStorage.setItem("sidebarCollapsed", sidebar.classList.contains("collapsed"));
            });
            
            const menuItems = document.querySelectorAll('.menu li');
            menuItems.forEach(item => {
                const link = item.querySelector('a');
                const text = link.querySelector('span').textContent.trim();
                item.setAttribute('data-title', text);
            });
            
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(function() {
                    alerts.forEach(alert => {
                        alert.style.transition = 'opacity 1s';
                        alert.style.opacity = '0';
                        setTimeout(function() {
                            alert.style.display = 'none';
                        }, 1000);
                    });
                }, 5000);
            }
            
            const unitPriceInput = document.getElementById('unit_price');
            const sellingPriceInput = document.getElementById('selling_price');
            
            unitPriceInput.addEventListener('input', function() {
                const unitPrice = parseFloat(this.value) || 0;
                const markup = 1.3;
                sellingPriceInput.value = (unitPrice * markup).toFixed(2);
            });
            
            const expiryDateInput = document.getElementById('expiry_date');
            const today = new Date().toISOString().split('T')[0];
            expiryDateInput.setAttribute('min', today);
            
            const productSelect = document.getElementById('product_id');
            
            productSelect.addEventListener('change', function() {
                const productId = this.value;
                
                if (productId) {
                    fetch(`get_product_details.php?id=${productId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                unitPriceInput.value = data.unit_price;
                                sellingPriceInput.value = data.selling_price;
                            }
                        })
                        .catch(error => console.error('Error fetching product details:', error));
                }
            });
        });
    </script>
</body>
</html>
