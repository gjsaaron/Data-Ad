<?php
require_once '../db.php';

$s = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = 'borrow_items_audit'");
$s->execute();
$exists = (bool)$s->fetchColumn();

$rows = $exists ? $pdo->query("
    SELECT
        a.audit_id,
        a.borrow_item_id,
        a.borrow_id,
        e.equipment_name,
        a.old_quantity,
        a.new_quantity,
        a.operation,
        TO_CHAR(a.changed_at, 'YYYY-MM-DD HH24:MI:SS') AS changed_at
    FROM borrow_items_audit a
    LEFT JOIN equipment e ON e.equipment_id = a.equipment_id
    ORDER BY a.audit_id DESC
")->fetchAll() : [];

$pageTitle   = 'Audit Log';
$currentPage = 'audit';
require_once '../includes/header.php';
?>

<?php if (!$exists): ?>
<div class="alert alert-danger">
    Audit table not found. Run <strong>sql/03_triggers.sql</strong> in pgAdmin first.
</div>
<?php else: ?>

<p class="result-count"><?= count($rows) ?> audit record(s) — every INSERT / UPDATE / DELETE on borrow_items is captured here</p>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Audit ID</th>
                    <th>Item ID</th>
                    <th>Borrow ID</th>
                    <th>Equipment</th>
                    <th class="text-right">Old Qty</th>
                    <th class="text-right">New Qty</th>
                    <th>Operation</th>
                    <th>Changed At</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:20px">
                    No audit records yet. Go to <a href="triggers_demo.php">Triggers Demo</a> and run the tests.
                </td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted"><?= $r['audit_id'] ?></td>
                    <td><?= $r['borrow_item_id'] ?? '—' ?></td>
                    <td><?= $r['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($r['equipment_name'] ?? '?') ?></td>
                    <td class="text-right"><?= $r['old_quantity'] ?? '—' ?></td>
                    <td class="text-right"><?= $r['new_quantity'] ?? '—' ?></td>
                    <td style="font-weight:600;color:<?= $r['operation']==='DELETE'?'#dc2626':($r['operation']==='INSERT'?'#166534':'#1d4ed8') ?>">
                        <?= $r['operation'] ?>
                    </td>
                    <td><?= $r['changed_at'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
