<?php
session_start();
include 'db_connect.php'; 

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}


$query_suppliers = "SELECT COUNT(*) as total_suppliers FROM supplier_list";
$result_suppliers = mysqli_query($conn, $query_suppliers);
$total_suppliers = 0;
if ($result_suppliers) {
    $row = mysqli_fetch_assoc($result_suppliers);
    $total_suppliers = $row['total_suppliers'];
}

$query_active = "SELECT COUNT(*) as active_suppliers FROM supplier_list WHERE status = 'active'";
$result_active = mysqli_query($conn, $query_active);
$active_suppliers = 0;
if ($result_active) {
    $row = mysqli_fetch_assoc($result_active);
    $active_suppliers = $row['active_suppliers'];
}


$alert_message = '';
$alert_type = '';


if (isset($_SESSION['alert_message']) && isset($_SESSION['alert_type'])) {
    $alert_message = $_SESSION['alert_message'];
    $alert_type = $_SESSION['alert_type'];

    unset($_SESSION['alert_message']);
    unset($_SESSION['alert_type']);
}


if (isset($_POST['add_supplier'])) {
    $supplier_name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
    $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $query = "INSERT INTO supplier_list (supplier_name, contact_person, contact_number, email, address, status) 
              VALUES ('$supplier_name', '$contact_person', '$contact_number', '$email', '$address', '$status')";
    
    if (mysqli_query($conn, $query)) {

        $_SESSION['alert_message'] = "Supplier added successfully!";
        $_SESSION['alert_type'] = "success";
        

        $check_table_query = "SHOW TABLES LIKE 'activity_log'";
        $table_result = mysqli_query($conn, $check_table_query);
        if ($table_result && mysqli_num_rows($table_result) > 0) {
            $user_id = $_SESSION["user_id"];
            $activity_type = "supplier";
            $description = "New supplier <strong>$supplier_name</strong> added";
            
            $log_query = "INSERT INTO activity_log (user_ID, activity_type, description, timestamp) 
                          VALUES ('$user_id', '$activity_type', '$description', NOW())";
            mysqli_query($conn, $log_query);
        }
        

        header("Location: suppliers.php");
        exit();
    } else {
        $_SESSION['alert_message'] = "Error: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "error";
        

        header("Location: suppliers.php");
        exit();
    }
}


if (isset($_POST['update_supplier'])) {
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $supplier_name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
    $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $query = "UPDATE supplier_list SET 
              supplier_name = '$supplier_name', 
              contact_person = '$contact_person', 
              contact_number = '$contact_number', 
              email = '$email', 
              address = '$address', 
              status = '$status' 
              WHERE supplier_id = '$supplier_id'";
    
    if (mysqli_query($conn, $query)) {

        $_SESSION['alert_message'] = "Supplier updated successfully!";
        $_SESSION['alert_type'] = "success";
        

        $check_table_query = "SHOW TABLES LIKE 'activity_log'";
        $table_result = mysqli_query($conn, $check_table_query);
        if ($table_result && mysqli_num_rows($table_result) > 0) {
            $user_id = $_SESSION["user_id"];
            $activity_type = "supplier";
            $description = "Supplier <strong>$supplier_name</strong> updated";
            
            $log_query = "INSERT INTO activity_log (user_ID, activity_type, description, timestamp) 
                          VALUES ('$user_id', '$activity_type', '$description', NOW())";
            mysqli_query($conn, $log_query);
        }
        

        header("Location: suppliers.php");
        exit();
    } else {
        $_SESSION['alert_message'] = "Error: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "error";
        

        header("Location: suppliers.php");
        exit();
    }
}


if (isset($_POST['delete_supplier'])) {
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);

    $name_query = "SELECT supplier_name FROM supplier_list WHERE supplier_id = '$supplier_id'";
    $name_result = mysqli_query($conn, $name_query);
    $supplier_name = "";
    if ($name_result && mysqli_num_rows($name_result) > 0) {
        $row = mysqli_fetch_assoc($name_result);
        $supplier_name = $row['supplier_name'];
    }
    
    $query = "DELETE FROM supplier_list WHERE supplier_id = '$supplier_id'";
    
    if (mysqli_query($conn, $query)) {

        $_SESSION['alert_message'] = "Supplier deleted successfully!";
        $_SESSION['alert_type'] = "success";
        

        $check_table_query = "SHOW TABLES LIKE 'activity_log'";
        $table_result = mysqli_query($conn, $check_table_query);
        if ($table_result && mysqli_num_rows($table_result) > 0) {
            $user_id = $_SESSION["user_id"];
            $activity_type = "supplier";
            $description = "Supplier <strong>$supplier_name</strong> deleted";
            
            $log_query = "INSERT INTO activity_log (user_ID, activity_type, description, timestamp) 
                          VALUES ('$user_id', '$activity_type', '$description', NOW())";
            mysqli_query($conn, $log_query);
        }
        

        header("Location: suppliers.php");
        exit();
    } else {
        $_SESSION['alert_message'] = "Error: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "error";

        header("Location: suppliers.php");
        exit();
    }
}


