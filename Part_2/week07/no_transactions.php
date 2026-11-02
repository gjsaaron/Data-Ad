<?php
require_once '../db.php';

$noTxn = $pdo->query("
    SELECT
        b.borrower_no,
        b.full_name,
        b.borrower_type
    FROM borrowers b
    LEFT JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    WHERE bt.borrow_id IS NULL
    ORDER BY b.borrower_type, b.full_name
")->fetchAll();

$allBorrowers = $pdo->query("
    SELECT
        b.borrower_no,
        b.full_name,
        b.borrower_type,
        COUNT(bt.borrow_id) AS txn_count
    FROM borrowers b
    LEFT JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    GROUP BY b.borrower_id, b.borrower_no, b.full_name, b.borrower_type
    ORDER BY txn_count ASC, b.full_name
")->fetchAll();

$pageTitle   = 'Borrowers Without Transactions';
$currentPage = 'no_txn';
require_once '../includes/header.php';
?>

<p class="result-count">
    LEFT JOIN — <?= count($noTxn) ?> borrower(s) have never made a transaction.
</p>

<div class="card" style="margin-bottom:16px">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Borrower No.</th><th>Name</th><th>Type</th></tr>
            </thead>
            <tbody>
            <?php if (empty($noTxn)): ?>
                <tr><td colspan="3" class="text-center text-muted" style="padding:20px">All borrowers have at least one transaction.</td></tr>
            <?php else: foreach ($noTxn as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_type']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="result-count" style="margin-top:16px">All borrowers — transaction count</p>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Borrower No.</th><th>Name</th><th>Type</th><th class="text-right">Transactions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($allBorrowers as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_type']) ?></td>
                    <td class="text-right <?= $r['txn_count'] == 0 ? 'fw-bold' : '' ?>"><?= $r['txn_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
