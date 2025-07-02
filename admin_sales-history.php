<?php
session_start();
include 'db_connect.php'; 




$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');


$cashier_filter = isset($_GET['cashier_id']) ? $_GET['cashier_id'] : '';


$query_cashiers = "SELECT user_ID, full_name FROM user_list WHERE user_role = 'cashier' OR user_role = 'admin' ORDER BY full_name";
$result_cashiers = mysqli_query($conn, $query_cashiers);
$cashiers = [];
if ($result_cashiers) {
    while ($row = mysqli_fetch_assoc($result_cashiers)) {
        $cashiers[] = $row;
    }
}


$query_total_sales = "SELECT SUM(total_amount) as total_amount FROM sale";
$result_total_sales = mysqli_query($conn, $query_total_sales);
$total_sales = 0;
if ($result_total_sales) {
    $row = mysqli_fetch_assoc($result_total_sales);
    $total_sales = $row['total_amount'] ? $row['total_amount'] : 0;
}


$today = date('Y-m-d');
$query_today_sales = "SELECT SUM(total_amount) as today_sales FROM sale WHERE DATE(sale_date) = '$today'";
$result_today_sales = mysqli_query($conn, $query_today_sales);
$today_sales = 0;
if ($result_today_sales) {
    $row = mysqli_fetch_assoc($result_today_sales);
    $today_sales = $row['today_sales'] ? $row['today_sales'] : 0;
}


$query_sales_by_cashier = "SELECT u.full_name, u.user_ID, COUNT(s.sale_id) as total_transactions, 
                          SUM(s.total_amount) as total_sales 
                          FROM sale s 
                          JOIN user_list u ON s.cashier_id = u.user_ID 
                          WHERE DATE(s.sale_date) BETWEEN '$start_date' AND '$end_date' ";
                          

if (!empty($cashier_filter)) {
    $query_sales_by_cashier .= " AND s.cashier_id = '$cashier_filter' ";
}

$query_sales_by_cashier .= " GROUP BY s.cashier_id 
                          ORDER BY total_sales DESC";
$result_sales_by_cashier = mysqli_query($conn, $query_sales_by_cashier);
$sales_by_cashier = [];
if ($result_sales_by_cashier) {
    while ($row = mysqli_fetch_assoc($result_sales_by_cashier)) {
        $sales_by_cashier[] = $row;
    }
}

$query_daily_sales = "SELECT DATE(sale_date) as sale_day, SUM(total_amount) as daily_total 
                     FROM sale 
                     WHERE DATE(sale_date) BETWEEN '$start_date' AND '$end_date' ";


if (!empty($cashier_filter)) {
    $query_daily_sales .= " AND cashier_id = '$cashier_filter' ";
}

$query_daily_sales .= " GROUP BY DATE(sale_date) 
                     ORDER BY sale_day";
$result_daily_sales = mysqli_query($conn, $query_daily_sales);
$daily_sales = [];
$daily_labels = [];
$daily_data = [];
if ($result_daily_sales) {
    while ($row = mysqli_fetch_assoc($result_daily_sales)) {
        $daily_sales[] = $row;
        $daily_labels[] = date('M d', strtotime($row['sale_day']));
        $daily_data[] = $row['daily_total'];
    }
}


$query_top_products = "SELECT p.product_name, SUM(si.quantity) as total_quantity, 
                      SUM(si.quantity * si.price) as total_revenue 
                      FROM sale_item si 
                      JOIN product_list p ON si.product_id = p.product_id 
                      JOIN sale s ON si.sale_id = s.sale_id 
                      WHERE DATE(s.sale_date) BETWEEN '$start_date' AND '$end_date' ";


if (!empty($cashier_filter)) {
    $query_top_products .= " AND s.cashier_id = '$cashier_filter' ";
}

$query_top_products .= " GROUP BY si.product_id 
                      ORDER BY total_revenue DESC 
                      LIMIT 10";
$result_top_products = mysqli_query($conn, $query_top_products);
$top_products = [];
if ($result_top_products) {
    while ($row = mysqli_fetch_assoc($result_top_products)) {
        $top_products[] = $row;
    }
}


