<?php
require_once '../db.php';

function triggerExists(PDO $pdo, string $name): bool {
    $s = $pdo->prepare("SELECT 1 FROM pg_trigger WHERE tgname = ?");
    $s->execute([$name]);
    return (bool)$s->fetchColumn();
}

function auditTableExists(PDO $pdo): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = 'borrow_items_audit'");
    $s->execute();
    return (bool)$s->fetchColumn();
}

$t1ok = triggerExists($pdo, 'trg_validate_borrow_quantity');
$t2ok = triggerExists($pdo, 'trg_borrow_items_audit');
$auditOk = auditTableExists($pdo);

$result = null;
$action = $_POST['action'] ?? '';

if ($t1ok && $t2ok && $auditOk) {

    if ($action === 'test_valid_insert') {
        // INSERT 1 unit of available equipment → trigger validates → audit records it
        try {
            $borrower = $pdo->query("SELECT borrower_id FROM borrowers LIMIT 1")->fetchColumn();
            $equip    = $pdo->query("
                SELECT e.equipment_id FROM equipment e
                WHERE e.quantity_available - COALESCE((
                    SELECT SUM(bi.quantity) FROM borrow_items bi
                    JOIN borrow_transactions bt ON bt.borrow_id = bi.borrow_id
                    WHERE bi.equipment_id = e.equipment_id AND bt.status IN ('Borrowed','Overdue')
                ), 0) > 0 LIMIT 1
            ")->fetchColumn();

            $pdo->beginTransaction();
            $bStmt = $pdo->prepare("INSERT INTO borrow_transactions (borrower_id,borrow_date,due_date,status) VALUES (?,CURRENT_DATE,CURRENT_DATE+3,'Borrowed') RETURNING borrow_id");
            $bStmt->execute([$borrower]);
            $bid = $bStmt->fetchColumn();
            $pdo->prepare("INSERT INTO borrow_items (borrow_id,equipment_id,quantity) VALUES (?,?,1)")->execute([$bid,$equip]);
            $pdo->commit();
            $result = ['type'=>'success','msg'=>"INSERT accepted. BEFORE trigger validated qty. AFTER trigger wrote to audit. borrow_id=$bid, equipment_id=$equip"];
        } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); $result=['type'=>'error','msg'=>$e->getMessage()]; }
    }

    elseif ($action === 'test_invalid_insert') {
        // INSERT 9999 units → BEFORE trigger fires and rejects
        try {
            $borrower = $pdo->query("SELECT borrower_id FROM borrowers LIMIT 1")->fetchColumn();
            $equip    = $pdo->query("SELECT equipment_id FROM equipment LIMIT 1")->fetchColumn();
            $pdo->beginTransaction();
            $bStmt = $pdo->prepare("INSERT INTO borrow_transactions (borrower_id,borrow_date,due_date,status) VALUES (?,CURRENT_DATE,CURRENT_DATE+3,'Borrowed') RETURNING borrow_id");
            $bStmt->execute([$borrower]);
            $bid = $bStmt->fetchColumn();
            $pdo->prepare("INSERT INTO borrow_items (borrow_id,equipment_id,quantity) VALUES (?,?,9999)")->execute([$bid,$equip]);
            $pdo->commit();
            $result = ['type'=>'error','msg'=>'Expected rejection but got commit (unexpected).'];
        } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); $result=['type'=>'rollback','msg'=>'BEFORE trigger rejected the INSERT. Transaction rolled back. '.$e->getMessage()]; }
    }

    elseif ($action === 'test_update') {
        // UPDATE an existing borrow_item quantity (decrease by 0 — just fires trigger)
        try {
            $item = $pdo->query("SELECT borrow_item_id, quantity FROM borrow_items ORDER BY borrow_item_id LIMIT 1")->fetch();
            if (!$item) throw new RuntimeException('No borrow_items found.');
            $newQty = max(1, (int)$item['quantity']); // same qty — valid update, just fires trigger
            $pdo->prepare("UPDATE borrow_items SET quantity = ? WHERE borrow_item_id = ?")->execute([$newQty, $item['borrow_item_id']]);
            $result = ['type'=>'success','msg'=>"UPDATE on borrow_item_id={$item['borrow_item_id']} from qty={$item['quantity']} to qty=$newQty. Both triggers fired. Audit recorded UPDATE."];
        } catch (Throwable $e) { $result=['type'=>'error','msg'=>$e->getMessage()]; }
    }

    elseif ($action === 'test_delete') {
        // DELETE the last inserted borrow_item → AFTER audit trigger records DELETE
        try {
            $item = $pdo->query("SELECT borrow_item_id FROM borrow_items ORDER BY borrow_item_id DESC LIMIT 1")->fetchColumn();
            if (!$item) throw new RuntimeException('No borrow_items to delete.');
            $pdo->prepare("DELETE FROM borrow_items WHERE borrow_item_id = ?")->execute([$item]);
            $result = ['type'=>'success','msg'=>"DELETE on borrow_item_id=$item. AFTER trigger wrote DELETE to audit log."];
        } catch (Throwable $e) { $result=['type'=>'error','msg'=>$e->getMessage()]; }
    }
}

