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
    Functions not found. Run <strong>sql/02_functions.sql</strong> in pgAdmin first.
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

<?php require_once '../includes/footer.php'; ?>