$query_recent_sales = "SELECT s.sale_id, s.sale_date, s.total_amount, u.full_name as cashier 
                      FROM sale s 
                      JOIN user_list u ON s.cashier_id = u.user_ID 
                      WHERE 1=1 ";


if (!empty($cashier_filter)) {
    $query_recent_sales .= " AND s.cashier_id = '$cashier_filter' ";
}

$query_recent_sales .= " ORDER BY s.sale_date DESC 
                      LIMIT 10";
$result_recent_sales = mysqli_query($conn, $query_recent_sales);
$recent_sales = [];
if ($result_recent_sales) {
    while ($row = mysqli_fetch_assoc($result_recent_sales)) {
        $recent_sales[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - St. Mark DrugStore</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .date-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
            background-color: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .date-filter label {
            font-weight: 500;
            margin-right: 5px;
        }
        
        .date-filter input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .date-filter button {
            padding: 8px 15px;
            background-color: #00b7ff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .date-filter button:hover {
            background-color: #0095d9;
        }
        
        .sales-by-cashier {
            margin-top: 30px;
        }
        
        .cashier-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .cashier-table th, .cashier-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cashier-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .cashier-table tr:last-child td {
            border-bottom: none;
        }
        
        .cashier-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .cashier-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cashier-avatar {
            width: 36px;
            height: 36px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .progress-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #00b7ff;
            border-radius: 4px;
        }
        
        .sales-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .summary-card h3 {
            margin-top: 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: #212529;
            margin: 10px 0;
        }
        
        .summary-change {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        .summary-change.positive {
            color: #28a745;
        }
        
        .summary-change.negative {
            color: #dc3545;
        }
        
        .two-column {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }
        
        @media (max-width: 992px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }
        
        .recent-sales {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .recent-sales h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .recent-sales-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .recent-sales-table th, .recent-sales-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .recent-sales-table th {
            font-weight: 600;
            color: #495057;
        }
        
        .recent-sales-table tr:last-child td {
            border-bottom: none;
        }
        
        .top-products {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .top-products h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-name {
            flex: 1;
        }
        
        .product-sales {
            font-weight: 600;
            color: #212529;
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
              <li class="active" data-title="Sales History"><a href="sales-history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
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
                    <h2>Sales History</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="date-filter">
                <form method="GET" action="" class="filter-form">
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div>
                            <label for="start_date">From:</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div>
                            <label for="end_date">To:</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div>
                            <label for="cashier_id">Cashier:</label>
                            <select id="cashier_id" name="cashier_id" style="padding: 8px 12px; border: 1px solid #e0e0e0; border-radius: 5px; font-size: 14px;">
                                <option value="">All Cashiers</option>
                                <?php foreach ($cashiers as $cashier): ?>
                                    <option value="<?php echo $cashier['user_ID']; ?>" <?php echo ($cashier_filter == $cashier['user_ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cashier['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit">Apply Filter</button>
                        <?php if (!empty($cashier_filter) || $start_date != date('Y-m-d', strtotime('-30 days')) || $end_date != date('Y-m-d')): ?>
                            <a href="sales-history.php" style="padding: 8px 15px; background-color: #6c757d; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px;">Reset Filters</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="sales-summary">
                <div class="summary-card">
                    <h3>Total Sales (All Time)</h3>
                    <div class="summary-value">₱<?php echo number_format($total_sales, 2); ?></div>
                </div>
                
                <div class="summary-card">
                    <h3>Today's Sales</h3>
                    <div class="summary-value">₱<?php echo number_format($today_sales, 2); ?></div>
                    <?php 

                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $query_yesterday = "SELECT SUM(total_amount) as yesterday_sales FROM sale WHERE DATE(sale_date) = '$yesterday'";
                    $result_yesterday = mysqli_query($conn, $query_yesterday);
                    $yesterday_sales = 0;
                    if ($result_yesterday) {
                        $row = mysqli_fetch_assoc($result_yesterday);
                        $yesterday_sales = $row['yesterday_sales'] ? $row['yesterday_sales'] : 0;
                    }
                    
                    $change = 0;
                    $change_percent = 0;
                    if ($yesterday_sales > 0) {
                        $change = $today_sales - $yesterday_sales;
                        $change_percent = ($change / $yesterday_sales) * 100;
                    }
                    ?>
                    <div class="summary-change <?php echo $change >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-<?php echo $change >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                        <?php echo abs(number_format($change_percent, 1)); ?>% from yesterday
                    </div>
                </div>
                
                <div class="summary-card">
                    <h3>Sales in Selected Period</h3>
                    <?php 
                    $period_total = array_sum($daily_data);
                    ?>
                    <div class="summary-value">₱<?php echo number_format($period_total, 2); ?></div>
                    <div>
                        <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($cashier_filter)): 
                // Get the cashier name
                $cashier_name = "";
                foreach ($cashiers as $cashier) {
                    if ($cashier['user_ID'] == $cashier_filter) {
                        $cashier_name = $cashier['full_name'];
                        break;
                    }
                }
            ?>
                <div style="margin-bottom: 20px; background-color: #f8f9fa; padding: 15px; border-radius: 10px; border-left: 4px solid #00b7ff;">
                    <h3 style="margin: 0; color: #495057;">
                        <i class="fas fa-user"></i> Showing sales for: <?php echo htmlspecialchars($cashier_name); ?>
                    </h3>
                </div>
            <?php endif; ?>
            
            <div class="chart-card">
                <h3>Sales Trend</h3>
                <div class="chart-wrapper" style="height: 300px;">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
            
            <div class="sales-by-cashier">
                <h3>Sales by Cashier</h3>
                <?php if (empty($sales_by_cashier)): ?>
                    <div class="no-data" style="text-align: center; padding: 20px; background: white; border-radius: 10px;">
                        No sales data available for the selected period
                    </div>
                <?php else: ?>
                    <table class="cashier-table">
                        <thead>
                            <tr>
                                <th>Cashier</th>
                                <th>Transactions</th>
                                <th>Total Sales</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Find the highest sales for percentage calculation
                            $highest_sales = max(array_column($sales_by_cashier, 'total_sales'));
                            
                            foreach ($sales_by_cashier as $cashier): 
                                $percentage = ($cashier['total_sales'] / $highest_sales) * 100;
                            ?>
                                <tr>
                                    <td>
                                        <div class="cashier-name">
                                            <div class="cashier-avatar">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <?php echo htmlspecialchars($cashier['full_name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($cashier['total_transactions']); ?></td>
                                    <td>₱<?php echo number_format($cashier['total_sales'], 2); ?></td>
                                    <td style="width: 30%;">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="two-column">
                <div class="recent-sales">
                    <h3>Recent Sales</h3>
                    <?php if (empty($recent_sales)): ?>
                        <div class="no-data" style="text-align: center; padding: 20px;">
                            No recent sales data available
                        </div>
                    <?php else: ?>
                        <table class="recent-sales-table">
                            <thead>
                                <tr>
                                    <th>Sale ID</th>
                                    <th>Date & Time</th>
                                    <th>Cashier</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td>#<?php echo $sale['sale_id']; ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($sale['sale_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($sale['cashier']); ?></td>
                                        <td>₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td><a href="receipt.php?id=<?php echo $sale['sale_id']; ?>" class="view-btn">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="top-products">
                    <h3>Top Selling Products</h3>
                    <?php if (empty($top_products)): ?>
                        <div class="no-data" style="text-align: center; padding: 20px;">
                            No product sales data available
                        </div>
                    <?php else: ?>
                        <?php foreach ($top_products as $index => $product): ?>
                            <?php if ($index < 5): // Show only top 5 ?>
                                <div class="product-item">
                                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="product-sales">₱<?php echo number_format($product['total_revenue'], 2); ?></div>
                                </div>
                            <?php endif; ?>
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
            

            const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
            const salesTrendChart = new Chart(salesTrendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($daily_labels); ?>,
                    datasets: [{
                        label: 'Daily Sales',
                        data: <?php echo json_encode($daily_data); ?>,
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
                }
            });
        });
    </script>
</body>
</html>
