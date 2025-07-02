<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}

// Initialize variables
$search = "";
$current_page = 1;
$records_per_page = 10;
$filter_role = "";
$success_message = "";
$error_message = "";

// Process search and filters
if (isset($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
}

if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $current_page = (int)$_GET['page'];
}

if (isset($_GET['role']) && !empty($_GET['role'])) {
    $filter_role = mysqli_real_escape_string($conn, $_GET['role']);
}

// Process user actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new user
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        // Check if username already exists
        $check_query = "SELECT * FROM user_list WHERE username = '$username'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists. Please choose a different username.']);
            exit;
        } else {
            $insert_query = "INSERT INTO user_list (username, password, full_name, user_role, status, date_created) 
                            VALUES ('$username', '$password', '$full_name', '$role', '$status', NOW())";
            
            if (mysqli_query($conn, $insert_query)) {
                echo json_encode(['success' => true, 'message' => 'User added successfully!']);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding user: ' . mysqli_error($conn)]);
                exit;
            }
        }
    }
    
    // Edit user
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $update_query = "UPDATE user_list SET 
                        full_name = '$full_name', 
                        user_role = '$role', 
                        status = '$status' 
                        WHERE user_ID = '$user_id'";
        
        // Update password only if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $update_query = "UPDATE user_list SET 
                            full_name = '$full_name', 
                            user_role = '$role', 
                            status = '$status',
                            password = '$password' 
                            WHERE user_ID = '$user_id'";
        }
        
        if (mysqli_query($conn, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating user: ' . mysqli_error($conn)]);
            exit;
        }
    }
    
    // Delete user
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
        
        $delete_query = "DELETE FROM user_list WHERE user_ID = '$user_id'";
        
        if (mysqli_query($conn, $delete_query)) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully!']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . mysqli_error($conn)]);
            exit;
        }
    }
}

// Build query for user listing with search and filters
$query = "SELECT * FROM user_list WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (username LIKE '%$search%' OR full_name LIKE '%$search%')";
}

if (!empty($filter_role)) {
    $query .= " AND user_role = '$filter_role'";
}

// Count total records for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$count_result = mysqli_query($conn, $count_query);
$total_records = 0;

if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_records = $count_row['total'];
}

$total_pages = ceil($total_records / $records_per_page);


if ($current_page < 1) {
    $current_page = 1;
} else if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $records_per_page;
$query .= " ORDER BY user_ID DESC LIMIT $offset, $records_per_page";

$result = mysqli_query($conn, $query);


$roles_query = "SELECT DISTINCT user_role FROM user_list ORDER BY user_role";
$roles_result = mysqli_query($conn, $roles_query);
$available_roles = [];

if ($roles_result) {
    while ($role_row = mysqli_fetch_assoc($roles_result)) {
        $available_roles[] = $role_row['user_role'];
    }
}

