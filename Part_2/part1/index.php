<?php
session_start();
require_once '../db.php';

$tab       = $_GET['tab'] ?? 'borrowers';
$toastMsg  = '';
$toastType = 'error';

// Auto-add fine_rate column if it doesn't exist yet
try {
    $pdo->exec("
        ALTER TABLE borrow_transactions
            ADD COLUMN IF NOT EXISTS fine_rate NUMERIC(8,2) NOT NULL DEFAULT 5.00
                CHECK (fine_rate >= 0);
        ALTER TABLE borrowers
            ADD CONSTRAINT IF NOT EXISTS uq_borrowers_full_name UNIQUE (full_name);
        ALTER TABLE equipment
            ADD CONSTRAINT IF NOT EXISTS uq_equipment_name UNIQUE (equipment_name);
    ");
} catch (Throwable $_) {}

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

$txnActive = !empty($_SESSION['txn_active']);
$txnQueue  = $_SESSION['txn_queue'] ?? [];

function queueOrRun(PDO $pdo, bool $txnActive, string $label, string $sql, array $params): void {
    if ($txnActive) {
        $_SESSION['txn_queue'][] = ['label' => $label, 'sql' => $sql, 'params' => $params];
    } else {
        $pdo->beginTransaction();
        $pdo->prepare($sql)->execute($params);
        $pdo->commit();
        $_SESSION['last_txn'] = ['status' => 'COMMIT', 'action' => $label, 'time' => date('H:i:s')];
    }
}

try {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add_borrower', 'add_equipment', 'add_transaction', 'add_item'], true) && !$txnActive) {
        throw new InvalidArgumentException('Please click BEGIN to start a transaction before adding records.');
    }

    if ($action === 'txn_begin') {
        $_SESSION['txn_active'] = true;
        $_SESSION['txn_queue']  = [];
        back($_POST['tab'] ?? $tab);
    }
    if ($action === 'txn_commit') {
        $queue = $_SESSION['txn_queue'] ?? [];
        if (empty($queue)) {
            $toastMsg = 'Nothing to commit — queue is empty.'; $toastType = 'warning';
        } else {
            $pdo->beginTransaction();
            foreach ($queue as $op) { $pdo->prepare($op['sql'])->execute($op['params']); }
            $pdo->commit();
            $_SESSION['last_txn'] = ['status'=>'COMMIT','action'=>count($queue).' operation(s) committed','time'=>date('H:i:s')];
            unset($_SESSION['txn_active'], $_SESSION['txn_queue']);
        }
        back($_POST['tab'] ?? $tab);
    }
    if ($action === 'txn_rollback') {
        $count = count($_SESSION['txn_queue'] ?? []);
        unset($_SESSION['txn_active'], $_SESSION['txn_queue']);
        $_SESSION['last_txn'] = ['status'=>'ROLLBACK','action'=>"Rolled back — $count queued operation(s) discarded",'time'=>date('H:i:s')];
        back($_POST['tab'] ?? $tab);
    }

    if ($action === 'add_borrower') {
        $no   = requireDigits('borrower_no',  'Borrower No.', 1, 20);
        $name = requireText  ('full_name',    'Full Name',    3, 120);
        $type = requireEnum  ('borrower_type','Borrower Type',['Student','Faculty','Staff']);
        if (!$txnActive) {
            $dup = $pdo->prepare('SELECT 1 FROM borrowers WHERE full_name = ? OR borrower_no = ?');
            $dup->execute([$name, $no]);
            if ($dup->fetch()) throw new InvalidArgumentException('A borrower with that Name or No. already exists.');
        }
        queueOrRun($pdo, $txnActive, "Add Borrower ($name)",
            'INSERT INTO borrowers (borrower_no,full_name,borrower_type) VALUES (?,?,?)', [$no, $name, $type]);
        back('borrowers');
    }
    if ($action === 'update_borrower') {
        $id   = requireInt   ('borrower_id',  'Borrower ID',  1);
        $no   = requireDigits('borrower_no',  'Borrower No.', 1, 20);
        $name = requireText  ('full_name',    'Full Name',    3, 120);
        $type = requireEnum  ('borrower_type','Borrower Type',['Student','Faculty','Staff']);
        if (!$txnActive) {
            $dup = $pdo->prepare('SELECT 1 FROM borrowers WHERE (full_name = ? OR borrower_no = ?) AND borrower_id <> ?');
            $dup->execute([$name, $no, $id]);
            if ($dup->fetch()) throw new InvalidArgumentException('A borrower with that Name or No. already exists.');
        }
        queueOrRun($pdo, $txnActive, "Update Borrower #$id ($name)",
            'UPDATE borrowers SET borrower_no=?,full_name=?,borrower_type=? WHERE borrower_id=?', [$no, $name, $type, $id]);
        back('borrowers');
    }
    if ($action === 'delete_borrower') {
        queueOrRun($pdo, $txnActive, "Delete Borrower #".(int)$_POST['borrower_id'],
            'DELETE FROM borrowers WHERE borrower_id=?', [(int)$_POST['borrower_id']]);
        back('borrowers');
    }

    if ($action === 'add_equipment') {
        $pno  = requireDigits('property_no',       'Property No.',   1, 30);
        $name = requireText  ('equipment_name',    'Equipment Name', 3, 120);
        $qty  = requireInt   ('quantity_available','Qty Available',  0, 9999);
        if (!$txnActive) {
            $dup = $pdo->prepare('SELECT 1 FROM equipment WHERE equipment_name = ? OR property_no = ?');
            $dup->execute([$name, $pno]);
            if ($dup->fetch()) throw new InvalidArgumentException('Equipment with that Name or Property No. already exists.');
        }
        queueOrRun($pdo, $txnActive, "Add Equipment ($name)",
            'INSERT INTO equipment (property_no,equipment_name,quantity_available) VALUES (?,?,?)', [$pno, $name, $qty]);
        back('equipment');
    }
    if ($action === 'update_equipment') {
        $id   = requireInt   ('equipment_id',      'Equipment ID',   1);
        $pno  = requireDigits('property_no',       'Property No.',   1, 30);
        $name = requireText  ('equipment_name',    'Equipment Name', 3, 120);
        $qty  = requireInt   ('quantity_available','Qty Available',  0, 9999);
        if (!$txnActive) {
            $dup = $pdo->prepare('SELECT 1 FROM equipment WHERE (equipment_name = ? OR property_no = ?) AND equipment_id <> ?');
            $dup->execute([$name, $pno, $id]);
            if ($dup->fetch()) throw new InvalidArgumentException('Equipment with that Name or Property No. already exists.');
        }
        queueOrRun($pdo, $txnActive, "Update Equipment #$id ($name)",
            'UPDATE equipment SET property_no=?,equipment_name=?,quantity_available=? WHERE equipment_id=?', [$pno, $name, $qty, $id]);
        back('equipment');
    }
    if ($action === 'delete_equipment') {
        queueOrRun($pdo, $txnActive, "Delete Equipment #".(int)$_POST['equipment_id'],
            'DELETE FROM equipment WHERE equipment_id=?', [(int)$_POST['equipment_id']]);
        back('equipment');
    }

    if ($action === 'add_transaction') {
        $bid    = requireInt  ('borrower_id',  'Borrower',    1);
        $bdate  = requireDate ('borrow_date',  'Borrow Date');
        $ddate  = requireDate ('due_date',     'Due Date');
        $status = requireEnum ('status',       'Status', ['Borrowed','Returned','Overdue','Cancelled']);
        $rate   = round((float)($_POST['fine_rate'] ?? 5), 2);
        if ($rate < 0) throw new InvalidArgumentException('"Fine Rate" cannot be negative.');
        if ($ddate < $bdate) throw new InvalidArgumentException('Due Date must be on or after Borrow Date.');
        queueOrRun($pdo, $txnActive, "Add Transaction ($status, $bdate)",
            'INSERT INTO borrow_transactions (borrower_id,borrow_date,due_date,status,fine_rate) VALUES (?,?,?,?,?)',
            [$bid, $bdate, $ddate, $status, $rate]);
        back('transactions');
    }
    if ($action === 'update_transaction') {
        $id     = requireInt  ('borrow_id',    'Transaction ID', 1);
        $bid    = requireInt  ('borrower_id',  'Borrower',       1);
        $bdate  = requireDate ('borrow_date',  'Borrow Date');
        $ddate  = requireDate ('due_date',     'Due Date');
        $status = requireEnum ('status',       'Status', ['Borrowed','Returned','Overdue','Cancelled']);
        $rate   = round((float)($_POST['fine_rate'] ?? 5), 2);
        if ($rate < 0) throw new InvalidArgumentException('"Fine Rate" cannot be negative.');
        if ($ddate < $bdate) throw new InvalidArgumentException('Due Date must be on or after Borrow Date.');
        queueOrRun($pdo, $txnActive, "Update Transaction #$id ($status)",
            'UPDATE borrow_transactions SET borrower_id=?,borrow_date=?,due_date=?,status=?,fine_rate=? WHERE borrow_id=?',
            [$bid, $bdate, $ddate, $status, $rate, $id]);
        back('transactions');
    }
    if ($action === 'delete_transaction') {
        queueOrRun($pdo, $txnActive, "Delete Transaction #".(int)$_POST['borrow_id'],
            'DELETE FROM borrow_transactions WHERE borrow_id=?', [(int)$_POST['borrow_id']]);
        back('transactions');
    }

    if ($action === 'add_item') {
        $bid = requireInt('borrow_id',    'Transaction', 1);
        $eid = requireInt('equipment_id', 'Equipment',   1);
        $qty = requireInt('quantity',     'Quantity',    1, 999);
        queueOrRun($pdo, $txnActive, "Add Borrow Item (txn #$bid, equip #$eid, qty $qty)",
            'INSERT INTO borrow_items (borrow_id,equipment_id,quantity) VALUES (?,?,?)', [$bid, $eid, $qty]);
        back('items');
    }
    if ($action === 'update_item') {
        $id  = requireInt('borrow_item_id', 'Item ID',  1);
        $qty = requireInt('quantity',        'Quantity', 1, 999);
        queueOrRun($pdo, $txnActive, "Update Borrow Item #$id (qty → $qty)",
            'UPDATE borrow_items SET quantity=? WHERE borrow_item_id=?', [$qty, $id]);
        back('items');
    }
    if ($action === 'delete_item') {
        queueOrRun($pdo, $txnActive, "Delete Borrow Item #".(int)$_POST['borrow_item_id'],
            'DELETE FROM borrow_items WHERE borrow_item_id=?', [(int)$_POST['borrow_item_id']]);
        back('items');
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($action === 'txn_commit') {
        unset($_SESSION['txn_active'], $_SESSION['txn_queue']);
    }
    $raw = $e->getMessage();
    if (strpos($raw, 'uq_borrowers_full_name') !== false)
        $raw = 'A borrower with that name already exists.';
    elseif (strpos($raw, 'uq_equipment_name') !== false)
        $raw = 'Equipment with that name already exists.';
    elseif (strpos($raw, 'borrower_no') !== false && strpos($raw, 'unique') !== false)
        $raw = 'Borrower No. already exists. Use a different number.';
    elseif (strpos($raw, 'property_no') !== false && strpos($raw, 'unique') !== false)
        $raw = 'Property No. already exists. Use a different number.';
    elseif (strpos($raw, 'quantity_available') !== false && strpos($raw, 'check') !== false)
        $raw = 'Quantity Available cannot be negative.';
    elseif (strpos($raw, 'borrow_items_quantity') !== false)
        $raw = 'Borrow item quantity must be at least 1.';
    elseif (strpos($raw, 'fine_rate') !== false && strpos($raw, 'check') !== false)
        $raw = 'Fine rate cannot be negative.';
    $toastMsg  = preg_replace('/^SQLSTATE\[\w+\]:\s[^:]+:\s\d+\s+ERROR:\s/i', '', $raw);
    $toastMsg  = $toastMsg ?: $raw;
    $toastType = 'error';
    $_SESSION['last_txn'] = ['status'=>'ROLLBACK','action'=>($_POST['action']??'unknown'),'time'=>date('H:i:s'),'error'=>$toastMsg];
}

$q = trim($_GET['q'] ?? '');
$checkQty = max(1, (int)($_GET['check_qty'] ?? 1));

function checkFn(PDO $pdo, string $fn): bool {
    $s = $pdo->prepare("SELECT 1 FROM pg_proc WHERE proname = ?");
    $s->execute([$fn]);
    return (bool)$s->fetchColumn();
}
$fnTotalOk = checkFn($pdo, 'fn_total_borrowed');
$fnAvailOk = checkFn($pdo, 'fn_check_equipment_availability');
$fnFineOk  = checkFn($pdo, 'fn_calculate_fine');

$allBorrowers    = $pdo->query('SELECT * FROM borrowers ORDER BY full_name')->fetchAll();
$allEquipment    = $pdo->query('SELECT * FROM equipment ORDER BY equipment_name')->fetchAll();
$allTransactions = $pdo->query('
    SELECT t.borrow_id, t.status, b.full_name
    FROM borrow_transactions t
    JOIN borrowers b ON b.borrower_id = t.borrower_id
    ORDER BY t.borrow_id DESC
')->fetchAll();

if ($tab === 'borrowers' && $q !== '') {
    $s = $pdo->prepare('SELECT * FROM borrowers WHERE full_name ILIKE ? OR borrower_no ILIKE ? OR borrower_type ILIKE ? ORDER BY borrower_id DESC');
    $s->execute(["%$q%", "%$q%", "%$q%"]);
    $borrowers = $s->fetchAll();
} else {
    $borrowers = $pdo->query('SELECT * FROM borrowers ORDER BY borrower_id DESC')->fetchAll();
}

$totalOutSql = $fnTotalOk ? 'fn_total_borrowed(e.equipment_id)' : '0';
$equipBaseSql = "
    SELECT e.*,
           $totalOutSql                          AS units_out,
           (e.quantity_available - $totalOutSql) AS available_now
    FROM equipment e
";
if ($tab === 'equipment' && $q !== '') {
    $s = $pdo->prepare("$equipBaseSql WHERE e.equipment_name ILIKE ? OR e.property_no ILIKE ? ORDER BY e.equipment_id DESC");
    $s->execute(["%$q%", "%$q%"]);
    $equipment = $s->fetchAll();
} else {
    $equipment = $pdo->query("$equipBaseSql ORDER BY e.equipment_id DESC")->fetchAll();
}

$fnCheckStmt = $fnAvailOk ? $pdo->prepare("SELECT fn_check_equipment_availability(?, ?)") : null;
foreach ($equipment as &$eq) {
    if ($fnCheckStmt) {
        $fnCheckStmt->execute([$eq['equipment_id'], $checkQty]);
        $eq['avail_status'] = $fnCheckStmt->fetchColumn();
    } else {
        $eq['avail_status'] = $eq['available_now'] >= $checkQty
            ? "AVAILABLE — {$eq['equipment_name']}: {$eq['available_now']} units in stock, $checkQty requested. OK to borrow."
            : "UNAVAILABLE — {$eq['equipment_name']}: only {$eq['available_now']} units in stock, $checkQty requested. Not enough.";
    }
}
unset($eq);

$fineSql = $fnFineOk ? 'fn_calculate_fine(t.borrow_id)' : 'ROUND(GREATEST(0, CURRENT_DATE - t.due_date) * t.fine_rate, 0)';
$txnBaseSql = "
    SELECT t.*,
           b.full_name, b.borrower_no,
           GREATEST(0, CURRENT_DATE - t.due_date) AS days_overdue,
           $fineSql                               AS total_fine
    FROM borrow_transactions t
    JOIN borrowers b ON b.borrower_id = t.borrower_id
";
if ($tab === 'transactions' && $q !== '') {
    $s = $pdo->prepare("$txnBaseSql WHERE b.full_name ILIKE ? OR b.borrower_no ILIKE ? OR t.status ILIKE ? OR CAST(t.borrow_id AS TEXT) ILIKE ? ORDER BY t.borrow_id DESC");
    $s->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
    $transactions = $s->fetchAll();
} else {
    $transactions = $pdo->query("$txnBaseSql ORDER BY t.borrow_id DESC")->fetchAll();
}

$itemBaseSql = "
    SELECT i.*, e.equipment_name, e.property_no
    FROM borrow_items i
    JOIN equipment e ON e.equipment_id = i.equipment_id
";
if ($tab === 'items' && $q !== '') {
    $s = $pdo->prepare("$itemBaseSql WHERE e.equipment_name ILIKE ? OR e.property_no ILIKE ? OR CAST(i.borrow_id AS TEXT) ILIKE ? ORDER BY i.borrow_item_id DESC");
    $s->execute(["%$q%", "%$q%", "%$q%"]);
    $items = $s->fetchAll();
} else {
    $items = $pdo->query("$itemBaseSql ORDER BY i.borrow_item_id DESC")->fetchAll();
}

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
    $params = ['tab' => $key];
    if ($q !== '') $params['q'] = $q;
    if ($key === 'equipment' && $checkQty > 1) $params['check_qty'] = $checkQty;
    $url = '?' . http_build_query($params);
    echo "<a href='$url' style='padding:7px 16px;font-size:13px;text-decoration:none;border-radius:6px 6px 0 0;$a'>$label</a>";
}
echo '</div>';
?>

<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;margin-bottom:16px;
            background:<?= $txnActive ? '#fffbeb' : '#f9fafb' ?>;
            border:1px solid <?= $txnActive ? '#fde68a' : '#e5e7eb' ?>;
            border-radius:8px;font-size:13px">
    <span style="font-weight:600;color:#374151">Transaction:</span>

    <?php if (!$txnActive): ?>
    <form method="post" style="display:inline">
        <input type="hidden" name="action" value="txn_begin">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <button class="btn" style="background:#1d4ed8;font-size:12px;padding:5px 14px">BEGIN</button>
    </form>
    <span style="color:#6b7280;font-size:12px">— Click BEGIN to start a transaction before adding records.</span>

    <?php else: ?>
    <span style="background:#fef3c7;color:#92400e;font-weight:700;padding:3px 10px;border-radius:20px;font-size:12px">
        ● TRANSACTION OPEN — <?= count($txnQueue) ?> operation(s) queued
    </span>
    <form method="post" style="display:inline">
        <input type="hidden" name="action" value="txn_commit">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <button class="btn" style="background:#166534;font-size:12px;padding:5px 14px">COMMIT</button>
    </form>
    <form method="post" style="display:inline">
        <input type="hidden" name="action" value="txn_rollback">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <button class="btn" style="background:#dc2626;font-size:12px;padding:5px 14px">ROLLBACK</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($txnActive && !empty($txnQueue)): ?>
<div class="card" style="margin-bottom:16px;border-color:#fde68a;background:#fffdfa">
    <div class="card-body" style="padding:12px 16px">
        <p style="font-size:12px;font-weight:600;color:#92400e;margin-bottom:8px">Pending Operations (not yet saved to database)</p>
        <ol style="margin:0;padding-left:18px;font-size:12.5px;color:#374151">
            <?php foreach ($txnQueue as $op): ?>
            <li style="margin-bottom:3px"><?= htmlspecialchars($op['label']) ?></li>
            <?php endforeach; ?>
        </ol>
    </div>
</div>
<?php endif; ?>

<?php if ($toastMsg): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ showToast(<?= json_encode($toastMsg) ?>, <?= json_encode($toastType) ?>); });</script>
<?php endif; ?>

