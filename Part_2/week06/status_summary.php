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
        COUNT(DISTINCT bt.borrow_id) AS transactions,
        COUNT(bi.borrow_item_id) AS item_lines,
        COALESCE(SUM(bi.quantity), 0) AS total_qty
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
                    <td><?= htmlspecialchars($r['status']) ?></td>
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
                </tr>
            </thead>
            <tbody>
            <?php foreach ($detail as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['status']) ?></td>
                    <td class="text-right"><?= $r['transactions'] ?></td>
                    <td class="text-right"><?= $r['item_lines'] ?></td>
                    <td class="text-right"><?= $r['total_qty'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
