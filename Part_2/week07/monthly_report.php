<?php
require_once '../db.php';

$rows = $pdo->query("
    SELECT
        DATE_TRUNC('month', bt.borrow_date)                     AS month_start,
        TO_CHAR(DATE_TRUNC('month', bt.borrow_date), 'FMMonth YYYY') AS month_label,
        EXTRACT(YEAR  FROM bt.borrow_date)::INT                 AS yr,
        EXTRACT(MONTH FROM bt.borrow_date)::INT                 AS mo,
        COUNT(DISTINCT bt.borrow_id)                            AS transactions,
        COUNT(bi.borrow_item_id)                                AS item_lines,
        COALESCE(SUM(bi.quantity), 0)                           AS total_qty,
        COUNT(DISTINCT CASE WHEN bt.status = 'Returned'  THEN bt.borrow_id END) AS returned,
        COUNT(DISTINCT CASE WHEN bt.status = 'Borrowed'  THEN bt.borrow_id END) AS borrowed,
        COUNT(DISTINCT CASE WHEN bt.status = 'Overdue'   THEN bt.borrow_id END) AS overdue,
        COUNT(DISTINCT CASE WHEN bt.status = 'Cancelled' THEN bt.borrow_id END) AS cancelled,
        COALESCE(
            SUM(
                CASE WHEN bt.status = 'Overdue'
                     THEN ROUND(GREATEST(0, CURRENT_DATE - bt.due_date) * bt.fine_rate, 0)
                     ELSE 0
                END
            ), 0
        )                                                       AS overdue_fines
    FROM borrow_transactions bt
    LEFT JOIN borrow_items bi ON bi.borrow_id = bt.borrow_id
    GROUP BY DATE_TRUNC('month', bt.borrow_date),
             EXTRACT(YEAR  FROM bt.borrow_date),
             EXTRACT(MONTH FROM bt.borrow_date)
    ORDER BY month_start DESC
")->fetchAll();

$pageTitle   = 'Monthly Borrowing Report';
$currentPage = 'monthly';
require_once '../includes/header.php';
?>

<p class="result-count">Date functions: DATE_TRUNC · TO_CHAR · EXTRACT · CURRENT_DATE · ROUND · GREATEST</p>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-right">Year</th>
                    <th class="text-right">Mo.</th>
                    <th class="text-right">Transactions</th>
                    <th class="text-right">Item Lines</th>
                    <th class="text-right">Total Qty</th>
                    <th class="text-right">Returned</th>
                    <th class="text-right">Borrowed</th>
                    <th class="text-right">Overdue</th>
                    <th class="text-right">Cancelled</th>
                    <th class="text-right">Overdue Fines (ROUND)</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="11" class="text-center text-muted" style="padding:20px">No data.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($r['month_label']) ?></td>
                    <td class="text-right"><?= $r['yr'] ?></td>
                    <td class="text-right"><?= $r['mo'] ?></td>
                    <td class="text-right"><?= $r['transactions'] ?></td>
                    <td class="text-right"><?= $r['item_lines'] ?></td>
                    <td class="text-right"><?= $r['total_qty'] ?></td>
                    <td class="text-right"><?= $r['returned'] ?: '-' ?></td>
                    <td class="text-right"><?= $r['borrowed'] ?: '-' ?></td>
                    <td class="text-right"><?= $r['overdue']  ?: '-' ?></td>
                    <td class="text-right"><?= $r['cancelled']?: '-' ?></td>
                    <td class="text-right">
                        <?= $r['overdue_fines'] > 0
                            ? '<span style="color:#dc2626;font-weight:700">₱' . number_format((float)$r['overdue_fines'], 0) . '</span>'
                            : '<span style="color:#9ca3af">—</span>' ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
            <tfoot>
                <?php $grandFine = array_sum(array_column($rows, 'overdue_fines')); ?>
                <tr>
                    <td colspan="10">Grand Total Fines</td>
                    <td class="text-right" style="color:#dc2626;font-weight:700">₱<?= number_format((float)$grandFine, 0) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
