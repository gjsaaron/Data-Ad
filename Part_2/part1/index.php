<?php
require_once '../db.php';

$tab       = $_GET['tab'] ?? 'borrowers';
$toastMsg  = '';
$toastType = 'error';

function back(string $tab): void {
    header('Location: ?tab=' . urlencode($tab)); exit;
}

function requireText(string $key, string $label, int $min = 1, int $max = 255): string {
    $v = trim($_POST[$key] ?? '');
    if ($v === '') throw new InvalidArgumentException("\"$label\" is required.");
    if (mb_strlen($v) < $min) throw new InvalidArgumentException("\"$label\" must be at least $min character(s).");
    if (mb_strlen($v) > $max) throw new InvalidArgumentException("\"$label\" must be $max characters or fewer.");
    return $v;
}
function requireDigits(string $key, string $label, int $min = 1, int $max = 20): string {
    $v = trim($_POST[$key] ?? '');
    if ($v === '') throw new InvalidArgumentException("\"$label\" is required.");
    if (!ctype_digit($v)) throw new InvalidArgumentException("\"$label\" must contain digits only (0–9).");
    if (strlen($v) < $min) throw new InvalidArgumentException("\"$label\" must be at least $min digit(s).");
    if (strlen($v) > $max) throw new InvalidArgumentException("\"$label\" must be at most $max digit(s).");
    return $v;
}
function requireInt(string $key, string $label, int $min = 0, int $max = PHP_INT_MAX): int {
    $raw = trim($_POST[$key] ?? '');
    if ($raw === '') throw new InvalidArgumentException("\"$label\" is required.");
    if (!ctype_digit($raw)) throw new InvalidArgumentException("\"$label\" must be a whole number (digits only).");
    $v = (int)$raw;
    if ($v < $min) throw new InvalidArgumentException("\"$label\" must be at least $min.");
    if ($v > $max) throw new InvalidArgumentException("\"$label\" must be at most $max.");
    return $v;
}
function requireDate(string $key, string $label): string {
    $v = trim($_POST[$key] ?? '');
    if ($v === '') throw new InvalidArgumentException("\"$label\" is required.");
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) || !checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4)))
        throw new InvalidArgumentException("\"$label\" is not a valid date.");
    return $v;
}
function requireEnum(string $key, string $label, array $allowed): string {
    $v = trim($_POST[$key] ?? '');
    if (!in_array($v, $allowed, true))
        throw new InvalidArgumentException("\"$label\" must be one of: " . implode(', ', $allowed) . ".");
    return $v;
}

