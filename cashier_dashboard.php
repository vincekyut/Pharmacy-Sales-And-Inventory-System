<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "cashier") {
    header("Location: index.php");
    exit();
}

$cashier_id = $_SESSION["user_id"];

$product_query = "SELECT * FROM product_list ORDER BY product_name";
$product_result = mysqli_query($conn, $product_query);
$total_products = mysqli_num_rows($product_result);

$today = date('Y-m-d');
$sales_query = "SELECT SUM(s.total_amount) as today_sales 
                FROM sale s 
                WHERE DATE(s.sale_date) = '$today' AND s.cashier_id = $cashier_id";
$sales_result = mysqli_query($conn, $sales_query);
$sales_data = mysqli_fetch_assoc($sales_result);
$today_sales = $sales_data['today_sales'] ? $sales_data['today_sales'] : 0;

$overall_sales_query = "SELECT SUM(si.total_amount) as overall_sales 
                        FROM sale si 
                        JOIN sale s ON si.sale_id = s.sale_id 
                        WHERE DATE(s.sale_date) = '$today'";
$overall_sales_result = mysqli_query($conn, $overall_sales_query);
$overall_sales_data = mysqli_fetch_assoc($overall_sales_result);
$overall_sales = $overall_sales_data['overall_sales'] ? $overall_sales_data['overall_sales'] : 0;

$sales_percentage = ($overall_sales > 0) ? ($today_sales / $overall_sales) * 100 : 0;

$transaction_query = "SELECT COUNT(DISTINCT s.sale_id) as transaction_count 
                      FROM sale s 
                      WHERE DATE(s.sale_date) = '$today' AND s.cashier_id = $cashier_id";
$transaction_result = mysqli_query($conn, $transaction_query);
$transaction_data = mysqli_fetch_assoc($transaction_result);
$transaction_count = $transaction_data['transaction_count'];

$recent_sales_query = "SELECT s.*, SUM(si.quantity) as item_count 
                      FROM sale s 
                      JOIN sale_item si ON s.sale_id = si.sale_id
                      WHERE s.cashier_id = $cashier_id
                      GROUP BY s.sale_id
                      ORDER BY s.sale_date DESC LIMIT 5";
$recent_sales_result = mysqli_query($conn, $recent_sales_query);

$all_time_sales_query = "SELECT SUM(si.total_amount) as all_time_sales 
                         FROM sale si 
                         JOIN sale s ON si.sale_id = s.sale_id 
                         WHERE s.cashier_id = $cashier_id";
$all_time_sales_result = mysqli_query($conn, $all_time_sales_query);
$all_time_sales_data = mysqli_fetch_assoc($all_time_sales_result);
$all_time_sales = $all_time_sales_data['all_time_sales'] ? $all_time_sales_data['all_time_sales'] : 0;

$all_time_transactions_query = "SELECT COUNT(DISTINCT sale_id) as all_time_transactions 
                               FROM sale 
                               WHERE cashier_id = $cashier_id";
