# Turismo-tacna
php -S localhost:8000 -t public
php create_db.php
curl http://localhost:8000/places
Perfecto. Te dejo una **explicación completa, paso a paso**, para que puedas exponer cada archivo y cada decisión técnica con seguridad. Está organizada “de lo general a lo específico”, siguiendo exactamente la estructura que armamos.

---

# 0) Visión general del sistema (qué es y cómo funciona)

* Es una **API REST en PHP** que expone tres recursos:

  * **Lugares turísticos** (`/places`)
  * **Restaurantes** (`/restaurants`)
  * **Clima actual** de Tacna (`/weather`) usando un **proxy** a un servicio público.
* La información de lugares y restaurantes se **persiste en SQLite** (archivo `dataset/turismo.db`).
* La **estructura** replica la de tu práctica: `public/` (router), `controllers/` (lógica por recurso), `lib/` (utilidades), `dataset/` (BD), y **`create_db.php`** (crea/siembra la BD).
* Todos los endpoints devuelven **JSON** y usan **códigos HTTP** correctos (200, 201, 204, 400, 404, 502).

---

# 1) Árbol y justificación de carpetas

```
turismo-php/
├─ public/
│  └─ index.php                # Router, CORS y mapeo de endpoints
├─ controllers/
│  ├─ PlacesController.php     # CRUD de lugares
│  ├─ RestaurantsController.php# CRUD de restaurantes
│  └─ WeatherController.php    # Proxy de clima
├─ dataset/
│  └─ turismo.db               # Archivo SQLite (se crea al correr create_db.php)
├─ lib/
│  └─ Database.php             # Singleton de conexión PDO a SQLite
└─ create_db.php               # Crea tablas y datos semilla
```

**Por qué así:** es una separación por capas muy simple:

* `public` atiende HTTP y enruta.
* `controllers` contiene la lógica de cada recurso (principio de responsabilidad única).
* `lib` guarda utilidades reutilizables (conexión DB).
* `dataset` aísla la persistencia (facilita respaldos/entrega).
* `create_db.php` permite **provisionar** el entorno en 1 comando.

---

# 2) Flujo de una petición (de extremo a extremo)

1. El navegador/Postman llama, por ejemplo, a `GET /places`.
2. **`public/index.php`**:

   * Aplica **CORS** y **headers** comunes.
   * Lee la **ruta** y **método** (`$_SERVER['REQUEST_URI']`, `$_SERVER['REQUEST_METHOD']`).
   * Según el patrón, invoca `PlacesController::index()`.
3. **`controllers/PlacesController.php`**:

   * Pide una **conexión** a la BD con `Database::conn()`.
   * Ejecuta la consulta SQL adecuada (listar, crear, etc.).
   * Devuelve una **respuesta JSON** con el **código HTTP** correspondiente.
4. El cliente recibe JSON y el **status code** (200, 201, etc.).

---

# 3) `create_db.php` — creación y semillas (por qué y cómo)

**Objetivo:** Garantizar que la BD existe, tenga el **esquema** correcto y algunos **datos iniciales** para probar.

Puntos clave del código:

* Crea `dataset/` si no existe.
* Detecta si el archivo `turismo.db` **no existe** para saber si debe **sembrar** (seed).
* Crea una conexión PDO a SQLite:

  ```php
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  ```

  * `ERRMODE_EXCEPTION`: los errores de SQL lanzan excepciones (más fácil de manejar).
  * `FETCH_ASSOC`: las consultas devuelven arreglos asociativos (clave = nombre de columna).
* Ajustes SQLite:

  * `PRAGMA foreign_keys = ON;` activa integridad referencial.
  * `PRAGMA journal_mode = WAL;` mejora concurrencia y confiabilidad en escrituras.
* **Crea tablas** si no existen:

  * `places`: `id`, `name`, `description`, `address`, `latitude`, `longitude`, `created_at`.
  * `restaurants`: `id`, `name`, `address`, `cuisine_type`, `created_at`.