$query = "SELECT * FROM supplier_list ORDER BY supplier_name ASC";
$result = mysqli_query($conn, $query);
$suppliers = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $suppliers[] = $row;
    }
}


$query_products_by_supplier = "SELECT s.supplier_name, COUNT(p.product_id) as product_count 
                              FROM supplier_list s
                              LEFT JOIN product_batches p ON s.supplier_id = p.supplier_id
                              GROUP BY s.supplier_id
                              ORDER BY product_count DESC
                              LIMIT 5";
$result_products = mysqli_query($conn, $query_products_by_supplier);
$supplier_names = [];
$product_counts = [];

if ($result_products) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $supplier_names[] = $row['supplier_name'];
        $product_counts[] = $row['product_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - St. Mark DrugStore</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .supplier-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .supplier-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            align-items: center;
        }
        
        .supplier-icon {
            width: 50px;
            height: 50px;
            background-color: rgba(0, 183, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .supplier-icon i {
            color: #00b7ff;
            font-size: 24px;
        }
        
        .supplier-info h3 {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        
        .supplier-info p {
            margin: 5px 0 0;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .supplier-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .supplier-list {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .supplier-chart {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .supplier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .supplier-header h3 {
            margin: 0;
        }
        
        .add-supplier-btn {
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .add-supplier-btn i {
            margin-right: 5px;
        }
        
        .supplier-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .supplier-table th, .supplier-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .supplier-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .supplier-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #e6f7ee;
            color: #0d9448;
        }
        
        .status-inactive {
            background-color: #feeae9;
            color: #d63031;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .edit-btn, .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .edit-btn {
            color: #00b7ff;
        }
        
        .delete-btn {
            color: #d63031;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #666;
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
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .cancel-btn {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
        }
        
        .submit-btn {
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
        }
        
        .delete-modal .submit-btn {
            background-color: #d63031;
        }
        
        /* Alert styles */
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .alert-success {
            background-color: #e6f7ee;
            color: #0d9448;
            border-left: 4px solid #0d9448;
        }
        
        .alert-error {
            background-color: #feeae9;
            color: #d63031;
            border-left: 4px solid #d63031;
        }
        
        @media (max-width: 992px) {
            .supplier-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .supplier-table th:nth-child(3),
            .supplier-table td:nth-child(3),
            .supplier-table th:nth-child(4),
            .supplier-table td:nth-child(4) {
                display: none;
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
              <li data-title="Batches"><a href="batch_management.php"><i class="fas fa-layer-group"></i> <span>Batch Management</span></a></li>
              <li class="active" data-title="Suppliers"><a href="suppliers.php"><i class="fas fa-truck"></i> <span>Suppliers</span></a></li>
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
                    <h2>Supplier Management</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($alert_message)): ?>
                <div class="alert alert-<?php echo $alert_type; ?>">
                    <i class="fas fa-<?php echo $alert_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $alert_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="supplier-stats">
                <div class="supplier-card">
                    <div class="supplier-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="supplier-info">
                        <h3>Total Suppliers</h3>
                        <p><?php echo number_format($total_suppliers); ?></p>
                    </div>
                </div>
                
                <div class="supplier-card">
                    <div class="supplier-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="supplier-info">
                        <h3>Active Suppliers</h3>
                        <p><?php echo number_format($active_suppliers); ?></p>
                    </div>
                </div>
                
                <div class="supplier-card">
                    <div class="supplier-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="supplier-info">
                        <h3>Products Supplied</h3>
                        <p><?php 
                            // Get total products supplied
                            $query_products = "SELECT COUNT(*) as total FROM product_batches WHERE supplier_id IS NOT NULL";
                            $result_products = mysqli_query($conn, $query_products);
                            $total_products_supplied = 0;
                            if ($result_products) {
                                $row = mysqli_fetch_assoc($result_products);
                                $total_products_supplied = $row['total'];
                            }
                            echo number_format($total_products_supplied);
                        ?></p>
                    </div>
                </div>
            </div>
            
            <div class="supplier-container">
                <div class="supplier-list">
                    <div class="supplier-header">
                        <h3>Supplier List</h3>
                        <button class="add-supplier-btn" id="openAddModal">
                            <i class="fas fa-plus"></i> Add Supplier
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="supplier-table">
                            <thead>
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Contact Person</th>
                                    <th>Contact Number</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($suppliers)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No suppliers found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                            <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                                            <td><?php echo htmlspecialchars($supplier['contact_number']); ?></td>
                                            <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $supplier['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo ucfirst($supplier['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($supplier)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="delete-btn" onclick="openDeleteModal(<?php echo $supplier['supplier_id']; ?>, '<?php echo htmlspecialchars($supplier['supplier_name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="supplier-chart">
                    <h3>Products by Supplier</h3>
                    <div style="height: 300px;">
                        <canvas id="supplierChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Supplier Modal -->
    <div id="addSupplierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Supplier</h3>
                <button class="close-btn" onclick="closeModal('addSupplierModal')">&times;</button>
            </div>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="supplier_name">Supplier Name</label>
                    <input type="text" id="supplier_name" name="supplier_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="contact_person">Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('addSupplierModal')">Cancel</button>
                    <button type="submit" name="add_supplier" class="submit-btn">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Supplier Modal -->
    <div id="editSupplierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Supplier</h3>
                <button class="close-btn" onclick="closeModal('editSupplierModal')">&times;</button>
            </div>
            <form action="" method="POST">
                <input type="hidden" id="edit_supplier_id" name="supplier_id">
                <div class="form-group">
                    <label for="edit_supplier_name">Supplier Name</label>
                    <input type="text" id="edit_supplier_name" name="supplier_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_contact_person">Contact Person</label>
                    <input type="text" id="edit_contact_person" name="contact_person" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_contact_number">Contact Number</label>
                    <input type="text" id="edit_contact_number" name="contact_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_address">Address</label>
                    <textarea id="edit_address" name="address" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('editSupplierModal')">Cancel</button>
                    <button type="submit" name="update_supplier" class="submit-btn">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Supplier Modal -->
    <div id="deleteSupplierModal" class="modal delete-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Supplier</h3>
                <button class="close-btn" onclick="closeModal('deleteSupplierModal')">&times;</button>
            </div>
            <form action="" method="POST">
                <input type="hidden" id="delete_supplier_id" name="supplier_id">
                <p>Are you sure you want to delete <strong id="delete_supplier_name"></strong>?</p>
                <p>This action cannot be undone.</p>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('deleteSupplierModal')">Cancel</button>
                    <button type="submit" name="delete_supplier" class="submit-btn">Delete</button>
                </div>
            </form>
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
            
            // Supplier Chart
            const supplierCtx = document.getElementById('supplierChart').getContext('2d');
            const supplierChart = new Chart(supplierCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($supplier_names); ?>,
                    datasets: [{
                        label: 'Number of Products',
                        data: <?php echo json_encode($product_counts); ?>,
                        backgroundColor: 'rgba(0, 183, 255, 0.7)',
                        borderColor: 'rgb(0, 183, 255)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 10,
                            titleColor: '#fff',
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyColor: '#fff',
                            bodyFont: {
                                size: 14
                            },
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Modal functionality
            document.getElementById('openAddModal').addEventListener('click', function() {
                document.getElementById('addSupplierModal').style.display = 'block';
            });
        });
        
        // Close modal function
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        

        function openEditModal(supplier) {
            document.getElementById('edit_supplier_id').value = supplier.supplier_id;
            document.getElementById('edit_supplier_name').value = supplier.supplier_name;
            document.getElementById('edit_contact_person').value = supplier.contact_person;
            document.getElementById('edit_contact_number').value = supplier.contact_number;
            document.getElementById('edit_email').value = supplier.email;
            document.getElementById('edit_address').value = supplier.address;
            document.getElementById('edit_status').value = supplier.status;
            
            document.getElementById('editSupplierModal').style.display = 'block';
        }
        

        function openDeleteModal(supplierId, supplierName) {
            document.getElementById('delete_supplier_id').value = supplierId;
            document.getElementById('delete_supplier_name').textContent = supplierName;
            
            document.getElementById('deleteSupplierModal').style.display = 'block';
        }
        

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>