<?php
declare(strict_types=1);

final class WeatherController {
  public static function current(): void {
    $url = 'https://wttr.in/Tacna?format=j1';
    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
      self::json(['error'=>'Falla consultando clima externo'], 502);
      return;
    }
    $data = json_decode($raw, true);
    $cur = $data['current_condition'][0] ?? null;

    $out = [
      'city' => 'Tacna',
      'temperature_c' => $cur['temp_C'] ?? null,
      'description' => $cur['weatherDesc'][0]['value'] ?? null,
    ];
    self::json($out);
  }

  private static function json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
  }
}
