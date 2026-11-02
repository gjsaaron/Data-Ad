<?php
require_once '../db.php';

// LEFT JOIN: equipment with no borrow history
$noHistory = $pdo->query("
    SELECT e.property_no, e.equipment_name, e.quantity_available
    FROM equipment e
    LEFT JOIN borrow_items bi ON bi.equipment_id = e.equipment_id
    WHERE bi.borrow_item_id IS NULL
    ORDER BY e.equipment_name
")->fetchAll();

// All equipment with borrow count for context
$allEquip = $pdo->query("
    SELECT
        e.property_no,
        e.equipment_name,
        e.quantity_available,
        COUNT(bi.borrow_item_id) AS borrow_count
    FROM equipment e
    LEFT JOIN borrow_items bi ON bi.equipment_id = e.equipment_id
    GROUP BY e.equipment_id, e.property_no, e.equipment_name, e.quantity_available
    ORDER BY borrow_count ASC, e.equipment_name
")->fetchAll();

$pageTitle   = 'Equipment Without Borrowing History';
$currentPage = 'no_history';
require_once '../includes/header.php';
?>

<p class="result-count">LEFT JOIN — <?= count($noHistory) ?> item(s) have never been borrowed.</p>

<div class="card" style="margin-bottom:16px">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Property No.</th><th>Equipment Name</th><th class="text-right">Qty Owned</th></tr>
            </thead>
            <tbody>
            <?php if (empty($noHistory)): ?>
                <tr><td colspan="3" class="text-center text-muted" style="padding:20px">All equipment has borrowing history.</td></tr>
            <?php else: foreach ($noHistory as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['property_no']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td class="text-right"><?= $r['quantity_available'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="result-count" style="margin-top:16px">All equipment — borrow count</p>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Property No.</th><th>Equipment Name</th><th class="text-right">Qty Owned</th><th class="text-right">Times Borrowed</th></tr>
            </thead>
            <tbody>
            <?php foreach ($allEquip as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['property_no']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td class="text-right"><?= $r['quantity_available'] ?></td>
                    <td class="text-right"><?= $r['borrow_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
