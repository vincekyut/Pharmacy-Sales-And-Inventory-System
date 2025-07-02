<?php
session_start();
include 'db_connect.php'; 

// Check if user is logged in and is admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}

// Initialize variables
$message = '';
$message_type = '';

// Handle Add Category
if (isset($_POST['add_category'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $query = "INSERT INTO category_list (category_name, category_description, status) 
              VALUES ('$name', '$description', '$status')";
    
    if (mysqli_query($conn, $query)) {
        $message = "Category added successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($conn);
        $message_type = "error";
    }
}

// Handle Edit Category
if (isset($_POST['edit_category'])) {
    $id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $query = "UPDATE category_list SET 
              category_name = '$name', 
              category_description = '$description', 
              status = '$status' 
              WHERE category_id = $id";
    
    if (mysqli_query($conn, $query)) {
        $message = "Category updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . mysqli_error($conn);
        $message_type = "error";
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Check if category has products
    $check_query = "SELECT COUNT(*) as product_count FROM product_list WHERE category_id = $id";
    $check_result = mysqli_query($conn, $check_query);
    $row = mysqli_fetch_assoc($check_result);
    
    if ($row['product_count'] > 0) {
        $message = "Cannot delete category. It has associated products.";
        $message_type = "error";
    } else {
        $query = "DELETE FROM category_list WHERE category_id = $id";
        
        if (mysqli_query($conn, $query)) {
            $message = "Category deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . mysqli_error($conn);
            $message_type = "error";
        }
    }
}

// Pagination settings
$items_per_page = isset($_GET['items_per_page']) ? intval($_GET['items_per_page']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "WHERE category_name LIKE '%$search%' OR category_description LIKE '%$search%'";
}

// Sorting
$sort_field = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'category_name';
$sort_direction = isset($_GET['direction']) && $_GET['direction'] == 'desc' ? 'DESC' : 'ASC';
$allowed_sort_fields = ['category_id', 'category_name', 'status', 'product_count'];

if (!in_array($sort_field, $allowed_sort_fields)) {
    $sort_field = 'category_name';
}

// Count total categories for pagination
$count_query = "SELECT COUNT(*) as total FROM category_list $search_condition";
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_categories = $count_row['total'];
$total_pages = ceil($total_categories / $items_per_page);

// Get categories with product count
$query = "SELECT pc.*, 
          (SELECT COUNT(*) FROM product_list pl WHERE pl.category_id = pc.category_id) as product_count 
          FROM category_list pc 
          $search_condition 
          ORDER BY $sort_field $sort_direction 
          LIMIT $offset, $items_per_page";

$result = mysqli_query($conn, $query);
$categories = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
}

// Get category by ID for editing
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = mysqli_real_escape_string($conn, $_GET['edit']);
    $query = "SELECT * FROM category_list WHERE category_id = $id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $edit_category = mysqli_fetch_assoc($result);
    }
}

// Function to generate sort URL
function getSortUrl($field) {
    global $sort_field, $sort_direction, $search, $page, $items_per_page;
    
    $direction = ($sort_field == $field && $sort_direction == 'ASC') ? 'desc' : 'asc';
    $url = "product_category.php?sort=$field&direction=$direction&page=$page&items_per_page=$items_per_page";
    
    if (!empty($search)) {
        $url .= "&search=" . urlencode($search);
    }
    
    return $url;
}

// Function to generate pagination URL
function getPaginationUrl($page_num) {
    global $sort_field, $sort_direction, $search, $items_per_page;
    
    $url = "product_category.php?page=$page_num&items_per_page=$items_per_page&sort=$sort_field&direction=$sort_direction";
    
    if (!empty($search)) {
        $url .= "&search=" . urlencode($search);
    }
    
    return $url;
}