* **Inserta semillas** si la BD es nueva (dos lugares y dos restaurantes) mediante `INSERT` preparados:

  ```php
  $stmt = $pdo->prepare("INSERT INTO places (...) VALUES (:name,:description,:address,:lat,:lng)");
  $stmt->execute([':name'=>$n, ...]);
  ```

  Esto previene inyecciones y errores por tipos.

**Cómo lo explicas:** “Este script prepara el ambiente en un paso; define tablas y carga datos mínimos para demostrar la API sin depender de carga manual.”

---

# 4) `lib/Database.php` — conexión a BD (patrón Singleton)

**Objetivo:** Ofrecer **una única conexión** PDO compartida por todos los controladores (evita reconectar en cada llamada).

Puntos clave:

* Atributo estático `private static ?PDO $pdo = null;`
* Método `conn()`:

  * Si `$pdo` es `null`, crea la conexión a `dataset/turismo.db`.
  * Configura **mismos atributos** que en `create_db.php`.
  * Devuelve siempre **la misma instancia** de PDO.

**Ventaja:** menos sobrecarga, un solo lugar para cambiar la configuración (si pasas a Postgres, solo tocas aquí).

---

# 5) `public/index.php` — router, CORS, y manejo de 404

**Responsabilidades:**

1. **CORS / Headers**:

   ```php
   header('Access-Control-Allow-Origin: *');
   header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
   header('Access-Control-Allow-Headers: Content-Type');
   if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
   ```

   * Permite que frontends externos consuman la API.
   * Responde **204** al preflight `OPTIONS`.

2. **Resolución de ruta y método**:

   ```php
   $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
   $method = $_SERVER['REQUEST_METHOD'];
   ```

   * `parse_url` limpia querystrings (`?page=...`).

3. **Tabla de rutas** (switch por ifs):

   * Salud: `GET /` → JSON con nombre, versión y endpoints.
   * **Places:**

     * `GET /places` → `PlacesController::index()`
     * `POST /places` → `PlacesController::store()`
     * `GET /places/{id}` → `PlacesController::show($id)`
     * `PUT/PATCH /places/{id}` → `PlacesController::update($id)`
     * `DELETE /places/{id}` → `PlacesController::destroy($id)`
       (El patrón con `preg_match('#^/places/(\d+)$#', $path, $m)` extrae el `id`.)
   * **Restaurants:** análogo a Places.
   * **Weather:** `GET /weather` → `WeatherController::current()`

4. **404 por defecto**:
   Si ninguna ruta coincide:

   ```php
   http_response_code(404);
   echo json_encode(['error'=>'No encontrado']);
   ```

**Cómo lo explicas:** “`index.php` es el **punto de entrada** HTTP. Aquí resolvemos CORS, detectamos a qué controlador llamar y devolvemos 404 si la ruta no existe.”

---

# 6) `controllers/PlacesController.php` — CRUD y helpers

**Métodos públicos:**

1. `index()` (**GET /places**)

   * Conecta: `$pdo = Database::conn();`
   * Consulta: `SELECT * FROM places ORDER BY id DESC`
   * Devuelve `json($rows)` → **200 OK** con lista completa.

2. `store()` (**POST /places**)

   * Lee el **cuerpo JSON** con `jsonInput()` (decodifica `php://input`).
   * **Validación mínima**: `name` y `description` son obligatorios.

     * Si faltan: `error(400, 'name y description son requeridos')`.
   * Inserta con `INSERT ... VALUES (:name, :description, ...)` usando **binds**.
   * Responde `json(['message'=>'Lugar creado'], 201)` → **201 Created**.

3. `show(int $id)` (**GET /places/{id}**)

   * Busca por ID. Si no existe: `404`.
   * Si existe: devuelve el registro **200 OK**.

