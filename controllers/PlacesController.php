<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/Database.php';

final class PlacesController {
  public static function index(): void {
    $pdo = Database::conn();
    $rows = $pdo->query("SELECT * FROM places ORDER BY id DESC")->fetchAll();
    self::json($rows);
  }

  public static function store(): void {
    $in = self::jsonInput();
    if (empty($in['name']) || empty($in['description'])) {
      self::error(400, 'name y description son requeridos'); return;
    }
    $pdo = Database::conn();
    $stmt = $pdo->prepare(
      "INSERT INTO places (name, description, address, latitude, longitude)
       VALUES (:name,:description,:address,:lat,:lng)"
    );
    $stmt->execute([
      ':name'=>$in['name'],
      ':description'=>$in['description'],
      ':address'=>$in['address'] ?? null,
      ':lat'=>$in['latitude'] ?? null,
      ':lng'=>$in['longitude'] ?? null,
    ]);
    self::json(['message'=>'Lugar creado'], 201);
  }

  public static function show(int $id): void {
    $pdo = Database::conn();
    $stmt = $pdo->prepare("SELECT * FROM places WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch();
    if (!$row) { self::error(404, 'Lugar no encontrado'); return; }
    self::json($row);
  }

  public static function update(int $id): void {
    $in = self::jsonInput();
    $pdo = Database::conn();
    $exists = $pdo->prepare("SELECT COUNT(1) c FROM places WHERE id=:id");
    $exists->execute([':id'=>$id]);
    if ((int)$exists->fetch()['c'] === 0) { self::error(404, 'Lugar no encontrado'); return; }

    $stmt = $pdo->prepare(
      "UPDATE places SET
        name = COALESCE(:name,name),
        description = COALESCE(:description,description),
        address = COALESCE(:address,address),
        latitude = COALESCE(:lat,latitude),
        longitude = COALESCE(:lng,longitude)
       WHERE id = :id"
    );
    $stmt->execute([
      ':name'=>$in['name'] ?? null,
      ':description'=>$in['description'] ?? null,
      ':address'=>$in['address'] ?? null,
      ':lat'=>$in['latitude'] ?? null,
      ':lng'=>$in['longitude'] ?? null,
      ':id'=>$id
    ]);
    self::json(['message'=>'Lugar actualizado']);
  }

  public static function destroy(int $id): void {
    $pdo = Database::conn();
    $del = $pdo->prepare("DELETE FROM places WHERE id = :id");
    $del->execute([':id'=>$id]);
    if ($del->rowCount() === 0) { self::error(404, 'Lugar no encontrado'); return; }
    http_response_code(204);
  }

  // Helpers
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
