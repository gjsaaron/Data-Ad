<?php
require_once '../db.php';

function viewExists(PDO $pdo, string $view): bool {
    $r = $pdo->prepare("SELECT 1 FROM pg_views WHERE viewname = ?");
    $r->execute([$view]);
    return (bool) $r->fetchColumn();
}

$v1ok   = viewExists($pdo, 'vw_borrowing_details');
$v2ok   = viewExists($pdo, 'vw_equipment_usage');
$v3ok   = viewExists($pdo, 'v_overdue_fines');

$details = $v1ok ? $pdo->query("SELECT * FROM vw_borrowing_details ORDER BY borrow_date DESC")->fetchAll() : [];
$usage   = $v2ok ? $pdo->query("SELECT * FROM vw_equipment_usage ORDER BY total_transactions DESC")->fetchAll() : [];
$fines   = $v3ok ? $pdo->query("SELECT * FROM v_overdue_fines ORDER BY total_fine DESC")->fetchAll() : [];

$pageTitle   = 'Database Views';
$currentPage = 'views';
require_once '../includes/header.php';
?>

<?php if (!$v1ok || !$v2ok || !$v3ok): ?>
<div class="alert alert-danger">
    One or more views not found. Run <strong>sql/01_views.sql</strong> and <strong>sql/04_fine_and_constraints.sql</strong> in pgAdmin, then reload.
</div>
<?php endif; ?>

<?php if ($v1ok): ?>
<p class="result-count">
    View: <strong>vw_borrowing_details</strong> — INNER JOIN across 4 tables · CASE · days_overdue
    (<?= count($details) ?> rows)
</p>
<div class="card" style="margin-bottom:20px">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Borrower No.</th><th>Name</th><th>Type</th>
                    <th>Equipment</th><th class="text-right">Qty</th>
                    <th>Borrow Date</th><th>Due Date</th><th>Status</th>
                    <th class="text-right">Days Overdue</th><th>Loan Status (CASE)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($details as $r): ?>
                <tr>
                    <td class="text-muted"><?= $r['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_type']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td class="text-right"><?= $r['quantity'] ?></td>
                    <td><?= $r['borrow_date'] ?></td>
                    <td><?= $r['due_date'] ?></td>
                    <td><?= $r['status'] ?></td>
                    <td class="text-right"><?= $r['days_overdue'] ?></td>
                    <td><?= htmlspecialchars($r['loan_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($details)): ?><tr><td colspan="11" class="text-center text-muted" style="padding:16px">No data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($v2ok): ?>
<p class="result-count">
    View: <strong>vw_equipment_usage</strong> — LEFT JOIN · COUNT · SUM · AVG · ROUND · ABS · CASE
    (<?= count($usage) ?> rows)
</p>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Property No.</th><th>Equipment</th>
                    <th class="text-right">Owned</th>
                    <th class="text-right">Total Txn (COUNT)</th>
                    <th class="text-right">Total Qty (SUM)</th>
                    <th class="text-right">Units Out</th>
                    <th class="text-right">Available (ROUND)</th>
                    <th class="text-right">Avg/Borrow (AVG)</th>
                    <th class="text-right">ABS Diff (ABS)</th>
                    <th>Status (CASE)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usage as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['property_no']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td class="text-right"><?= $r['units_owned'] ?></td>
                    <td class="text-right"><?= $r['total_transactions'] ?></td>
                    <td class="text-right"><?= $r['total_qty_borrowed'] ?></td>
                    <td class="text-right"><?= $r['units_out'] ?></td>
                    <td class="text-right"><?= $r['available_now'] ?></td>
                    <td class="text-right"><?= $r['avg_qty_per_borrow'] ?></td>
                    <td class="text-right"><?= $r['abs_stock_diff'] ?></td>
                    <td><?= availBadge((int)$r['available_now'], (int)$r['units_owned']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($v3ok): ?>
<p class="result-count">
    View: <strong>v_overdue_fines</strong> — ROUND · GREATEST · days overdue × fine_rate
    (<?= count($fines) ?> rows)
</p>
<div class="card" style="margin-bottom:20px">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Txn ID</th><th>Borrower No.</th><th>Name</th>
                    <th>Borrow Date</th><th>Due Date</th><th>Status</th>
                    <th class="text-right">Fine Rate</th>
                    <th class="text-right">Days Overdue</th>
                    <th class="text-right">Total Fine (ROUND)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($fines as $r): ?>
                <tr>
                    <td class="text-muted"><?= $r['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= $r['borrow_date'] ?></td>
                    <td><?= $r['due_date'] ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td class="text-right">₱<?= number_format((float)$r['fine_rate'], 2) ?></td>
                    <td class="text-right"><?= $r['days_overdue'] > 0 ? '<span style="color:#dc2626;font-weight:600">' . (int)$r['days_overdue'] . '</span>' : '<span style="color:#9ca3af">0</span>' ?></td>
                    <td class="text-right"><?= $r['total_fine'] > 0 ? '<span style="color:#dc2626;font-weight:700">₱' . number_format((float)$r['total_fine'], 0) . '</span>' : '<span style="color:#9ca3af">₱0</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($fines)): ?><tr><td colspan="9" class="text-center text-muted" style="padding:16px">No data.</td></tr><?php endif; ?>
            </tbody>
            <?php if (!empty($fines)): ?>
            <tfoot>
                <?php $grand = array_sum(array_column($fines, 'total_fine')); ?>
                <tr>
                    <td colspan="8">Grand Total Fines</td>
                    <td class="text-right" style="color:#dc2626;font-weight:700">₱<?= number_format((float)$grand, 0) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