if (empty($available_roles)) {
    $available_roles = ['admin', 'cashier', 'inventory', 'manager'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - St. Mark DrugStore</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .user-container {
            padding: 20px;
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .search-filter {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-filter input, .search-filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .search-filter button {
            padding: 8px 12px;
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .search-filter button:hover {
            background-color: #0090cc;
        }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .user-table th, .user-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .user-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .user-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .user-actions {
            display: flex;
            gap: 5px;
        }
        
        .user-actions button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .edit-btn {
            background-color: #ffc107;
            color: #212529;
        }
        
        .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        
        .add-btn {
            padding: 8px 16px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .add-btn:hover {
            background-color: #218838;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
        }
        
        .pagination a:hover {
            background-color: #f5f5f5;
        }
        
        .pagination .active {
            background-color: #00b7ff;
            color: white;
            border-color: #00b7ff;
        }
        
        .pagination .disabled {
            color: #aaa;
            cursor: not-allowed;
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
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 5px;
            width: 50%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .form-actions button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .cancel-btn {
            background-color: #f8f9fa;
            color: #333;
        }
        
        .save-btn {
            background-color: #00b7ff;
            color: white;
        }
        
        .save-btn:hover {
            background-color: #0090cc;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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
        
        .user-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .role-admin {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .role-cashier {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .role-inventory {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .role-manager {
            background-color: #d6d8db;
            color: #1b1e21;
        }
        
        @media (max-width: 768px) {
            .user-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .search-filter {
                flex-wrap: wrap;
                width: 100%;
            }
            
            .search-filter input, .search-filter select {
                flex: 1;
                min-width: 120px;
            }
            
            .modal-content {
                width: 90%;
                margin: 20% auto;
            }
            
            .user-table {
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
              <li class="active" data-title="Users"><a href="#"><i class="fas fa-users"></i> <span>Users</span></a></li>
              <li data-title="Inventory"><a href="inventory.php"><i class="fas fa-boxes"></i> <span>Inventory</span></a></li>
              <li data-title="Sales"><a href="admin_newsale.php"><i class="fas fa-shopping-cart"></i> <span>New Sales</span></a></li>
              <li data-title="Sales"><a href="admin_sales-history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
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
                    <h2>St. Mark Drug Store</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="user-container">
                <div class="user-header">
                    <h2>User Management</h2>
                    
                    <div class="search-filter">
                        <form action="" method="GET" id="searchForm">
                            <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                            
                            <select name="role">
                                <option value="">All Roles</option>
                                <?php foreach ($available_roles as $role): ?>
                                    <option value="<?php echo $role; ?>" <?php echo ($filter_role === $role) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($role); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="submit"><i class="fas fa-search"></i> Search</button>
                        </form>
                        
                        <button class="add-btn" id="addUserBtn"><i class="fas fa-plus"></i> Add User</button>
                    </div>
                </div>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="user-table-container">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    // Determine role class
                                    $role_class = 'role-badge';
                                    if ($row['user_role'] === 'admin') {
                                        $role_class .= ' role-admin';
                                    } elseif ($row['user_role'] === 'cashier') {
                                        $role_class .= ' role-cashier';
                                    } elseif ($row['user_role'] === 'inventory') {
                                        $role_class .= ' role-inventory';
                                    } elseif ($row['user_role'] === 'manager') {
                                        $role_class .= ' role-manager';
                                    }
                                    
                                    // Determine status class
                                    $status_class = 'user-status';
                                    if ($row['status'] === 'active') {
                                        $status_class .= ' status-active';
                                    } else {
                                        $status_class .= ' status-inactive';
                                    }
                                    
                                    echo "<tr>";
                                    echo "<td>" . $row['user_ID'] . "</td>";
                                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                                    echo "<td><span class='" . $role_class . "'>" . ucfirst($row['user_role']) . "</span></td>";
                                    echo "<td><span class='" . $status_class . "'>" . ucfirst($row['status']) . "</span></td>";
                                    echo "<td>" . date('M j, Y', strtotime($row['date_created'])) . "</td>";
                                    echo "<td class='user-actions'>";
                                    echo "<button class='edit-btn' onclick='editUser(" . json_encode($row) . ")'><i class='fas fa-edit'></i> Edit</button>";
                                    echo "<button class='delete-btn' onclick='confirmDelete(" . $row['user_ID'] . ", \"" . htmlspecialchars($row['full_name']) . "\")'><i class='fas fa-trash'></i> Delete</button>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align: center;'>No users found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=1&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>"><i class="fas fa-angle-double-left"></i></a>
                            <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>"><i class="fas fa-angle-left"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                            <span class="disabled"><i class="fas fa-angle-left"></i></span>
                        <?php endif; ?>
                        
                        <?php
 
                        $range = 2; 
                        $start_page = max(1, $current_page - $range);
                        $end_page = min($total_pages, $current_page + $range);
                        
      
                        if ($start_page > 1) {
                            echo "<a href='?page=1&search=" . urlencode($search) . "&role=" . urlencode($filter_role) . "'>1</a>";
                            if ($start_page > 2) {
                                echo "<span class='disabled'>...</span>";
                            }
                        }
                        

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo "<span class='active'>" . $i . "</span>";
                            } else {
                                echo "<a href='?page=" . $i . "&search=" . urlencode($search) . "&role=" . urlencode($filter_role) . "'>" . $i . "</a>";
                            }
                        }
                        
   
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo "<span class='disabled'>...</span>";
                            }
                            echo "<a href='?page=" . $total_pages . "&search=" . urlencode($search) . "&role=" . urlencode($filter_role) . "'>" . $total_pages . "</a>";
                        }
                        ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>"><i class="fas fa-angle-right"></i></a>
                            <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>"><i class="fas fa-angle-double-right"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-angle-right"></i></span>
                            <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <span class="close">&times;</span>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <?php foreach ($available_roles as $role): ?>
                            <option value="<?php echo $role; ?>"><?php echo ucfirst($role); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="save-btn">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit User</h3>
                <span class="close">&times;</span>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input type="text" id="edit_username" disabled>
                </div>
                
                <div class="form-group">
                    <label for="edit_password">Password (leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password">
                </div>
                
                <div class="form-group">
                    <label for="edit_full_name">Full Name</label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_role">Role</label>
                    <select id="edit_role" name="role" required>
                        <?php foreach ($available_roles as $role): ?>
                            <option value="<?php echo $role; ?>"><?php echo ucfirst($role); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="save-btn">Update User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
                <span class="close">&times;</span>
            </div>
            <p>Are you sure you want to delete the user <strong id="delete_user_name"></strong>?</p>
            <p>This action cannot be undone.</p>
            
            <form action="" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete_user_id" name="user_id">
                
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('deleteUserModal')">Cancel</button>
                    <button type="submit" class="delete-btn">Delete User</button>
                </div>
            </form>
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
            

            const addUserBtn = document.getElementById("addUserBtn");
            const addUserModal = document.getElementById("addUserModal");
            const editUserModal = document.getElementById("editUserModal");
            const deleteUserModal = document.getElementById("deleteUserModal");
            const closeButtons = document.querySelectorAll(".close");
            

            addUserBtn.addEventListener("click", function() {
                addUserModal.style.display = "block";
            });
            

            closeButtons.forEach(button => {
                button.addEventListener("click", function() {
                    const modal = this.closest(".modal");
                    modal.style.display = "none";
                });
            });
            

            window.addEventListener("click", function(event) {
                if (event.target === addUserModal) {
                    addUserModal.style.display = "none";
                }
                if (event.target === editUserModal) {
                    editUserModal.style.display = "none";
                }
                if (event.target === deleteUserModal) {
                    deleteUserModal.style.display = "none";
                }
            });
            

            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(function() {
                    alerts.forEach(alert => {
                        alert.style.opacity = '0';
                        alert.style.transition = 'opacity 0.5s';
                        setTimeout(function() {
                            alert.style.display = 'none';
                        }, 500);
                    });
                }, 5000);
            }
            

            const addUserForm = document.querySelector('#addUserModal form');
            if (addUserForm) {
                addUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            location.reload();
                        }
                    });
                });
            }
            

            const editUserForm = document.querySelector('#editUserModal form');
            if (editUserForm) {
                editUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            location.reload();
                        }
                    });
                });
            }
            

            const deleteUserForm = document.querySelector('#deleteUserModal form');
            if (deleteUserForm) {
                deleteUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            location.reload();
                        }
                    });
                });
            }
        });
        

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.user_ID;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_role').value = user.user_role;
            document.getElementById('edit_status').value = user.status;
            document.getElementById('edit_password').value = '';
            document.getElementById('editUserModal').style.display = 'block';
        }
        

        function confirmDelete(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name').textContent = userName;
            
            document.getElementById('deleteUserModal').style.display = 'block';
        }
        

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
</body>
</html>