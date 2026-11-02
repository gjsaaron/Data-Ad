<?php
require_once '../db.php';

$minTxn  = max(1, (int)($_GET['min_txn']  ?? 1));
$minQty  = max(1, (int)($_GET['min_qty']  ?? 1));
$minGrp  = max(1, (int)($_GET['min_grp']  ?? 1));
$status  = in_array($_GET['sw'] ?? '', ['Borrowed','Returned','Overdue','Cancelled','']) ? ($_GET['sw'] ?? '') : '';

// HAVING: borrowers with >= N transactions
$stmtA = $pdo->prepare("
    SELECT b.borrower_no, b.full_name, b.borrower_type,
           COUNT(bt.borrow_id) AS txn_count,
           COALESCE(SUM(bi.quantity),0) AS total_items
    FROM borrowers b
    LEFT JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    LEFT JOIN borrow_items bi ON bi.borrow_id = bt.borrow_id
    GROUP BY b.borrower_id, b.borrower_no, b.full_name, b.borrower_type
    HAVING COUNT(bt.borrow_id) >= :n
    ORDER BY txn_count DESC
");
$stmtA->execute([':n' => $minTxn]);
$havingBorrowers = $stmtA->fetchAll();

// HAVING: equipment with >= N total qty borrowed
$stmtB = $pdo->prepare("
    SELECT e.property_no, e.equipment_name,
           COUNT(DISTINCT bt.borrow_id) AS borrow_count,
           SUM(bi.quantity) AS total_qty
    FROM equipment e
    JOIN borrow_items bi ON bi.equipment_id = e.equipment_id
    JOIN borrow_transactions bt ON bt.borrow_id = bi.borrow_id
    GROUP BY e.equipment_id, e.property_no, e.equipment_name
    HAVING SUM(bi.quantity) >= :n
    ORDER BY total_qty DESC
");
$stmtB->execute([':n' => $minQty]);
$havingEquipment = $stmtB->fetchAll();

// WHERE (pre-filter by status)
$whereClause = $status ? 'WHERE bt.status = :s' : '';
$stmtW = $pdo->prepare("
    SELECT b.borrower_type, COUNT(DISTINCT bt.borrow_id) AS cnt
    FROM borrowers b
    JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    $whereClause
    GROUP BY b.borrower_type ORDER BY cnt DESC
");
$stmtW->execute($status ? [':s' => $status] : []);
$whereResult = $stmtW->fetchAll();

// HAVING (post-filter by group count)
$stmtH = $pdo->prepare("
    SELECT b.borrower_type, COUNT(DISTINCT bt.borrow_id) AS cnt
    FROM borrowers b
    JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    GROUP BY b.borrower_type
    HAVING COUNT(DISTINCT bt.borrow_id) >= :n
    ORDER BY cnt DESC
");
$stmtH->execute([':n' => $minGrp]);
$havingResult = $stmtH->fetchAll();

$pageTitle   = 'Grouped Borrowing Analysis';
$currentPage = 'grouped';
require_once '../includes/header.php';
?>

<!-- Controls -->
<form method="get" class="filter-row" style="margin-bottom:20px">
    <div class="form-group">
        <label>Min Transactions (HAVING)</label>
        <input type="number" name="min_txn" value="<?= $minTxn ?>" min="1" style="width:70px">
    </div>
    <div class="form-group">
        <label>Min Qty Borrowed (HAVING)</label>
        <input type="number" name="min_qty" value="<?= $minQty ?>" min="1" style="width:70px">
    </div>
    <div class="form-group">
        <label>Min Txn per Type (HAVING)</label>
        <input type="number" name="min_grp" value="<?= $minGrp ?>" min="1" style="width:70px">
    </div>
    <div class="form-group">
        <label>Status Filter (WHERE)</label>
        <select name="sw">
            <option value="">All</option>
            <?php foreach (['Borrowed','Returned','Overdue','Cancelled'] as $s): ?>
            <option <?= $status===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn">Apply</button></div>
</form>

<!-- A: HAVING on borrowers -->
<p class="text-muted" style="margin-bottom:6px">Borrowers with ≥ <?= $minTxn ?> transactions (HAVING)</p>
<div class="card" style="margin-bottom:16px">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Borrower No.</th><th>Name</th><th>Type</th><th class="text-right">Transactions</th><th class="text-right">Items</th></tr></thead>
            <tbody>
            <?php if (empty($havingBorrowers)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:20px">No borrowers meet this threshold.</td></tr>
            <?php else: foreach ($havingBorrowers as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_type']) ?></td>
                    <td class="text-right"><?= $r['txn_count'] ?></td>
                    <td class="text-right"><?= $r['total_items'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- B: HAVING on equipment -->
<p class="text-muted" style="margin-bottom:6px">Equipment with ≥ <?= $minQty ?> total qty borrowed (HAVING)</p>
<div class="card" style="margin-bottom:20px">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Property No.</th><th>Equipment</th><th class="text-right">Times Borrowed</th><th class="text-right">Total Qty</th></tr></thead>
            <tbody>
            <?php if (empty($havingEquipment)): ?>
                <tr><td colspan="4" class="text-center text-muted" style="padding:20px">No equipment meets this threshold.</td></tr>
            <?php else: foreach ($havingEquipment as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['property_no']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td class="text-right"><?= $r['borrow_count'] ?></td>
                    <td class="text-right"><?= $r['total_qty'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- C: WHERE vs HAVING side by side -->
<div class="grid-2">
    <div>
        <p class="text-muted" style="margin-bottom:6px">
            WHERE <?= $status ? "status = '$status'" : '(no filter)' ?> — filters rows before grouping
        </p>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Borrower Type</th><th class="text-right">Transactions</th></tr></thead>
                    <tbody>
                    <?php foreach ($whereResult as $r): ?>
                        <tr><td><?= htmlspecialchars($r['borrower_type']) ?></td><td class="text-right"><?= $r['cnt'] ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($whereResult)): ?><tr><td colspan="2" class="text-muted text-center" style="padding:16px">No results</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div>
        <p class="text-muted" style="margin-bottom:6px">
            HAVING count ≥ <?= $minGrp ?> — filters groups after grouping
        </p>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Borrower Type</th><th class="text-right">Transactions</th></tr></thead>
                    <tbody>
                    <?php foreach ($havingResult as $r): ?>
                        <tr><td><?= htmlspecialchars($r['borrower_type']) ?></td><td class="text-right"><?= $r['cnt'] ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($havingResult)): ?><tr><td colspan="2" class="text-muted text-center" style="padding:16px">No groups meet threshold</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
