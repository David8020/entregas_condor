<?php
// backend/api.php (Versión Novato)

// Incluir el archivo de configuración donde está conectarDB() y otras configuraciones.
require_once 'config.php';

// Iniciar la sesión PHP.
// Esto es necesario para recordar si un usuario ha iniciado sesión.
// Debe llamarse antes de cualquier salida al navegador.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Conexión a la Base de Datos ---
// Obtenemos el objeto PDO para interactuar con la BD.
$pdo = conectarDB();

// --- Cabeceras para la Respuesta JSON ---
// Indicar que la respuesta será en formato JSON y codificación UTF-8.
header('Content-Type: application/json; charset=utf-8');
// Permitir solicitudes desde cualquier origen (CORS). Útil para desarrollo.
// En producción, deberías restringirlo a tu dominio de frontend.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Cabeceras permitidas

// Manejar solicitud OPTIONS (pre-flight) para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200); // OK
    exit(); // Terminar script
}


// --- Función Auxiliar para Enviar Respuestas JSON ---
/**
 * Envía una respuesta JSON al cliente y termina la ejecución del script.
 * @param int $codigoHttp El código de estado HTTP (ej. 200 para OK, 400 para error de cliente).
 * @param array $datos El array de datos a convertir en JSON.
 */
function enviarRespuestaJSON(int $codigoHttp, array $datos): void {
    http_response_code($codigoHttp); // Establece el código de estado HTTP
    echo json_encode($datos);        // Convierte el array a JSON y lo imprime
    exit;                            // Termina la ejecución del script
}

// --- Enrutador Principal ---
// Determinamos la acción solicitada a través del parámetro 'r' en la URL.
// Ejemplo: api.php?r=productos
$ruta = $_GET['r'] ?? 'default'; // Si 'r' no está definido, usamos 'default'.

