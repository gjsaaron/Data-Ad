<?php
require_once 'db.php';
$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
require_once 'includes/header.php';

$c = [];
foreach (['borrowers','equipment','borrow_transactions','borrow_items'] as $t)
    $c[$t] = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();

$statusRows = $pdo->query("
    SELECT status, COUNT(*) AS cnt
    FROM borrow_transactions GROUP BY status ORDER BY cnt DESC
")->fetchAll();
?>

<div class="stat-grid">
    <div class="stat-box"><div class="label">Borrowers</div><div class="value"><?= $c['borrowers'] ?></div></div>
    <div class="stat-box"><div class="label">Equipment</div><div class="value"><?= $c['equipment'] ?></div></div>
    <div class="stat-box"><div class="label">Transactions</div><div class="value"><?= $c['borrow_transactions'] ?></div></div>
    <div class="stat-box"><div class="label">Borrow Items</div><div class="value"><?= $c['borrow_items'] ?></div></div>
</div>

<div class="card">
    <div class="card-body">
        <table>
            <thead><tr><th>Status</th><th class="text-right">Count</th></tr></thead>
            <tbody>
            <?php foreach ($statusRows as $r): ?>
                <tr>
                    <td><?= $r['status'] ?></td>
                    <td class="text-right"><?= $r['cnt'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