<?php
$lastTxn = $_SESSION['last_txn'] ?? null;
unset($_SESSION['last_txn']);
if ($lastTxn):
    $isCommit = $lastTxn['status'] === 'COMMIT';
    $bgColor  = $isCommit ? '#f0fdf4' : '#fff1f2';
    $bdColor  = $isCommit ? '#bbf7d0' : '#fecdd3';
    $txColor  = $isCommit ? '#166534' : '#9f1239';
    $icon     = $isCommit ? '✅' : '⚠️';
?>
<div style="background:<?= $bgColor ?>;border:1px solid <?= $bdColor ?>;border-radius:8px;
            padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px">
    <span style="font-size:16px"><?= $icon ?></span>
    <span>
        <strong style="color:<?= $txColor ?>"><?= $lastTxn['status'] ?></strong>
        &nbsp;—&nbsp;<?= htmlspecialchars($lastTxn['action']) ?>
        &nbsp;<span style="color:#9ca3af"><?= $lastTxn['time'] ?></span>
        <?php if (!$isCommit && !empty($lastTxn['error'])): ?>
            &nbsp;<span style="color:#dc2626">— <?= htmlspecialchars($lastTxn['error']) ?></span>
        <?php endif; ?>
    </span>
</div>
<?php endif; ?>

