<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/Database.php';

final class RestaurantsController {
  public static function index(): void {
    $pdo = Database::conn();
    $rows = $pdo->query("SELECT * FROM restaurants ORDER BY id DESC")->fetchAll();
    self::json($rows);
  }

  public static function store(): void {
    $in = self::jsonInput();
    foreach (['name','address','cuisine_type'] as $k) {
      if (empty($in[$k])) { self::error(400, "$k es requerido"); return; }
    }
    $pdo = Database::conn();
    $stmt = $pdo->prepare(
      "INSERT INTO restaurants (name, address, cuisine_type)
       VALUES (:name,:address,:cuisine)"
    );
    $stmt->execute([
      ':name'=>$in['name'], ':address'=>$in['address'], ':cuisine'=>$in['cuisine_type']
    ]);
    self::json(['message'=>'Restaurante creado'], 201);
  }

  public static function show(int $id): void {
    $pdo = Database::conn();
    $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch();
    if (!$row) { self::error(404, 'Restaurante no encontrado'); return; }
    self::json($row);
  }

  public static function update(int $id): void {
    $in = self::jsonInput();
    $pdo = Database::conn();
    $exists = $pdo->prepare("SELECT COUNT(1) c FROM restaurants WHERE id=:id");
    $exists->execute([':id'=>$id]);
    if ((int)$exists->fetch()['c'] === 0) { self::error(404, 'Restaurante no encontrado'); return; }

    $stmt = $pdo->prepare(
      "UPDATE restaurants SET
        name = COALESCE(:name,name),
        address = COALESCE(:address,address),
        cuisine_type = COALESCE(:cuisine,cuisine_type)
       WHERE id = :id"
    );
    $stmt->execute([
      ':name'=>$in['name'] ?? null,
      ':address'=>$in['address'] ?? null,
      ':cuisine'=>$in['cuisine_type'] ?? null,
      ':id'=>$id
    ]);
    self::json(['message'=>'Restaurante actualizado']);
  }

  public static function destroy(int $id): void {
    $pdo = Database::conn();
    $del = $pdo->prepare("DELETE FROM restaurants WHERE id=:id");
    $del->execute([':id'=>$id]);
    if ($del->rowCount() === 0) { self::error(404, 'Restaurante no encontrado'); return; }
    http_response_code(204);
  }

  // Helpers (idénticos a Places)
  private static function json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
  }
  private static function error(int $code, string $msg): void {
    self::json(['error'=>$msg], $code);
  }
  private static function jsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }
}