// Usamos un switch para manejar las diferentes rutas.
try {
    switch ($ruta) {
        case 'registro': // Ruta para registrar un nuevo cliente
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Los datos del nuevo cliente vendrán en el cuerpo de la solicitud POST.
                // Para este ejemplo simple, esperamos datos de formulario (x-www-form-urlencoded).
                // Si envías JSON desde Postman, asegúrate de decodificarlo:
                // $datosJSON = json_decode(file_get_contents('php://input'), true);
                // Y luego usar $datosJSON['nombre'] en lugar de $_POST['nombre'].
                // Por simplicidad, usaremos $_POST aquí.
                
                $nombre = $_POST['nombre'] ?? null;
                $apellidos = $_POST['apellidos'] ?? null;
                $correo = $_POST['correo'] ?? null;
                $password = $_POST['password'] ?? null; // Contraseña en texto plano

                if (!$nombre || !$apellidos || !$correo || !$password) {
                    enviarRespuestaJSON(400, ['error' => true, 'mensaje' => 'Todos los campos son obligatorios: nombre, apellidos, correo, password.']);
                }
                if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    enviarRespuestaJSON(400, ['error' => true, 'mensaje' => 'El formato del correo no es válido.']);
                }

                // Verificar si el correo ya existe
                $stmtCheck = $pdo->prepare("SELECT id_cliente FROM Cliente WHERE correo = :correo");
                $stmtCheck->bindParam(':correo', $correo);
                $stmtCheck->execute();
                if ($stmtCheck->fetch()) {
                    enviarRespuestaJSON(400, ['error' => true, 'mensaje' => 'El correo electrónico ya está registrado.']);
                }

                // Hashear la contraseña antes de guardarla
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $sql = "INSERT INTO Cliente (nombre, apellidos, correo, password_hash, telefono, direccion) 
                        VALUES (:nombre, :apellidos, :correo, :password_hash, :telefono, :direccion)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':apellidos', $apellidos);
                $stmt->bindParam(':correo', $correo);
                $stmt->bindParam(':password_hash', $passwordHash);
                // Campos opcionales
                $telefono = $_POST['telefono'] ?? null;
                $direccion = $_POST['direccion'] ?? null;
                $stmt->bindParam(':telefono', $telefono);
                $stmt->bindParam(':direccion', $direccion);
                
                $stmt->execute();
                $idCliente = $pdo->lastInsertId();
                registrarEnBitacora($pdo, null, 'Registro Nuevo Cliente', json_encode(['id_cliente' => $idCliente, 'correo' => $correo]));
                enviarRespuestaJSON(201, ['error' => false, 'mensaje' => 'Cliente registrado exitosamente.', 'id_cliente' => $idCliente]);
            } else {
                enviarRespuestaJSON(405, ['error' => true, 'mensaje' => 'Método no permitido para /registro. Use POST.']);
            }
            break;

        case 'login': // Ruta para iniciar sesión
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $correo = $_POST['correo'] ?? null;
                $password = $_POST['password'] ?? null;

                if (!$correo || !$password) {
                    enviarRespuestaJSON(400, ['error' => true, 'mensaje' => 'Correo y contraseña son obligatorios.']);
                }

                $sql = "SELECT id_cliente, password_hash, nombre FROM Cliente WHERE correo = :correo";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':correo', $correo);
                $stmt->execute();
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($cliente && password_verify($password, $cliente['password_hash'])) {
                    // Contraseña correcta, iniciar sesión.
                    $_SESSION['cliente_id'] = $cliente['id_cliente'];
                    $_SESSION['cliente_nombre'] = $cliente['nombre'];
                    registrarEnBitacora($pdo, $cliente['id_cliente'], 'Login Exitoso', json_encode(['correo' => $correo]));
                    enviarRespuestaJSON(200, ['error' => false, 'mensaje' => 'Login exitoso.', 'cliente_id' => $cliente['id_cliente'], 'nombre' => $cliente['nombre']]);
                } else {
                    registrarEnBitacora($pdo, null, 'Login Fallido', json_encode(['correo' => $correo]));
                    enviarRespuestaJSON(401, ['error' => true, 'mensaje' => 'Credenciales incorrectas.']);
                }
            } else {
                enviarRespuestaJSON(405, ['error' => true, 'mensaje' => 'Método no permitido para /login. Use POST.']);
            }
            break;

        case 'logout': // Ruta para cerrar sesión
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $cliente_id_logout = $_SESSION['cliente_id'] ?? null;
                session_unset();    // Elimina todas las variables de sesión
                session_destroy();  // Destruye la sesión
                registrarEnBitacora($pdo, $cliente_id_logout, 'Logout Exitoso', null);
                enviarRespuestaJSON(200, ['error' => false, 'mensaje' => 'Sesión cerrada exitosamente.']);
            } else {
                 enviarRespuestaJSON(405, ['error' => true, 'mensaje' => 'Método no permitido para /logout. Use GET.']);
            }
            break;

        case 'productos': // Ruta para manejar productos
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Listar todos los productos
                $stmt = $pdo->query("SELECT p.id_producto, p.nombre_producto, p.descripcion, p.precio, pr.nombre_proveedor, i.cantidad_disponible 
                                     FROM Producto p
                                     JOIN Proveedor pr ON p.id_proveedor = pr.id_proveedor
                                     LEFT JOIN Inventario i ON p.id_producto = i.id_producto
                                     ORDER BY p.nombre_producto ASC");
                $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                registrarEnBitacora($pdo, $_SESSION['cliente_id'] ?? null, 'Consulta Productos', null);
                enviarRespuestaJSON(200, ['error' => false, 'datos' => $productos]);

            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Crear un nuevo producto (simplificado, sin validación de admin)
                // Para un sistema real, esto debería estar protegido.
                // Asumimos que los datos vienen como JSON en el cuerpo de la solicitud
                $datosProducto = json_decode(file_get_contents('php://input'), true);

                $nombre = $datosProducto['nombre_producto'] ?? null;
                $precio = $datosProducto['precio'] ?? null;
                $id_proveedor = $datosProducto['id_proveedor'] ?? null;
                $descripcion = $datosProducto['descripcion'] ?? '';
                $cantidad_inicial = $datosProducto['cantidad_inicial_inventario'] ?? 0;

                if (!$nombre || !isset($precio) || !$id_proveedor) {
                    enviarRespuestaJSON(400, ['error' => true, 'mensaje' => 'Campos obligatorios: nombre_producto, precio, id_proveedor.']);
                }
                if (!is_numeric($precio) || $precio < 0 || !is_numeric($id_proveedor) || !is_numeric($cantidad_inicial) || $cantidad_inicial < 0) {
                     enviarRespuestaJSON(400, ['error' => true, 'mensaje' => 'Precio, id_proveedor y cantidad_inicial deben ser numéricos y no negativos.']);
                }
                
                $pdo->beginTransaction();
                try {
                    $sqlProd = "INSERT INTO Producto (nombre_producto, descripcion, precio, id_proveedor) 
                                VALUES (:nombre, :descripcion, :precio, :id_proveedor)";
                    $stmtProd = $pdo->prepare($sqlProd);
                    $stmtProd->bindParam(':nombre', $nombre);
                    $stmtProd->bindParam(':descripcion', $descripcion);
                    $stmtProd->bindParam(':precio', $precio);
                    $stmtProd->bindParam(':id_proveedor', $id_proveedor, PDO::PARAM_INT);
                    $stmtProd->execute();
                    $idProducto = $pdo->lastInsertId();

                    $sqlInv = "INSERT INTO Inventario (id_producto, cantidad_disponible) VALUES (:id_producto, :cantidad)";
                    $stmtInv = $pdo->prepare($sqlInv);
                    $stmtInv->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
                    $stmtInv->bindParam(':cantidad', $cantidad_inicial, PDO::PARAM_INT);
                    $stmtInv->execute();

                    $pdo->commit();
                    registrarEnBitacora($pdo, $_SESSION['cliente_id'] ?? null, 'Creación Producto', json_encode($datosProducto));
                    enviarRespuestaJSON(201, ['error' => false, 'mensaje' => 'Producto creado exitosamente.', 'id_producto' => $idProducto]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    registrarEnBitacora($pdo, $_SESSION['cliente_id'] ?? null, 'Error Creación Producto', $e->getMessage());
                    enviarRespuestaJSON(500, ['error' => true, 'mensaje' => 'Error al crear el producto: ' . $e->getMessage()]);
                }
            } else {
                enviarRespuestaJSON(405, ['error' => true, 'mensaje' => 'Método no permitido para /productos. Use GET o POST.']);
            }
            break;

        case 'proveedores': // Ruta para listar proveedores
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $stmt = $pdo->query("SELECT id_proveedor, nombre_proveedor, contacto, telefono FROM Proveedor ORDER BY nombre_proveedor ASC");
                $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                registrarEnBitacora($pdo, $_SESSION['cliente_id'] ?? null, 'Consulta Proveedores', null);
                enviarRespuestaJSON(200, ['error' => false, 'datos' => $proveedores]);
            } else {
                enviarRespuestaJSON(405, ['error' => true, 'mensaje' => 'Método no permitido para /proveedores. Use GET.']);
            }
            break;

        case 'pedidos': // Ruta para manejar pedidos
            // Verificar si el cliente ha iniciado sesión
            if (!isset($_SESSION['cliente_id'])) {
                registrarEnBitacora($pdo, null, 'Intento Acceso Pedidos Sin Login', null);
                enviarRespuestaJSON(401, ['error' => true, 'mensaje' => 'No autorizado. Debes iniciar sesión para gestionar pedidos.']);
            }
            $idCliente = $_SESSION['cliente_id'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Crear un nuevo pedido
                // Esperamos un JSON con la estructura: { "productos": [ {"id_producto": X, "cantidad": Y}, ... ] }
                $datosPedido = json_decode(file_get_contents('php://input'), true);

                if (empty($datosPedido['productos']) || !is_array($datosPedido['productos'])) {
                    enviarRespuestaJSON(400, ['error' => true, 'mensaje' => 'Se requiere un array de "productos" para crear el pedido.']);
                }

                $pdo->beginTransaction(); // Iniciar transacción
                try {
                    // 1. Crear la cabecera del Pedido
                    $sqlPedido = "INSERT INTO Pedido (id_cliente, estado) VALUES (:id_cliente, 'Pendiente')";
                    $stmtPedido = $pdo->prepare($sqlPedido);
                    $stmtPedido->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
                    $stmtPedido->execute();
                    $idPedido = $pdo->lastInsertId();
                    $totalPedidoFinal = 0;

                    // 2. Procesar cada producto del pedido
                    foreach ($datosPedido['productos'] as $item) {
                        $idProducto = $item['id_producto'] ?? null;
                        $cantidad = $item['cantidad'] ?? null;

                        if (!$idProducto || !$cantidad || !is_numeric($idProducto) || !is_numeric($cantidad) || $cantidad <= 0) {
                            throw new Exception("Datos de producto inválidos en el pedido. ID: {$idProducto}, Cant: {$cantidad}");
                        }

                        // Obtener precio y verificar stock (CON BLOQUEO PARA EVITAR CONDICIONES DE CARRERA)
                        $stmtProdInfo = $pdo->prepare("SELECT p.precio, i.cantidad_disponible 
                                                       FROM Producto p 
                                                       JOIN Inventario i ON p.id_producto = i.id_producto 
                                                       WHERE p.id_producto = :id_producto FOR UPDATE"); // FOR UPDATE bloquea la fila
                        $stmtProdInfo->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
                        $stmtProdInfo->execute();
                        $infoProducto = $stmtProdInfo->fetch(PDO::FETCH_ASSOC);

                        if (!$infoProducto) {
                            throw new Exception("Producto con ID {$idProducto} no encontrado.");
                        }
                        if ($infoProducto['cantidad_disponible'] < $cantidad) {
                            throw new Exception("Stock insuficiente para el producto ID {$idProducto}. Disponible: {$infoProducto['cantidad_disponible']}, Solicitado: {$cantidad}.");
                        }

                        $precioUnitario = $infoProducto['precio'];
                        $subtotal = $precioUnitario * $cantidad;
                        $totalPedidoFinal += $subtotal;

                        // Insertar en DetallePedido
                        $sqlDetalle = "INSERT INTO DetallePedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
                                       VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario, :subtotal)";
                        $stmtDetalle = $pdo->prepare($sqlDetalle);
                        $stmtDetalle->bindParam(':id_pedido', $idPedido, PDO::PARAM_INT);
                        $stmtDetalle->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
                        $stmtDetalle->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
                        $stmtDetalle->bindParam(':precio_unitario', $precioUnitario);
                        $stmtDetalle->bindParam(':subtotal', $subtotal);
                        $stmtDetalle->execute();

                        // Actualizar Inventario
                        $sqlInvUpdate = "UPDATE Inventario SET cantidad_disponible = cantidad_disponible - :cantidad 
                                         WHERE id_producto = :id_producto";
                        $stmtInvUpdate = $pdo->prepare($sqlInvUpdate);
                        $stmtInvUpdate->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
                        $stmtInvUpdate->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
                        $stmtInvUpdate->execute();
                    }

                    // 3. Actualizar el total en la tabla Pedido
                    $sqlTotalUpdate = "UPDATE Pedido SET total = :total WHERE id_pedido = :id_pedido";
                    $stmtTotalUpdate = $pdo->prepare($sqlTotalUpdate);
                    $stmtTotalUpdate->bindParam(':total', $totalPedidoFinal);
                    $stmtTotalUpdate->bindParam(':id_pedido', $idPedido, PDO::PARAM_INT);
                    $stmtTotalUpdate->execute();

                    $pdo->commit(); // Confirmar transacción
                    registrarEnBitacora($pdo, $idCliente, 'Creación Pedido', json_encode(['id_pedido' => $idPedido, 'total' => $totalPedidoFinal, 'productos' => $datosPedido['productos']]));
                    enviarRespuestaJSON(201, ['error' => false, 'mensaje' => 'Pedido creado exitosamente.', 'id_pedido' => $idPedido, 'total' => $totalPedidoFinal]);

                } catch (Exception $e) {
                    $pdo->rollBack(); // Revertir transacción en caso de error
                    registrarEnBitacora($pdo, $idCliente, 'Error Creación Pedido', $e->getMessage() . " Datos: " . json_encode($datosPedido));
                    enviarRespuestaJSON(500, ['error' => true, 'mensaje' => 'Error al crear el pedido: ' . $e->getMessage()]);
                }

            } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Listar pedidos del cliente logueado
                $sql = "SELECT id_pedido, fecha_pedido, total, estado FROM Pedido WHERE id_cliente = :id_cliente ORDER BY fecha_pedido DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
                $stmt->execute();
                $pedidosCliente = $stmt->fetchAll(PDO::FETCH_ASSOC);
                registrarEnBitacora($pdo, $idCliente, 'Consulta Mis Pedidos', null);
                enviarRespuestaJSON(200, ['error' => false, 'datos' => $pedidosCliente]);
            } else {
                enviarRespuestaJSON(405, ['error' => true, 'mensaje' => 'Método no permitido para /pedidos. Use GET o POST.']);
            }
            break;

        case 'inventario': // Ruta para listar el inventario
             // Opcional: Verificar si el cliente ha iniciado sesión, aunque para listar podría ser público o solo admin.
             // Por simplicidad, lo dejamos abierto pero registramos si hay sesión.
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $sql = "SELECT i.id_producto, p.nombre_producto, i.cantidad_disponible, i.fecha_actualizacion 
                        FROM Inventario i
                        JOIN Producto p ON i.id_producto = p.id_producto
                        ORDER BY p.nombre_producto ASC";
                $stmt = $pdo->query($sql);
                $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
                registrarEnBitacora($pdo, $_SESSION['cliente_id'] ?? null, 'Consulta Inventario', null);
                enviarRespuestaJSON(200, ['error' => false, 'datos' => $inventario]);
            } else {
                enviarRespuestaJSON(405, ['error' => true, 'mensaje' => 'Método no permitido para /inventario. Use GET.']);
            }
            break;
        
        case 'ver_sesion': // Ruta de prueba para ver datos de sesión
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (isset($_SESSION['cliente_id'])) {
                    enviarRespuestaJSON(200, [
                        'error' => false,
                        'mensaje' => 'Sesión activa.',
                        'cliente_id' => $_SESSION['cliente_id'],
                        'cliente_nombre' => $_SESSION['cliente_nombre'] ?? 'No disponible'
                    ]);
                } else {
                    enviarRespuestaJSON(200, ['error' => false, 'mensaje' => 'No hay sesión activa.']);
                }
            } else {
                enviarRespuestaJSON(405, ['error' => true, 'mensaje' => 'Método no permitido. Use GET.']);
            }
            break;


        default: // Ruta no encontrada
            enviarRespuestaJSON(404, ['error' => true, 'mensaje' => 'Ruta no encontrada: ' . htmlspecialchars($ruta)]);
            break;
    }
} catch (PDOException $e) {
    // Error general de base de datos no capturado específicamente
    registrarEnBitacora($pdo, $_SESSION['cliente_id'] ?? null, 'Error PDO General', $e->getMessage());
    enviarRespuestaJSON(500, ['error' => true, 'mensaje' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Otro tipo de error general
    registrarEnBitacora($pdo, $_SESSION['cliente_id'] ?? null, 'Error General Aplicación', $e->getMessage());
    enviarRespuestaJSON(500, ['error' => true, 'mensaje' => 'Error en la aplicación: ' . $e->getMessage()]);
}

?>
