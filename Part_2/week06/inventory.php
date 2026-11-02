<?php
require_once '../db.php';

$sortBy  = in_array($_GET['sort'] ?? '', ['property_no','equipment_name','quantity_available','available_now'])
           ? $_GET['sort'] : 'equipment_name';
$sortDir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$stock   = in_array($_GET['stock'] ?? '', ['in_stock','low_stock','out_of_stock'])
           ? $_GET['stock'] : 'all';

$rows = $pdo->query("
    SELECT
        e.property_no,
        e.equipment_name,
        e.quantity_available AS owned,
        COALESCE((
            SELECT SUM(bi.quantity)
            FROM borrow_items bi
            JOIN borrow_transactions bt ON bt.borrow_id = bi.borrow_id
            WHERE bi.equipment_id = e.equipment_id
              AND bt.status IN ('Borrowed','Overdue')
        ), 0) AS units_out,
        e.quantity_available - COALESCE((
            SELECT SUM(bi.quantity)
            FROM borrow_items bi
            JOIN borrow_transactions bt ON bt.borrow_id = bi.borrow_id
            WHERE bi.equipment_id = e.equipment_id
              AND bt.status IN ('Borrowed','Overdue')
        ), 0) AS available_now
    FROM equipment e
    ORDER BY $sortBy $sortDir
")->fetchAll();

// filter by stock status
$rows = array_filter($rows, function($r) use ($stock) {
    $a = (int)$r['available_now']; $o = (int)$r['owned'];
    if ($stock === 'out_of_stock') return $a <= 0;
    if ($stock === 'low_stock')    return $a > 0 && $o > 0 && ($a/$o) <= 0.25;
    if ($stock === 'in_stock')     return $a > 0 && !($o > 0 && ($a/$o) <= 0.25);
    return true;
});

// DISTINCT borrower types
$types = $pdo->query("SELECT DISTINCT borrower_type FROM borrowers ORDER BY borrower_type")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle   = 'Equipment Inventory Listing';
$currentPage = 'inventory';
require_once '../includes/header.php';
?>

<!-- Distinct borrower types -->
<p style="margin-bottom:16px;color:#6b7280;font-size:13px">
    Borrower types: <?= implode(', ', array_map('htmlspecialchars', $types)) ?>
</p>

<!-- Filters -->
<form method="get" class="filter-row">
    <div class="form-group">
        <label>Stock Status</label>
        <select name="stock">
            <option value="all"          <?= $stock === 'all'          ? 'selected':'' ?>>All</option>
            <option value="in_stock"     <?= $stock === 'in_stock'     ? 'selected':'' ?>>In Stock</option>
            <option value="low_stock"    <?= $stock === 'low_stock'    ? 'selected':'' ?>>Low Stock</option>
            <option value="out_of_stock" <?= $stock === 'out_of_stock' ? 'selected':'' ?>>Out of Stock</option>
        </select>
    </div>
    <div class="form-group">
        <label>Sort By</label>
        <select name="sort">
            <option value="equipment_name"     <?= $sortBy === 'equipment_name'     ? 'selected':'' ?>>Name</option>
            <option value="property_no"        <?= $sortBy === 'property_no'        ? 'selected':'' ?>>Property No.</option>
            <option value="quantity_available" <?= $sortBy === 'quantity_available' ? 'selected':'' ?>>Qty Owned</option>
            <option value="available_now"      <?= $sortBy === 'available_now'      ? 'selected':'' ?>>Available Now</option>
        </select>
    </div>
    <div class="form-group">
        <label>Direction</label>
        <select name="dir">
            <option value="ASC"  <?= $sortDir === 'ASC'  ? 'selected':'' ?>>ASC</option>
            <option value="DESC" <?= $sortDir === 'DESC' ? 'selected':'' ?>>DESC</option>
        </select>
    </div>
    <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn">Apply</button></div>
    <div class="form-group"><label>&nbsp;</label><a href="inventory.php" class="btn btn-outline">Reset</a></div>
</form>

<div class="result-count"><?= count($rows) ?> item(s) shown</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Property No.</th>
                    <th>Equipment Name</th>
                    <th class="text-right">Qty Owned</th>
                    <th class="text-right">Units Out</th>
                    <th class="text-right">Available Now</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $avail = (int)$r['available_now'];
                $owned = (int)$r['owned'];
                $status = $avail <= 0 ? 'Out of Stock'
                        : ($owned > 0 && ($avail/$owned) <= 0.25 ? 'Low Stock' : 'In Stock');
            ?>
                <tr>
                    <td><?= htmlspecialchars($r['property_no']) ?></td>
                    <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                    <td class="text-right"><?= $owned ?></td>
                    <td class="text-right"><?= $r['units_out'] ?></td>
                    <td class="text-right fw-bold"><?= $avail ?></td>
                    <td><?= $status ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
