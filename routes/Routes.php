<?php
// El router decide qué controlador y qué método ejecutar
// según la URL y el método HTTP.

// Cargamos los controladores y la clase Jwt (para validar tokens)
require_once __DIR__ . "/../controllers/ProductoController.php";
require_once __DIR__ . "/../controllers/UsuarioController.php";
require_once __DIR__ . "/../controllers/CarritoController.php";
require_once __DIR__ . "/../controllers/CompraController.php";
require_once __DIR__ . "/../controllers/TicketController.php";
require_once __DIR__ . "/../controllers/FavoritoController.php";
require_once __DIR__ . "/../utils/Jwt.php";

// ¿Quién hace el pedido?
// Lee el token del header Authorization y lo valida.
// Si es válido devuelve los datos del usuario; si no, error 401.
function usuarioAutenticado() {
    // Todos los headers del pedido
    $headers = getallheaders();

    // "Bearer eyJ..." (o vacío si no vino)
    $auth = $headers["Authorization"] ?? "";

    // Tiene que empezar con "Bearer "
    if (!str_starts_with($auth, "Bearer ")) {
        Response::json(["error" => "Falta el token"], 401);
        exit; // Detiene todo el script
    }

    // Saca las 7 letras de "Bearer "
    $token = substr($auth, 7);

    // Comprueba firma y vencimiento
    $payload = Jwt::verificar($token);

    if (!$payload) {
        Response::json(["error" => "Token inválido o vencido"], 401);
        exit;
    }

    // Trae id, email y rol del usuario
    return $payload;
}

// ¿Puede hacerlo?
// Solo deja pasar a los usuarios con rol admin (si no, error 403).
function requiereAdmin($usuario) {
    if ($usuario["rol"] !== "admin") {
        Response::json(["error" => "No autorizado, se requiere rol admin"], 403);
        exit;
    }
}

// GET, POST, PUT o DELETE
$metodo = $_SERVER["REQUEST_METHOD"];

// parse_url(..., PHP_URL_PATH) se queda solo con la ruta,
// SIN el "?buscar=..."
$ruta = trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/");

// Separa la ruta en pedazos por cada "/"
$partes = explode("/", $ruta);

// Descartamos todo lo que viene antes de "public"
// (api_proyecto/public/...)
$pos = array_search("public", $partes);
if ($pos !== false) {
    $partes = array_slice($partes, $pos + 1);
}

// Ejemplo: /carrito/actualizar/3
//   -> recurso="carrito", accion="actualizar", param="3"
$recurso = $partes[0] ?? null; // productos, usuarios, carrito...
$accion  = $partes[1] ?? null; // registro, agregar, o un id
$param   = $partes[2] ?? null; // el id del final (si hay)

// Lee el cuerpo JSON del pedido y lo convierte en array
// ([] si no vino nada)
$datos = json_decode(file_get_contents("php://input"), true) ?? [];

switch ($recurso) {
    case "productos":
        $c = new ProductoController();

        // GET /productos
        if ($metodo === "GET" && $accion === null) {
            $c->listar();
        }
        // GET /productos/3
        elseif ($metodo === "GET") {
            $c->verUno($accion);
        }
        // POST /productos/imagen (solo admin)
        elseif ($metodo === "POST" && $accion === "imagen") {
            requiereAdmin(usuarioAutenticado());
            $c->subirImagen();
        }
        // POST /productos (solo admin)
        elseif ($metodo === "POST") {
            requiereAdmin(usuarioAutenticado());
            $c->crear($datos);
        }
        // PUT /productos/3 (solo admin)
        elseif ($metodo === "PUT") {
            requiereAdmin(usuarioAutenticado());
            $c->editar($accion, $datos);
        }
        // DELETE /productos/3 (solo admin)
        elseif ($metodo === "DELETE") {
            requiereAdmin(usuarioAutenticado());
            $c->eliminar($accion);
        }
        break;

    case "usuarios":
        $c = new UsuarioController();

        // POST /usuarios/registro (público)
        if ($metodo === "POST" && $accion === "registro") {
            $c->registro($datos);
        }
        // POST /usuarios/login (público)
        elseif ($metodo === "POST" && $accion === "login") {
            $c->login($datos);
        }
        // GET /usuarios/todos: lista completa (solo admin)
        elseif ($metodo === "GET" && $accion === "todos") {
            requiereAdmin(usuarioAutenticado());
            $c->todos();
        }
        // GET /usuarios: mi perfil
        elseif ($metodo === "GET") {
            $c->perfil(usuarioAutenticado());
        }
        break;

    case "carrito":
        $c = new CarritoController();

        // Todo el carrito exige estar logueado
        $u = usuarioAutenticado();

        // GET /carrito
        if ($metodo === "GET" && $accion === null) {
            $c->ver($u);
        }
        // POST /carrito/agregar
        elseif ($metodo === "POST" && $accion === "agregar") {
            $c->agregar($u, $datos);
        }
        // PUT /carrito/actualizar/3
        elseif ($metodo === "PUT" && $accion === "actualizar") {
            $c->actualizar($u, $param, $datos);
        }
        // DELETE /carrito/eliminar/3
        elseif ($metodo === "DELETE" && $accion === "eliminar") {
            $c->eliminar($u, $param);
        }
        // DELETE /carrito/vaciar
        elseif ($metodo === "DELETE" && $accion === "vaciar") {
            $c->vaciar($u);
        }
        break;

    case "compras":
        $c = new CompraController();
        $u = usuarioAutenticado();

        // POST /compras/finalizar
        if ($metodo === "POST" && $accion === "finalizar") {
            $c->finalizar($u);
        }
        // GET /compras/todas: todas las ventas (solo admin)
        elseif ($metodo === "GET" && $accion === "todas") {
            requiereAdmin($u);
            $c->todas();
        }
        // GET /compras: mis compras
        elseif ($metodo === "GET") {
            $c->historial($u);
        }
        break;

    case "tickets":
        $c = new TicketController();

        // Hay que estar logueado
        usuarioAutenticado();

        // POST /tickets/generar/3: crea el PDF
        if ($metodo === "POST" && $accion === "generar") {
            $c->generar($param);
        }
        // GET /tickets/3: datos del ticket
        elseif ($metodo === "GET") {
            $c->verTicket($accion);
        }
        break;

    case "favoritos":
        $c = new FavoritoController();
        $u = usuarioAutenticado();

        // GET /favoritos
        if ($metodo === "GET" && $accion === null) {
            $c->listar($u);
        }
        // POST /favoritos/agregar
        elseif ($metodo === "POST" && $accion === "agregar") {
            $c->agregar($u, $datos);
        }
        // DELETE /favoritos/eliminar/3
        elseif ($metodo === "DELETE" && $accion === "eliminar") {
            $c->eliminar($u, $param);
        }
        break;

    default:
        // Ninguna ruta coincidió
        Response::json(["error" => "Ruta no válida"], 404);
}