<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "cashier") {
    header("Location: index.php");
    exit();
}

$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category_list']) ? $_GET['category_list'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'product_name';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// Get categories for filter dropdown
$category_query = "SELECT DISTINCT category_name FROM category_list ORDER BY category_name";
$category_result = mysqli_query($conn, $category_query);

// Build the query
$query = "SELECT * FROM product_list WHERE 1=1";

// Add search term filter
if (!empty($search_term)) {
    $query .= " AND (product_name LIKE '%$search_term%' OR product_id LIKE '%$search_term%' OR description LIKE '%$search_term%')";
}

// Add category filter
if (!empty($category)) {
    $query .= " AND category_id = '$category'";
}

// Add sorting
$allowed_sort_columns = ['product_name', 'selling_price', 'quantity', 'category_id'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'product_name';
}
if ($sort_order != 'ASC' && $sort_order != 'DESC') {
    $sort_order = 'ASC';
}
$query .= " ORDER BY $sort_by $sort_order";

// Execute query
$result = mysqli_query($conn, $query);
$total_products = mysqli_num_rows($result);

// Get low stock products
$system_settings_query = "SELECT low_stock_threshold FROM system_settings LIMIT 1";
$system_settings_result = mysqli_query($conn, $system_settings_query);
$system_settings = mysqli_fetch_assoc($system_settings_result);
$low_stock_threshold = $system_settings['low_stock_threshold'];

