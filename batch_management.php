<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_batch'])) {
        $product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
        $batch_number = mysqli_real_escape_string($conn, $_POST['batch_number']);
        $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
        $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date']);
        $manufacturing_date = mysqli_real_escape_string($conn, $_POST['manufacturing_date']);
        $status = ($quantity > 0) ? 'active' : 'inactive';
        $query = "INSERT INTO product_batches (product_id, batch_number, quantity, expiry_date, manufacturing_date, status, created_at) 
                  VALUES ('$product_id', '$batch_number', '$quantity', '$expiry_date', '$manufacturing_date', '$status', NOW())";
        if (mysqli_query($conn, $query)) {
            $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'activity_log'");
            if (mysqli_num_rows($check_table) > 0) {
                $user_id = $_SESSION["user_id"];
                $activity = "Added new batch #$batch_number for product ID: $product_id";
                mysqli_query($conn, "INSERT INTO activity_log (user_ID, activity_type, description, timestamp) 
                                    VALUES ('$user_id', 'batch', '$activity', NOW())");
            }
            $_SESSION['success'] = "Batch added successfully!";
        } else {
            $_SESSION['error'] = "Error adding batch: " . mysqli_error($conn);
        }
        header("Location: batch_management.php");
        exit();
    }
    if (isset($_POST['update_batch'])) {
        $batch_id = mysqli_real_escape_string($conn, $_POST['batch_id']);
        $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
        $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date']);
        $status = ($quantity > 0) ? 'active' : 'inactive';
        $query = "UPDATE product_batches SET 
                  quantity = '$quantity', 
                  expiry_date = '$expiry_date', 
                  status = '$status',
                  updated_at = NOW() 
                  WHERE batch_id = '$batch_id'";
        if (mysqli_query($conn, $query)) {
            $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'activity_log'");
            if (mysqli_num_rows($check_table) > 0) {
                $user_id = $_SESSION["user_id"];
                $activity = "Updated batch ID: $batch_id";
                mysqli_query($conn, "INSERT INTO activity_log (user_ID, activity_type, description, timestamp) 
                                    VALUES ('$user_id', 'batch', '$activity', NOW())");
            }
            $_SESSION['success'] = "Batch updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating batch: " . mysqli_error($conn);
        }
        header("Location: batch_management.php");
        exit();
    }
    if (isset($_POST['delete_batch'])) {
        $batch_id = mysqli_real_escape_string($conn, $_POST['batch_id']);
        $batch_info_query = "SELECT batch_number, product_id FROM product_batches WHERE batch_id = '$batch_id'";
        $batch_info_result = mysqli_query($conn, $batch_info_query);
        $batch_info = mysqli_fetch_assoc($batch_info_result);
        $query = "DELETE FROM product_batches WHERE batch_id = '$batch_id'";
        if (mysqli_query($conn, $query)) {
            $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'activity_log'");
            if (mysqli_num_rows($check_table) > 0 && $batch_info) {
                $user_id = $_SESSION["user_id"];
                $activity = "Deleted batch #" . $batch_info['batch_number'] . " for product ID: " . $batch_info['product_id'];
                mysqli_query($conn, "INSERT INTO activity_log (user_ID, activity_type, description, timestamp) 
                                    VALUES ('$user_id', 'batch', '$activity', NOW())");
            }
            $_SESSION['success'] = "Batch deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting batch: " . mysqli_error($conn);
        }
        header("Location: batch_management.php");
        exit();
    }
}

$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'product_batches'");
if (mysqli_num_rows($check_table) == 0) {
    $create_table = "CREATE TABLE product_batches (
        batch_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        product_id INT(11) NOT NULL,
        batch_number VARCHAR(50) NOT NULL,
        quantity INT(11) NOT NULL DEFAULT 0,
        manufacturing_date DATE,
        expiry_date DATE,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES product_list(product_id) ON DELETE CASCADE
    )";
    if (mysqli_query($conn, $create_table)) {
        $_SESSION['success'] = "Product batches table created successfully!";
    } else {
        $_SESSION['error'] = "Error creating product batches table: " . mysqli_error($conn);
    }
}