<form method="get" class="card" style="margin-bottom:16px">
    <div class="card-body" style="padding:10px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <span style="font-weight:600;font-size:13px;color:#374151">Search <?= htmlspecialchars($tabs[$tab]) ?>:</span>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
               placeholder="<?= match($tab) {
                   'equipment'    => 'Search equipment name or property no...',
                   'transactions' => 'Search borrower name, no., status (e.g. Overdue), ID...',
                   'items'        => 'Search equipment name, property no., borrow ID...',
                   default        => 'Search borrower name, no., type...',
               } ?>"
               style="flex:1;min-width:220px;padding:6px 12px;font-size:13px">

        <?php if ($tab === 'equipment'): ?>
        <div style="display:flex;align-items:center;gap:6px">
            <label style="font-size:12px;color:#6b7280;white-space:nowrap;margin:0">Check Qty:</label>
            <input type="number" name="check_qty" min="1" max="999" value="<?= htmlspecialchars((string)$checkQty) ?>"
                   style="width:70px;padding:6px 8px;font-size:13px" title="Quantity to check availability">
        </div>
        <?php endif; ?>

        <button class="btn" style="padding:6px 16px;font-size:13px">Search</button>
        <?php if ($q !== '' || ($tab === 'equipment' && $checkQty > 1)): ?>
        <a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-outline" style="padding:6px 12px;font-size:13px">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($tab === 'borrowers'): ?>