try {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_borrower') {
        $no   = requireDigits('borrower_no',  'Borrower No.', 1, 20);
        $name = requireText  ('full_name',    'Full Name',    3, 120);
        $type = requireEnum  ('borrower_type','Borrower Type',['Student','Faculty','Staff']);
        $pdo->prepare('INSERT INTO borrowers (borrower_no,full_name,borrower_type) VALUES (?,?,?)')
            ->execute([$no, $name, $type]);
        back('borrowers');
    }
    if ($action === 'update_borrower') {
        $id   = requireInt   ('borrower_id',  'Borrower ID',  1);
        $no   = requireDigits('borrower_no',  'Borrower No.', 1, 20);
        $name = requireText  ('full_name',    'Full Name',    3, 120);
        $type = requireEnum  ('borrower_type','Borrower Type',['Student','Faculty','Staff']);
        $pdo->prepare('UPDATE borrowers SET borrower_no=?,full_name=?,borrower_type=? WHERE borrower_id=?')
            ->execute([$no, $name, $type, $id]);
        back('borrowers');
    }
    if ($action === 'delete_borrower') {
        $pdo->prepare('DELETE FROM borrowers WHERE borrower_id=?')->execute([(int)$_POST['borrower_id']]);
        back('borrowers');
    }

    if ($action === 'add_equipment') {
        $pno  = requireDigits('property_no',       'Property No.',   1, 30);
        $name = requireText  ('equipment_name',    'Equipment Name', 3, 120);
        $qty  = requireInt   ('quantity_available','Qty Available',  0, 9999);
        $pdo->prepare('INSERT INTO equipment (property_no,equipment_name,quantity_available) VALUES (?,?,?)')
            ->execute([$pno, $name, $qty]);
        back('equipment');
    }
    if ($action === 'update_equipment') {
        $id   = requireInt   ('equipment_id',      'Equipment ID',   1);
        $pno  = requireDigits('property_no',       'Property No.',   1, 30);
        $name = requireText  ('equipment_name',    'Equipment Name', 3, 120);
        $qty  = requireInt   ('quantity_available','Qty Available',  0, 9999);
        $pdo->prepare('UPDATE equipment SET property_no=?,equipment_name=?,quantity_available=? WHERE equipment_id=?')
            ->execute([$pno, $name, $qty, $id]);
        back('equipment');
    }
    if ($action === 'delete_equipment') {
        $pdo->prepare('DELETE FROM equipment WHERE equipment_id=?')->execute([(int)$_POST['equipment_id']]);
        back('equipment');
    }

    if ($action === 'add_transaction') {
        $bid    = requireInt ('borrower_id',  'Borrower',    1);
        $bdate  = requireDate('borrow_date',  'Borrow Date');
        $ddate  = requireDate('due_date',     'Due Date');
        $status = requireEnum('status',       'Status', ['Borrowed','Returned','Overdue','Cancelled']);
        if ($ddate < $bdate) throw new InvalidArgumentException("Due Date must be on or after Borrow Date.");
        $pdo->prepare('INSERT INTO borrow_transactions (borrower_id,borrow_date,due_date,status) VALUES (?,?,?,?)')
            ->execute([$bid, $bdate, $ddate, $status]);
        back('transactions');
    }
    if ($action === 'update_transaction') {
        $id     = requireInt ('borrow_id',    'Transaction ID', 1);
        $bid    = requireInt ('borrower_id',  'Borrower',       1);
        $bdate  = requireDate('borrow_date',  'Borrow Date');
        $ddate  = requireDate('due_date',     'Due Date');
        $status = requireEnum('status',       'Status', ['Borrowed','Returned','Overdue','Cancelled']);
        if ($ddate < $bdate) throw new InvalidArgumentException("Due Date must be on or after Borrow Date.");
        $pdo->prepare('UPDATE borrow_transactions SET borrower_id=?,borrow_date=?,due_date=?,status=? WHERE borrow_id=?')
            ->execute([$bid, $bdate, $ddate, $status, $id]);
        back('transactions');
    }
    if ($action === 'delete_transaction') {
        $pdo->prepare('DELETE FROM borrow_transactions WHERE borrow_id=?')->execute([(int)$_POST['borrow_id']]);
        back('transactions');
    }

    if ($action === 'add_item') {
        $bid  = requireInt('borrow_id',    'Transaction', 1);
        $eid  = requireInt('equipment_id', 'Equipment',   1);
        $qty  = requireInt('quantity',     'Quantity',    1, 999);
        $pdo->prepare('INSERT INTO borrow_items (borrow_id,equipment_id,quantity) VALUES (?,?,?)')
            ->execute([$bid, $eid, $qty]);
        back('items');
    }
    if ($action === 'update_item') {
        $id  = requireInt('borrow_item_id', 'Item ID',  1);
        $qty = requireInt('quantity',        'Quantity', 1, 999);
        $pdo->prepare('UPDATE borrow_items SET quantity=? WHERE borrow_item_id=?')
            ->execute([$qty, $id]);
        back('items');
    }
    if ($action === 'delete_item') {
        $pdo->prepare('DELETE FROM borrow_items WHERE borrow_item_id=?')->execute([(int)$_POST['borrow_item_id']]);
        back('items');
    }

} catch (Throwable $e) {
    $raw = $e->getMessage();
    $toastMsg  = preg_replace('/^SQLSTATE\[\w+\]:\s[^:]+:\s\d+\s+ERROR:\s/i', '', $raw);
    $toastMsg  = $toastMsg ?: $raw;
    $toastType = 'error';
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$borrowers    = $pdo->query('SELECT * FROM borrowers ORDER BY borrower_id DESC')->fetchAll();
$equipment    = $pdo->query('SELECT * FROM equipment ORDER BY equipment_id DESC')->fetchAll();
$transactions = $pdo->query('
    SELECT t.*, b.full_name, b.borrower_no
    FROM borrow_transactions t
    JOIN borrowers b ON b.borrower_id = t.borrower_id
    ORDER BY t.borrow_id DESC
')->fetchAll();
$items = $pdo->query('
    SELECT i.*, e.equipment_name, e.property_no
    FROM borrow_items i
    JOIN equipment e ON e.equipment_id = i.equipment_id
    ORDER BY i.borrow_item_id DESC
')->fetchAll();

$editBorrower = $editEquipment = $editTransaction = $editItem = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($tab === 'borrowers')     { $s=$pdo->prepare('SELECT * FROM borrowers WHERE borrower_id=?');            $s->execute([$editId]); $editBorrower    = $s->fetch(); }
    if ($tab === 'equipment')     { $s=$pdo->prepare('SELECT * FROM equipment WHERE equipment_id=?');           $s->execute([$editId]); $editEquipment   = $s->fetch(); }
    if ($tab === 'transactions')  { $s=$pdo->prepare('SELECT * FROM borrow_transactions WHERE borrow_id=?');   $s->execute([$editId]); $editTransaction = $s->fetch(); }
    if ($tab === 'items')         { $s=$pdo->prepare('SELECT * FROM borrow_items WHERE borrow_item_id=?');     $s->execute([$editId]); $editItem        = $s->fetch(); }
}

$pageTitle   = 'CRUD — Campus Equipment Borrowing';
$currentPage = 'part1';
require_once '../includes/header.php';

$tabs = ['borrowers'=>'Borrowers','equipment'=>'Equipment','transactions'=>'Transactions','items'=>'Borrow Items'];
echo '<div style="display:flex;gap:4px;border-bottom:1px solid #e5e7eb;margin-bottom:20px">';
foreach ($tabs as $key => $label) {
    $a = $tab===$key ? 'background:#fff;border:1px solid #e5e7eb;border-bottom-color:#fff;color:#1d4ed8;font-weight:600;margin-bottom:-1px' : 'color:#6b7280';
    echo "<a href='?tab=$key' style='padding:7px 16px;font-size:13px;text-decoration:none;border-radius:6px 6px 0 0;$a'>$label</a>";
}
echo '</div>';
?>

<?php if ($toastMsg): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ showToast(<?= json_encode($toastMsg) ?>, <?= json_encode($toastType) ?>); });</script>
<?php endif; ?>