$products_query = "SELECT product_id, product_name FROM product_list ORDER BY product_name";
$products_result = mysqli_query($conn, $products_query);
$products = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$product_filter = isset($_GET['product_filter']) ? mysqli_real_escape_string($conn, $_GET['product_filter']) : '';
$status_filter = isset($_GET['status_filter']) ? mysqli_real_escape_string($conn, $_GET['status_filter']) : '';

$batches_query = "SELECT pb.*, pl.product_name 
                 FROM product_batches pb
                 JOIN product_list pl ON pb.product_id = pl.product_id
                 WHERE NOT (pb.quantity = 0 AND pb.status = 'inactive') AND pb.status != 'archived'";
if (!empty($search)) {
    $batches_query .= " AND (pb.batch_number LIKE '%$search%' OR pl.product_name LIKE '%$search%')";
}
if (!empty($product_filter)) {
    $batches_query .= " AND pb.product_id = '$product_filter'";
}
if (!empty($status_filter)) {
    $batches_query .= " AND pb.status = '$status_filter'";
}
$batches_query .= " ORDER BY pb.receipt_date DESC";
$batches_result = mysqli_query($conn, $batches_query);

$update_zero_batches = "UPDATE product_batches SET status = 'inactive' WHERE quantity = 0 AND status = 'active'";
mysqli_query($conn, $update_zero_batches);

$count_query = "SELECT COUNT(*) as total FROM product_batches";
$count_result = mysqli_query($conn, $count_query);
$total_batches = mysqli_fetch_assoc($count_result)['total'];

$active_query = "SELECT COUNT(*) as active FROM product_batches WHERE status = 'active'";
$active_result = mysqli_query($conn, $active_query);
$active_batches = mysqli_fetch_assoc($active_result)['active'];

$archived_query = "SELECT COUNT(*) as archived FROM product_batches WHERE status = 'archived'";
$archived_result = mysqli_query($conn, $archived_query);
$archived_batches = mysqli_fetch_assoc($archived_result)['archived'];

