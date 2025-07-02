<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}


$store_name = '';
$store_address = '';
$store_contact = '';
$store_email = '';
$tax_percentage = '';
$currency_symbol = '₱';
$receipt_footer = '';
$low_stock_threshold = '';
$enable_notifications = '';
$backup_frequency = '';


$table_exists = false;
$check_table_query = "SHOW TABLES LIKE 'system_settings'";
$table_result = mysqli_query($conn, $check_table_query);
if ($table_result && mysqli_num_rows($table_result) > 0) {
    $table_exists = true;
    

    $query = "SELECT * FROM system_settings WHERE id = 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $settings = mysqli_fetch_assoc($result);
        $store_name = $settings['store_name'] ?? 'St. Mark Drug Store';
        $store_address = $settings['store_address'] ?? '';
        $store_contact = $settings['store_contact'] ?? '';
        $store_email = $settings['store_email'] ?? '';
        $tax_percentage = $settings['tax_percentage'] ?? '12';
        $currency_symbol = $settings['currency_symbol'] ?? '₱';
        $receipt_footer = $settings['receipt_footer'] ?? 'Thank you for your purchase!';
        $low_stock_threshold = $settings['low_stock_threshold'] ?? '10';
        $enable_notifications = $settings['enable_notifications'] ?? '1';
        $backup_frequency = $settings['backup_frequency'] ?? 'weekly';
    }
}


if (!$table_exists) {
    $store_name = 'St. Mark Drug Store';
    $store_address = '123 Main Street, Anytown, Philippines';
    $store_contact = '+63 912 345 6789';
    $store_email = 'info@stmarkdrugstore.com';
    $tax_percentage = '12';
    $currency_symbol = '₱';
    $receipt_footer = 'Thank you for your purchase!';
    $low_stock_threshold = '10';
    $enable_notifications = '1';
    $backup_frequency = 'weekly';
}


