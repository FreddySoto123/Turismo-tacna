<?php
declare(strict_types=1);

require_once __DIR__ . '/../controllers/PlacesController.php';
require_once __DIR__ . '/../controllers/RestaurantsController.php';
require_once __DIR__ . '/../controllers/WeatherController.php';

// CORS / headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Salud
if ($path === '/' && $method === 'GET') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'name'=>'Turismo Tacna Digital API (PHP – estructura de práctica)',
    'version'=>'1.0.0',
    'endpoints'=>[
      'GET /places','POST /places','GET /places/{id}','PUT /places/{id}','DELETE /places/{id}',
      'GET /restaurants','POST /restaurants','GET /restaurants/{id}','PUT /restaurants/{id}','DELETE /restaurants/{id}',
      'GET /weather'
    ]
  ]);
  exit;
}

// Rutas Places
if ($path === '/places' && $method === 'GET') { PlacesController::index(); exit; }
if ($path === '/places' && $method === 'POST') { PlacesController::store(); exit; }
if (preg_match('#^/places/(\d+)$#',$path,$m)) {
  $id = (int)$m[1];
  if ($method === 'GET')   { PlacesController::show($id); exit; }
  if ($method === 'PUT' || $method === 'PATCH') { PlacesController::update($id); exit; }
  if ($method === 'DELETE'){ PlacesController::destroy($id); exit; }
}

// Rutas Restaurants
if ($path === '/restaurants' && $method === 'GET') { RestaurantsController::index(); exit; }
if ($path === '/restaurants' && $method === 'POST') { RestaurantsController::store(); exit; }
if (preg_match('#^/restaurants/(\d+)$#',$path,$m)) {
  $id = (int)$m[1];
  if ($method === 'GET')   { RestaurantsController::show($id); exit; }
  if ($method === 'PUT' || $method === 'PATCH') { RestaurantsController::update($id); exit; }
  if ($method === 'DELETE'){ RestaurantsController::destroy($id); exit; }
}

// Rutas Weather
if ($path === '/weather' && $method === 'GET') { WeatherController::current(); exit; }

// No encontrado (404 de la práctica)
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error'=>'No encontrado']);