$query = "SELECT low_stock_threshold FROM system_settings LIMIT 1";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$reorder_level = $row['low_stock_threshold'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Categories - St. Mark DrugStore</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Additional styles for product category page */
        .category-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .search-add {
            display: flex;
            gap: 10px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding: 8px 12px 8px 35px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            width: 250px;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .add-btn {
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s;
        }
        
        .add-btn:hover {
            background-color: #0098d6;
        }
        
        .category-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .category-table th, .category-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .category-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .category-table tr:last-child td {
            border-bottom: none;
        }
        
        .sort-link {
            color: #495057;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .sort-link i {
            margin-left: 5px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #e3fcef;
            color: #0d6832;
        }
        
        .status-inactive {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .edit-btn, .delete-btn {
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .edit-btn {
            background-color: #f8f9fa;
            color: #495057;
            border: 1px solid #ced4da;
        }
        
        .edit-btn:hover {
            background-color: #e9ecef;
        }
        
        .delete-btn {
            background-color: #fff5f5;
            color: #e03131;
            border: 1px solid #ffc9c9;
        }
        
        .delete-btn:hover {
            background-color: #ffe3e3;
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .items-per-page {
            padding: 4px 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .pagination-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .page-btn {
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            color: #495057;
        }
        
        .page-btn:hover:not(:disabled) {
            background-color: #e9ecef;
        }
        
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
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
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #343a40;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .cancel-btn {
            background-color: #f8f9fa;
            color: #495057;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 8px 15px;
            cursor: pointer;
        }
        
        .save-btn {
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 15px;
            cursor: pointer;
        }
        
        .save-btn:hover {
            background-color: #0098d6;
        }
        
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #e3fcef;
            color: #0d6832;
            border: 1px solid #c3fae8;
        }
        
        .alert-error {
            background-color: #fff5f5;
            color: #e03131;
            border: 1px solid #ffc9c9;
        }
        
        .confirm-dialog {
            text-align: center;
        }
        
        .confirm-dialog p {
            margin-bottom: 20px;
            color: #495057;
        }
        
        @media (max-width: 768px) {
            .category-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .search-add {
                width: 100%;
                flex-direction: column;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .add-btn {
                width: 100%;
                justify-content: center;
            }
            
            .category-table th:nth-child(3), 
            .category-table td:nth-child(3) {
                display: none;
            }
            
            .pagination {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
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
                <li class="active" data-title="Product"><a href="product_category.php"><i class="fas fa-tags"></i> <span>Product Category</span></a></li>
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
                    <h2>Manage Categories</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="content-wrapper">
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <div class="category-container">
                    <div class="category-header">
                        <h3>Category List</h3>
                        <div class="search-add">
                            <form method="GET" action="product_category.php" class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search categories..." value="<?php echo htmlspecialchars($search); ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_field); ?>">
                                <input type="hidden" name="direction" value="<?php echo htmlspecialchars($sort_direction); ?>">
                                <input type="hidden" name="page" value="1">
                                <input type="hidden" name="items_per_page" value="<?php echo $items_per_page; ?>">
                            </form>
                            <button class="add-btn" onclick="openAddModal()">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </div>
                    </div>
                    
                    <table class="category-table">
                        <thead>
                            <tr>
                                <th width="60">
                                    <a href="<?php echo getSortUrl('category_id'); ?>" class="sort-link">
                                        ID
                                        <?php if ($sort_field == 'category_id'): ?>
                                            <i class="fas fa-sort-<?php echo $sort_direction == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo getSortUrl('category_name'); ?>" class="sort-link">
                                        Category Name
                                        <?php if ($sort_field == 'category_name'): ?>
                                            <i class="fas fa-sort-<?php echo $sort_direction == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Description</th>
                                <th width="100">
                                    <a href="<?php echo getSortUrl('product_count'); ?>" class="sort-link">
                                        Products
                                        <?php if ($sort_field == 'product_count'): ?>
                                            <i class="fas fa-sort-<?php echo $sort_direction == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="100">
                                    <a href="<?php echo getSortUrl('status'); ?>" class="sort-link">
                                        Status
                                        <?php if ($sort_field == 'status'): ?>
                                            <i class="fas fa-sort-<?php echo $sort_direction == 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No categories found</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['category_id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['category_description']); ?></td>
                                    <td><?php echo $category['product_count']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $category['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                            <i class="fas fa-<?php echo $category['status'] == 'active' ? 'check-circle' : 'times-circle'; ?>"></i>
                                            <?php echo ucfirst($category['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="edit-btn" onclick="openEditModal(<?php echo $category['category_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="delete-btn" onclick="confirmDelete(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['category_name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing <?php echo min(($page - 1) * $items_per_page + 1, $total_categories); ?> to 
                            <?php echo min($page * $items_per_page, $total_categories); ?> of 
                            <?php echo $total_categories; ?> categories
                            
                            <form method="GET" action="product_category.php" style="display: inline-flex; align-items: center; gap: 5px;">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_field); ?>">
                                <input type="hidden" name="direction" value="<?php echo htmlspecialchars($sort_direction); ?>">
                                <input type="hidden" name="page" value="1">
                                
                                <select name="items_per_page" class="items-per-page" onchange="this.form.submit()">
                                    <option value="5" <?php echo $items_per_page == 5 ? 'selected' : ''; ?>>5</option>
                                    <option value="10" <?php echo $items_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="20" <?php echo $items_per_page == 20 ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                </select>
                                per page
                            </form>
                        </div>
                        
                        <div class="pagination-buttons">
                            <button class="page-btn" onclick="window.location.href='<?php echo getPaginationUrl(1); ?>'" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                                <i class="fas fa-angle-double-left"></i>
                            </button>
                            <button class="page-btn" onclick="window.location.href='<?php echo getPaginationUrl($page - 1); ?>'" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                                <i class="fas fa-angle-left"></i>
                            </button>
                            
                            <span style="margin: 0 10px;">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            
                            <button class="page-btn" onclick="window.location.href='<?php echo getPaginationUrl($page + 1); ?>'" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                                <i class="fas fa-angle-right"></i>
                            </button>
                            <button class="page-btn" onclick="window.location.href='<?php echo getPaginationUrl($total_pages); ?>'" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                                <i class="fas fa-angle-double-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Category Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Category</h3>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="product_category.php">
                <div class="form-group">
                    <label for="name">Category Name</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_category" class="save-btn">Add Category</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Category</h3>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="product_category.php">
                <input type="hidden" id="edit_category_id" name="category_id">
                <div class="form-group">
                    <label for="edit_name">Category Name</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_category" class="save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Category</h3>
                <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="confirm-dialog">
                <p>Are you sure you want to delete the category "<span id="delete_category_name"></span>"?</p>
                <p>This action cannot be undone and may affect products in this category.</p>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
                    <button type="button" id="confirm_delete_btn" class="delete-btn">Delete</button>
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
        });
        
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(categoryId) {
            fetch(`get_category.php?id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_category_id').value = data.category_id;
                    document.getElementById('edit_name').value = data.category_name;
                    document.getElementById('edit_description').value = data.category_description;
                    document.getElementById('edit_status').value = data.status;
                    document.getElementById('editModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching category data:', error);
                    alert('Error loading category data. Please try again.');
                });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function confirmDelete(categoryId, categoryName) {
            document.getElementById('delete_category_name').textContent = categoryName;
            document.getElementById('confirm_delete_btn').onclick = function() {
                window.location.href = `product_category.php?delete=${categoryId}`;
            };
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>