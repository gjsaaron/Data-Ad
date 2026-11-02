<?php
require_once '../db.php';

$rows = $pdo->query("
    SELECT
        e.property_no,
        e.equipment_name,
        COUNT(DISTINCT bt.borrow_id)            AS txn_count,
        SUM(bi.quantity)                        AS total_qty,
        ROUND(AVG(bi.quantity), 2)              AS avg_qty,
        MIN(bi.quantity)                        AS min_qty,
        MAX(bi.quantity)                        AS max_qty
    FROM equipment e
    JOIN borrow_items        bi ON bi.equipment_id = e.equipment_id
    JOIN borrow_transactions bt ON bt.borrow_id    = bi.borrow_id
    GROUP BY e.equipment_id, e.property_no, e.equipment_name
    ORDER BY total_qty DESC
")->fetchAll();

$totals = $pdo->query("
    SELECT
        COUNT(DISTINCT bt.borrow_id)  AS total_txn,
        SUM(bi.quantity)              AS grand_total,
        ROUND(AVG(bi.quantity), 2)    AS overall_avg,
        MIN(bi.quantity)              AS overall_min,
        MAX(bi.quantity)              AS overall_max
    FROM borrow_items bi
    JOIN borrow_transactions bt ON bt.borrow_id = bi.borrow_id
")->fetch();

$pageTitle   = 'Equipment Borrowing Summary';
$currentPage = 'equip_summary';
require_once '../includes/header.php';
?>

<div class="result-count">Aggregate functions: COUNT · SUM · AVG · MIN · MAX</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Property No.</th>
                    <th>Equipment Name</th>
                    <th class="text-right">COUNT (Transactions)</th>
                    <th class="text-right">SUM (Total Qty)</th>
                    <th class="text-right">AVG (Avg Qty)</th>
                    <th class="text-right">MIN (Min Qty)</th>
                    <th class="text-right">MAX (Max Qty)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['property_no']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td class="text-right"><?= $r['txn_count'] ?></td>
                    <td class="text-right fw-bold"><?= $r['total_qty'] ?></td>
                    <td class="text-right"><?= $r['avg_qty'] ?></td>
                    <td class="text-right"><?= $r['min_qty'] ?></td>
                    <td class="text-right"><?= $r['max_qty'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:20px">No data.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($totals && $totals['grand_total']): ?>
            <tfoot>
                <tr>
                    <td colspan="2">Overall</td>
                    <td class="text-right"><?= $totals['total_txn'] ?></td>
                    <td class="text-right"><?= $totals['grand_total'] ?></td>
                    <td class="text-right"><?= $totals['overall_avg'] ?></td>
                    <td class="text-right"><?= $totals['overall_min'] ?></td>
                    <td class="text-right"><?= $totals['overall_max'] ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