4. `update(int $id)` (**PUT/PATCH /places/{id}**)

   * Verifica que el ID **exista** (previene actualizar algo inexistente → **404**).
   * Hace **update parcial** con `COALESCE(:param, columna)`:

     ```sql
     UPDATE places SET
       name = COALESCE(:name, name),
       description = COALESCE(:description, description),
       ...
     WHERE id = :id
     ```

     Así **PUT o PATCH** pueden enviar solo los campos a modificar.
   * Devuelve `200 OK` con mensaje de éxito.

5. `destroy(int $id)` (**DELETE /places/{id}**)

   * Ejecuta `DELETE`.
   * Si no borró filas → **404** (no existe).
   * Si borró → **204 No Content** (sin cuerpo).

**Helpers privados (reutilizables):**

* `json($data, int $code = 200)` → setea **status** y `Content-Type: application/json` y hace `echo json_encode($data)`.
* `error($code, $msg)` → genera JSON estándar de error con ese **status**.
* `jsonInput()` → parsea el body JSON y devuelve un array.

**Cómo lo explicas:** “Cada método mapea un verbo HTTP a una operación SQL. Uso `PDO::prepare` + `execute` con parámetros nombrados para evitar inyecciones y mantener legibilidad.”

---

# 7) `controllers/RestaurantsController.php` — mismo patrón que Places

* **Idéntica estructura** y helpers.
* **Validación POST** exige: `name`, `address`, `cuisine_type`.
* **Sugerencia opcional de negocio** (si te piden `409 Conflict` como en la práctica):

  * Crear índice único por (`name`, `address`).
  * Capturar `PDOException` con código SQLSTATE `23000` y responder **409**.

**Cómo lo explicas:** “Es el mismo CRUD, solo cambian los campos. Mantener el patrón homogéneo facilita mantenimiento y la exposición.”

---

# 8) `controllers/WeatherController.php` — proxy a servicio externo

**Objetivo:** Exponer un endpoint **simple** `/weather` que devuelva **solo lo necesario** del clima de Tacna: `{ city, temperature_c, description }`.

Pasos del método `current()`:

1. Define la URL externa: `https://wttr.in/Tacna?format=j1` (devuelve JSON).
2. Hace la solicitud con **timeout**:

   ```php
   $ctx = stream_context_create(['http' => ['timeout' => 10]]);
   $raw = @file_get_contents($url, false, $ctx);
   ```

   * Si falla (no hay red o cae el servicio): responde **502 Bad Gateway** (`Falla consultando clima externo`).
3. Decodifica JSON: `$data = json_decode($raw, true);`
4. Extrae resumen actual:

   ```php
   $cur = $data['current_condition'][0] ?? null;
   ```
5. Arma la **respuesta reducida**:

   ```php
   $out = [
     'city' => 'Tacna',
     'temperature_c' => $cur['temp_C'] ?? null,
     'description' => $cur['weatherDesc'][0]['value'] ?? null,
   ];
   ```
6. Devuelve `json($out)` → **200 OK**.

**Cómo lo explicas:** “Usamos **proxy** para encapsular el servicio externo: si cambia la API de clima, solo tocamos este controlador; el frontend siempre recibe el mismo formato limpio.”

---

# 9) Códigos HTTP y por qué están así

* **200 OK**: Éxito en consultas GET y actualizaciones PUT/PATCH.
* **201 Created**: Se creó un recurso (POST).
* **204 No Content**: Se eliminó un recurso (DELETE) sin cuerpo en la respuesta.
* **400 Bad Request**: Faltan campos obligatorios o el JSON es inválido.
* **404 Not Found**: ID no existe o ruta no mapeada.
* **409 Conflict** (opcional): Violación de una regla de negocio (p. ej., duplicado).
* **502 Bad Gateway**: Fallo al llamar a servicio externo (clima).

**Cómo lo explicas:** “Usamos los **códigos estándar REST** para que el cliente entienda el resultado sin inspeccionar el cuerpo.”

---

# 10) Validación y seguridad básica