<div class="card" style="margin-bottom:16px"><div class="card-body">
    <p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px"><?= $editBorrower ? 'EDIT BORROWER' : 'ADD BORROWER' ?></p>
    <?php if (!$txnActive && !$editBorrower): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:12.5px;color:#92400e">
        🔒 Click <strong>BEGIN</strong> above to open a transaction before adding.
    </div>
    <?php endif; ?>
    <form method="post" class="filter-row" data-validate <?= (!$txnActive && !$editBorrower) ? 'data-require-txn="1"' : '' ?>>
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
        <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn" <?= (!$txnActive && !$editBorrower) ? 'disabled style="opacity:0.5;cursor:not-allowed" title="Click BEGIN above first"' : '' ?>><?= $editBorrower ? 'Update' : 'Add' ?></button></div>
        <?php if ($editBorrower): ?>
        <div class="form-group"><label>&nbsp;</label><a href="?tab=borrowers" class="btn btn-outline">Cancel</a></div>
        <?php endif; ?>
    </form>
</div></div>

<p class="result-count"><?= count($borrowers) ?> borrower(s)<?= $q !== '' ? ' matching "' . htmlspecialchars($q) . '"' : '' ?></p>
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
    <?php if (!$txnActive && !$editEquipment): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:12.5px;color:#92400e">
        🔒 Click <strong>BEGIN</strong> above to open a transaction before adding.
    </div>
    <?php endif; ?>
    <form method="post" class="filter-row" data-validate <?= (!$txnActive && !$editEquipment) ? 'data-require-txn="1"' : '' ?>>
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
        <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn" <?= (!$txnActive && !$editEquipment) ? 'disabled style="opacity:0.5;cursor:not-allowed" title="Click BEGIN above first"' : '' ?>><?= $editEquipment ? 'Update' : 'Add' ?></button></div>
        <?php if ($editEquipment): ?>
        <div class="form-group"><label>&nbsp;</label><a href="?tab=equipment" class="btn btn-outline">Cancel</a></div>
        <?php endif; ?>
    </form>
