<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: index.php");
    exit();
}


$archive_query = "SELECT pb.*, pl.product_name FROM product_batches pb JOIN product_list pl ON pb.product_id = pl.product_id WHERE pb.status = 'archived' OR pb.quantity = 0 ORDER BY pb.receipt_date DESC";
$archive_result = mysqli_query($conn, $archive_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Products - St. Mark DrugStore</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: #f4f7fa;
            font-family: 'Segoe UI', 'Arial', sans-serif;
            color: #333;
        }
        .main-content {
            max-width: 950px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 32px rgba(0,0,0,0.08);
            padding: 40px 36px 32px 36px;
        }
        h2 {
            font-size: 2.1rem;
            font-weight: 700;
            color: #0075fc;
            margin-bottom: 18px;
            letter-spacing: 1px;
        }
        .archive-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .archive-table th, .archive-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #ebe9f1;
            text-align: left;
        }
        .archive-table th {
            background: #f3f7fb;
            font-weight: 700;
            color: #0075fc;
            font-size: 1.08rem;
            letter-spacing: 0.5px;
        }
        .archive-table tr:last-child td {
            border-bottom: none;
        }
        .archive-table tbody tr:hover {
            background: #f0f6ff;
            transition: background 0.2s;
        }
        .status-archived {
            background: #e0e0e0;
            color: #646464;
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 28px;
            color: #fff;
            background: linear-gradient(90deg, #0075fc 60%, #00c6fb 100%);
            padding: 10px 26px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 2px 8px rgba(0,117,252,0.08);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .back-btn:hover {
            background: linear-gradient(90deg, #005bb5 60%, #009ec3 100%);
            box-shadow: 0 4px 16px rgba(0,117,252,0.13);
        }
        @media (max-width: 700px) {
            .main-content { padding: 18px 4px; }
            .archive-table th, .archive-table td { padding: 10px 6px; font-size: 0.98rem; }
            h2 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <div class="main-content" style="padding: 30px;">
        <h2>Archived Products / Batches</h2>
        <a href="batch_management.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
        <table class="archive-table">
            <thead>
                <tr>
                    <th>Batch #</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($archive_result) > 0): ?>
                    <?php while ($batch = mysqli_fetch_assoc($archive_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                            <td><?php echo htmlspecialchars($batch['product_name']); ?></td>
                            <td><span style="font-weight:600;color:#e74c3c;"><?php echo number_format($batch['quantity']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($batch['expiry_date'])); ?></td>
                            <td><span class="status-archived"><i class="fas fa-archive"></i> Archived</span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;">No archived products found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
