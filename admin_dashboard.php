<?php
session_start();
include 'db_connect.php'; 

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}

$enable_notifications = true; 
$low_stock_threshold = 10; 


$settings_query = "SELECT enable_notifications, low_stock_threshold FROM system_settings WHERE id = 1";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    $settings = mysqli_fetch_assoc($settings_result);
    $enable_notifications = $settings['enable_notifications'] == '1';
    $low_stock_threshold = $settings['low_stock_threshold'];
}

$notifications = [];


if ($enable_notifications) {

    $low_stock_query = "SELECT p.product_name, i.quantity 
                        FROM inventory i 
                        JOIN product_list p ON i.product_id = p.product_id 
                        WHERE i.quantity < $low_stock_threshold 
                        ORDER BY i.quantity ASC 
                        LIMIT 5";
    $low_stock_result = mysqli_query($conn, $low_stock_query);
    if ($low_stock_result && mysqli_num_rows($low_stock_result) > 0) {
        while ($row = mysqli_fetch_assoc($low_stock_result)) {
            $notifications[] = [
                'type' => 'low_stock',
                'message' => 'Low stock alert: ' . $row['product_name'] . ' (Only ' . $row['quantity'] . ' left)',
                'icon' => 'exclamation-triangle',
                'color' => '#ff4d4d'
            ];
        }
    }
    
    $today = date('Y-m-d');
    $thirty_days = date('Y-m-d', strtotime('+30 days'));
    $expiring_query = "SELECT p.product_name, p.prod_expiry 
                       FROM product_list p 
                       WHERE p.prod_expiry BETWEEN '$today' AND '$thirty_days' 
                       ORDER BY p.prod_expiry ASC 
                       LIMIT 5";
    $expiring_result = mysqli_query($conn, $expiring_query);
    if ($expiring_result && mysqli_num_rows($expiring_result) > 0) {
        while ($row = mysqli_fetch_assoc($expiring_result)) {
            $days_left = floor((strtotime($row['prod_expiry']) - strtotime($today)) / (60 * 60 * 24));
            $notifications[] = [
                'type' => 'expiring',
                'message' => 'Expiring soon: ' . $row['product_name'] . ' (' . $days_left . ' days left)',
                'icon' => 'calendar-times',
                'color' => '#ff9900'
            ];
        }
    }
    

    $expired_query = "SELECT p.product_name, p.prod_expiry 
                     FROM product_list p 
                     WHERE p.prod_expiry < '$today' 
                     ORDER BY p.prod_expiry DESC 
                     LIMIT 5";
    $expired_result = mysqli_query($conn, $expired_query);
    if ($expired_result && mysqli_num_rows($expired_result) > 0) {
        while ($row = mysqli_fetch_assoc($expired_result)) {
            $days_ago = floor((strtotime($today) - strtotime($row['prod_expiry'])) / (60 * 60 * 24));
            $notifications[] = [
                'type' => 'expired',
                'message' => 'Expired product: ' . $row['product_name'] . ' (Expired ' . $days_ago . ' days ago)',
                'icon' => 'trash-alt',
                'color' => '#dc3545'
            ];
        }
    }
    
}

// total products
$query_products = "SELECT COUNT(*) as total_products FROM product_list";
$result_products = mysqli_query($conn, $query_products);
$total_products = 0;
if ($result_products) {
    $row = mysqli_fetch_assoc($result_products);
    $total_products = $row['total_products'];
}

$today = date('Y-m-d');
$query_today_sales = "SELECT SUM(total_amount) as today_sales FROM sale WHERE DATE(sale_date) = '$today'";
$result_today_sales = mysqli_query($conn, $query_today_sales);
$today_sales = 0;
if ($result_today_sales) {
    $row = mysqli_fetch_assoc($result_today_sales);
    $today_sales = $row['today_sales'] ? $row['today_sales'] : 0;
}

$query_staff = "SELECT COUNT(*) as total_staff FROM user_list WHERE user_role = 'cashier'";
$result_staff = mysqli_query($conn, $query_staff);
$total_staff = 0;
if ($result_staff) {
    $row = mysqli_fetch_assoc($result_staff);
    $total_staff = $row['total_staff'];
}

$query_total_sales = "SELECT SUM(total_amount) as total_amount FROM sale";
$result_total_sales = mysqli_query($conn, $query_total_sales);
$total_sales = 0;
if ($result_total_sales) {
    $row = mysqli_fetch_assoc($result_total_sales);
    $total_sales = $row['total_amount'] ? $row['total_amount'] : 0;
}

