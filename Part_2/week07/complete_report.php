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
        LOWER(e.equipment_name)                             AS equipment_lower,
        bi.quantity,
        TO_CHAR(bt.borrow_date, 'Mon DD, YYYY')            AS borrow_date,
        TO_CHAR(bt.due_date,    'Mon DD, YYYY')            AS due_date,
        bt.status,
        CURRENT_DATE                                        AS today,
        EXTRACT(YEAR  FROM bt.borrow_date)::INT            AS borrow_year,
        EXTRACT(MONTH FROM bt.borrow_date)::INT            AS borrow_month,
        CASE WHEN bt.status IN ('Borrowed','Overdue')
             THEN GREATEST(0, CURRENT_DATE - bt.due_date)
             ELSE 0
        END                                                 AS days_overdue
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

<div class="result-count"><?= count($rows) ?> record(s) — SQL functions used: INITCAP · TRIM · UPPER · LOWER · CONCAT_WS · TO_CHAR · CURRENT_DATE · EXTRACT</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Borrower No.</th>
                    <th>Name (INITCAP)</th>
                    <th>Type (UPPER)</th>
                    <th>Combined ID (CONCAT_WS)</th>
                    <th>Equipment (UPPER)</th>
                    <th class="text-right">Qty</th>
                    <th>Borrow Date (TO_CHAR)</th>
                    <th>Due Date (TO_CHAR)</th>
                    <th>Status</th>
                    <th class="text-right">Days Overdue</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="11" class="text-center text-muted" style="padding:20px">No records found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted"><?= $r['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_type']) ?></td>
                    <td style="font-size:12px;color:#6b7280"><?= htmlspecialchars($r['combined_id']) ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($r['equipment_upper']) ?></td>
                    <td class="text-right"><?= $r['quantity'] ?></td>
                    <td><?= $r['borrow_date'] ?></td>
                    <td><?= $r['due_date'] ?></td>
                    <td><?= $r['status'] ?></td>
                    <td class="text-right <?= $r['days_overdue'] > 0 ? 'fw-bold' : 'text-muted' ?>"><?= $r['days_overdue'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
