<?php
declare(strict_types=1);

$dbPath = __DIR__ . '/dataset/turismo.db';
if (!is_dir(__DIR__ . '/dataset')) {
  mkdir(__DIR__ . '/dataset', 0777, true);
}
$needSeed = !file_exists($dbPath);

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON;');
$pdo->exec('PRAGMA journal_mode = WAL;');

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS places (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  description TEXT NOT NULL,
  address TEXT,
  latitude REAL,
  longitude REAL,
  created_at TEXT DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS restaurants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  address TEXT NOT NULL,
  cuisine_type TEXT NOT NULL,
  created_at TEXT DEFAULT (datetime('now'))
);
SQL);

if ($needSeed) {
  // Lugares semilla
  $stmt = $pdo->prepare("INSERT INTO places (name, description, address, latitude, longitude)
                         VALUES (:name,:description,:address,:lat,:lng)");
  $seedPlaces = [
    ['Arco Parabólico','Monumento emblemático del centro de Tacna','Av. San Martín s/n',-18.0132,-70.2517],
    ['Catedral de Tacna','Templo neorrenacentista de piedra','Jr. 2 de Mayo 514',-18.0136,-70.2511],
  ];
  foreach ($seedPlaces as [$n,$d,$a,$la,$lo]) {
    $stmt->execute([':name'=>$n,':description'=>$d,':address'=>$a,':lat'=>$la,':lng'=>$lo]);
  }

  // Restaurantes semilla
  $stmt2 = $pdo->prepare("INSERT INTO restaurants (name, address, cuisine_type)
                          VALUES (:name,:address,:cuisine)");
  $seedRestaurants = [
    ['La Glorieta','Calle Zela 700','Comida típica'],
    ['Mercado Central – Puestos','Calle Inclán 500','Casera'],
  ];
  foreach ($seedRestaurants as [$n,$a,$c]) {
    $stmt2->execute([':name'=>$n,':address'=>$a,':cuisine'=>$c]);
  }
}

echo "Base de datos lista en dataset/turismo.db\n";