$query_daily = "SELECT HOUR(sale_date) as hour, SUM(total_amount) as hourly_sales 
               FROM sale 
               WHERE DATE(sale_date) = CURRENT_DATE() 
               GROUP BY HOUR(sale_date)";
$result_daily = mysqli_query($conn, $query_daily);
$daily_sales = array_fill(0, 24, 0); 
if ($result_daily) {
    while ($row = mysqli_fetch_assoc($result_daily)) {
        $hour_index = $row['hour'];
        $daily_sales[$hour_index] = $row['hourly_sales'];
    }
}

$monthly_sales = array_fill(0, 12, 0); 
$query_monthly = "SELECT MONTH(sale_date) as month, SUM(total_amount) as monthly_sales 
                 FROM sale
                 WHERE YEAR(sale_date) = YEAR(CURRENT_DATE()) 
                 GROUP BY MONTH(sale_date)";
$result_monthly = mysqli_query($conn, $query_monthly);
if ($result_monthly) {
    while ($row = mysqli_fetch_assoc($result_monthly)) {
        $month_index = $row['month'] - 1; 
        $monthly_sales[$month_index] = $row['monthly_sales'];
    }
}

$yearly_sales = array_fill(0, 5, 0); 
$current_year = date('Y');
$start_year = $current_year - 4; 
$query_yearly = "SELECT YEAR(sale_date) as year, SUM(total_amount) as yearly_sales 
                FROM sale 
                WHERE YEAR(sale_date) BETWEEN $start_year AND $current_year 
                GROUP BY YEAR(sale_date)";
$result_yearly = mysqli_query($conn, $query_yearly);
if ($result_yearly) {
    while ($row = mysqli_fetch_assoc($result_yearly)) {
        $year_index = $row['year'] - $start_year;         
        if ($year_index >= 0 && $year_index < 5) {
            $yearly_sales[$year_index] = $row['yearly_sales'];
        }
    }
}

// weekly sales
$weekly_sales = array_fill(0, 7, 0);
$query_weekly = "SELECT 
                  DAYOFWEEK(sale_date) as day_of_week, 
                  SUM(total_amount) as daily_sales 
                FROM sale 
                WHERE sale_date >= DATE_SUB(CURRENT_DATE(), INTERVAL DAYOFWEEK(CURRENT_DATE())-1 DAY)
                  AND sale_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7-DAYOFWEEK(CURRENT_DATE()) DAY)
                GROUP BY DAYOFWEEK(sale_date)
                ORDER BY DAYOFWEEK(sale_date)";
$result_weekly = mysqli_query($conn, $query_weekly);
if ($result_weekly) {
    while ($row = mysqli_fetch_assoc($result_weekly)) {
        $day_index = $row['day_of_week'] - 1; // Adjust for 0-based array
        $weekly_sales[$day_index] = $row['daily_sales'];
    }
}

// top selling products
$query_top_products = "SELECT p.product_name, p.prod_measure, SUM(si.quantity) as total_sold 
                      FROM sale_item si 
                      JOIN product_list p ON si.product_id = p.product_id 
                      GROUP BY si.product_id 
                      ORDER BY total_sold DESC 
                      LIMIT 6";
$result_top_products = mysqli_query($conn, $query_top_products);
$top_products = [];
$top_products_names = [];
$top_products_sales = [];
$top_products_display = [];

if ($result_top_products) {
    while ($row = mysqli_fetch_assoc($result_top_products)) {
        $top_products[] = $row;
        $top_products_names[] = $row['product_name'];
        $top_products_sales[] = $row['total_sold'];
        

        if (count($top_products_display) < 5) {
            $top_products_display[] = $row;
        }
    }
}


$recent_activities = [];
$table_exists = false;

$check_table_query = "SHOW TABLES LIKE 'activity_log'";
$table_result = mysqli_query($conn, $check_table_query);
if ($table_result && mysqli_num_rows($table_result) > 0) {
    $table_exists = true;

    $query_recent = "SELECT a.activity_type, a.description, a.timestamp, u.full_name 
                    FROM activity_log a 
                    LEFT JOIN user_list u ON a.user_ID = u.user_ID
                    ORDER BY a.timestamp DESC 
                    LIMIT 3";
    $result_recent = mysqli_query($conn, $query_recent);
    if ($result_recent) {
        while ($row = mysqli_fetch_assoc($result_recent)) {
            $recent_activities[] = $row;
        }
    }
}