<?php if ($tab === 'borrowers'): ?>

<div class="card" style="margin-bottom:16px"><div class="card-body">
    <p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px"><?= $editBorrower ? 'EDIT BORROWER' : 'ADD BORROWER' ?></p>
    <form method="post" class="filter-row" data-validate>
        <input type="hidden" name="action" value="<?= $editBorrower ? 'update_borrower' : 'add_borrower' ?>">
        <?php if ($editBorrower): ?><input type="hidden" name="borrower_id" value="<?= $editBorrower['borrower_id'] ?>"><?php endif; ?>

        <div class="form-group">
            <label>Borrower No. <span style="color:#9ca3af;font-weight:400">(digits only)</span></label>
            <input type="text" inputmode="numeric" name="borrower_no"
                   value="<?= htmlspecialchars($editBorrower['borrower_no'] ?? '') ?>"
                   placeholder="e.g. 001" required maxlength="20"
                   data-numeric data-label="Borrower No." style="min-width:110px">
        </div>
        <div class="form-group">
            <label>Full Name <span style="color:#9ca3af;font-weight:400">(min 3 chars)</span></label>
            <input type="text" name="full_name"
                   value="<?= htmlspecialchars($editBorrower['full_name'] ?? '') ?>"
                   placeholder="First Last Name" required minlength="3" maxlength="120"
                   data-label="Full Name" style="min-width:160px">
        </div>
        <div class="form-group">
            <label>Type</label>
            <select name="borrower_type">
                <?php foreach (['Student','Faculty','Staff'] as $t): ?>
                <option value="<?= $t ?>" <?= ($editBorrower['borrower_type'] ?? 'Student') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn"><?= $editBorrower ? 'Update' : 'Add' ?></button></div>
        <?php if ($editBorrower): ?>
        <div class="form-group"><label>&nbsp;</label><a href="?tab=borrowers" class="btn btn-outline">Cancel</a></div>
        <?php endif; ?>
    </form>