* Validación mínima en `store()` y `update()`:

  * `places`: `name`, `description` requeridos en POST.
  * `restaurants`: `name`, `address`, `cuisine_type` requeridos en POST.
* **Inyección SQL** mitigada** con `PDO::prepare` + parámetros **nombrados**.
* **CORS** habilitado para facilitar consumo desde web/app móvil.
* **Errores**: siempre se devuelven como JSON con un `error` legible (no HTML).

> Si te piden más seguridad: agregar un middleware simple de **API key** (header `X-API-Key`) en `public/index.php` o añadir **rate limiting** con un contador en archivo.

---

# 11) Pruebas y casos que puedes mostrar en vivo

1. **Listar**: `GET /places` → 200 con arreglo JSON.
2. **Crear**: `POST /places` con JSON válido → 201.
3. **Error 400**: `POST /places` sin `name` → 400 con `{error: "..."}`
4. **Mostrar**: `GET /places/1` → 200 o 404 si no existe.
5. **Actualizar**: `PUT /places/1` con `{"address":"..."}`
6. **Eliminar**: `DELETE /places/1` → 204; repetir → 404.
7. **Clima**: `GET /weather` → 200 con `{city, temperature_c, description}`; simular caída (desconectar red) → 502.

---

# 12) Cómo defender decisiones de diseño ante preguntas

* **¿Por qué SQLite?**
  Porque es **liviano**, no requiere servicio externo y cabe en un archivo, ideal para examen y demos. Se puede cambiar a Postgres/MySQL pasando por `lib/Database.php` y ajustando SQL.

* **¿Por qué controladores separados?**
  Aísla la lógica por recurso (**SRP**). Si crecen las reglas, cada controlador escala sin afectar al resto.

* **¿Por qué un router “manual” y no un framework?**
  La práctica exigía **PHP puro**. El router en `public/index.php` es **claro, auditable** y suficiente.

* **¿Por qué `COALESCE` en el update?**
  Permite **updates parciales** sin exigir todos los campos (soporta tanto PUT como PATCH).

* **¿Por qué proxy de clima?**
  Para **estandarizar el JSON** hacia el cliente y **proteger** la app de cambios del proveedor externo.

* **¿Cómo escalarías esto?**

  * Mover a **Nginx/Apache** con PHP-FPM.
  * Cambiar a **PostgreSQL**.
  * Añadir **índices** (p. ej., `CREATE INDEX` sobre `name` en `places`).
  * Agregar **capa de validación** más robusta y **cache** (clima con TTL).

---

# 13) “Guion” rápido para tu exposición (2–3 minutos)

1. **Arquitectura**: “API REST PHP, recursos Places/Restaurants, clima por proxy; SQLite; estructura por carpetas (public/controllers/lib/dataset).”
2. **Ciclo de una petición**: “`index.php` resuelve ruta, llama controlador, controlador usa `Database::conn()` (PDO), ejecuta SQL y responde JSON.”
3. **CRUD ejemplo**: Mostrar `POST /places` (validación, `INSERT`, `201`).
4. **Update parcial** con `COALESCE`.
5. **Clima**: `GET /weather` → explicar `file_get_contents` con timeout, `502` en error.
6. **Códigos HTTP** y **manejo de errores**.
7. **Cómo se provisiona**: `php create_db.php` y `php -S ... -t public`.

---

Si quieres, te preparo una **colección de Postman** con todos los requests listos (incluyendo casos de error) y un **README.md** cortito para entregar a la ingeniera.

# Post


http://localhost:8000/places
Body (raw JSON)
{
  "name": "Petroglifos de Miculla",
  "description": "Conjunto arqueológico preincaico",
  "address": "Miculla, Tacna",
  "latitude": -17.982,
  "longitude": -70.033
}

http://localhost:8000/placesBody (incompleto)
{
  "description": "Falta el nombre"
}

# get
http://localhost:8000/places/1