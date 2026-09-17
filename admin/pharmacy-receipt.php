<?php
ob_start();
$pageTitle = "Pharmacy Receipt";
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'pharmacist']);

$db = Database::getConnection();
$receiptNo = sanitize($_GET['receipt_no'] ?? '');

$sale = [];

if (!empty($receiptNo)) {
    // 1. Try fetching from pharmacy_sales
    try {
        $stmt = $db->prepare("
            SELECT s.*, 
                   COALESCE(u.name, 'Walk-In Customer') as patient_name,
                   COALESCE(pi.brand_name, pi.medicine_name, pi.name, 'Dispensed Medicine') as medicine_display_name
            FROM pharmacy_sales s
            LEFT JOIN patients p ON s.patient_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN pharmacy_inventory pi ON s.medicine_id = pi.id
            WHERE s.receipt_no = ?
            LIMIT 1
        ");
        $stmt->execute([$receiptNo]);
        $sale = $stmt->fetch();
    } catch (\PDOException $e) {
        $sale = [];
    }

    // 2. Fallback: Try fetching from invoices table if sales record is missing
    if (empty($sale)) {
        try {
            $stmtInv = $db->prepare("
                SELECT i.invoice_number as receipt_no, i.created_at, i.patient_id, 
                       COALESCE(u.name, 'Walk-In Customer') as patient_name,
                       COALESCE(i.total_amount, i.amount, i.grand_total, 0.00) as total_amount,
                       'Cash' as payment_method, 1 as quantity, 0.00 as unit_price,
                       'Prescription Order' as medicine_display_name
                FROM invoices i
                LEFT JOIN patients p ON i.patient_id = p.id
                LEFT JOIN users u ON p.user_id = u.id
                WHERE i.invoice_number = ?
                LIMIT 1
            ");
            $stmtInv->execute([$receiptNo]);
            $sale = $stmtInv->fetch();
        } catch (\PDOException $e) {
            $sale = [];
        }
    }
}

if (empty($sale)) {
?>
    <div class="container my-5" style="max-width: 500px;">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
            <div class="text-danger mb-3">
                <i class="bi bi-exclamation-triangle-fill display-4"></i>
            </div>
            <h4 class="fw-bold text-dark">Receipt Not Found</h4>
            <p class="text-muted small">No recorded sale matching receipt number <code><?= sanitize($receiptNo) ?></code> was found in the database.</p>
            <div class="mt-3">
                <a href="pharmacy-pos.php" class="btn btn-primary fw-bold rounded-pill px-4">Back to POS</a>
            </div>
        </div>
    </div>
<?php
    require_once __DIR__ . '/../includes/footer.php';
    ob_end_flush();
    exit();
}

$recNumber = $sale['receipt_no'] ?? $receiptNo;
$recDate   = $sale['created_at'] ?? 'now';
$patName   = $sale['patient_name'] ?? 'Walk-In Customer';
$payMethod = $sale['payment_method'] ?? 'Cash';
$medName   = $sale['medicine_display_name'] ?? 'Dispensed Item';
$qty       = (int)($sale['quantity'] ?? 1);
$unitPrice = (float)($sale['unit_price'] ?? ($sale['total_amount'] ?? 0.00));
$totalAmt  = (float)($sale['total_amount'] ?? ($unitPrice * $qty));
if ($unitPrice == 0 && $totalAmt > 0) { $unitPrice = $totalAmt / max(1, $qty); }
?>

<div class="container my-5" style="max-width: 480px;">
    <div class="card border shadow-sm p-4 rounded-4" id="printable-receipt">
        <div class="text-center mb-3">
            <h4 class="fw-bold mb-0 text-primary">CarePlus Pharmacy</h4>
            <small class="text-muted">Official Payment & Dispense Receipt</small>
            <hr class="my-3">
        </div>

        <div class="mb-3 small">
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Receipt No:</span>
                <code class="fw-bold text-dark"><?= sanitize($recNumber) ?></code>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Date & Time:</span>
                <span class="fw-semibold text-dark"><?= formatDate($recDate) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Patient Name:</span>
                <span class="fw-semibold text-dark"><?= sanitize($patName) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Payment Method:</span>
                <span class="badge bg-light text-dark border"><?= sanitize($payMethod) ?></span>
            </div>
        </div>

        <table class="table table-sm align-middle border-top border-bottom small mb-3">
            <thead class="table-light">
                <tr>
                    <th>Item Description</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-semibold text-dark"><?= sanitize($medName) ?></td>
                    <td class="text-center"><?= $qty ?></td>
                    <td class="text-end"><?= formatCurrency($unitPrice) ?></td>
                    <td class="text-end fw-bold text-dark"><?= formatCurrency($totalAmt) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center fw-bold fs-5 mb-4 p-2 bg-light rounded-3">
            <span class="text-dark">Total Paid:</span>
            <span class="text-success"><?= formatCurrency($totalAmt) ?></span>
        </div>

        <div class="text-center text-muted small mb-4">
            <p class="mb-0">Thank you for choosing CarePlus Hospital!</p>
            <small>Keep this receipt for your personal medical records.</small>
        </div>

        <div class="d-print-none text-center">
            <button onclick="window.print()" class="btn btn-primary fw-bold rounded-pill px-4 me-2 shadow-sm">
                <i class="bi bi-printer me-1"></i> Print Receipt
            </button>
            <a href="pharmacy-pos.php" class="btn btn-outline-secondary rounded-pill px-3">
                Return to POS
            </a>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>