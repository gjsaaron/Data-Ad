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
        COUNT(DISTINCT CASE WHEN bt.status = 'Cancelled' THEN bt.borrow_id END) AS cancelled
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

<p class="result-count">Date functions used: DATE_TRUNC · TO_CHAR · EXTRACT · CURRENT_DATE</p>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Month (TO_CHAR)</th>
                    <th class="text-right">Year (EXTRACT)</th>
                    <th class="text-right">Month No.</th>
                    <th class="text-right">Transactions</th>
                    <th class="text-right">Item Lines</th>
                    <th class="text-right">Total Qty</th>
                    <th class="text-right">Returned</th>
                    <th class="text-right">Borrowed</th>
                    <th class="text-right">Overdue</th>
                    <th class="text-right">Cancelled</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="text-center text-muted" style="padding:20px">No data.</td></tr>
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
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
