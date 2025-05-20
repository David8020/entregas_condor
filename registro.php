<?php
// registro.php (Página de Registro de Clientes)
require_once __DIR__ . '/includes/header.php'; // Incluye el header, session_start() y conexión $pdo

// Determinar qué navegación mostrar
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Un admin no debería estar registrándose como cliente mientras está logueado como admin
    // Podríamos redirigirlo o mostrar un mensaje. Por ahora, mostramos nav de visitante.
    require_once __DIR__ . '/includes/nav_visitante.php';
} elseif (isset($_SESSION['cliente_id'])) {
    // Si el cliente ya está logueado, redirigir a la página principal o a mis_pedidos.php
    header("Location: " . obtener_url_base() . "index.php");
    exit;
} else {
    require_once __DIR__ . '/includes/nav_visitante.php';
}

$mensaje_error = '';
$mensaje_exito = '';

// Procesar el formulario cuando se envía (método POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoger los datos del formulario
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    $telefono = trim($_POST['telefono'] ?? ''); // Opcional
    $direccion = trim($_POST['direccion'] ?? ''); // Opcional

    // Validaciones básicas
    if (empty($nombre) || empty($apellidos) || empty($correo) || empty($password) || empty($confirmar_password)) {
        $mensaje_error = "Todos los campos marcados con (*) son obligatorios.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje_error = "El formato del correo electrónico no es válido.";
    } elseif (strlen($password) < 6) {
        $mensaje_error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($password !== $confirmar_password) {
        $mensaje_error = "Las contraseñas no coinciden.";
    } else {
        // Si las validaciones básicas pasan, procedemos a interactuar con la BD
        try {
            // Verificar si el correo ya existe
            $stmtCheck = $pdo->prepare("SELECT id_cliente FROM Cliente WHERE correo = :correo");
            $stmtCheck->bindParam(':correo', $correo);
            $stmtCheck->execute();

            if ($stmtCheck->fetch()) {
                $mensaje_error = "El correo electrónico ya está registrado. Por favor, intente con otro o <a href='login.php'>inicie sesión</a>.";
            } else {
                // Hashear la contraseña antes de guardarla
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Preparar la sentencia SQL para insertar el nuevo cliente
                $sql = "INSERT INTO Cliente (nombre, apellidos, correo, password_hash, telefono, direccion) 
                        VALUES (:nombre, :apellidos, :correo, :password_hash, :telefono, :direccion)";
                $stmt = $pdo->prepare($sql);

                // Vincular los parámetros
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':apellidos', $apellidos);
                $stmt->bindParam(':correo', $correo);
                $stmt->bindParam(':password_hash', $passwordHash);
                $stmt->bindParam(':telefono', $telefono, $telefono ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':direccion', $direccion, $direccion ? PDO::PARAM_STR : PDO::PARAM_NULL);

                // Ejecutar la sentencia
                if ($stmt->execute()) {
                    $idCliente = $pdo->lastInsertId();
                    registrarEnBitacora($pdo, $idCliente, 'Registro Nuevo Cliente', json_encode(['correo' => $correo]));
                    $mensaje_exito = "¡Registro exitoso! Ahora puedes <a href='login.php'>iniciar sesión</a>.";
                    // Limpiar los campos del POST para que no se repueblen en el formulario
                    $_POST = array(); 
                } else {
                    $mensaje_error = "Error al registrar el cliente. Por favor, inténtelo de nuevo.";
                    registrarEnBitacora($pdo, null, 'Error Registro Cliente', json_encode(['correo' => $correo, 'errorInfo' => $stmt->errorInfo()]));
                }
            }
        } catch (PDOException $e) {
            // Manejar errores de la base de datos
            $mensaje_error = "Error de base de datos: " . $e->getMessage();
            // En un entorno de producción, no mostrar $e->getMessage() directamente al usuario.
            // Loguear el error detallado para el administrador.
            error_log("Error PDO en registro.php: " . $e->getMessage());
            registrarEnBitacora($pdo, null, 'Excepción PDO Registro Cliente', $e->getMessage());
        }
    }
}
?>

<div class="container">
    <div class="main-content">
        <h2>Registro de Nuevo Cliente</h2>
        <p>Crea una cuenta para realizar tus pedidos de forma rápida y sencilla.</p>

        <?php if ($mensaje_error): ?>
            <p class="error"><?php echo $mensaje_error; ?></p>
        <?php endif; ?>
        <?php if ($mensaje_exito): ?>
            <p class="success"><?php echo $mensaje_exito; ?></p>
        <?php endif; ?>

        <?php if (!$mensaje_exito): // Solo mostrar el formulario si no hubo éxito en el registro ?>
        <form action="registro.php" method="post">
            <div>
                <label for="nombre">Nombre (*):</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="apellidos">Apellidos (*):</label>
                <input type="text" id="apellidos" name="apellidos" value="<?php echo htmlspecialchars($_POST['apellidos'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="correo">Correo Electrónico (*):</label>
                <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="password">Contraseña (*):</label>
                <input type="password" id="password" name="password" required>
                <small>Mínimo 6 caracteres.</small>
            </div>
            <div>
                <label for="confirmar_password">Confirmar Contraseña (*):</label>
                <input type="password" id="confirmar_password" name="confirmar_password" required>
            </div>
            <div>
                <label for="telefono">Teléfono (Opcional):</label>
                <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
            </div>
            <div>
                <label for="direccion">Dirección (Opcional):</label>
                <textarea id="direccion" name="direccion"><?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?></textarea>
            </div>
            <button type="submit">Registrarse</button>
        </form>
        <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a>.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php'; // Incluye el footer común
?>
