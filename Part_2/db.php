<?php
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres');
define('DB_PASS', 'krazy1001');

define('BASE_URL', '/Data-Ad/Part_2');

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
        <h2 style="margin:0 0 12px">Database Connection Failed</h2>
        <p style="margin:0 0 8px"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>
        <p style="font-size:13px;color:#be123c;margin:0">
            Check db.php: host=<strong>' . DB_HOST . '</strong>,
            port=<strong>' . DB_PORT . '</strong>,
            dbname=<strong>' . DB_NAME . '</strong>,
            user=<strong>' . DB_USER . '</strong>
        </p>
    </div>');
}

function h(mixed $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
}

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

function availBadge(int $availNow, int $owned): string
{
    if ($availNow <= 0) {
        return '<span class="badge badge-outofstock">Out of Stock</span>';
    }
    if ($owned > 0 && (($availNow / $owned) <= 0.25 || ($availNow <= 2 && $availNow < $owned))) {
        return '<span class="badge badge-instock">Available</span> <span class="badge badge-lowstock">Low Stock</span>';
    }
    return '<span class="badge badge-instock">Available</span>';
}