$all_time_transactions_result = mysqli_query($conn, $all_time_transactions_query);
$all_time_transactions_data = mysqli_fetch_assoc($all_time_transactions_result);
$all_time_transactions = $all_time_transactions_data['all_time_transactions'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - St. MArk DrugStore Pharmacy</title>
    <link rel="stylesheet" href="css/cashier.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #00b7ff, #0075fc);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .stat-icon i {
            color: white;
            font-size: 24px;
        }
        .stat-info {
            flex-grow: 1;
        }
        .stat-info h3 {
            font-size: 14px;
            color: #666;
            margin: 0 0 5px 0;
            font-weight: 500;
        }
        .stat-info p {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .stat-info .percentage {
            font-size: 14px;
            color: #28a745;
            margin-top: 5px;
        }
        .stat-info .percentage.negative {
            color: #dc3545;
        }
        .performance-indicator {
            display: flex;
            align-items: center;
            margin-top: 5px;
            font-size: 13px;
        }
        .performance-indicator i {
            margin-right: 5px;
        }
        .performance-indicator.positive {
            color: #28a745;
        }
        .performance-indicator.negative {
            color: #dc3545;
        }
        .performance-indicator.neutral {
            color: #6c757d;
        }
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .sales-table th, .sales-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .sales-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        .sales-table tr:last-child td {
            border-bottom: none;
        }
        .sales-table tr:hover {
            background-color: #f8f9fa;
        }
        .view-btn {
            display: inline-block;
            padding: 6px 12px;
            background-color: #00b7ff;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            transition: background-color 0.3s ease;
        }
        .view-btn:hover {
            background-color: #0075fc;
        }
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .section-title h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        .personal-stats-label {
            background-color: #e6f7ff;
            color: #0075fc;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
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
                <li class="active" data-title="Dashboard"><a href="cashier_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li data-title="New Sale"><a href="new_sale.php"><i class="fas fa-shopping-cart"></i> <span>New Sale</span></a></li>
                <li data-title="Sales History"><a href="sales_history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
                <li data-title="Product Search"><a href="product_search.php"><i class="fas fa-search"></i> <span>Product Search</span></a></li>
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
                    <h2>My Dashboard</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3>My Today's Sales</h3>
                        <p>₱<?php echo number_format($today_sales, 2); ?></p>
                        <div class="performance-indicator <?php echo ($sales_percentage >= 30) ? 'positive' : (($sales_percentage >= 10) ? 'neutral' : 'negative'); ?>">
                            <i class="fas <?php echo ($sales_percentage >= 30) ? 'fa-chart-line' : (($sales_percentage >= 10) ? 'fa-equals' : 'fa-chart-line-down'); ?>"></i>
                            <span><?php echo round($sales_percentage, 1); ?>% of store total</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>My Transactions Today</h3>
                        <p><?php echo $transaction_count; ?></p>
                        <div class="performance-indicator neutral">
                            <i class="fas fa-cash-register"></i>
                            <span>Avg. ₱<?php echo ($transaction_count > 0) ? number_format($today_sales / $transaction_count, 2) : '0.00'; ?> per transaction</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stat-info">
                        <h3>My All-Time Sales</h3>
                        <p>₱<?php echo number_format($all_time_sales, 2); ?></p>
                        <div class="performance-indicator neutral">
                            <i class="fas fa-history"></i>
                            <span>Across <?php echo $all_time_transactions; ?> transactions</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Store Total Today</h3>
                        <p>₱<?php echo number_format($overall_sales, 2); ?></p>
                        <div class="performance-indicator neutral">
                            <i class="fas fa-users"></i>
                            <span>From all cashiers</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="action-buttons">
                    <a href="new_sale.php" class="action-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <span>New Sale</span>
                    </a>
                    
                    <a href="product_search.php" class="action-btn">
                        <i class="fas fa-search"></i>
                        <span>Find Product</span>
                    </a>
                    
                    <a href="sales_history.php" class="action-btn">
                        <i class="fas fa-history"></i>
                        <span>View History</span>
                    </a>
                </div>
            </div>
            
            <div class="recent-sales">
                <div class="section-title">
                    <h3>My Recent Sales</h3>
                    <span class="personal-stats-label">Personal Activity</span>
                </div>
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date & Time</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($recent_sales_result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($recent_sales_result)): ?>
                            <tr>
                                <td><?php echo $row['invoice_number'] ? $row['invoice_number'] : 'INV-' . sprintf('%03d', $row['sale_id']); ?></td>
                                <td><?php echo date('M d, h:i A', strtotime($row['sale_date'])); ?></td>
                                <td><?php echo $row['item_count']; ?></td>
                                <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td><a href="receipt.php?id=<?php echo $row['sale_id']; ?>" class="view-btn">View</a></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No recent sales found</td>
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
        });
    </script>
</body>
</html>