</div></div>

<p class="result-count">
    <?= count($equipment) ?> equipment item(s)<?= $q !== '' ? ' matching "' . htmlspecialchars($q) . '"' : '' ?>
    <?php if ($checkQty > 1): ?> — checked availability for qty: <?= $checkQty ?><?php endif; ?>
</p>

<?php if ($q !== '' && !empty($equipment)): ?>
<?php
$topItem = $equipment[0];
$topMsg  = $topItem['avail_status'] ?? '';
$isAvail = str_starts_with($topMsg, 'AVAILABLE');
?>
<div style="background:<?= $isAvail ? '#f0fdf4' : '#fff1f2' ?>;border:1px solid <?= $isAvail ? '#bbf7d0' : '#fecdd3' ?>;border-radius:8px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:8px;font-size:13px">
    <span><?= $isAvail ? '✅' : '⚠️' ?></span>
    <span style="color:<?= $isAvail ? '#166534' : '#9f1239' ?>">
        <strong>Availability Check:</strong> <?= htmlspecialchars($topMsg) ?>
    </span>
</div>
<?php endif; ?>

<div class="card"><div class="table-wrap"><table>
    <thead><tr>
        <th>ID</th>
        <th>Property No.</th>
        <th>Equipment Name</th>
        <th class="text-right">Qty Owned</th>
        <th class="text-right">Units Out</th>
        <th class="text-right">Available Now</th>
        <th>Availability (Qty <?= $checkQty ?>)</th>
        <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($equipment as $r): ?>
    <?php
    $isAvail = str_starts_with($r['avail_status'] ?? '', 'AVAILABLE');
    ?>
    <tr>
        <td class="text-muted"><?= $r['equipment_id'] ?></td>
        <td><?= htmlspecialchars($r['property_no']) ?></td>
        <td><?= htmlspecialchars($r['equipment_name']) ?></td>
        <td class="text-right"><?= $r['quantity_available'] ?></td>
        <td class="text-right"><?= $r['units_out'] ?></td>
        <td class="text-right"><strong><?= $r['available_now'] ?></strong></td>
        <td>
            <span class="badge <?= $isAvail ? 'badge-returned' : 'badge-overdue' ?>" title="<?= htmlspecialchars($r['avail_status'] ?? '') ?>">
                <?= $isAvail ? 'Available' : 'Unavailable' ?>
            </span>
        </td>
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
    <?php if (empty($equipment)): ?><tr><td colspan="8" class="text-center text-muted" style="padding:20px">No equipment found.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>

