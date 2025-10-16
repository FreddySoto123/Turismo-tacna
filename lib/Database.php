<?php
declare(strict_types=1);

final class Database {
  private static ?PDO $pdo = null;

  public static function conn(): PDO {
    if (self::$pdo === null) {
      $dbPath = __DIR__ . '/../dataset/turismo.db';
      if (!file_exists($dbPath)) {
        throw new RuntimeException("BD no encontrada. Ejecuta primero: php create_db.php");
      }
      self::$pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      self::$pdo->exec('PRAGMA foreign_keys = ON;');
    }
    return self::$pdo;
  }
}