$low_stock_query = "SELECT i.*, p.product_name FROM inventory i JOIN product_list p ON i.product_id = p.product_id WHERE i.quantity <= $low_stock_threshold ORDER BY i.quantity ASC LIMIT 5";
$low_stock_result = mysqli_query($conn, $low_stock_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Search - St. Mark DrugStore Pharmacy</title>
    <link rel="stylesheet" href="css/cashier.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .filter-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-group label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        .form-group input, .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        .filter-btn {
            padding: 10px 15px;
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        .filter-btn:hover {
            background-color: #0075fc;
        }
        .reset-btn {
            padding: 10px 15px;
            background-color: #f8f9fa;
            color: #495057;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        .reset-btn:hover {
            background-color: #e9ecef;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .product-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }
        .product-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .product-code {
            font-size: 12px;
            color: #666;
            background-color: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .product-body {
            padding: 15px;
        }
        .product-price {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        .product-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        .product-detail {
            display: flex;
            flex-direction: column;
        }
        .detail-label {
            font-size: 12px;
            color: #666;
        }
        .detail-value {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }
        .stock-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .in-stock {
            background-color: #e6f7ee;
            color: #28a745;
        }
        .low-stock {
            background-color: #fff3e6;
            color: #fd7e14;
        }
        .out-of-stock {
            background-color: #ffe6e6;
            color: #dc3545;
        }
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .add-to-cart-btn {
            flex: 1;
            padding: 8px 0;
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .add-to-cart-btn:hover {
            background-color: #0075fc;
        }
        .view-details-btn {
            padding: 8px 15px;
            background-color: #f8f9fa;
            color: #495057;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        .view-details-btn:hover {
            background-color: #e9ecef;
        }
        .low-stock-alert {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        .alert-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .alert-icon {
            width: 40px;
            height: 40px;
            background-color: #fff3e6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .alert-icon i {
            color: #fd7e14;
            font-size: 20px;
        }
        .alert-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .low-stock-table {
            width: 100%;
            border-collapse: collapse;
        }
        .low-stock-table th, .low-stock-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .low-stock-table th {
            font-weight: 500;
            color: #666;
            font-size: 13px;
        }
        .low-stock-table tr:last-child td {
            border-bottom: none;
        }
        .no-results {
            text-align: center;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .no-results i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        .no-results h3 {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }
        .no-results p {
            font-size: 14px;
            color: #999;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #495057;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        .pagination a:hover, .pagination a.active {
            background-color: #00b7ff;
            color: white;
            border-color: #00b7ff;
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
                <li data-title="Dashboard"><a href="cashier_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li data-title="New Sale"><a href="new_sale.php"><i class="fas fa-shopping-cart"></i> <span>New Sale</span></a></li>
                <li data-title="Sales History"><a href="sales_history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
                <li class="active" data-title="Product Search"><a href="product_search.php"><i class="fas fa-search"></i> <span>Product Search</span></a></li>
                <li data-title="Settings"><a href="cashier_settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
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
                    <h2>Product Search</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="filter-container">
                <form class="filter-form" method="GET" action="product_search.php">
                    <div class="form-group">
                        <label for="search">Search Products</label>
                        <input type="text" id="search" name="search" placeholder="Name, Code or Description" value="<?php echo $search_term; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">All Categories</option>
                            <?php while($cat = mysqli_fetch_assoc($category_result)): ?>
                                <option value="<?php echo $cat['category_name']; ?>" <?php echo ($category == $cat['category_name']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['category_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sort_by">Sort By</label>
                        <select id="sort_by" name="sort_by">
                            <option value="product_name" <?php echo ($sort_by == 'product_name') ? 'selected' : ''; ?>>Name</option>
                            <option value="price" <?php echo ($sort_by == 'price') ? 'selected' : ''; ?>>Price</option>
                            <option value="stock_quantity" <?php echo ($sort_by == 'stock_quantity') ? 'selected' : ''; ?>>Stock</option>
                            <option value="category" <?php echo ($sort_by == 'category') ? 'selected' : ''; ?>>Category</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sort_order">Order</label>
                        <select id="sort_order" name="sort_order">
                            <option value="ASC" <?php echo ($sort_order == 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                            <option value="DESC" <?php echo ($sort_order == 'DESC') ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="filter-btn">Search</button>
                        <a href="product_search.php" class="reset-btn">Reset</a>
                    </div>
                </form>
            </div>
            
            <?php if(mysqli_num_rows($low_stock_result) > 0): ?>
                <div class="low-stock-alert">
                    <div class="alert-header">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="alert-title">Low Stock Alert</h3>
                    </div>
                    
                    <table class="low-stock-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Code</th>
                                <th>Current Stock</th>
                                <th>Low Stock Threshold</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($low_stock = mysqli_fetch_assoc($low_stock_result)): ?>
                                <tr>
                                    <td><?php echo $low_stock['product_name']; ?></td>
                                    <td><?php echo $low_stock['product_id']; ?></td>
                                    <td><?php echo $low_stock['quantity']; ?></td>
                                    <td><?php echo $low_stock_threshold; ?></td>
                                    <td>
                                        <?php if($low_stock['quantity'] <= 0): ?>
                                            <span class="stock-status out-of-stock">Out of Stock</span>
                                        <?php else: ?>
                                            <span class="stock-status low-stock">Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if(mysqli_num_rows($result) > 0): ?>
                <p>Found <?php echo $total_products; ?> products</p>
                
                <div class="product-grid">
                    <?php while($product = mysqli_fetch_assoc($result)): ?>
                        <div class="product-card">
                            <div class="product-header">
                                <h3 class="product-name"><?php echo $product['product_name']; ?></h3>
                                <span class="product-code"><?php echo $product['product_id']; ?></span>
                            </div>
                            <div class="product-body">
                                <?php
                                // Fetch image path from product_list
                                $image_path = !empty($product['image_path']) ? $product['image_path'] : 'uploads/products/default.png';
                                ?>
                                <div style="text-align:center; margin-bottom:10px;">
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="max-width:100px; max-height:100px; object-fit:cover; border-radius:8px; background:#f8f8f8;" onerror="this.onerror=null;this.src='uploads/products/default.png';">
                                </div>
                                <div class="product-price">₱<?php echo number_format($product['selling_price'], 2); ?></div>
                                
                                <div class="product-details">
                                    <div class="product-detail">
                                        <span class="detail-label">Category</span>
                                        <span class="detail-value"><?php echo $product['category_id']; ?></span>
                                    </div>
                                    
                                    <div class="product-detail">
                                        <span class="detail-label">Stock</span>
                                        <span class="detail-value">
                                            <?php
                                            // Fetch inventory quantity for this product
                                            $product_id = $product['product_id'];
                                            $inventory_query = "SELECT quantity FROM inventory WHERE product_id = ? LIMIT 1";
                                            $inventory_stmt = mysqli_prepare($conn, $inventory_query);
                                            $inventory_quantity = 0;
                                            if ($inventory_stmt) {
                                                mysqli_stmt_bind_param($inventory_stmt, "i", $product_id);
                                                mysqli_stmt_execute($inventory_stmt);
                                                $inventory_result = mysqli_stmt_get_result($inventory_stmt);
                                                if ($inv_row = mysqli_fetch_assoc($inventory_result)) {
                                                    $inventory_quantity = $inv_row['quantity'];
                                                }
                                            }
                                            
                                            if($inventory_quantity <= 0): ?>
                                                <span class="stock-status out-of-stock">Out of Stock</span>
                                            <?php elseif($inventory_quantity <= $low_stock_threshold): ?>
                                                <span class="stock-status low-stock">Low Stock (<?php echo $inventory_quantity; ?>)</span>
                                            <?php else: ?>
                                                <span class="stock-status in-stock">In Stock (<?php echo $inventory_quantity; ?>)</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="product-actions">
                                     <button class="add-to-cart-btn" <?php echo ($inventory_quantity <= 0) ? 'disabled' : ''; ?> onclick="addToCart(<?php echo $product['product_id']; ?>)">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                    <button class="view-details-btn" onclick="viewProductDetails(<?php echo $product['product_id']; ?>)">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="pagination">
                    <a href="#" class="active">1</a>
                    <a href="#">2</a>
                    <a href="#">3</a>
                    <a href="#">Next</a>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search filters or categories</p>
                </div>
            <?php endif; ?>
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
        });
        
        function addToCart(productId) {
            // Redirect to new sale page with product ID
            window.location.href = 'new_sale.php?add_product=' + productId;
        }
        
        function viewProductDetails(productId) {
            // Show product details (could be a modal or redirect)
            alert('View details for product ID: ' + productId);
            // In a real implementation, you might use a modal or redirect to a product details page
        }
    </script>
</body>
</html>
