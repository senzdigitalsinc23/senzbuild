<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Load .env
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

$pdo = new PDO(
    'mysql:host=' . ($_ENV['DB_HOST'] ?? 'db') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'agh_validations') . ';charset=utf8mb4',
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Create HR unit (schema may or may not have a `code` column depending on migrations)
$hasCode = (bool) $pdo->query("SHOW COLUMNS FROM units LIKE 'code'")->fetch();
if ($hasCode) {
    $pdo->exec("INSERT INTO units (name, code, description) VALUES ('Human Resources', 'HR', 'Human Resources Department') ON DUPLICATE KEY UPDATE name = name");
} else {
    $pdo->exec("INSERT INTO units (name, description) VALUES ('Human Resources', 'Human Resources Department') ON DUPLICATE KEY UPDATE name = name");
}
$hrUnit = $pdo->query("SELECT id FROM units WHERE name = 'Human Resources'")->fetch(PDO::FETCH_ASSOC);
if (!$hrUnit) {
    fwrite(STDERR, "Failed to create or find Human Resources unit\n");
    exit(1);
}
$hrUnitId = $hrUnit['id'];

$accounts = [
    ['name' => 'System Administrator', 'email' => 'admin@ghs.gov.gh',        'password' => 'admin123',    'role' => 'admin'],
    ['name' => 'HR Incharge',          'email' => 'incharge1@validation.com', 'password' => 'incharge123', 'role' => 'hr'],
];

foreach ($accounts as $acc) {
    $stmt = $pdo->prepare("SELECT id FROM validation_staff WHERE email = :email");
    $stmt->execute(['email' => $acc['email']]);
    $exists = $stmt->fetch();

    if (!$exists) {
        $hash = password_hash($acc['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO validation_staff (name, email, password, role, unit_id, password_changed) VALUES (:name, :email, :password, :role, :unit_id, 1)");
        $stmt->execute(['name' => $acc['name'], 'email' => $acc['email'], 'password' => $hash, 'role' => $acc['role'], 'unit_id' => $hrUnitId]);
        echo "Created: {$acc['email']}\n";
    } else {
        echo "Already exists: {$acc['email']}\n";
    }
}
