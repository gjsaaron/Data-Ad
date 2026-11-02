<?php
declare(strict_types=1);
/**
 * db.php — Shared PDO connection for Equipment Borrowing System (Parts 2–5)
 *
 * Every report page starts with:
 *   require_once '../db.php';   (from week06/, week07/, etc.)
 *   require_once 'db.php';      (from index.php at Part_2 root)
 */

// ── Connection settings ──────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres');
define('DB_PASS', 'Aaron0928');

// ── Base URL (used in header.php links) ──────────────────────────────────────
// Points to the Part_2 folder root, no trailing slash.
define('BASE_URL', '/Data%20Ad/Part_2');

// ── Connect ──────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('
    <div style="font-family:Arial,sans-serif;padding:40px;max-width:600px;margin:60px auto;
                background:#fff1f2;border:1px solid #fecdd3;border-radius:12px;color:#9f1239">
        <h2 style="margin:0 0 12px">⚠️ Database Connection Failed</h2>
        <p style="margin:0 0 8px"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>
        <p style="font-size:13px;color:#be123c;margin:0">
            Check <code>db.php</code>: host=<strong>' . DB_HOST . '</strong>, 
            port=<strong>' . DB_PORT . '</strong>, 
            dbname=<strong>' . DB_NAME . '</strong>, 
            user=<strong>' . DB_USER . '</strong>
        </p>
    </div>');
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * h() — Safely escape a value for HTML output.
 * Use this everywhere you echo user/database data.
 */
function h(mixed $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
}

/**
 * badge() — Return a styled HTML badge for a status value.
 */
function statusBadge(string $status): string
{
    $map = [
        'Borrowed'  => 'badge-borrowed',
        'Returned'  => 'badge-returned',
        'Overdue'   => 'badge-overdue',
        'Cancelled' => 'badge-cancelled',
    ];
    $cls = $map[$status] ?? 'badge-cancelled';
    return '<span class="badge ' . $cls . '">' . h($status) . '</span>';
}

/**
 * typeBadge() — Return a styled HTML badge for a borrower type.
 */
function typeBadge(string $type): string
{
    $map = [
        'Student' => 'badge-student',
        'Faculty' => 'badge-faculty',
        'Staff'   => 'badge-staff',
    ];
    $cls = $map[$type] ?? 'badge-blue';
    return '<span class="badge ' . $cls . '">' . h($type) . '</span>';
}

/**
 * availBadge() — Return a stock status badge based on available quantity.
 * Rule: available_now = quantity_available − units currently Borrowed/Overdue
 */
function availBadge(int $availNow, int $owned): string
{
    if ($availNow <= 0) {
        return '<span class="badge badge-outofstock">Out of Stock</span>';
    }
    // Low stock: less than 25% of owned quantity available (minimum 1 unit threshold)
    if ($owned > 0 && ($availNow / $owned) <= 0.25) {
        return '<span class="badge badge-lowstock">Low Stock</span>';
    }
    return '<span class="badge badge-instock">Available</span>';
}
