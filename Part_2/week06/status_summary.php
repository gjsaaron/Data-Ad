<?php
require_once '../db.php';

$rows = $pdo->query("
    SELECT
        bt.status,
        COUNT(*) AS cnt,
        ROUND(100.0 * COUNT(*) / NULLIF(SUM(COUNT(*)) OVER (), 0), 1) AS pct
    FROM borrow_transactions bt
    GROUP BY bt.status
    ORDER BY cnt DESC
")->fetchAll();

$detail = $pdo->query("
    SELECT
        bt.status,
        COUNT(DISTINCT bt.borrow_id)                                                    AS transactions,
        COUNT(bi.borrow_item_id)                                                        AS item_lines,
        COALESCE(SUM(bi.quantity), 0)                                                   AS total_qty,
        COALESCE(
            SUM(
                CASE WHEN bt.status = 'Overdue'
                     THEN ROUND(GREATEST(0, CURRENT_DATE - bt.due_date) * bt.fine_rate, 0)
                     ELSE 0
                END
            ), 0
        )                                                                               AS total_fine
    FROM borrow_transactions bt
    LEFT JOIN borrow_items bi ON bi.borrow_id = bt.borrow_id
    GROUP BY bt.status
    ORDER BY total_qty DESC
")->fetchAll();

$pageTitle   = 'Transaction Status Summary';
$currentPage = 'status_summary';
require_once '../includes/header.php';
?>

<div class="card" style="margin-bottom:16px">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">% of Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td class="text-right"><?= $r['cnt'] ?></td>
                    <td class="text-right"><?= $r['pct'] ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="text-right">Transactions</th>
                    <th class="text-right">Item Lines</th>
                    <th class="text-right">Total Qty</th>
                    <th class="text-right">Total Fine (ROUND)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($detail as $r): ?>
                <tr>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td class="text-right"><?= $r['transactions'] ?></td>
                    <td class="text-right"><?= $r['item_lines'] ?></td>
                    <td class="text-right"><?= $r['total_qty'] ?></td>
                    <td class="text-right">
                        <?php if ($r['status'] === 'Overdue' && $r['total_fine'] > 0): ?>
                            <span style="color:#dc2626;font-weight:700">₱<?= number_format((float)$r['total_fine'], 0) ?></span>
                        <?php else: ?>
                            <span style="color:#9ca3af">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php $totFine = array_sum(array_column($detail, 'total_fine')); ?>
                <tr>
                    <td colspan="4">Total Overdue Fines</td>
                    <td class="text-right" style="color:#dc2626;font-weight:700">₱<?= number_format((float)$totFine, 0) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