</div></div>

<p class="result-count"><?= count($borrowers) ?> borrower(s)</p>
<div class="card"><div class="table-wrap"><table>
    <thead><tr><th>ID</th><th>Borrower No.</th><th>Full Name</th><th>Type</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($borrowers as $r): ?>
    <tr>
        <td class="text-muted"><?= $r['borrower_id'] ?></td>
        <td><?= htmlspecialchars($r['borrower_no']) ?></td>
        <td><?= htmlspecialchars($r['full_name']) ?></td>
        <td><?= htmlspecialchars($r['borrower_type']) ?></td>
        <td style="white-space:nowrap">
            <a href="?tab=borrowers&edit=<?= $r['borrower_id'] ?>" class="btn btn-outline" style="font-size:12px;padding:4px 10px">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this borrower?')">
                <input type="hidden" name="action" value="delete_borrower">
                <input type="hidden" name="borrower_id" value="<?= $r['borrower_id'] ?>">
                <button class="btn" style="background:#ef4444;font-size:12px;padding:4px 10px">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($borrowers)): ?><tr><td colspan="5" class="text-center text-muted" style="padding:20px">No borrowers yet.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>

<?php elseif ($tab === 'equipment'): ?>

<div class="card" style="margin-bottom:16px"><div class="card-body">
    <p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px"><?= $editEquipment ? 'EDIT EQUIPMENT' : 'ADD EQUIPMENT' ?></p>
    <form method="post" class="filter-row" data-validate>
        <input type="hidden" name="action" value="<?= $editEquipment ? 'update_equipment' : 'add_equipment' ?>">
        <?php if ($editEquipment): ?><input type="hidden" name="equipment_id" value="<?= $editEquipment['equipment_id'] ?>"><?php endif; ?>

        <div class="form-group">
            <label>Property No. <span style="color:#9ca3af;font-weight:400">(digits only)</span></label>
            <input type="text" inputmode="numeric" name="property_no"
                   value="<?= htmlspecialchars($editEquipment['property_no'] ?? '') ?>"
                   placeholder="e.g. 1001" required maxlength="30"
                   data-numeric data-label="Property No." style="min-width:100px">
        </div>
        <div class="form-group">
            <label>Equipment Name <span style="color:#9ca3af;font-weight:400">(3–120 chars)</span></label>
            <input type="text" name="equipment_name"
                   value="<?= htmlspecialchars($editEquipment['equipment_name'] ?? '') ?>"
                   placeholder="Equipment Name" required minlength="3" maxlength="120"
                   data-label="Equipment Name" style="min-width:180px">
        </div>
        <div class="form-group">
            <label>Qty Available <span style="color:#9ca3af;font-weight:400">(0–9999)</span></label>
            <input type="number" name="quantity_available"
                   value="<?= htmlspecialchars((string)($editEquipment['quantity_available'] ?? '1')) ?>"
                   min="0" max="9999" step="1" required
                   data-label="Qty Available" class="no-spin" style="width:80px">
        </div>
        <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn"><?= $editEquipment ? 'Update' : 'Add' ?></button></div>
        <?php if ($editEquipment): ?>
        <div class="form-group"><label>&nbsp;</label><a href="?tab=equipment" class="btn btn-outline">Cancel</a></div>
        <?php endif; ?>
    </form>
</div></div>

