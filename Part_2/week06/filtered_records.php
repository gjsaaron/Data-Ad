<?php
require_once '../db.php';

$statuses     = array_filter((array)($_GET['status'] ?? []), fn($s) => in_array($s, ['Borrowed','Returned','Overdue','Cancelled']));
$borrowerType = in_array($_GET['borrower_type'] ?? '', ['Student','Faculty','Staff','']) ? ($_GET['borrower_type'] ?? '') : '';
$dateFrom     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$dateTo       = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']   ?? '') ? $_GET['date_to']   : '';
$search       = trim($_GET['search'] ?? '');
$sortBy       = in_array($_GET['sort'] ?? '', ['borrow_date','due_date','full_name','status']) ? $_GET['sort'] : 'borrow_date';
$sortDir      = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$where = []; $params = [];

if (!empty($statuses)) {
    $ph = [];
    foreach (array_values($statuses) as $i => $s) { $ph[] = ":s$i"; $params[":s$i"] = $s; }
    $where[] = 'bt.status IN (' . implode(',', $ph) . ')';
}
if ($borrowerType) { $where[] = 'b.borrower_type = :bt'; $params[':bt'] = $borrowerType; }
if ($dateFrom)     { $where[] = 'bt.borrow_date >= :df'; $params[':df'] = $dateFrom; }
if ($dateTo)       { $where[] = 'bt.borrow_date <= :dt'; $params[':dt'] = $dateTo; }
if ($search)       { $where[] = '(b.full_name ILIKE :s OR e.equipment_name ILIKE :s2)'; $params[':s'] = "%$search%"; $params[':s2'] = "%$search%"; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT bt.borrow_id, b.borrower_no, b.full_name, b.borrower_type,
           e.equipment_name, bi.quantity,
           bt.borrow_date, bt.due_date, bt.status
    FROM borrow_transactions bt
    JOIN borrowers    b  ON b.borrower_id  = bt.borrower_id
    JOIN borrow_items bi ON bi.borrow_id   = bt.borrow_id
    JOIN equipment    e  ON e.equipment_id = bi.equipment_id
    $whereSql
    ORDER BY $sortBy $sortDir
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle   = 'Filtered Borrowing Records';
$currentPage = 'filtered';
require_once '../includes/header.php';
?>

<!-- Filters -->
<form method="get">
    <div class="filter-row" style="margin-bottom:10px">
        <div class="form-group">
            <label>Status</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;padding-top:2px">
                <?php foreach (['Borrowed','Returned','Overdue','Cancelled'] as $s): ?>
                <label style="display:flex;align-items:center;gap:4px;font-size:13px;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="status[]" value="<?= $s ?>"
                           <?= in_array($s, $statuses) ? 'checked' : '' ?>>
                    <?= $s ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="filter-row">
        <div class="form-group">
            <label>Borrower Type</label>
            <select name="borrower_type">
                <option value="">All</option>
                <?php foreach (['Student','Faculty','Staff'] as $t): ?>
                <option <?= $borrowerType === $t ? 'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Date From</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label>Date To</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div class="form-group">
            <label>Search</label>
            <input type="text" name="search" placeholder="Name or equipment" value="<?= htmlspecialchars($search) ?>" style="min-width:160px">
        </div>
        <div class="form-group">
            <label>Sort By</label>
            <select name="sort">
                <option value="borrow_date" <?= $sortBy==='borrow_date'?'selected':'' ?>>Borrow Date</option>
                <option value="due_date"    <?= $sortBy==='due_date'   ?'selected':'' ?>>Due Date</option>
                <option value="full_name"   <?= $sortBy==='full_name'  ?'selected':'' ?>>Name</option>
                <option value="status"      <?= $sortBy==='status'     ?'selected':'' ?>>Status</option>
            </select>
        </div>
        <div class="form-group">
            <label>Direction</label>
            <select name="dir">
                <option value="DESC" <?= $sortDir==='DESC'?'selected':'' ?>>DESC</option>
                <option value="ASC"  <?= $sortDir==='ASC' ?'selected':'' ?>>ASC</option>
            </select>
        </div>
        <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn">Filter</button></div>
        <div class="form-group"><label>&nbsp;</label><a href="filtered_records.php" class="btn btn-outline">Clear</a></div>
    </div>
</form>

<div class="result-count"><?= count($rows) ?> record(s) found</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Borrower No.</th><th>Name</th><th>Type</th>
                    <th>Equipment</th><th class="text-right">Qty</th>
                    <th>Borrow Date</th><th>Due Date</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:24px">No records match the filters.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted"><?= $r['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($r['borrower_no']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= htmlspecialchars($r['borrower_type']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td class="text-right"><?= $r['quantity'] ?></td>
                    <td><?= $r['borrow_date'] ?></td>
                    <td><?= $r['due_date'] ?></td>
                    <td><?= $r['status'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
