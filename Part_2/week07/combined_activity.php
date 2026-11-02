<?php
require_once '../db.php';

// Set A: borrowers with active loans (Borrowed or Overdue)
$setA = $pdo->query("
    SELECT DISTINCT b.borrower_no, b.full_name, b.borrower_type
    FROM borrowers b
    JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    WHERE bt.status IN ('Borrowed','Overdue')
")->fetchAll();

// Set B: borrowers who have returned items
$setB = $pdo->query("
    SELECT DISTINCT b.borrower_no, b.full_name, b.borrower_type
    FROM borrowers b
    JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    WHERE bt.status = 'Returned'
")->fetchAll();

// UNION: unique borrowers from both sets (no duplicates)
$union = $pdo->query("
    SELECT b.borrower_no, b.full_name, b.borrower_type
    FROM borrowers b
    JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    WHERE bt.status IN ('Borrowed','Overdue')

    UNION

    SELECT b.borrower_no, b.full_name, b.borrower_type
    FROM borrowers b
    JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    WHERE bt.status = 'Returned'

    ORDER BY full_name
")->fetchAll();

// UNION ALL: all rows including duplicates
$unionAll = $pdo->query("
    SELECT b.borrower_no, b.full_name, b.borrower_type
    FROM borrowers b
    JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    WHERE bt.status IN ('Borrowed','Overdue')

    UNION ALL

    SELECT b.borrower_no, b.full_name, b.borrower_type
    FROM borrowers b
    JOIN borrow_transactions bt ON bt.borrower_id = b.borrower_id
    WHERE bt.status = 'Returned'

    ORDER BY full_name
")->fetchAll();

$pageTitle   = 'Combined Borrower Activity';
$currentPage = 'combined';
require_once '../includes/header.php';

function simpleTable($rows, $emptyMsg = 'No records.') {
    if (empty($rows)) { echo "<p class='text-muted' style='padding:12px'>$emptyMsg</p>"; return; }
    echo '<div class="table-wrap"><table>';
    echo '<thead><tr><th>Borrower No.</th><th>Name</th><th>Type</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td>' . htmlspecialchars($r['borrower_no']) . '</td>'
           . '<td>' . htmlspecialchars($r['full_name']) . '</td>'
           . '<td>' . htmlspecialchars($r['borrower_type']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
?>

<p class="result-count">
    Set A: <?= count($setA) ?> · Set B: <?= count($setB) ?> ·
    UNION: <strong><?= count($union) ?> unique</strong> ·
    UNION ALL: <strong><?= count($unionAll) ?> total</strong>
    (difference = <?= count($unionAll) - count($union) ?> duplicate row(s) removed by UNION)
</p>

<div class="grid-2" style="margin-bottom:16px">
    <div>
        <p class="text-muted" style="margin-bottom:6px">Set A — Active borrowers (Borrowed / Overdue)</p>
        <div class="card"><?php simpleTable($setA, 'No active borrows.'); ?></div>
    </div>
    <div>
        <p class="text-muted" style="margin-bottom:6px">Set B — Borrowers who returned items</p>
        <div class="card"><?php simpleTable($setB, 'No returned transactions.'); ?></div>
    </div>
</div>

<div class="grid-2">
    <div>
        <p class="text-muted" style="margin-bottom:6px">UNION — unique rows only (<?= count($union) ?> rows)</p>
        <div class="card"><?php simpleTable($union); ?></div>
    </div>
    <div>
        <p class="text-muted" style="margin-bottom:6px">UNION ALL — all rows including duplicates (<?= count($unionAll) ?> rows)</p>
        <div class="card"><?php simpleTable($unionAll); ?></div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