// Recent audit log entries
$auditRows = $auditOk ? $pdo->query("
    SELECT a.audit_id, a.borrow_item_id, a.borrow_id, e.equipment_name,
           a.old_quantity, a.new_quantity, a.operation,
           TO_CHAR(a.changed_at, 'YYYY-MM-DD HH24:MI:SS') AS changed_at
    FROM borrow_items_audit a
    LEFT JOIN equipment e ON e.equipment_id = a.equipment_id
    ORDER BY a.audit_id DESC LIMIT 10
")->fetchAll() : [];

$pageTitle   = 'Triggers Demo';
$currentPage = 'triggers';
require_once '../includes/header.php';
?>

<?php if (!$t1ok || !$t2ok || !$auditOk): ?>
<div class="alert alert-danger">
    Triggers or audit table not found. Run <strong>sql/02_functions.sql</strong> then <strong>sql/03_triggers.sql</strong> in pgAdmin first.
</div>
<?php else: ?>

<?php if ($result): ?>
<div class="alert alert-<?= $result['type']==='success'?'success':($result['type']==='rollback'?'info':'danger') ?>" style="margin-bottom:16px">
    <strong><?= $result['type']==='success'?'✅ OK':($result['type']==='rollback'?'↩ Rolled Back':'❌ Error') ?>:</strong>
    <?= htmlspecialchars($result['msg']) ?>
</div>
<?php endif; ?>

<form method="post" style="margin-bottom:20px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="submit" name="action" value="test_valid_insert"   class="btn">Test 1 — Valid INSERT</button>
        <button type="submit" name="action" value="test_invalid_insert" class="btn btn-outline">Test 2 — Invalid INSERT (exceeds stock)</button>
        <button type="submit" name="action" value="test_update"         class="btn btn-outline">Test 3 — UPDATE item</button>
        <button type="submit" name="action" value="test_delete"         class="btn btn-outline">Test 4 — DELETE item</button>
    </div>
</form>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;font-size:12.5px">
    <div class="card"><div class="card-body">
        <strong>trg_validate_borrow_quantity</strong> — BEFORE INSERT OR UPDATE<br>
        <span class="text-muted">Rejects the row if requested qty exceeds available stock.</span>
    </div></div>
    <div class="card"><div class="card-body">
        <strong>trg_borrow_items_audit</strong> — AFTER INSERT OR UPDATE OR DELETE<br>
        <span class="text-muted">Writes every change to borrow_items_audit automatically.</span>
    </div></div>
</div>

<p class="result-count">Last 10 audit log entries (most recent first)</p>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Audit ID</th><th>Item ID</th><th>Borrow ID</th><th>Equipment</th>
                    <th class="text-right">Old Qty</th><th class="text-right">New Qty</th>
                    <th>Operation</th><th>Changed At</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($auditRows)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:20px">No audit records yet. Run the tests above.</td></tr>
            <?php else: foreach ($auditRows as $r): ?>
                <tr>
                    <td class="text-muted"><?= $r['audit_id'] ?></td>
                    <td><?= $r['borrow_item_id'] ?? '—' ?></td>
                    <td><?= $r['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($r['equipment_name'] ?? '?') ?></td>
                    <td class="text-right"><?= $r['old_quantity'] ?? '—' ?></td>
                    <td class="text-right"><?= $r['new_quantity'] ?? '—' ?></td>
                    <td><strong><?= $r['operation'] ?></strong></td>
                    <td><?= $r['changed_at'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
