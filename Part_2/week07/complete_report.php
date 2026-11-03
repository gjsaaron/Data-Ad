<?php
require_once '../db.php';

$statusFilter = in_array($_GET['status'] ?? '', ['Borrowed','Returned','Overdue','Cancelled','']) ? ($_GET['status'] ?? '') : '';
$where  = $statusFilter ? 'WHERE bt.status = :s' : '';
$params = $statusFilter ? [':s' => $statusFilter] : [];

$stmt = $pdo->prepare("
    SELECT
        bt.borrow_id,
        b.borrower_no,
        INITCAP(TRIM(b.full_name))                          AS borrower_name,
        UPPER(b.borrower_type)                              AS borrower_type,
        CONCAT_WS(' / ', b.borrower_no, b.full_name)       AS combined_id,
        UPPER(e.equipment_name)                             AS equipment_upper,
        bi.quantity,
        TO_CHAR(bt.borrow_date, 'Mon DD, YYYY')            AS borrow_date,
        TO_CHAR(bt.due_date,    'Mon DD, YYYY')            AS due_date,
        bt.status,
        bt.fine_rate,
        GREATEST(0, CURRENT_DATE - bt.due_date)            AS days_overdue,
        CASE WHEN bt.status = 'Overdue'
             THEN ROUND(GREATEST(0, CURRENT_DATE - bt.due_date) * bt.fine_rate, 0)
             ELSE 0
        END                                                 AS total_fine
    FROM borrow_transactions bt
    JOIN borrowers    b  ON b.borrower_id  = bt.borrower_id
    JOIN borrow_items bi ON bi.borrow_id   = bt.borrow_id
    JOIN equipment    e  ON e.equipment_id = bi.equipment_id
    $where
    ORDER BY bt.borrow_date DESC, bt.borrow_id
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle   = 'Complete Borrowing Report';
$currentPage = 'complete_report';
require_once '../includes/header.php';
?>

<form method="get" class="filter-row">
    <div class="form-group">
        <label>Status</label>
        <select name="status">
            <option value="">All</option>
            <?php foreach (['Borrowed','Returned','Overdue','Cancelled'] as $s): ?>
            <option <?= $statusFilter===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn">Filter</button></div>
    <div class="form-group"><label>&nbsp;</label><a href="complete_report.php" class="btn btn-outline">Clear</a></div>
</form>

<div class="result-count"><?= count($rows) ?> record(s) — SQL functions: INITCAP · TRIM · UPPER · CONCAT_WS · TO_CHAR · CURRENT_DATE · EXTRACT · ROUND · GREATEST</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Borrower No.</th>
                    <th>Name (INITCAP)</th>
                    <th>Type (UPPER)</th>
                    <th>Equipment</th>
                    <th class="text-right">Qty</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-right">Days Overdue</th>
                    <th class="text-right">Fine Rate</th>
                    <th class="text-right">Total Fine (ROUND)</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="12" class="text-center text-muted" style="padding:20px">No records found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <?php $isOverdue = $r['status'] === 'Overdue'; ?>
                <tr>
                    <td class="text-muted"><?= $r['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_type']) ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($r['equipment_upper']) ?></td>
                    <td class="text-right"><?= $r['quantity'] ?></td>
                    <td><?= $r['borrow_date'] ?></td>
                    <td><?= $r['due_date'] ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td class="text-right"><?= $isOverdue ? '<span style="color:#dc2626;font-weight:600">' . (int)$r['days_overdue'] . '</span>' : '<span style="color:#9ca3af">0</span>' ?></td>
                    <td class="text-right">₱<?= number_format((float)$r['fine_rate'], 2) ?></td>
                    <td class="text-right"><?= $isOverdue ? '<span style="color:#dc2626;font-weight:700">₱' . number_format((float)$r['total_fine'], 0) . '</span>' : '<span style="color:#9ca3af">—</span>' ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
            <tfoot>
                <?php $grandFine = array_sum(array_column($rows, 'total_fine')); ?>
                <tr>
                    <td colspan="11">Total Overdue Fines</td>
                    <td class="text-right" style="color:#dc2626;font-weight:700">₱<?= number_format((float)$grandFine, 0) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