<?php elseif ($tab === 'transactions'): ?>

<div class="card" style="margin-bottom:16px"><div class="card-body">
    <p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px"><?= $editTransaction ? 'EDIT TRANSACTION' : 'ADD TRANSACTION' ?></p>
    <?php if (!$txnActive && !$editTransaction): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:12.5px;color:#92400e">
        🔒 Click <strong>BEGIN</strong> above to open a transaction before adding.
    </div>
    <?php endif; ?>
    <form method="post" class="filter-row" data-validate id="txn-form" <?= (!$txnActive && !$editTransaction) ? 'data-require-txn="1"' : '' ?>>
        <input type="hidden" name="action" value="<?= $editTransaction ? 'update_transaction' : 'add_transaction' ?>">
        <?php if ($editTransaction): ?><input type="hidden" name="borrow_id" value="<?= $editTransaction['borrow_id'] ?>"><?php endif; ?>

        <div class="form-group">
            <label>Borrower</label>
            <select name="borrower_id" style="min-width:180px">
                <?php foreach ($allBorrowers as $b): ?>
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
        <div class="form-group">
            <label>Fine Rate <span style="color:#9ca3af;font-weight:400">(₱/day)</span></label>
            <input type="number" name="fine_rate" min="0" max="9999" step="0.01"
                   value="<?= htmlspecialchars((string)($editTransaction['fine_rate'] ?? '5.00')) ?>"
                   required data-label="Fine Rate" style="width:90px">
        </div>
        <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn" <?= (!$txnActive && !$editTransaction) ? 'disabled style="opacity:0.5;cursor:not-allowed" title="Click BEGIN above first"' : '' ?>><?= $editTransaction ? 'Update' : 'Add' ?></button></div>
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

