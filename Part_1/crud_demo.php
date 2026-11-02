<?php

// =====================================================
// DATABASE CONNECTION
// =====================================================

$host = 'localhost';
$port = '5432';
$dbname = 'postgres';
$user = 'postgres';
$password = 'Aaron0928';

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) {
    die("<p style='color:red;font-family:Arial;padding:20px'>Connection failed: " . htmlspecialchars(pg_last_error()) . "</p>");
}

// =====================================================
// CREATE TABLES
// =====================================================

pg_query($conn, "
    CREATE TABLE IF NOT EXISTS borrowers (
        borrower_id   SERIAL PRIMARY KEY,
        borrower_no   VARCHAR(30)  NOT NULL UNIQUE,
        full_name     VARCHAR(120) NOT NULL,
        borrower_type VARCHAR(50)  NOT NULL
    );
    CREATE TABLE IF NOT EXISTS equipment (
        equipment_id       SERIAL PRIMARY KEY,
        property_no        VARCHAR(30)  NOT NULL UNIQUE,
        equipment_name     VARCHAR(120) NOT NULL,
        quantity_available INT NOT NULL DEFAULT 0 CHECK (quantity_available >= 0)
    );
    CREATE TABLE IF NOT EXISTS borrow_transactions (
        borrow_id   SERIAL PRIMARY KEY,
        borrower_id INT NOT NULL REFERENCES borrowers(borrower_id),
        borrow_date DATE NOT NULL,
        due_date    DATE NOT NULL,
        status      VARCHAR(20) NOT NULL DEFAULT 'Pending'
    );
    CREATE TABLE IF NOT EXISTS borrow_items (
        borrow_item_id SERIAL PRIMARY KEY,
        borrow_id      INT NOT NULL REFERENCES borrow_transactions(borrow_id),
        equipment_id   INT NOT NULL REFERENCES equipment(equipment_id),
        quantity       INT NOT NULL DEFAULT 1,
        UNIQUE (borrow_id, equipment_id)
    );
");

$action = $_POST['action'] ?? '';
$tab    = $_GET['tab']    ?? 'borrowers';

// =====================================================
// BORROWERS
// =====================================================

if ($action === 'add_borrower') {
    $r = pg_query_params($conn,
        'INSERT INTO borrowers (borrower_no, full_name, borrower_type) VALUES ($1,$2,$3)',
        [trim($_POST['borrower_no']), trim($_POST['full_name']), trim($_POST['borrower_type'])]
    );
    if (!$r) $error = pg_last_error($conn);
    else { header("Location: ?tab=borrowers"); exit; }
}

if ($action === 'delete_borrower') {
    $r = pg_query_params($conn, 'DELETE FROM borrowers WHERE borrower_id=$1', [(int)$_POST['borrower_id']]);
    if (!$r) $error = pg_last_error($conn);
    else { header("Location: ?tab=borrowers"); exit; }
}

// =====================================================
// EQUIPMENT
// =====================================================

if ($action === 'add_equipment') {
    $r = pg_query_params($conn,
        'INSERT INTO equipment (property_no, equipment_name, quantity_available) VALUES ($1,$2,$3)',
        [trim($_POST['property_no']), trim($_POST['equipment_name']), (int)$_POST['quantity_available']]
    );
    if (!$r) $error = pg_last_error($conn);
    else { header("Location: ?tab=equipment"); exit; }
}

if ($action === 'delete_equipment') {
    $r = pg_query_params($conn, 'DELETE FROM equipment WHERE equipment_id=$1', [(int)$_POST['equipment_id']]);
    if (!$r) $error = pg_last_error($conn);
    else { header("Location: ?tab=equipment"); exit; }
}

// =====================================================
// BORROW TRANSACTIONS
// =====================================================

if ($action === 'add_transaction') {
    $r = pg_query_params($conn,
        'INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status) VALUES ($1,$2,$3,$4)',
        [(int)$_POST['borrower_id'], $_POST['borrow_date'], $_POST['due_date'], trim($_POST['status'])]
    );
    if (!$r) $error = pg_last_error($conn);
    else { header("Location: ?tab=transactions"); exit; }
}

if ($action === 'update_transaction') {
    $r = pg_query_params($conn,
        'UPDATE borrow_transactions SET status=$1 WHERE borrow_id=$2',
        [trim($_POST['status']), (int)$_POST['borrow_id']]
    );
    if (!$r) $error = pg_last_error($conn);
    else { header("Location: ?tab=transactions"); exit; }
}

if ($action === 'delete_transaction') {
    $r = pg_query_params($conn, 'DELETE FROM borrow_transactions WHERE borrow_id=$1', [(int)$_POST['borrow_id']]);
    if (!$r) $error = pg_last_error($conn);
    else { header("Location: ?tab=transactions"); exit; }
}

// =====================================================
// BORROW ITEMS
// =====================================================

if ($action === 'add_item') {
    $r = pg_query_params($conn,
        'INSERT INTO borrow_items (borrow_id, equipment_id, quantity) VALUES ($1,$2,$3)',
        [(int)$_POST['borrow_id'], (int)$_POST['equipment_id'], (int)$_POST['quantity']]
    );
    if (!$r) $error = pg_last_error($conn);
    else { header("Location: ?tab=items"); exit; }
}

if ($action === 'delete_item') {
    $r = pg_query_params($conn, 'DELETE FROM borrow_items WHERE borrow_item_id=$1', [(int)$_POST['borrow_item_id']]);
    if (!$r) $error = pg_last_error($conn);
    else { header("Location: ?tab=items"); exit; }
}

// =====================================================
// FETCH DATA
// =====================================================

$borrowers = pg_fetch_all(pg_query($conn,
    'SELECT * FROM borrowers ORDER BY borrower_id DESC')) ?: [];

$equipment = pg_fetch_all(pg_query($conn,
    'SELECT * FROM equipment ORDER BY equipment_id DESC')) ?: [];

$transactions = pg_fetch_all(pg_query($conn,
    'SELECT t.*, b.full_name, b.borrower_no
     FROM borrow_transactions t
     JOIN borrowers b ON b.borrower_id = t.borrower_id
     ORDER BY t.borrow_id DESC')) ?: [];

$items = pg_fetch_all(pg_query($conn,
    'SELECT i.*, e.equipment_name, e.property_no
     FROM borrow_items i
     JOIN borrow_transactions bt ON bt.borrow_id = i.borrow_id
     JOIN equipment e ON e.equipment_id = i.equipment_id
     ORDER BY i.borrow_item_id DESC')) ?: [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Equipment Borrowing</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 24px; margin: 0; }

        .wrap {
            max-width: 960px; margin: 0 auto; background: #fff;
            padding: 28px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }

        h1 { font-size: 20px; color: #333; margin-bottom: 18px; }
        h2 {
            font-size: 12px; font-weight: bold; text-transform: uppercase;
            color: #777; margin: 20px 0 8px; border-bottom: 1px solid #eee; padding-bottom: 5px;
        }

        /* Tabs */
        .tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 2px solid #ddd; }
        .tabs a {
            padding: 8px 16px; text-decoration: none; font-size: 14px;
            color: #555; border-radius: 5px 5px 0 0; border: 1px solid transparent;
        }
        .tabs a.active {
            background: #fff; border-color: #ddd; border-bottom-color: #fff;
            color: #333; font-weight: bold; margin-bottom: -2px;
        }
        .tabs a:hover:not(.active) { background: #f5f5f5; }

        /* Forms */
        .form-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        input[type=text], input[type=number], input[type=date], select {
            padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px;
            font-size: 14px; flex: 1; min-width: 120px;
        }
        input:focus, select:focus { outline: none; border-color: #4a90d9; }

        button {
            padding: 8px 16px; border: none; border-radius: 5px;
            font-size: 14px; cursor: pointer; color: #fff; white-space: nowrap;
        }
        .btn-add    { background: #4a90d9; }
        .btn-update { background: #5aab6e; }
        .btn-delete { background: #e05050; font-size: 12px; padding: 4px 10px; }
        button:hover { opacity: .85; }

        .error {
            background: #fde; color: #c00; padding: 10px;
            border-radius: 5px; margin-bottom: 14px; font-size: 14px;
        }

        /* Table */
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 8px; }
        th, td { padding: 9px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; color: #555; font-size: 12px; text-transform: uppercase; }
        tr:hover td { background: #fafafa; }
        td:first-child { color: #aaa; width: 40px; font-size: 13px; }

        .empty { color: #aaa; font-size: 14px; padding: 10px 0; }

        /* Status badges */
        .badge {
            display: inline-block; padding: 2px 8px;
            border-radius: 12px; font-size: 12px; font-weight: bold;
        }
        .badge-pending  { background: #fff3cd; color: #856404; }
        .badge-returned { background: #d1e7dd; color: #0f5132; }
        .badge-overdue  { background: #f8d7da; color: #842029; }

        /* Inline status update */
        .inline-form { display: flex; gap: 6px; align-items: center; }
        .inline-form select { min-width: 100px; flex: none; padding: 4px 8px; font-size: 13px; }
        .inline-form button { padding: 4px 10px; font-size: 12px; }
    </style>
</head>
<body>
<div class="wrap">

    <h1>Campus Laboratory Equipment Borrowing</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <a href="?tab=borrowers"    class="<?= $tab === 'borrowers'    ? 'active' : '' ?>">Borrowers</a>
        <a href="?tab=equipment"    class="<?= $tab === 'equipment'    ? 'active' : '' ?>">Equipment</a>
        <a href="?tab=transactions" class="<?= $tab === 'transactions' ? 'active' : '' ?>">Transactions</a>
        <a href="?tab=items"        class="<?= $tab === 'items'        ? 'active' : '' ?>">Borrow Items</a>
    </div>

    <?php if ($tab === 'borrowers'): ?>

        <!-- ===== BORROWERS ===== -->
        <h2>Add Borrower</h2>
        <form method="post">
            <input type="hidden" name="action" value="add_borrower">
            <div class="form-row">
                <input type="text" name="borrower_no"   placeholder="Borrower No."       required>
                <input type="text" name="full_name"     placeholder="Full Name"           required>
                <input type="text" name="borrower_type" placeholder="Type (e.g. Student)" required>
                <button type="submit" class="btn-add">Add</button>
            </div>
        </form>

        <h2>Borrowers List</h2>
        <?php if (empty($borrowers)): ?>
            <p class="empty">No borrowers yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Borrower No.</th><th>Full Name</th><th>Type</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($borrowers as $b): ?>
                <tr>
                    <td><?= (int)$b['borrower_id'] ?></td>
                    <td><?= htmlspecialchars($b['borrower_no'],   ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($b['full_name'],     ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($b['borrower_type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Delete borrower?')">
                            <input type="hidden" name="action"      value="delete_borrower">
                            <input type="hidden" name="borrower_id" value="<?= (int)$b['borrower_id'] ?>">
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    <?php elseif ($tab === 'equipment'): ?>

        <!-- ===== EQUIPMENT ===== -->
        <h2>Add Equipment</h2>
        <form method="post">
            <input type="hidden" name="action" value="add_equipment">
            <div class="form-row">
                <input type="text"   name="property_no"        placeholder="Property No."  required>
                <input type="text"   name="equipment_name"     placeholder="Equipment Name" required>
                <input type="number" name="quantity_available" placeholder="Qty Available" min="0" required style="max-width:110px; flex:none;">
                <button type="submit" class="btn-add">Add</button>
            </div>
        </form>

        <h2>Equipment List</h2>
        <?php if (empty($equipment)): ?>
            <p class="empty">No equipment yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Property No.</th><th>Equipment Name</th><th>Qty Available</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($equipment as $e): ?>
                <tr>
                    <td><?= (int)$e['equipment_id'] ?></td>
                    <td><?= htmlspecialchars($e['property_no'],        ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($e['equipment_name'],     ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$e['quantity_available'] ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Delete equipment?')">
                            <input type="hidden" name="action"       value="delete_equipment">
                            <input type="hidden" name="equipment_id" value="<?= (int)$e['equipment_id'] ?>">
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    <?php elseif ($tab === 'transactions'): ?>

        <!-- ===== BORROW TRANSACTIONS ===== -->
        <h2>Add Transaction</h2>
        <form method="post">
            <input type="hidden" name="action" value="add_transaction">
            <div class="form-row">
                <input type="number" name="borrower_id" placeholder="Borrower ID" min="1" required style="max-width:110px; flex:none;">
                <input type="date"   name="borrow_date" required>
                <input type="date"   name="due_date"    required>
                <select name="status">
                    <option value="Pending">Pending</option>
                    <option value="Returned">Returned</option>
                    <option value="Overdue">Overdue</option>
                </select>
                <button type="submit" class="btn-add">Add</button>
            </div>
        </form>

        <h2>Transactions List</h2>
        <?php if (empty($transactions)): ?>
            <p class="empty">No transactions yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Borrower</th><th>Borrow Date</th><th>Due Date</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $t):
                $badge = match($t['status']) {
                    'Returned' => 'badge-returned',
                    'Overdue'  => 'badge-overdue',
                    default    => 'badge-pending'
                };
            ?>
                <tr>
                    <td><?= (int)$t['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($t['borrower_no'] . ' — ' . $t['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($t['borrow_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($t['due_date'],    ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <form method="post" class="inline-form">
                                <input type="hidden" name="action"    value="update_transaction">
                                <input type="hidden" name="borrow_id" value="<?= (int)$t['borrow_id'] ?>">
                                <select name="status">
                                    <option value="Pending"  <?= $t['status'] === 'Pending'  ? 'selected' : '' ?>>Pending</option>
                                    <option value="Returned" <?= $t['status'] === 'Returned' ? 'selected' : '' ?>>Returned</option>
                                    <option value="Overdue"  <?= $t['status'] === 'Overdue'  ? 'selected' : '' ?>>Overdue</option>
                                </select>
                                <button type="submit" class="btn-update">Save</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete transaction?')">
                                <input type="hidden" name="action"    value="delete_transaction">
                                <input type="hidden" name="borrow_id" value="<?= (int)$t['borrow_id'] ?>">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    <?php elseif ($tab === 'items'): ?>

        <!-- ===== BORROW ITEMS ===== -->
        <h2>Add Borrow Item</h2>
        <form method="post">
            <input type="hidden" name="action" value="add_item">
            <div class="form-row">
                <input type="number" name="borrow_id"    placeholder="Transaction ID" min="1" required style="max-width:130px; flex:none;">
                <input type="number" name="equipment_id" placeholder="Equipment ID"   min="1" required style="max-width:120px; flex:none;">
                <input type="number" name="quantity"     placeholder="Qty"            min="1" value="1" required style="max-width:70px; flex:none;">
                <button type="submit" class="btn-add">Add</button>
            </div>
        </form>

        <h2>Borrow Items List</h2>
        <?php if (empty($items)): ?>
            <p class="empty">No borrow items yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Transaction ID</th><th>Equipment</th><th>Property No.</th><th>Qty</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr>
                    <td><?= (int)$i['borrow_item_id'] ?></td>
                    <td><?= (int)$i['borrow_id'] ?></td>
                    <td><?= htmlspecialchars($i['equipment_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($i['property_no'],    ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$i['quantity'] ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Delete this item?')">
                            <input type="hidden" name="action"         value="delete_item">
                            <input type="hidden" name="borrow_item_id" value="<?= (int)$i['borrow_item_id'] ?>">
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    <?php endif; ?>

</div>
</body>
</html>
