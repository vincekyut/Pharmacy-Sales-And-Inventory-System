<?php
session_start();
include 'db_connect.php';

// error report
error_reporting(E_ALL);
ini_set('display_errors', 1);

// no caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$back_url = 'cashier_dashboard.php'; 

// referrer URL parsing
if (!empty($referrer)) {
    $path_parts = parse_url($referrer);
    if (isset($path_parts['path'])) {
        $path_segments = explode('/', $path_parts['path']);
        $filename = end($path_segments);
        if (!empty($filename)) {
            $back_url = $filename;
        }
    }
}


if (isset($_GET['id'])) {

    unset($_SESSION['receipt_data']);
    
    $sale_id = $_GET['id'];
    
    // sale information
    $sale_query = "SELECT * FROM sale WHERE sale_id = ?";
    $stmt = mysqli_prepare($conn, $sale_query);
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    mysqli_stmt_execute($stmt);
    $sale_result = mysqli_stmt_get_result($stmt);
    $sale = mysqli_fetch_assoc($sale_result);
    if (!$sale) {
        error_log("Sale not found for ID: $sale_id");
        header("Location: $back_url");
        exit();
    }
    // Get cashier full name from user_list
    $cashier_fullname = "Cashier";
    if (!empty($sale['cashier_ID'])) {
        $user_query = "SELECT full_name FROM user_list WHERE user_ID = ?";
        $user_stmt = mysqli_prepare($conn, $user_query);
        mysqli_stmt_bind_param($user_stmt, "i", $sale['cashier_ID']);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        if ($user_row = mysqli_fetch_assoc($user_result)) {
            $cashier_fullname = $user_row['full_name'];
        }
    }
    
    // debug output
    error_log("Sale data for ID $sale_id: " . print_r($sale, true));
    error_log("Invoice number from database: " . $sale['invoice_number']);
    
    // sale items
    $items_query = "SELECT si.*, p.product_name, p.prod_measure FROM sale_item si 
                    JOIN product_list p ON si.product_id = p.product_id 
                    WHERE si.sale_id = ?";
    $stmt = mysqli_prepare($conn, $items_query);
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    mysqli_stmt_execute($stmt);
    $items_result = mysqli_stmt_get_result($stmt);
    
    $items = array();
    $subtotal = 0;
    
    while ($item = mysqli_fetch_assoc($items_result)) {
        $items[] = array(
            'id' => $item['product_id'],
            'name' => $item['product_name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'measure' => $item['prod_measure']

        );
        
        $subtotal += $item['subtotal'];
    }
    
    // discount calculation
    $discount_amount = 0;
    if ($sale['discount_type'] == 'pwd' || $sale['discount_type'] == 'senior') {
        $discount_amount = $subtotal * 0.20; // 20% discount
    }
    
    // set session data
    $_SESSION['receipt_data'] = array(
        'invoice_number' => $sale['invoice_number'],
        'sale_id' => $sale_id,
        'items' => $items,
        'subtotal' => $subtotal,
        'discount_type' => $sale['discount_type'],
        'discount_amount' => $discount_amount,
        'total_amount' => $sale['total_amount'],
        'cash_payment' => $sale['cash_payment'],
        'change' => $sale['cash_payment'] - $sale['total_amount'],
        'sale_date' => $sale['sale_date'],
        'cashier_ID' => $sale['cashier_ID'],
        'cashier_fullname' => $cashier_fullname
    );
    

    error_log("Receipt data set in session: " . print_r($_SESSION['receipt_data'], true));
} else if (!isset($_SESSION['receipt_data'])) {
    header("Location: $back_url");
    exit();
}

$receipt = $_SESSION['receipt_data'];
error_log("Receipt data retrieved from session: " . print_r($receipt, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - St. Mark Drug Store</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");
        
        :root {
            --primary-color: #0075fc;
            --primary-light: #e6f2ff;
            --secondary-color: #00b7ff;
            --accent-color: #00d1b2;
            --text-color: #333;
            --text-light: #666;
            --text-lighter: #999;
            --border-color: #eaeaea;
            --bg-color: #f8fafc;
            --white: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
        }
        
        .page-container {
            width: 100%;
            max-width: 1000px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
        }
        
        .header-actions {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .left-actions, .right-actions {
            flex: 1;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-color);
            margin: 0 20px;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 117, 252, 0.2);
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 117, 252, 0.3);
        }
        
        .btn-back i {
            font-size: 12px;
        }
        
        @media (max-width: 600px) {
            .header-actions {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .page-title {
                order: -1;
                margin: 0 0 10px 0;
            }
        }
        
        .receipt-container {
            background-color: var(--white);
            width: 100%;
            max-width: 500px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .receipt-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }
        
        .receipt-header::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 0;
            right: 0;
            height: 1px;
            background: repeating-linear-gradient(90deg, var(--border-color), var(--border-color) 5px, transparent 5px, transparent 12px);
        }
        
        .receipt-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .receipt-header p {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 3px;
        }
        
        .receipt-info {
            margin-bottom: 30px;
            background-color: var(--primary-light);
            padding: 15px;
            border-radius: 10px;
        }
        
        .receipt-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .receipt-info-row:last-child {
            margin-bottom: 0;
        }
        
        .receipt-info-row .label {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .receipt-items {
            margin-bottom: 30px;
        }
        
        .receipt-items-header {
            display: flex;
            justify-content: space-between;
            padding-bottom: 10px;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .receipt-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed var(--border-color);
        }
        
        .receipt-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .receipt-item-header {
            display: flex;
            justify-content: space-between;
            font-weight: 500;
            font-size: 15px;
            margin-bottom: 5px;
        }
        
        .receipt-item-details {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: var(--text-lighter);
        }
        
        .receipt-summary {
            margin-bottom: 30px;
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
        }
        
        .receipt-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .receipt-summary-row.total {
            font-weight: 700;
            font-size: 18px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px dashed var(--border-color);
            color: var(--primary-color);
        }
        
        .receipt-summary-row.payment, .receipt-summary-row.change {
            font-weight: 500;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 30px;
            position: relative;
            padding-top: 20px;
        }
        
        .receipt-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: repeating-linear-gradient(90deg, var(--border-color), var(--border-color) 5px, transparent 5px, transparent 12px);
        }
        
        .receipt-footer p {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 5px;
        }
        
        .receipt-footer .tagline {
            font-size: 16px;
            font-weight: 600;
            color: var(--accent-color);
            margin-top: 10px;
        }
        
        .receipt-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            width: 100%;
            max-width: 500px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            flex: 1;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 10px rgba(0, 117, 252, 0.2);
        }
        
        .btn-primary:hover {
            background-color: #0062d3;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 117, 252, 0.3);
        }
        
        .btn-secondary {
            background-color: #f1f5f9;
            color: var(--text-color);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .btn-secondary:hover {
            background-color: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        }
        
        .qr-code {
            margin: 20px auto 0;
            width: 100px;
            height: 100px;
            background-color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        
        .qr-code img {
            width: 80px;
            height: 80px;
        }
        
        .barcode {
            margin: 20px auto 0;
            text-align: center;
        }
        
        .barcode img {
            max-width: 80%;
            height: 50px;
        }
        
        .barcode-text {
            font-size: 12px;
            color: var(--text-lighter);
            margin-top: 5px;
        }
        
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            
            .page-container {
                max-width: 100%;
            }
            
            .header-actions, .receipt-actions {
                display: none;
            }
            
            .receipt-container {
                box-shadow: none;
                max-width: 100%;
                padding: 20px;
                margin: 0;
            }
            
            @page {
                margin: 0.5cm;
                size: 80mm 297mm;
            }
        }
        
        @media (max-width: 600px) {
            .receipt-container {
                padding: 30px 20px;
            }
            
            .receipt-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <div style="background-color: #f0f0f0; padding: 10px; margin-bottom: 20px; border-radius: 5px; display: none;">
        <p><strong>Debug Info:</strong></p>
        <p>Sale ID: <?php echo isset($_GET['id']) ? $_GET['id'] : 'Not set'; ?></p>
        <p>Invoice Number: <?php echo $receipt['invoice_number']; ?></p>
    </div>
    
    <div class="page-container">
    <div class="header-actions">
        <div class="left-actions">
            <a href="<?php echo $back_url; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
        <h1 class="page-title">Receipt <?php echo $receipt['invoice_number']; ?></h1>
        <div class="right-actions">
        </div>
    </div>
        
        <div class="receipt-container" id="receipt">
            <div class="receipt-header">
                <h1>St. Mark Drug Store</h1>
                <p>Your Health, Our Priority</p>
                <p>Bolingit, San Carlos City Pangasinan</p>
                <p>Phone: (639) 122354762</p>
                <p>stmarkdrugstore@gmail.com</p>
            </div>
            
            <div class="receipt-info">
                <div class="receipt-info-row">
                    <span class="label">Invoice #:</span>
                    <span><?php echo $receipt['invoice_number']; ?></span>
                </div>
                <div class="receipt-info-row">
                    <span class="label">Date:</span>
                    <span><?php echo date('M d, Y h:i A', strtotime($receipt['sale_date'])); ?></span>
                </div>
                <div class="receipt-info-row">
                    <span class="label">Cashier:</span>
                    <span><?php echo isset($receipt['cashier_fullname']) ? htmlspecialchars($receipt['cashier_fullname']) : "Cashier"; ?></span>
                </div>
            </div>
            
            <div class="receipt-items">
                <div class="receipt-items-header">
                    <span>Item</span>
                    <span>Amount</span>
                </div>
                <?php foreach($receipt['items'] as $item): ?>
                    <div class="receipt-item">
                        <div class="receipt-item-header">
                            <span><?php echo htmlspecialchars($item['name']); ?></span>
                            <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                        <div class="receipt-item-details">
                            <span><?php echo $item['quantity']; ?> x ₱<?php echo number_format($item['price'], 2); ?> (<?php echo htmlspecialchars($item['measure']); ?>)</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="receipt-summary">
                <div class="receipt-summary-row">
                    <span>Subtotal:</span>
                    <span>₱<?php echo number_format($receipt['subtotal'], 2); ?></span>
                </div>
                
                <?php if($receipt['discount_amount'] > 0): ?>
                    <div class="receipt-summary-row">
                        <span>Discount (<?php echo $receipt['discount_type'] == 'pwd' ? 'PWD' : 'Senior Citizen'; ?> 20%):</span>
                        <span>₱<?php echo number_format($receipt['discount_amount'], 2); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="receipt-summary-row total">
                    <span>Total:</span>
                    <span>₱<?php echo number_format($receipt['total_amount'], 2); ?></span>
                </div>
                
                <div class="receipt-summary-row payment">
                    <span>Cash Payment:</span>
                    <span>₱<?php echo number_format($receipt['cash_payment'], 2); ?></span>
                </div>
                
                <div class="receipt-summary-row change">
                    <span>Change:</span>
                    <span>₱<?php echo number_format($receipt['change'], 2); ?></span>
                </div>
            </div>
            
            <div class="barcode">
                <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($receipt['invoice_number']); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=96&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&codepage=&qunit=Mm&quiet=0" alt="Barcode">
                <div class="barcode-text"><?php echo $receipt['invoice_number']; ?></div>
            </div>
            
            <div class="receipt-footer">
                <p>Thank you for shopping at St. Mark Drug Store!</p>
                <p>This receipt serves as your official proof of purchase.</p>
                <p class="tagline">Get Well Soon!</p>
            </div>
        </div>
        
        <div class="receipt-actions">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <button class="btn btn-secondary" onclick="downloadPDF()">
                <i class="fas fa-download"></i> Download PDF
            </button>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        // Download PDF function
        function downloadPDF() {
            const element = document.getElementById('receipt');
            const timestamp = new Date().getTime();
            const opt = {
                margin: [10, 10, 10, 10],
                filename: '<?php echo $receipt['invoice_number']; ?>-receipt-' + timestamp + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: [80, 297], orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save();
        }
        
        // back button functionality
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>