<p class="result-count"><?= count($transactions) ?> transaction(s)<?= $q !== '' ? ' matching "' . htmlspecialchars($q) . '"' : '' ?></p>
<div class="card"><div class="table-wrap"><table>
    <thead><tr><th>ID</th><th>Borrower</th><th>Borrow Date</th><th>Due Date</th><th>Status</th><th class="text-right">Fine Rate</th><th class="text-right">Days Overdue</th><th class="text-right">Total Fine</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($transactions as $r): ?>
    <?php $isOverdue = $r['status'] === 'Overdue'; ?>
    <tr>
        <td class="text-muted"><?= $r['borrow_id'] ?></td>
        <td><?= htmlspecialchars($r['borrower_no'] . ' — ' . $r['full_name']) ?></td>
        <td><?= $r['borrow_date'] ?></td>
        <td><?= $r['due_date'] ?></td>
        <td><?= statusBadge($r['status']) ?></td>
        <td class="text-right">₱<?= number_format((float)$r['fine_rate'], 2) ?></td>
        <td class="text-right"><?= $isOverdue ? '<span style="color:#dc2626;font-weight:600">' . (int)$r['days_overdue'] . ' day(s)</span>' : '<span style="color:#9ca3af">—</span>' ?></td>
        <td class="text-right"><?= $isOverdue ? '<span style="color:#dc2626;font-weight:700">₱' . number_format((float)$r['total_fine'], 0) . '</span>' : '<span style="color:#9ca3af">—</span>' ?></td>
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
    <?php if (empty($transactions)): ?><tr><td colspan="9" class="text-center text-muted" style="padding:20px">No transactions found.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>

