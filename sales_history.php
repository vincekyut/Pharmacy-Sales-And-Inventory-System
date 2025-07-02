<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "cashier") {
    header("Location: index.php");
    exit();
}




$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$search_term = isset($_GET['search']) ? $_GET['search'] : '';


$query = "SELECT s.*, SUM(si.quantity) as item_count 
          FROM sale s 
          JOIN sale_item si ON s.sale_id = si.sale_id
          WHERE 1";

if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(s.sale_date) BETWEEN '$start_date' AND '$end_date'";
}

if (!empty($search_term)) {
    $query .= " AND (s.invoice_number LIKE '%$search_term%' OR s.customer_name LIKE '%$search_term%')";
}


$query .= " GROUP BY s.sale_id ORDER BY s.sale_date DESC";

$result = mysqli_query($conn, $query);

$summary_query = "SELECT 
                    COUNT(DISTINCT s.sale_id) as total_transactions,
                    SUM(s.total_amount) as total_sales,
                    AVG(s.total_amount) as average_sale,
                    COUNT(DISTINCT DATE(s.sale_date)) as unique_days
                  FROM sale s
                  WHERE 1";

if (!empty($start_date) && !empty($end_date)) {
    $summary_query .= " AND DATE(s.sale_date) BETWEEN '$start_date' AND '$end_date'";
}

$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - St. Mark DrugStore Pharmacy</title>
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
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        .summary-card h4 {
            font-size: 14px;
            color: #666;
            margin: 0 0 10px 0;
            font-weight: 500;
        }
        .summary-card p {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
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
                <li class="active" data-title="Sales History"><a href="sales_history.php"><i class="fas fa-history"></i> <span>Sales History</span></a></li>
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
                    <h2>Sales History</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="filter-container">
                <form class="filter-form" method="GET" action="sales_history.php">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Invoice # or Customer" value="<?php echo $search_term; ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="filter-btn">Apply Filters</button>
                        <a href="sales_history.php" class="reset-btn">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="summary-stats">
                <div class="summary-card">
                    <h4>Total Transactions</h4>
                    <p><?php echo $summary['total_transactions'] ? $summary['total_transactions'] : 0; ?></p>
                </div>
                
                <div class="summary-card">
                    <h4>Total Sales</h4>
                    <p>₱<?php echo number_format($summary['total_sales'] ? $summary['total_sales'] : 0, 2); ?></p>
                </div>
                
                <div class="summary-card">
                    <h4>Average Sale</h4>
                    <p>₱<?php echo number_format($summary['average_sale'] ? $summary['average_sale'] : 0, 2); ?></p>
                </div>
                
                <div class="summary-card">
                    <h4>Days with Sales</h4>
                    <p><?php echo $summary['unique_days'] ? $summary['unique_days'] : 0; ?></p>
                </div>
            </div>
            
            <?php if(mysqli_num_rows($result) > 0): ?>
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
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo $row['invoice_number'] ? $row['invoice_number'] : 'INV-' . sprintf('%03d', $row['sale_id']); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($row['sale_date'])); ?></td>
                            <td><?php echo $row['item_count']; ?></td>
                            <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td><a href="receipt.php?id=<?php echo $row['sale_id']; ?>" class="view-btn">View</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <div class="pagination">
                    <a href="#" class="active">1</a>
                    <a href="#">2</a>
                    <a href="#">3</a>
                    <a href="#">Next</a>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No sales found</h3>
                    <p>Try adjusting your search filters or date range</p>
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
    </script>
</body>
</html>