if (!$table_exists) {
    $recent_activities = [
        [
            'activity_type' => 'sale',
            'description' => 'New sale completed by <strong>' . $_SESSION["full_name"] . '</strong>',
            'timestamp' => date('Y-m-d H:i:s', time() - 300), // 5 minutes ago
            'full_name' => $_SESSION["full_name"]
        ],
        [
            'activity_type' => 'product',
            'description' => 'New product added to inventory',
            'timestamp' => date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
            'full_name' => $_SESSION["full_name"]
        ],
        [
            'activity_type' => 'user',
            'description' => 'New user registered in the system',
            'timestamp' => date('Y-m-d H:i:s', time() - 86400), // 1 day ago
            'full_name' => $_SESSION["full_name"]
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - St. Mark DrugStore</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .chart-toggle {
            display: flex;
            gap: 4px;
        }
        
        .toggle-btn {
            padding: 4px 8px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .toggle-btn.active {
            background-color: #00b7ff;
            color: white;
            border-color: #00b7ff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .toggle-btn:hover:not(.active) {
            background-color: #e9ecef;
        }
        

        .notifications-container {
            margin-bottom: 30px;
            display: <?php echo $enable_notifications && !empty($notifications) ? 'block' : 'none'; ?>;
        }
        
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .notifications-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        
        .notifications-header .badge {
            background-color: #ff4d4d;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }
        
        .notifications-list {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: background-color 0.2s ease;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background-color: #f9f9f9;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-content p {
            margin: 0;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        
        .notification-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .view-btn {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .view-btn:hover {
            background-color: #dee2e6;
        }
        
        .dismiss-btn {
            background-color: transparent;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }
        
        .dismiss-btn:hover {
            background-color: #f8f9fa;
        }
        
        .view-all-btn {
            background-color: transparent;
            color: #00b7ff;
            border: 1px solid #00b7ff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .view-all-btn:hover {
            background-color: #00b7ff;
            color: white;
        }
        
        .hidden-notification {
            display: none;
        }

        .view-all-btn {
            background-color: transparent;
            color: #00b7ff;
            border: 1px solid #00b7ff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .view-all-btn:hover {
            background-color: #00b7ff;
            color: white;
        }

        .view-all-btn.active {
            background-color: #00b7ff;
            color: white;
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
              <li class="active" data-title="Dashboard"><a href="#"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
              <li data-title="Users"><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
              <li data-title="Inventory"><a href="inventory.php"><i class="fas fa-boxes"></i> <span>Inventory</span></a></li>
              <li data-title="Sales"><a href="admin_newsale.php"><i class="fas fa-shopping-cart"></i> <span>New Sales</span></a></li>
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
                    <h2>St. Mark Drug Store</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            

            <?php if ($enable_notifications && !empty($notifications)): ?>
            <div class="notifications-container">
                <div class="notifications-header">
                    <h3>
                        <i class="fas fa-bell" style="color: #00b7ff;"></i>
                        Notifications
                        <span class="badge"><?php echo count($notifications); ?></span>
                    </h3>
                    <button class="view-all-btn">View All</button>
                </div>
                <div class="notifications-list">
                    <?php foreach ($notifications as $index => $notification): ?>
                        <div class="notification-item<?php echo $index >= 3 ? ' hidden-notification' : ''; ?>">
                            <div class="notification-icon" style="background-color: <?php echo $notification['color']; ?>20; color: <?php echo $notification['color']; ?>;">
                                <i class="fas fa-<?php echo $notification['icon']; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <p><?php echo $notification['message']; ?></p>
                                <div class="notification-actions">
                                    <?php if ($notification['type'] == 'low_stock'): ?>
                                        <button class="notification-btn view-btn">View Inventory</button>
                                    <?php elseif ($notification['type'] == 'expiring' || $notification['type'] == 'expired'): ?>
                                        <button class="notification-btn view-btn">View Product</button>
                                    <?php elseif ($notification['type'] == 'pending_order'): ?>
                                        <button class="notification-btn view-btn">Process Orders</button>
                                    <?php endif; ?>
                                    <button class="notification-btn dismiss-btn">Dismiss</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Products</h3>
                        <p><?php echo number_format($total_products); ?></p>
                    </div>
                </div>
                
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Today's Sales</h3>
                        <p>₱<?php echo number_format($today_sales, 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Staff</h3>
                        <p><?php echo number_format($total_staff); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Sales</h3>
                        <p>₱<?php echo number_format($total_sales, 2); ?></p>
                    </div>
                </div>
            </div>
            
            
            <div class="chart-container">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Sales Overview</h3>
                        <div class="chart-toggle">
                            <button class="toggle-btn" data-period="today">Today</button>
                            <button class="toggle-btn" data-period="weekly">Weekly</button>
                            <button class="toggle-btn active" data-period="monthly">Monthly</button>
                            <button class="toggle-btn" data-period="yearly">Yearly</button>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <h3>Top Selling Products</h3>
                    <div class="chart-wrapper">
                        <canvas id="productsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="recent-activity">
                <h3>Recent Activity</h3>
                <?php if (!$table_exists): ?>
                <div class="alert alert-info" style="background-color: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <strong>Note:</strong> The activity_log table doesn't exist yet. Create it to track real activities. Sample data is shown below.
                </div>
                <?php endif; ?>
                <div class="activity-list">
                    <?php if (empty($recent_activities)): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="activity-details">
                                <p>No recent activities found</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php 
                                    $icon = 'info-circle';
                                    if (strpos(strtolower($activity['activity_type']), 'sale') !== false) {
                                        $icon = 'shopping-cart';
                                    } elseif (strpos(strtolower($activity['activity_type']), 'user') !== false) {
                                        $icon = 'user-plus';
                                    } elseif (strpos(strtolower($activity['activity_type']), 'product') !== false) {
                                        $icon = 'pills';
                                    } elseif (strpos(strtolower($activity['activity_type']), 'shipment') !== false) {
                                        $icon = 'truck';
                                    }
                                    ?>
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                </div>
                                <div class="activity-details">
                                    <p><?php echo $activity['description']; ?></p>
                                    <span class="time">
                                        <?php 
                                        $timestamp = strtotime($activity['timestamp']);
                                        $now = time();
                                        $diff = $now - $timestamp;
                                        
                                        if ($diff < 60) {
                                            echo "Just now";
                                        } elseif ($diff < 3600) {
                                            echo floor($diff / 60) . " minutes ago";
                                        } elseif ($diff < 86400) {
                                            echo floor($diff / 3600) . " hours ago";
                                        } elseif ($diff < 172800) {
                                            echo "Yesterday";
                                        } else {
                                            echo date('M j, Y', $timestamp);
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Products Table -->
            <div class="top-products">
                <h3>Top Selling Products</h3>
                <div class="product-list">
                    <?php if (empty($top_products_display)): ?>
                        <div class="no-data">No product sales data available</div>
                    <?php else: ?>
                        <?php 
                        // Find the maximum sales value for percentage calculation
                        $max_sales = !empty($top_products_display) ? max(array_column($top_products_display, 'total_sold')) : 0;
                        
                        foreach ($top_products_display as $index => $product): 
                            $percentage = $max_sales > 0 ? ($product['total_sold'] / $max_sales) * 100 : 0;
                        ?>
                            <div class="product-item">
                                <div class="product-rank"><?php echo $index + 1; ?></div>
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="product-category"><?php echo htmlspecialchars($product['prod_measure']); ?></div>
                                </div>
                                <div class="product-sales">
                                    <div class="sales-bar" style="width: <?php echo $percentage; ?>%"></div>
                                    <div class="sales-value"><?php echo number_format($product['total_sold']); ?> units</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
            

            const dismissButtons = document.querySelectorAll('.dismiss-btn');
            dismissButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const notificationItem = this.closest('.notification-item');
                    notificationItem.style.height = notificationItem.offsetHeight + 'px';
                    notificationItem.style.overflow = 'hidden';
                    
                    setTimeout(() => {
                        notificationItem.style.height = '0';
                        notificationItem.style.padding = '0';
                        notificationItem.style.margin = '0';
                        notificationItem.style.opacity = '0';
                        notificationItem.style.transition = 'all 0.3s ease';
                    }, 10);
                    
                    setTimeout(() => {
                        notificationItem.remove();
                        

                        const badge = document.querySelector('.notifications-header .badge');
                        let count = parseInt(badge.textContent) - 1;
                        badge.textContent = count;
                        

                        if (count === 0) {
                            document.querySelector('.notifications-container').style.display = 'none';
                        }
                    }, 300);
                });
            });
            

            const viewButtons = document.querySelectorAll('.notification-btn.view-btn');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const notificationType = this.closest('.notification-item').querySelector('.notification-icon i').className;
                    
                    if (notificationType.includes('exclamation-triangle')) {
                        window.location.href = 'inventory.php';
                    } else if (notificationType.includes('calendar-times') || notificationType.includes('trash-alt')) {
                        window.location.href = 'product_dashboard.php';
                    } else if (notificationType.includes('clock')) {
                        window.location.href = 'admin_newsale.php';
                    }
                });
            });

            const viewAllBtn = document.querySelector('.view-all-btn');
            if (viewAllBtn) {
                viewAllBtn.addEventListener('click', function() {
                    const hiddenNotifications = document.querySelectorAll('.hidden-notification');
                    const isExpanded = this.classList.contains('active');
                    
                    if (isExpanded) {

                        hiddenNotifications.forEach(item => {
                            item.style.display = 'none';
                        });
                        this.textContent = 'View All';
                        this.classList.remove('active');
                    } else {

                        hiddenNotifications.forEach(item => {
                            item.style.display = 'flex';
                        });
                        this.textContent = 'Show Less';
                        this.classList.add('active');
                    }
                });
            }


            const dailyData = {
                labels: ['12am', '1am', '2am', '3am', '4am', '5am', '6am', '7am', '8am', '9am', '10am', '11am', 
                         '12pm', '1pm', '2pm', '3pm', '4pm', '5pm', '6pm', '7pm', '8pm', '9pm', '10pm', '11pm'],
                datasets: [{
                    label: 'Today\'s Sales (₱)',
                    data: <?php echo json_encode($daily_sales); ?>,
                    backgroundColor: 'rgba(0, 183, 255, 0.1)',
                    borderColor: 'rgb(0, 183, 255)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgb(0, 183, 255)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }]
            };

            const weeklyData = {
                labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                datasets: [{
                    label: 'Weekly Sales (₱)',
                    data: <?php echo json_encode($weekly_sales); ?>,
                    backgroundColor: 'rgba(0, 183, 255, 0.1)',
                    borderColor: 'rgb(0, 183, 255)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgb(0, 183, 255)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }]
            };

            const monthlyData = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Monthly Sales (₱)',
                    data: <?php echo json_encode($monthly_sales); ?>,
                    backgroundColor: 'rgba(0, 183, 255, 0.1)',
                    borderColor: 'rgb(0, 183, 255)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgb(0, 183, 255)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }]
            };

            const yearlyData = {
                labels: [
                    <?php 
                    for ($i = 0; $i < 5; $i++) {
                        echo "'" . ($start_year + $i) . "'" . ($i < 4 ? ", " : "");
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Yearly Sales (₱)',
                    data: <?php echo json_encode($yearly_sales); ?>,
                    backgroundColor: 'rgba(0, 183, 255, 0.1)',
                    borderColor: 'rgb(0, 183, 255)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgb(0, 183, 255)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }]
            };


            const chartOptions = {
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
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return `₱${context.parsed.y.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            };


            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(salesCtx, {
                type: 'line',
                data: monthlyData,
                options: chartOptions
            });


            const toggleButtons = document.querySelectorAll('.toggle-btn');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {

                    toggleButtons.forEach(btn => btn.classList.remove('active'));

                    this.classList.add('active');
                    

                    const period = this.getAttribute('data-period');
                    
                    if (period === 'today') {
                        salesChart.data = dailyData;
                    } else if (period === 'weekly') {
                        salesChart.data = weeklyData;
                    } else if (period === 'monthly') {
                        salesChart.data = monthlyData;
                    } else if (period === 'yearly') {
                        salesChart.data = yearlyData;
                    }
                    
                    salesChart.update();
                });
            });


            const productsCtx = document.getElementById('productsChart').getContext('2d');
            const productsChart = new Chart(productsCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($top_products_names); ?>,
                    datasets: [{
                        data: <?php echo json_encode($top_products_sales); ?>,
                        backgroundColor: [
                            'rgb(0, 183, 255)',
                            '#0075fc',
                            '#4dabf5',
                            '#82c4f8',
                            '#b5dcfb',
                            '#e8f4fe'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                boxWidth: 12,
                                font: {
                                    size: 12
                                }
                            }
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
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((acc, data) => acc + data, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${context.label}: ${value} units (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '65%',
                    radius: '90%'
                }
            });
        });
    </script>
</body>
</html>