$today = date('Y-m-d');
$thirty_days = date('Y-m-d', strtotime('+30 days'));
$expiring_query = "SELECT COUNT(*) as expiring FROM product_batches WHERE expiry_date BETWEEN '$today' AND '$thirty_days' AND status != 'archived'";
$expiring_result = mysqli_query($conn, $expiring_query);
$expiring_batches = mysqli_fetch_assoc($expiring_result)['expiring'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Management - St. Mark DrugStore</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .batch-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }
        
        .total-icon {
            background-color: rgba(0, 183, 255, 0.1);
            color: #00b7ff;
        }
        
        .active-icon {
            background-color: rgba(40, 199, 111, 0.1);
            color: #28c76f;
        }
        
        .inactive-icon {
            background-color: rgba(234, 84, 85, 0.1);
            color: #ea5455;
        }
        
        .expiring-icon {
            background-color: rgba(255, 159, 67, 0.1);
            color: #ff9f43;
        }
        
        .stat-info h3 {
            font-size: 14px;
            color: #6e6b7b;
            margin: 0 0 5px 0;
        }
        
        .stat-info p {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        
        .batch-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .search-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .search-filters input,
        .search-filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-filters button {
            padding: 8px 15px;
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .search-filters button:hover {
            background-color: #0095d9;
        }
        
        .add-batch-btn {
            padding: 10px 20px;
            background-color: #28c76f;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-batch-btn:hover {
            background-color: #24b263;
        }
        
        .batch-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .batch-table th {
            background-color: #f8f8f8;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #6e6b7b;
            border-bottom: 1px solid #ebe9f1;
        }
        
        .batch-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #ebe9f1;
            color: #5e5873;
        }
        
        .batch-table tr:last-child td {
            border-bottom: none;
        }
        
        .batch-table tr:hover {
            background-color: #f8f8f8;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background-color: rgba(40, 199, 111, 0.1);
            color: #28c76f;
        }
        
        .status-inactive {
            background-color: rgba(234, 84, 85, 0.1);
            color: #ea5455;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .edit-btn, .delete-btn {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .edit-btn {
            background-color: rgba(255, 159, 67, 0.1);
            color: #ff9f43;
        }
        
        .edit-btn:hover {
            background-color: rgba(255, 159, 67, 0.2);
        }
        
        .delete-btn {
            background-color: rgba(234, 84, 85, 0.1);
            color: #ea5455;
        }
        
        .delete-btn:hover {
            background-color: rgba(234, 84, 85, 0.2);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #5e6b77;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .cancel-btn {
            padding: 10px 15px;
            background-color: #f8f8f8;
            color: #6e6b7b;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .submit-btn {
            padding: 10px 15px;
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .submit-btn:hover {
            background-color: #0095d9;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: rgba(40, 199, 111, 0.1);
            color: #28c76f;
            border-left: 4px solid #28c76f;
        }
        
        .alert-error {
            background-color: rgba(234, 84, 85, 0.1);
            color: #ea5455;
            border-left: 4px solid #ea5455;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6e6b7b;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .batch-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .search-filters {
                width: 100%;
            }
            
            .search-filters input {
                flex: 1;
            }
            
            .batch-table {
                display: block;
                overflow-x: auto;
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
                <li data-title="Receive"><a href="receive_product.php"><i class="fas fa-truck-loading"></i> <span>Receive Product</span></a></li>
                <li class="active" data-title="Batches"><a href="batch_management.php"><i class="fas fa-layer-group"></i> <span>Batch Management</span></a></li>
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
                    <h2>Batch Management</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="batch-stats">
                <div class="stat-card">
                    <div class="stat-icon total-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Batches</h3>
                        <p><?php echo number_format($total_batches); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon active-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Active Batches</h3>
                        <p><?php echo number_format($active_batches); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon inactive-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Archive Batches</h3>
                        <p><?php echo number_format($archived_batches); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon expiring-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Expiring Soon</h3>
                        <p><?php echo number_format($expiring_batches); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="batch-actions">
                <form class="search-filters" method="GET" action="">
                    <input type="text" name="search" placeholder="Search batch number or product" value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="product_filter">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['product_id']; ?>" <?php echo ($product_filter == $product['product_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status_filter">
                        <option value="">All Status</option>
                        <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    
                    <button type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
                
                <button class="add-batch-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Batch
                </button>
                <a href="archive_products.php" class="add-batch-btn" style="background:#888;">Go to Archive</a>
            </div>
            
            <?php if (mysqli_num_rows($batches_result) > 0): ?>
                <table class="batch-table">
                    <thead>
                        <tr>
                            <th>Batch #</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($batch = mysqli_fetch_assoc($batches_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                                <td><?php echo htmlspecialchars($batch['product_name']); ?></td>
                                <td><?php echo number_format($batch['quantity']); ?></td>

                                <td>
                                    <?php 
                                    $expiry_date = strtotime($batch['expiry_date']);
                                    $today = strtotime(date('Y-m-d'));
                                    $days_left = floor(($expiry_date - $today) / (60 * 60 * 24));
                                    
                                    echo date('M d, Y', $expiry_date);
                                    
                                    if ($days_left <= 30 && $days_left > 0) {
                                        echo ' <span style="color: #ff9f43; font-size: 12px;">(' . $days_left . ' days left)</span>';
                                    } elseif ($days_left <= 0) {
                                        echo ' <span style="color: #ea5455; font-size: 12px;">(Expired)</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_label = ucfirst($batch['status']);
                                    if ($batch['quantity'] > 0 && $batch['quantity'] <= 5) {
                                        $status_class = 'status-inactive';
                                        $status_label = 'Low';
                                    } elseif ($batch['status'] == 'active') {
                                        $status_class = 'status-active';
                                    } elseif ($batch['status'] == 'inactive') {
                                        $status_class = 'status-inactive';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="edit-btn" onclick="openEditModal(<?php echo $batch['batch_ID']; ?>, '<?php echo $batch['batch_number']; ?>', <?php echo $batch['quantity']; ?>, '<?php echo $batch['expiry_date']; ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="delete-btn" onclick="confirmDelete(<?php echo $batch['batch_ID']; ?>, '<?php echo $batch['batch_number']; ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-layer-group"></i>
                    <h3>No batches found</h3>
                    <p>There are no batches matching your search criteria. Try adjusting your filters or add a new batch.</p>
                    <button class="add-batch-btn" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New Batch
                    </button>
                </div>
            <?php endif; ?>
            
            <div id="addBatchModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeAddModal()">&times;</span>
                    <h2>Add New Batch</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="product_id">Product</label>
                            <select id="product_id" name="product_id" required>
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['product_id']; ?>">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="batch_number">Batch Number</label>
                            <input type="text" id="batch_number" name="batch_number" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="manufacturing_date">Manufacturing Date</label>
                            <input type="date" id="manufacturing_date" name="manufacturing_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="expiry_date">Expiry Date</label>
                            <input type="date" id="expiry_date" name="expiry_date" required>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
                            <button type="submit" name="add_batch" class="submit-btn">Add Batch</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="editBatchModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeEditModal()">&times;</span>
                    <h2>Edit Batch</h2>
                    <form method="POST" action="">
                        <input type="hidden" id="edit_batch_id" name="batch_id">
                        
                        <div class="form-group">
                            <label for="edit_batch_number">Batch Number</label>
                            <input type="text" id="edit_batch_number" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_quantity">Quantity</label>
                            <input type="number" id="edit_quantity" name="quantity" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_expiry_date">Expiry Date</label>
                            <input type="date" id="edit_expiry_date" name="expiry_date" required>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                            <button type="submit" name="update_batch" class="submit-btn">Update Batch</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="deleteBatchModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeDeleteModal()">&times;</span>
                    <h2>Delete Batch</h2>
                    <p>Are you sure you want to delete batch <span id="delete_batch_number"></span>? This action cannot be undone.</p>
                    <form method="POST" action="">
                        <input type="hidden" id="delete_batch_id" name="batch_id">
                        
                        <div class="form-buttons">
                            <button type="button" class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
                            <button type="submit" name="delete_batch" class="delete-btn" style="padding: 10px 15px;">Delete Batch</button>
                        </div>
                    </form>
                </div>
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
            
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('manufacturing_date').max = today;
            document.getElementById('expiry_date').min = today;
            
            document.getElementById('manufacturing_date').addEventListener('change', function() {
                document.getElementById('expiry_date').min = this.value;
            });
        });
        
        function openAddModal() {
            document.getElementById('addBatchModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addBatchModal').style.display = 'none';
        }
        
        function openEditModal(batchId, batchNumber, quantity, expiryDate) {
            document.getElementById('edit_batch_id').value = batchId;
            document.getElementById('edit_batch_number').value = batchNumber;
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('edit_expiry_date').value = expiryDate;
            document.getElementById('editBatchModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editBatchModal').style.display = 'none';
        }
        
        function confirmDelete(batchId, batchNumber) {
            document.getElementById('delete_batch_id').value = batchId;
            document.getElementById('delete_batch_number').textContent = batchNumber;
            document.getElementById('deleteBatchModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteBatchModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const addModal = document.getElementById('addBatchModal');
            const editModal = document.getElementById('editBatchModal');
            const deleteModal = document.getElementById('deleteBatchModal');
            
            if (event.target == addModal) {
                closeAddModal();
            } else if (event.target == editModal) {
                closeEditModal();
            } else if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>