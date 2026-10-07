<?php

use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);
require $root.'/backend/vendor/autoload.php';
require __DIR__.'/uso-tablas-baseline.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.pgsql');
if (app()->environment() !== 'local' || $config['host'] !== '127.0.0.1' || ! empty($config['url'])) {
    throw new RuntimeException('Auditoría limitada a la conexión local explícita.');
}
$pdo = new PDO('pgsql:host=127.0.0.1;port='.$config['port'].';dbname='.$config['database'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$pdo->exec('SET TRANSACTION READ ONLY');
$report = auditarUsoTablas($pdo, $root);
$pdo->rollBack();
file_put_contents($root.'/docs/uso-tablas-actual.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
exportarUsoTablasCsv($report, $root.'/docs/uso-tablas-actual.csv');
$removed = array_keys(array_filter($report, fn ($row) => $row['decision'] === 'retirar'));
echo json_encode(['relations' => count($report), 'candidates' => $removed], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
