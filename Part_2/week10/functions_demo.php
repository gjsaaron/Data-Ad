<?php
require_once '../db.php';

function fnExists(PDO $pdo, string $fn): bool {
    return (bool) $pdo->prepare("SELECT 1 FROM pg_proc WHERE proname = ?")->execute([$fn]) &&
           (bool) $pdo->prepare("SELECT 1 FROM pg_proc WHERE proname = ?")->execute([$fn])->fetchColumn();
}

function checkFn(PDO $pdo, string $fn): bool {
    $s = $pdo->prepare("SELECT 1 FROM pg_proc WHERE proname = ?");
    $s->execute([$fn]);
    return (bool)$s->fetchColumn();
}

$fn1ok = checkFn($pdo, 'fn_total_borrowed');
$fn2ok = checkFn($pdo, 'fn_check_equipment_availability');
$fn3ok = checkFn($pdo, 'fn_calculate_fine');

$fineResult = null;
if ($fn3ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fine_borrow_id'])) {
    $fineId = (int)($_POST['fine_borrow_id'] ?? 0);
    if ($fineId > 0) {
        $stmt = $pdo->prepare("SELECT fn_calculate_fine(:id)");
        $stmt->execute([':id' => $fineId]);
        $fineResult = $stmt->fetchColumn();
    }
}

$checkResult = null;
if ($fn2ok && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $eqId = (int)($_POST['equip_id'] ?? 0);
    $qty  = (int)($_POST['qty']      ?? 0);
    if ($eqId > 0 && $qty > 0) {
        $stmt = $pdo->prepare("SELECT fn_check_equipment_availability(:e, :q)");
        $stmt->execute([':e' => $eqId, ':q' => $qty]);
        $checkResult = $stmt->fetchColumn();
    }
}

$equipList = [];
if ($fn1ok) {
    $equipList = $pdo->query("
        SELECT
            e.equipment_id,
            e.property_no,
            e.equipment_name,
            e.quantity_available,
            fn_total_borrowed(e.equipment_id)                            AS units_out,
            e.quantity_available - fn_total_borrowed(e.equipment_id)     AS available_now
        FROM equipment e
        ORDER BY e.equipment_name
    ")->fetchAll();
}

$pageTitle   = 'Functions Demo';
$currentPage = 'functions';
require_once '../includes/header.php';
?>

<?php if (!$fn1ok || !$fn2ok): ?>
<div class="alert alert-danger">
    Functions not found. Run <strong>sql/02_functions.sql</strong> and <strong>sql/fn_calculate_fine.sql</strong> in pgAdmin first.
</div>
<?php else: ?>

<!-- fn_total_borrowed table -->
<p class="result-count">fn_total_borrowed(equipment_id) — SQL function, returns units currently out</p>
<div class="card" style="margin-bottom:20px">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Property No.</th><th>Equipment Name</th>
                    <th class="text-right">Qty Owned</th>
                    <th class="text-right">Units Out (fn_total_borrowed)</th>
                    <th class="text-right">Available Now</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($equipList as $r): ?>
                <tr>
                    <td class="text-muted"><?= $r['equipment_id'] ?></td>
                    <td><?= htmlspecialchars($r['property_no']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td class="text-right"><?= $r['quantity_available'] ?></td>
                    <td class="text-right"><?= $r['units_out'] ?></td>
                    <td class="text-right"><?= $r['available_now'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="result-count">fn_check_equipment_availability(id, qty) — PL/pgSQL function, returns availability message</p>
<div class="card">
    <div class="card-body">
        <?php if ($checkResult): ?>
        <div class="alert <?= str_starts_with($checkResult, 'AVAILABLE') ? 'alert-success' : 'alert-danger' ?>" style="margin-bottom:14px">
            <?= htmlspecialchars($checkResult) ?>
        </div>
        <?php endif; ?>

        <form method="post" class="filter-row">
            <div class="form-group">
                <label>Equipment</label>
                <select name="equip_id">
                    <?php foreach ($equipList as $e): ?>
                    <option value="<?= $e['equipment_id'] ?>" <?= ($_POST['equip_id']??'')==$e['equipment_id']?'selected':'' ?>>
                        <?= htmlspecialchars($e['equipment_name']) ?> (avail: <?= $e['available_now'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity to Request</label>
                <input type="number" name="qty" min="1" value="<?= htmlspecialchars($_POST['qty']??'1') ?>" style="width:80px">
            </div>
            <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn">Check</button></div>
        </form>
    </div>
</div>

<?php endif; ?>

<?php if ($fn3ok): ?>
<?php
$overdueTxns = $pdo->query("
    SELECT t.borrow_id, b.borrower_no, b.full_name, t.due_date, t.fine_rate,
           fn_calculate_fine(t.borrow_id) AS fine_amount
    FROM borrow_transactions t
    JOIN borrowers b ON b.borrower_id = t.borrower_id
    WHERE t.status = 'Overdue'
    ORDER BY fine_amount DESC
")->fetchAll();
?>
<p class="result-count" style="margin-top:20px">fn_calculate_fine(borrow_id) — PL/pgSQL function using ROUND · GREATEST · fine_rate</p>
<div class="card" style="margin-bottom:20px">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Txn ID</th><th>Borrower No.</th><th>Name</th>
                    <th>Due Date</th>
                    <th class="text-right">Fine Rate (₱/day)</th>
                    <th class="text-right">Fine (fn_calculate_fine)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($overdueTxns as $r): ?>
                <tr>
                    <td class="text-muted"><?= $r['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= $r['due_date'] ?></td>
                    <td class="text-right">₱<?= number_format((float)$r['fine_rate'], 2) ?></td>
                    <td class="text-right"><span style="color:#dc2626;font-weight:700">₱<?= number_format((float)$r['fine_amount'], 0) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($overdueTxns)): ?><tr><td colspan="6" class="text-center text-muted" style="padding:16px">No overdue transactions.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px">LOOK UP FINE BY TRANSACTION ID</p>
        <?php if ($fineResult !== null): ?>
        <div class="alert <?= $fineResult > 0 ? 'alert-danger' : 'alert-success' ?>" style="margin-bottom:14px">
            <?= $fineResult > 0
                ? 'Fine: ₱' . number_format((float)$fineResult, 0)
                : 'No fine — transaction is not Overdue.' ?>
        </div>
        <?php endif; ?>
        <form method="post" class="filter-row">
            <div class="form-group">
                <label>Transaction ID</label>
                <input type="number" name="fine_borrow_id" min="1"
                       value="<?= htmlspecialchars($_POST['fine_borrow_id'] ?? '') ?>"
                       style="width:90px" placeholder="e.g. 4">
            </div>
            <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn">Calculate</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
