<?php
$pageTitle   = $pageTitle   ?? 'Equipment Borrowing System';
$currentPage = $currentPage ?? '';

// Depth helper: pages in subfolders need '../' to reach BASE_URL correctly
// BASE_URL is already an absolute path so links are always correct.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 14px;
            background: #f3f4f6;
            color: #111827;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 200px;
            min-width: 200px;
            background: #fff;
            border-right: 1px solid #e5e7eb;
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            overflow-y: auto;
        }
        .sidebar-title {
            padding: 16px 14px;
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            line-height: 1.4;
        }
        .nav-group {
            padding: 10px 0 4px;
        }
        .nav-label {
            padding: 4px 14px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #9ca3af;
        }
        .sidebar a {
            display: block;
            padding: 6px 14px;
            font-size: 13px;
            color: #374151;
            text-decoration: none;
        }
        .sidebar a:hover { background: #f3f4f6; }
        .sidebar a.active {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
        }

        /* Main */
        .main {
            margin-left: 200px;
            flex: 1;
            padding: 24px;
        }
        .page-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #111827;
        }

        /* Cards */
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .card-body { padding: 16px; }

        /* Tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        th {
            background: #f9fafb;
            text-align: left;
            padding: 8px 12px;
            font-size: 11.5px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .05em;
            border-bottom: 1px solid #e5e7eb;
        }
        td {
            padding: 9px 12px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9fafb; }
        tfoot td {
            background: #f9fafb;
            font-weight: 600;
            border-top: 1px solid #e5e7eb;
            border-bottom: none;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #9ca3af; font-size: 12px; }
        .fw-bold { font-weight: 600; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11.5px;
            font-weight: 600;
        }
        .badge-borrowed  { background: #dbeafe; color: #1e40af; }
        .badge-returned  { background: #dcfce7; color: #166534; }
        .badge-overdue   { background: #fee2e2; color: #991b1b; }
        .badge-cancelled { background: #f3f4f6; color: #6b7280; }
        .badge-student   { background: #ede9fe; color: #5b21b6; }
        .badge-faculty   { background: #fef9c3; color: #854d0e; }
        .badge-staff     { background: #dcfce7; color: #166534; }

        /* Forms */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            margin-bottom: 16px;
        }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group label { font-size: 11.5px; font-weight: 600; color: #6b7280; }
        input, select {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13.5px;
            font-family: inherit;
            color: #111827;
            background: #fff;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .btn {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            color: #fff;
            background: #2563eb;
        }
        .btn:hover { background: #1d4ed8; }
        .btn-outline {
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-outline:hover { background: #f3f4f6; }

        /* Alerts */
        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13.5px;
            margin-bottom: 16px;
        }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-danger  { background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }
        .alert-info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

        /* Toast popup */
        #toast {
            position: fixed;
            top: 20px; right: 20px;
            z-index: 9999;
            min-width: 280px; max-width: 420px;
            background: #1e293b;
            color: #fff;
            border-radius: 8px;
            padding: 0;
            box-shadow: 0 8px 24px rgba(0,0,0,.25);
            opacity: 0;
            transform: translateX(110%);
            transition: opacity .3s ease, transform .3s ease;
            pointer-events: none;
        }
        #toast.show {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
        }
        #toast.toast-error   { background: #7f1d1d; border-left: 4px solid #ef4444; }
        #toast.toast-success { background: #14532d; border-left: 4px solid #22c55e; }
        #toast.toast-warning { background: #78350f; border-left: 4px solid #f59e0b; }
        .toast-inner {
            display: flex; align-items: flex-start; gap: 10px; padding: 14px 16px;
        }
        .toast-icon { font-size: 18px; line-height: 1; margin-top: 1px; }
        .toast-body { flex: 1; }
        .toast-title { font-size: 13px; font-weight: 700; margin-bottom: 2px; }
        .toast-msg   { font-size: 12.5px; opacity: .85; line-height: 1.4; }
        .toast-close {
            background: none; border: none; color: #fff; cursor: pointer;
            font-size: 16px; opacity: .6; padding: 0; line-height: 1; margin-top: 1px;
        }
        .toast-close:hover { opacity: 1; }

        /* Input error highlight */
        input.input-error {
            border-color: #ef4444 !important;
            background: #fff5f5 !important;
        }
        input.input-error:focus { box-shadow: 0 0 0 2px rgba(239,68,68,.2); }

        /* Stat grid */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4,1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat-box {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px 16px;
        }
        .stat-box .label { font-size: 11.5px; color: #6b7280; font-weight: 600; }
        .stat-box .value { font-size: 26px; font-weight: 700; margin: 4px 0 2px; }
        .stat-box .sub   { font-size: 11.5px; color: #9ca3af; }

        /* Grid layouts */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* Empty state */
        .empty { padding: 30px; text-align: center; color: #9ca3af; font-size: 13.5px; }

        /* Result count */
        .result-count { font-size: 12.5px; color: #6b7280; margin-bottom: 10px; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .stat-grid, .grid-2 { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-title">Equipment<br>Borrowing System</div>

    <div class="nav-group">
        <div class="nav-label">Main</div>
        <a href="<?= BASE_URL ?>/index.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    </div>

    <div class="nav-group">
        <div class="nav-label">Week 4 &amp; 5 — CRUD</div>
        <a href="<?= BASE_URL ?>/part1/index.php" class="<?= $currentPage === 'part1' ? 'active' : '' ?>">Equipment Borrowing</a>
    </div>

    <div class="nav-group">
        <div class="nav-label">Week 6</div>
        <a href="<?= BASE_URL ?>/week06/inventory.php"        class="<?= $currentPage === 'inventory'     ? 'active' : '' ?>">Inventory Listing</a>
        <a href="<?= BASE_URL ?>/week06/filtered_records.php" class="<?= $currentPage === 'filtered'      ? 'active' : '' ?>">Filtered Records</a>
        <a href="<?= BASE_URL ?>/week06/borrower_summary.php" class="<?= $currentPage === 'borrow_summary'? 'active' : '' ?>">Borrower Summary</a>
        <a href="<?= BASE_URL ?>/week06/status_summary.php"   class="<?= $currentPage === 'status_summary'? 'active' : '' ?>">Status Summary</a>
        <a href="<?= BASE_URL ?>/week06/grouped_analysis.php" class="<?= $currentPage === 'grouped'       ? 'active' : '' ?>">Grouped Analysis</a>
    </div>

    <div class="nav-group">
        <div class="nav-label">Week 7</div>
        <a href="<?= BASE_URL ?>/week07/complete_report.php"   class="<?= $currentPage === 'complete_report'? 'active' : '' ?>">Complete Report</a>
        <a href="<?= BASE_URL ?>/week07/equipment_summary.php" class="<?= $currentPage === 'equip_summary'  ? 'active' : '' ?>">Equipment Summary</a>
        <a href="<?= BASE_URL ?>/week07/no_transactions.php"   class="<?= $currentPage === 'no_txn'         ? 'active' : '' ?>">No Transactions</a>
        <a href="<?= BASE_URL ?>/week07/no_history.php"        class="<?= $currentPage === 'no_history'     ? 'active' : '' ?>">No History</a>
        <a href="<?= BASE_URL ?>/week07/combined_activity.php" class="<?= $currentPage === 'combined'       ? 'active' : '' ?>">Combined Activity</a>
        <a href="<?= BASE_URL ?>/week07/monthly_report.php"    class="<?= $currentPage === 'monthly'        ? 'active' : '' ?>">Monthly Report</a>
    </div>

    <div class="nav-group">
        <div class="nav-label">Week 8</div>
        <a href="<?= BASE_URL ?>/week08/views.php"             class="<?= $currentPage === 'views'      ? 'active' : '' ?>">Database Views</a>
        <a href="<?= BASE_URL ?>/week08/transactions_demo.php" class="<?= $currentPage === 'txn_demo'   ? 'active' : '' ?>">Transactions Demo</a>
        <a href="<?= BASE_URL ?>/week08/constraints_demo.php"  class="<?= $currentPage === 'constraints'? 'active' : '' ?>">Constraints Demo</a>
    </div>

    <div class="nav-group">
        <div class="nav-label">Week 10</div>
        <a href="<?= BASE_URL ?>/week10/functions_demo.php" class="<?= $currentPage === 'functions'? 'active' : '' ?>">Functions Demo</a>
        <a href="<?= BASE_URL ?>/week10/triggers_demo.php"  class="<?= $currentPage === 'triggers' ? 'active' : '' ?>">Triggers Demo</a>
        <a href="<?= BASE_URL ?>/week10/audit_log.php"      class="<?= $currentPage === 'audit'    ? 'active' : '' ?>">Audit Log</a>
    </div>

</nav>

<div class="main">
    <div class="page-title"><?= htmlspecialchars($pageTitle) ?></div>