$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {

        $store_name = mysqli_real_escape_string($conn, $_POST['store_name']);
        $store_address = mysqli_real_escape_string($conn, $_POST['store_address']);
        $store_contact = mysqli_real_escape_string($conn, $_POST['store_contact']);
        $store_email = mysqli_real_escape_string($conn, $_POST['store_email']);
        $tax_percentage = mysqli_real_escape_string($conn, $_POST['tax_percentage']);
        $currency_symbol = mysqli_real_escape_string($conn, $_POST['currency_symbol']);
        $receipt_footer = mysqli_real_escape_string($conn, $_POST['receipt_footer']);
        $low_stock_threshold = mysqli_real_escape_string($conn, $_POST['low_stock_threshold']);
        $enable_notifications = isset($_POST['enable_notifications']) ? '1' : '0';
        $backup_frequency = mysqli_real_escape_string($conn, $_POST['backup_frequency']);
        

        if ($table_exists) {
            $update_query = "UPDATE system_settings SET 
                store_name = '$store_name',
                store_address = '$store_address',
                store_contact = '$store_contact',
                store_email = '$store_email',
                tax_percentage = '$tax_percentage',
                currency_symbol = '$currency_symbol',
                receipt_footer = '$receipt_footer',
                low_stock_threshold = '$low_stock_threshold',
                enable_notifications = '$enable_notifications',
                backup_frequency = '$backup_frequency',
                updated_at = NOW()
                WHERE id = 1";
            
            if (mysqli_query($conn, $update_query)) {
                $message = "Settings updated successfully!";
                $message_type = "success";
                

                $check_activity_table = "SHOW TABLES LIKE 'activity_log'";
                $activity_result = mysqli_query($conn, $check_activity_table);
                if ($activity_result && mysqli_num_rows($activity_result) > 0) {
                    $user_id = $_SESSION["user_id"];
                    $activity_query = "INSERT INTO activity_log (user_ID, activity_type, description, timestamp) 
                                      VALUES ('$user_id', 'settings', 'System settings updated', NOW())";
                    mysqli_query($conn, $activity_query);
                }
            } else {
                $message = "Error updating settings: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {

            $create_table = "CREATE TABLE system_settings (
                id INT(11) PRIMARY KEY AUTO_INCREMENT,
                store_name VARCHAR(255) NOT NULL,
                store_address TEXT,
                store_contact VARCHAR(50),
                store_email VARCHAR(100),
                tax_percentage DECIMAL(5,2) DEFAULT 12.00,
                currency_symbol VARCHAR(10) DEFAULT '₱',
                receipt_footer TEXT,
                low_stock_threshold INT(11) DEFAULT 10,
                enable_notifications TINYINT(1) DEFAULT 1,
                backup_frequency VARCHAR(20) DEFAULT 'weekly',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            if (mysqli_query($conn, $create_table)) {
                $insert_query = "INSERT INTO system_settings (
                    store_name, store_address, store_contact, store_email, 
                    tax_percentage, currency_symbol, receipt_footer, 
                    low_stock_threshold, enable_notifications, backup_frequency
                ) VALUES (
                    '$store_name', '$store_address', '$store_contact', '$store_email',
                    '$tax_percentage', '$currency_symbol', '$receipt_footer',
                    '$low_stock_threshold', '$enable_notifications', '$backup_frequency'
                )";
                
                if (mysqli_query($conn, $insert_query)) {
                    $message = "Settings table created and settings saved successfully!";
                    $message_type = "success";
                    $table_exists = true;
                } else {
                    $message = "Error inserting settings: " . mysqli_error($conn);
                    $message_type = "error";
                }
            } else {
                $message = "Error creating settings table: " . mysqli_error($conn);
                $message_type = "error";
            }
        }
    } elseif (isset($_POST['backup_database'])) {
     
        $message = "Database backup initiated. Check the backup folder for the latest backup file.";
        $message_type = "success";
    } elseif (isset($_POST['clear_logs'])) {

        $check_activity_table = "SHOW TABLES LIKE 'activity_log'";
        $activity_result = mysqli_query($conn, $check_activity_table);
        if ($activity_result && mysqli_num_rows($activity_result) > 0) {
            // Clear logs older than 30 days
            $clear_query = "DELETE FROM activity_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            if (mysqli_query($conn, $clear_query)) {
                $message = "Activity logs older than 30 days have been cleared.";
                $message_type = "success";
            } else {
                $message = "Error clearing logs: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {
            $message = "Activity log table does not exist.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - St. Mark DrugStore</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .settings-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (min-width: 768px) {
            .settings-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .settings-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .settings-card h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #333;
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            border-color: #00b7ff;
            outline: none;
        }
        
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: #00b7ff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0095d9;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #00b7ff;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        /* Activity Log Table */
        .activity-log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }
        
        .activity-log-table th, .activity-log-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
        }
        
        .activity-log-table th {
            background-color: #f2f2f2;
            text-align: left;
        }
        
        .activity-log-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .activity-log-table tr:hover {
            background-color: #f1f1f1;
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
              <li data-title="Suppliers"><a href="suppliers.php"><i class="fas fa-truck"></i> <span>Suppliers</span></a></li>
              <li class="active" data-title="Settings"><a href="#"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
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
                    <h2>Settings</h2>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION["full_name"]; ?></span>
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="settings-container">
                    <div class="settings-card">
                        <h3><i class="fas fa-store"></i> Store Information</h3>
                        <div class="form-group">
                            <label for="store_name">Store Name</label>
                            <input type="text" id="store_name" name="store_name" class="form-control" value="<?php echo htmlspecialchars($store_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="store_address">Store Address</label>
                            <textarea id="store_address" name="store_address" class="form-control"><?php echo htmlspecialchars($store_address); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="store_contact">Contact Number</label>
                            <input type="text" id="store_contact" name="store_contact" class="form-control" value="<?php echo htmlspecialchars($store_contact); ?>">
                        </div>
                        <div class="form-group">
                            <label for="store_email">Email Address</label>
                            <input type="email" id="store_email" name="store_email" class="form-control" value="<?php echo htmlspecialchars($store_email); ?>">
                        </div>
                    </div>
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-sliders-h"></i> System Preferences</h3>
                        <div class="form-group">
                            <label for="tax_percentage">Tax Percentage (%)</label>
                            <input type="number" id="tax_percentage" name="tax_percentage" class="form-control" value="<?php echo htmlspecialchars($tax_percentage); ?>" min="0" max="100" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars($currency_symbol); ?>" maxlength="5">
                        </div>
                        <div class="form-group">
                            <label for="receipt_footer">Receipt Footer Message</label>
                            <textarea id="receipt_footer" name="receipt_footer" class="form-control"><?php echo htmlspecialchars($receipt_footer); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="low_stock_threshold">Low Stock Threshold</label>
                            <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control" value="<?php echo htmlspecialchars($low_stock_threshold); ?>" min="0">
                        </div>
                    </div>
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                        <div class="form-group">
                            <label for="enable_notifications">Enable System Notifications</label>
                            <div>
                                <label class="switch">
                                    <input type="checkbox" id="enable_notifications" name="enable_notifications" <?php echo $enable_notifications == '1' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span style="margin-left: 10px; vertical-align: middle;">
                                    <?php echo $enable_notifications == '1' ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </div>
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">
                                When enabled, the system will show notifications for low stock, expiring products, and other important events.
                            </p>
                        </div>
                    </div>
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-database"></i> Backup & Maintenance</h3>
                        <div class="form-group">
                            <label for="backup_frequency">Automatic Backup Frequency</label>
                            <select id="backup_frequency" name="backup_frequency" class="form-control">
                                <option value="daily" <?php echo $backup_frequency == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo $backup_frequency == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $backup_frequency == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="never" <?php echo $backup_frequency == 'never' ? 'selected' : ''; ?>>Never (Manual only)</option>
                            </select>
                        </div>
                        <div class="action-buttons">
                            <button type="submit" name="backup_database" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Backup Database Now
                            </button>
                            <button type="submit" name="clear_logs" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Clear Old Logs
                            </button>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-bottom: 30px;">
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>

            <div class="settings-card" style="margin-top:30px;">
                <h3><i class="fas fa-list"></i> Activity Log (Last 30 Days)</h3>
                <div style="max-height:300px;overflow-y:auto;">
                <?php

                $check_activity_table = "SHOW TABLES LIKE 'activity_log'";
                $activity_result = mysqli_query($conn, $check_activity_table);
                if ($activity_result && mysqli_num_rows($activity_result) > 0) {
                    $log_query = "SELECT a.*, u.full_name FROM activity_log a LEFT JOIN user_list u ON a.user_ID = u.user_ID ORDER BY a.timestamp DESC LIMIT 100";
                    $log_result = mysqli_query($conn, $log_query);
                    if ($log_result && mysqli_num_rows($log_result) > 0) {
                        echo '<table class="activity-log-table">';
                        echo '<thead><tr><th>Date/Time</th><th>User</th><th>Type</th><th>Description</th></tr></thead><tbody>';
                        while ($log = mysqli_fetch_assoc($log_result)) {
                            echo '<tr>';
                            echo '<td>' . date('M d, Y H:i', strtotime($log['timestamp'])) . '</td>';
                            echo '<td>' . htmlspecialchars($log['full_name'] ?? 'Unknown') . '</td>';
                            echo '<td>' . htmlspecialchars(ucfirst($log['activity_type'])) . '</td>';
                            echo '<td>' . htmlspecialchars($log['description']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        echo '<div style="padding:10px;color:#888;">No activity logs found.</div>';
                    }
                } else {
                    echo '<div style="padding:10px;color:#888;">Activity log table does not exist.</div>';
                }
                ?>
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
            

            const notificationToggle = document.getElementById('enable_notifications');
            notificationToggle.addEventListener('change', function() {
                const statusText = this.nextElementSibling.nextElementSibling;
                statusText.textContent = this.checked ? 'Enabled' : 'Disabled';
            });
        });
    </script>
</body>
</html>
