<?php
require_once '../db.php';

// Run a constraint test and return result label + message
function runTest(PDO $pdo, string $sql, array $params = []): array {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pdo->rollBack(); // always rollback — just testing
        return ['pass' => false, 'label' => 'No Error (unexpected)', 'msg' => 'The constraint did NOT fire.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['pass' => true, 'label' => 'Constraint Fired ✅', 'msg' => $e->getMessage()];
    }
}

$tests = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $run = $_POST['run'] ?? '';

    // Get real IDs for use in tests
    $borrowerId = (int) $pdo->query("SELECT borrower_id FROM borrowers LIMIT 1")->fetchColumn();
    $equipId    = (int) $pdo->query("SELECT equipment_id FROM equipment LIMIT 1")->fetchColumn();
    $borrowId   = (int) $pdo->query("SELECT borrow_id FROM borrow_transactions LIMIT 1")->fetchColumn();
    $existingItem = $pdo->query("SELECT borrow_id, equipment_id FROM borrow_items LIMIT 1")->fetch();

    if ($run === '1' || $run === 'all') {
        // Test 1: UNIQUE on equipment.property_no
        $existing = $pdo->query("SELECT property_no FROM equipment LIMIT 1")->fetchColumn();
        $tests[1] = ['name' => 'UNIQUE — duplicate property_no',
            'desc' => "Tries to insert an equipment with an already-existing property_no.",
            'result' => runTest($pdo,
                "INSERT INTO equipment (property_no, equipment_name, quantity_available) VALUES (?,?,1)",
                [$existing, 'Duplicate Test'])];
    }
    if ($run === '2' || $run === 'all') {
        // Test 2: CHECK — negative quantity_available
        $tests[2] = ['name' => 'CHECK — negative quantity_available',
            'desc' => "Tries to insert equipment with quantity_available = -1 (violates CHECK >= 0).",
            'result' => runTest($pdo,
                "INSERT INTO equipment (property_no, equipment_name, quantity_available) VALUES ('CHK-9999','Test',?)",
                [-1])];
    }
    if ($run === '3' || $run === 'all') {
        // Test 3: FOREIGN KEY — invalid borrower_id on transaction
        $tests[3] = ['name' => 'FOREIGN KEY — invalid borrower_id',
            'desc' => "Tries to create a transaction referencing borrower_id = 99999 (does not exist).",
            'result' => runTest($pdo,
                "INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status) VALUES (99999, CURRENT_DATE, CURRENT_DATE+1, 'Borrowed')")];
    }
    if ($run === '4' || $run === 'all') {
        // Test 4: CHECK — invalid status value
        $tests[4] = ['name' => "CHECK — invalid status value",
            'desc' => "Tries to insert a transaction with status = 'Invalid' (not in the allowed list).",
            'result' => runTest($pdo,
                "INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status) VALUES (?,CURRENT_DATE,CURRENT_DATE+1,'Invalid')",
                [$borrowerId])];
    }
    if ($run === '5' || $run === 'all') {
        // Test 5: CHECK — due_date before borrow_date
        $tests[5] = ['name' => 'CHECK — due_date before borrow_date',
            'desc' => "Tries to insert a transaction where due_date (yesterday) < borrow_date (today).",
            'result' => runTest($pdo,
                "INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status) VALUES (?,CURRENT_DATE,CURRENT_DATE-1,'Borrowed')",
                [$borrowerId])];
    }
}

$pageTitle   = 'Constraints Demo';
$currentPage = 'constraints';
require_once '../includes/header.php';
?>

<p class="result-count">Each test tries a violating INSERT inside a transaction, then rolls back.</p>

<form method="post" style="margin-bottom:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="submit" name="run" value="1" class="btn btn-outline">Test 1 — UNIQUE</button>
        <button type="submit" name="run" value="2" class="btn btn-outline">Test 2 — CHECK (qty)</button>
        <button type="submit" name="run" value="3" class="btn btn-outline">Test 3 — FOREIGN KEY</button>
        <button type="submit" name="run" value="4" class="btn btn-outline">Test 4 — CHECK (status)</button>
        <button type="submit" name="run" value="5" class="btn btn-outline">Test 5 — CHECK (dates)</button>
        <button type="submit" name="run" value="all" class="btn">Run All 5</button>
    </div>
</form>

<?php foreach ($tests as $n => $t): ?>
<div class="card" style="margin-bottom:12px">
    <div class="card-body">
        <p style="font-weight:600;font-size:13px;margin-bottom:4px">
            Test <?= $n ?> — <?= htmlspecialchars($t['name']) ?>
            <span style="margin-left:8px;font-size:12px;font-weight:400;
                         color:<?= $t['result']['pass'] ? '#166534' : '#9f1239' ?>">
                <?= htmlspecialchars($t['result']['label']) ?>
            </span>
        </p>
        <p class="text-muted" style="font-size:12.5px;margin-bottom:6px"><?= htmlspecialchars($t['desc']) ?></p>
        <p style="font-size:12px;font-family:monospace;background:#f9fafb;padding:8px 10px;
                  border-radius:5px;color:#374151;word-break:break-all">
            <?= htmlspecialchars($t['result']['msg']) ?>
        </p>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($tests)): ?>
<div class="card"><div class="card-body text-muted">Click a button above to run a test.</div></div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