<p class="result-count"><?= count($equipment) ?> equipment item(s)</p>
<div class="card"><div class="table-wrap"><table>
    <thead><tr><th>ID</th><th>Property No.</th><th>Equipment Name</th><th class="text-right">Qty Available</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($equipment as $r): ?>
    <tr>
        <td class="text-muted"><?= $r['equipment_id'] ?></td>
        <td><?= htmlspecialchars($r['property_no']) ?></td>
        <td><?= htmlspecialchars($r['equipment_name']) ?></td>
        <td class="text-right"><?= $r['quantity_available'] ?></td>
        <td style="white-space:nowrap">
            <a href="?tab=equipment&edit=<?= $r['equipment_id'] ?>" class="btn btn-outline" style="font-size:12px;padding:4px 10px">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this equipment?')">
                <input type="hidden" name="action" value="delete_equipment">
                <input type="hidden" name="equipment_id" value="<?= $r['equipment_id'] ?>">
                <button class="btn" style="background:#ef4444;font-size:12px;padding:4px 10px">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($equipment)): ?><tr><td colspan="5" class="text-center text-muted" style="padding:20px">No equipment yet.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>

<?php elseif ($tab === 'transactions'): ?>

<div class="card" style="margin-bottom:16px"><div class="card-body">
    <p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px"><?= $editTransaction ? 'EDIT TRANSACTION' : 'ADD TRANSACTION' ?></p>
    <form method="post" class="filter-row" data-validate id="txn-form">
        <input type="hidden" name="action" value="<?= $editTransaction ? 'update_transaction' : 'add_transaction' ?>">
        <?php if ($editTransaction): ?><input type="hidden" name="borrow_id" value="<?= $editTransaction['borrow_id'] ?>"><?php endif; ?>

        <div class="form-group">
            <label>Borrower</label>
            <select name="borrower_id" style="min-width:180px">
                <?php foreach ($borrowers as $b): ?>
                <option value="<?= $b['borrower_id'] ?>" <?= ($editTransaction['borrower_id'] ?? '') == $b['borrower_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['borrower_no'] . ' — ' . $b['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Borrow Date</label>
            <input type="date" name="borrow_date" id="borrow_date"
                   value="<?= htmlspecialchars($editTransaction['borrow_date'] ?? date('Y-m-d')) ?>"
                   required data-label="Borrow Date">
        </div>
        <div class="form-group">
            <label>Due Date</label>
            <input type="date" name="due_date" id="due_date"
                   value="<?= htmlspecialchars($editTransaction['due_date'] ?? date('Y-m-d', strtotime('+3 days'))) ?>"
                   required data-label="Due Date">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <?php foreach (['Borrowed','Returned','Overdue','Cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= ($editTransaction['status'] ?? 'Borrowed') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn"><?= $editTransaction ? 'Update' : 'Add' ?></button></div>
        <?php if ($editTransaction): ?>
        <div class="form-group"><label>&nbsp;</label><a href="?tab=transactions" class="btn btn-outline">Cancel</a></div>
        <?php endif; ?>
    </form>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form    = document.getElementById('txn-form');
    if (!form) return;
    var bDate   = document.getElementById('borrow_date');
    var dDate   = document.getElementById('due_date');

    function checkDates() {
        if (bDate.value && dDate.value && dDate.value < bDate.value) {
            dDate.classList.add('input-error');
            showToast('Due Date must be on or after Borrow Date.', 'warning', 'Invalid Date Range');
            return false;
        }
        dDate.classList.remove('input-error');
        return true;
    }

    bDate.addEventListener('change', checkDates);
    dDate.addEventListener('change', checkDates);

    form.addEventListener('submit', function (e) {
        if (!checkDates()) e.preventDefault();
    }, true); 
});
</script>

<p class="result-count"><?= count($transactions) ?> transaction(s)</p>
<div class="card"><div class="table-wrap"><table>
    <thead><tr><th>ID</th><th>Borrower</th><th>Borrow Date</th><th>Due Date</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($transactions as $r): ?>
    <tr>
        <td class="text-muted"><?= $r['borrow_id'] ?></td>
        <td><?= htmlspecialchars($r['borrower_no'] . ' — ' . $r['full_name']) ?></td>
        <td><?= $r['borrow_date'] ?></td>
        <td><?= $r['due_date'] ?></td>
        <td><?= htmlspecialchars($r['status']) ?></td>
        <td style="white-space:nowrap">
            <a href="?tab=transactions&edit=<?= $r['borrow_id'] ?>" class="btn btn-outline" style="font-size:12px;padding:4px 10px">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this transaction and all its items?')">
                <input type="hidden" name="action" value="delete_transaction">
                <input type="hidden" name="borrow_id" value="<?= $r['borrow_id'] ?>">
                <button class="btn" style="background:#ef4444;font-size:12px;padding:4px 10px">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($transactions)): ?><tr><td colspan="6" class="text-center text-muted" style="padding:20px">No transactions yet.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>