<?php elseif ($tab === 'items'): ?>

<div class="card" style="margin-bottom:16px"><div class="card-body">
    <p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px"><?= $editItem ? 'EDIT BORROW ITEM (qty only)' : 'ADD BORROW ITEM' ?></p>
    <?php if (!$txnActive && !$editItem): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:12.5px;color:#92400e">
        🔒 Click <strong>BEGIN</strong> above to open a transaction before adding.
    </div>
    <?php endif; ?>
    <form method="post" class="filter-row" data-validate <?= (!$txnActive && !$editItem) ? 'data-require-txn="1"' : '' ?>>
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
                    <?php foreach ($allTransactions as $t): ?>
                    <option value="<?= $t['borrow_id'] ?>">#<?= $t['borrow_id'] ?> — <?= htmlspecialchars($t['full_name']) ?> (<?= $t['status'] ?>)</option>
                    <?php endforeach; ?>
                    <?php if (empty($allTransactions)): ?><option disabled>No transactions found</option><?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Equipment</label>
                <select name="equipment_id" style="min-width:180px">
                    <?php foreach ($allEquipment as $e): ?>
                    <option value="<?= $e['equipment_id'] ?>"><?= htmlspecialchars($e['equipment_name']) ?> (<?= $e['quantity_available'] ?> avail)</option>
                    <?php endforeach; ?>
                    <?php if (empty($allEquipment)): ?><option disabled>No equipment found</option><?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity <span style="color:#9ca3af;font-weight:400">(1–999)</span></label>
                <input type="number" name="quantity" value="1"
                       min="1" max="999" step="1" required
                       data-label="Quantity" class="no-spin" style="width:70px">
            </div>
            <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn" <?= (!$txnActive) ? 'disabled style="opacity:0.5;cursor:not-allowed" title="Click BEGIN above first"' : '' ?>>Add</button></div>
        <?php endif; ?>
    </form>
</div></div>

<p class="result-count"><?= count($items) ?> borrow item(s)<?= $q !== '' ? ' matching "' . htmlspecialchars($q) . '"' : '' ?></p>
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

<?php
$auditExists = (bool)$pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name='borrow_items_audit'")->fetchColumn();
if ($auditExists):
    $auditRows = $pdo->query("
        SELECT a.audit_id, a.borrow_item_id, a.borrow_id,
               e.equipment_name, a.old_quantity, a.new_quantity, a.operation,
               TO_CHAR(a.changed_at,'YYYY-MM-DD HH24:MI:SS') AS changed_at
        FROM borrow_items_audit a
        LEFT JOIN equipment e ON e.equipment_id = a.equipment_id
        ORDER BY a.audit_id DESC LIMIT 15
    ")->fetchAll();
?>
<p class="result-count" style="margin-top:20px">Audit Log — last 15 changes to Borrow Items (trigger: <strong>trg_borrow_items_audit</strong>)</p>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Audit ID</th><th>Item ID</th><th>Borrow ID</th><th>Equipment</th>
                <th class="text-right">Old Qty</th><th class="text-right">New Qty</th>
                <th>Operation</th><th>Timestamp</th>
            </tr></thead>
            <tbody>
            <?php if (empty($auditRows)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:16px">No audit records yet. Add, edit, or delete a borrow item.</td></tr>
            <?php else: foreach ($auditRows as $ar): ?>
                <?php
                $opColor = match($ar['operation']) {
                    'INSERT' => '#166534',
                    'UPDATE' => '#1d4ed8',
                    'DELETE' => '#dc2626',
                    default  => '#374151',
                };
                ?>
                <tr>
                    <td class="text-muted"><?= $ar['audit_id'] ?></td>
                    <td><?= $ar['borrow_item_id'] ?? '—' ?></td>
                    <td><?= $ar['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($ar['equipment_name'] ?? '?') ?></td>
                    <td class="text-right"><?= $ar['old_quantity'] ?? '—' ?></td>
                    <td class="text-right"><?= $ar['new_quantity'] ?? '—' ?></td>
                    <td><strong style="color:<?= $opColor ?>"><?= $ar['operation'] ?></strong></td>
                    <td style="font-size:12px;color:#6b7280"><?= $ar['changed_at'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-require-txn]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof showToast === 'function') {
                showToast('Please click BEGIN to start a transaction before adding.', 'warning', 'Transaction Required');
            }
        }, true);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
