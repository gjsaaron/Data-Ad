<?php
require_once '../db.php';

$groups = $pdo->query("
    SELECT
        b.borrower_type,
        COUNT(*) AS total_borrowers,
        COUNT(DISTINCT bt.borrow_id) AS transactions,
        COALESCE(SUM(bi.quantity), 0) AS total_items
    FROM borrowers b
    LEFT JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    LEFT JOIN borrow_items bi ON bi.borrow_id = bt.borrow_id
    GROUP BY b.borrower_type
    ORDER BY total_borrowers DESC
")->fetchAll();

$detail = $pdo->query("
    SELECT
        b.borrower_no, b.full_name, b.borrower_type,
        COUNT(bt.borrow_id) AS transactions,
        COALESCE(SUM(bi.quantity), 0) AS items
    FROM borrowers b
    LEFT JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    LEFT JOIN borrow_items bi ON bi.borrow_id = bt.borrow_id
    GROUP BY b.borrower_id, b.borrower_no, b.full_name, b.borrower_type
    ORDER BY b.borrower_type, transactions DESC
")->fetchAll();

$pageTitle   = 'Borrower Type Summary';
$currentPage = 'borrow_summary';
require_once '../includes/header.php';
?>

<!-- Summary by type -->
<div class="card" style="margin-bottom:16px">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Borrower Type</th>
                    <th class="text-right">Total Borrowers</th>
                    <th class="text-right">Transactions</th>
                    <th class="text-right">Total Items Borrowed</th>
                </tr>
            </thead>
            <tbody>
            <?php $tb=0;$tt=0;$ti=0; foreach ($groups as $g): $tb+=$g['total_borrowers'];$tt+=$g['transactions'];$ti+=$g['total_items']; ?>
                <tr>
                    <td><?= htmlspecialchars($g['borrower_type']) ?></td>
                    <td class="text-right"><?= $g['total_borrowers'] ?></td>
                    <td class="text-right"><?= $g['transactions'] ?></td>
                    <td class="text-right"><?= $g['total_items'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td>Total</td><td class="text-right"><?= $tb ?></td><td class="text-right"><?= $tt ?></td><td class="text-right"><?= $ti ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Per-borrower detail -->
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Borrower No.</th><th>Name</th><th>Type</th>
                    <th class="text-right">Transactions</th><th class="text-right">Items Borrowed</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($detail as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_type']) ?></td>
                    <td class="text-right"><?= $r['transactions'] ?></td>
                    <td class="text-right"><?= $r['items'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
