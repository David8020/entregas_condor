<?php
// login.php (Página de Login de Clientes)
require_once __DIR__ . '/includes/header.php'; // Incluye el header, session_start() y conexión $pdo

// Determinar qué navegación mostrar
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Un admin no debería estar logueándose como cliente.
    require_once __DIR__ . '/includes/nav_visitante.php'; // O redirigir a admin/index.php
} elseif (isset($_SESSION['cliente_id'])) {
    // Si el cliente ya está logueado, redirigir a la página principal o a mis_pedidos.php
    header("Location: " . obtener_url_base() . "index.php");
    exit;
} else {
    require_once __DIR__ . '/includes/nav_visitante.php';
}

$mensaje_error = '';

// Procesar el formulario cuando se envía (método POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($correo) || empty($password)) {
        $mensaje_error = "Correo electrónico y contraseña son obligatorios.";
    } else {
        try {
            $sql = "SELECT id_cliente, nombre, apellidos, password_hash FROM Cliente WHERE correo = :correo";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':correo', $correo);
            $stmt->execute();
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cliente && password_verify($password, $cliente['password_hash'])) {
                // Contraseña correcta, iniciar sesión.
                $_SESSION['cliente_id'] = $cliente['id_cliente'];
                $_SESSION['cliente_nombre'] = htmlspecialchars($cliente['nombre']); // Guardar nombre para mostrar
                $_SESSION['cliente_apellidos'] = htmlspecialchars($cliente['apellidos']);
                $_SESSION['cliente_correo'] = htmlspecialchars($correo);
                
                // Regenerar ID de sesión por seguridad después del login
                session_regenerate_id(true);

                registrarEnBitacora($pdo, $cliente['id_cliente'], 'Login Cliente Exitoso', json_encode(['correo' => $correo]));
                
                // Redirigir al catálogo o a la página principal
                header("Location: " . obtener_url_base() . "catalogo.php");
                exit;
            } else {
                $mensaje_error = "Correo electrónico o contraseña incorrectos.";
                registrarEnBitacora($pdo, null, 'Login Cliente Fallido', json_encode(['correo' => $correo]));
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error de base de datos: " . $e->getMessage();
            error_log("Error PDO en login.php: " . $e->getMessage());
            registrarEnBitacora($pdo, null, 'Excepción PDO Login Cliente', $e->getMessage());
        }
    }
}


// Mostrar mensajes flash (ej. desde checkout si se requiere login)
if (isset($_SESSION['mensaje_flash_login'])) {
    echo "<p class='" . ($_SESSION['mensaje_flash_login_tipo'] ?? 'error') . "'>" . $_SESSION['mensaje_flash_login'] . "</p>";
    unset($_SESSION['mensaje_flash_login'], $_SESSION['mensaje_flash_login_tipo']);
}


?>

<div class="container">
    <div class="main-content">
        <h2>Iniciar Sesión</h2>
        <p>Ingresa tus credenciales para acceder a tu cuenta y realizar pedidos.</p>

        <?php if ($mensaje_error): ?>
            <p class="error"><?php echo $mensaje_error; ?></p>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div>
                <label for="correo">Correo Electrónico:</label>
                <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
        <p>¿No tienes una cuenta? <a href="registro.php">Regístrate aquí</a>.</p>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php'; // Incluye el footer común
?>