<?php elseif ($tab === 'items'): ?>

<div class="card" style="margin-bottom:16px"><div class="card-body">
    <p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px"><?= $editItem ? 'EDIT BORROW ITEM (qty only)' : 'ADD BORROW ITEM' ?></p>
    <form method="post" class="filter-row" data-validate>
        <?php if ($editItem): ?>
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="borrow_item_id" value="<?= $editItem['borrow_item_id'] ?>">
            <div class="form-group">
                <label>Item ID</label>
                <input type="text" value="<?= $editItem['borrow_item_id'] ?>" disabled style="width:70px;background:#f9fafb;color:#9ca3af">
            </div>
            <div class="form-group">
                <label>New Quantity <span style="color:#9ca3af;font-weight:400">(1–999)</span></label>
                <input type="number" name="quantity"
                       value="<?= htmlspecialchars((string)$editItem['quantity']) ?>"
                       min="1" max="999" step="1" required
                       data-label="Quantity" class="no-spin" style="width:80px">
            </div>
            <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn">Update</button></div>
            <div class="form-group"><label>&nbsp;</label><a href="?tab=items" class="btn btn-outline">Cancel</a></div>
        <?php else: ?>
            <input type="hidden" name="action" value="add_item">
            <div class="form-group">
                <label>Transaction</label>
                <select name="borrow_id" style="min-width:200px">
                    <?php foreach ($transactions as $t): ?>
                    <option value="<?= $t['borrow_id'] ?>">#<?= $t['borrow_id'] ?> — <?= htmlspecialchars($t['full_name']) ?> (<?= $t['status'] ?>)</option>
                    <?php endforeach; ?>
                    <?php if (empty($transactions)): ?><option disabled>No transactions found</option><?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Equipment</label>
                <select name="equipment_id" style="min-width:180px">
                    <?php foreach ($equipment as $e): ?>
                    <option value="<?= $e['equipment_id'] ?>"><?= htmlspecialchars($e['equipment_name']) ?> (<?= $e['quantity_available'] ?> avail)</option>
                    <?php endforeach; ?>
                    <?php if (empty($equipment)): ?><option disabled>No equipment found</option><?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity <span style="color:#9ca3af;font-weight:400">(1–999)</span></label>
                <input type="number" name="quantity" value="1"
                       min="1" max="999" step="1" required
                       data-label="Quantity" class="no-spin" style="width:70px">
            </div>
            <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn">Add</button></div>
        <?php endif; ?>
    </form>
</div></div>

<p class="result-count"><?= count($items) ?> borrow item(s)</p>
<div class="card"><div class="table-wrap"><table>
    <thead><tr><th>ID</th><th>Transaction ID</th><th>Equipment</th><th>Property No.</th><th class="text-right">Qty</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $r): ?>
    <tr>
        <td class="text-muted"><?= $r['borrow_item_id'] ?></td>
        <td><?= $r['borrow_id'] ?></td>
        <td><?= htmlspecialchars($r['equipment_name']) ?></td>
        <td><?= htmlspecialchars($r['property_no']) ?></td>
        <td class="text-right"><?= $r['quantity'] ?></td>
        <td style="white-space:nowrap">
            <a href="?tab=items&edit=<?= $r['borrow_item_id'] ?>" class="btn btn-outline" style="font-size:12px;padding:4px 10px">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this borrow item?')">
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="borrow_item_id" value="<?= $r['borrow_item_id'] ?>">
                <button class="btn" style="background:#ef4444;font-size:12px;padding:4px 10px">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($items)): ?><tr><td colspan="6" class="text-center text-muted" style="padding:20px">No borrow items yet.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
