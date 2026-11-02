<?php
require_once '../db.php';

$result  = null;
$action  = $_POST['action'] ?? '';

if ($action === 'success') {
    try {
        $pdo->beginTransaction();

        $borrower = $pdo->query("SELECT borrower_id FROM borrowers LIMIT 1")->fetchColumn();
        $equip    = $pdo->query("
            SELECT e.equipment_id
            FROM equipment e
            WHERE e.quantity_available > COALESCE((
                SELECT SUM(bi.quantity)
                FROM borrow_items bi
                JOIN borrow_transactions bt ON bt.borrow_id = bi.borrow_id
                WHERE bi.equipment_id = e.equipment_id AND bt.status IN ('Borrowed','Overdue')
            ), 0)
            LIMIT 1
        ")->fetchColumn();

        if (!$borrower || !$equip) throw new RuntimeException('No available equipment found for test.');

        $stmt = $pdo->prepare("
            INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
            VALUES (?, CURRENT_DATE, CURRENT_DATE + 3, 'Borrowed')
            RETURNING borrow_id
        ");
        $stmt->execute([$borrower]);
        $borrowId = $stmt->fetchColumn();

        $pdo->prepare("
            INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
            VALUES (?, ?, 1)
        ")->execute([$borrowId, $equip]);

        $pdo->commit();  

        $result = ['type' => 'success', 'msg' => "Transaction committed. New borrow_id = $borrowId, equipment_id = $equip, qty = 1."];

    } catch (Throwable $e) {
        $pdo->rollBack();
        $result = ['type' => 'error', 'msg' => 'Rolled back. ' . $e->getMessage()];
    }

} elseif ($action === 'rollback') {
    try {
        $pdo->beginTransaction();

        $borrower = $pdo->query("SELECT borrower_id FROM borrowers LIMIT 1")->fetchColumn();
        $equip    = $pdo->query("SELECT equipment_id FROM equipment LIMIT 1")->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
            VALUES (?, CURRENT_DATE, CURRENT_DATE + 3, 'Borrowed')
            RETURNING borrow_id
        ");
        $stmt->execute([$borrower]);
        $borrowId = $stmt->fetchColumn();

        $pdo->prepare("
            INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
            VALUES (?, ?, 9999)
        ")->execute([$borrowId, $equip]);

        $pdo->commit();
        $result = ['type' => 'success', 'msg' => 'Committed (unexpected).'];

    } catch (Throwable $e) {
        $pdo->rollBack();  
        $result = ['type' => 'rollback', 'msg' => 'Rolled back successfully. Error: ' . $e->getMessage()];
    }
}

$txnCount  = (int) $pdo->query("SELECT COUNT(*) FROM borrow_transactions")->fetchColumn();
$itemCount = (int) $pdo->query("SELECT COUNT(*) FROM borrow_items")->fetchColumn();

$pageTitle   = 'Transactions Demo';
$currentPage = 'txn_demo';
require_once '../includes/header.php';
?>

<?php if ($result): ?>
<div class="alert alert-<?= $result['type'] === 'success' ? 'success' : ($result['type'] === 'rollback' ? 'info' : 'danger') ?>" style="margin-bottom:16px">
    <strong><?= $result['type'] === 'success' ? '✅ COMMIT' : ($result['type'] === 'rollback' ? '↩ ROLLBACK' : '❌ Error') ?>:</strong>
    <?= htmlspecialchars($result['msg']) ?>
</div>
<?php endif; ?>

<p class="result-count">Current row counts — borrow_transactions: <strong><?= $txnCount ?></strong> · borrow_items: <strong><?= $itemCount ?></strong></p>

<div class="grid-2" style="margin-top:8px">
    <div class="card">
        <div class="card-body">
            <p style="font-size:13px;font-weight:600;margin-bottom:6px">Test 1 — Successful Transaction (COMMIT)</p>
            <p class="text-muted" style="font-size:12.5px;margin-bottom:14px">
                Inserts a new borrow transaction + valid item (qty = 1).
                If no error occurs, <code>COMMIT</code> saves both rows permanently.
            </p>
            <form method="post">
                <input type="hidden" name="action" value="success">
                <button type="submit" class="btn">Run → COMMIT</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p style="font-size:13px;font-weight:600;margin-bottom:6px">Test 2 — Failed Transaction (ROLLBACK)</p>
            <p class="text-muted" style="font-size:12.5px;margin-bottom:14px">
                Inserts a transaction header, then tries to add 9999 units (exceeds stock).
                The error triggers a <code>ROLLBACK</code> — <em>neither</em> row is saved.
            </p>
            <form method="post">
                <input type="hidden" name="action" value="rollback">
                <button type="submit" class="btn btn-outline">Run → ROLLBACK</button>
            </form>
        </div>
    </div>
</div>

<?php if ($result): ?>
<p class="result-count" style="margin-top:16px">
    After action — borrow_transactions:
    <strong><?= $pdo->query("SELECT COUNT(*) FROM borrow_transactions")->fetchColumn() ?></strong> ·
    borrow_items:
    <strong><?= $pdo->query("SELECT COUNT(*) FROM borrow_items")->fetchColumn() ?></strong>
    <?php if ($result['type'] === 'rollback'): ?> — counts unchanged (rollback worked) <?php endif; ?>
</p>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
