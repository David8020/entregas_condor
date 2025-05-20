<?php
// admin/index.php (Login y Dashboard del Administrador)
// El header de admin podría ser diferente o podríamos usar el mismo y adaptar la navegación.
// Por ahora, usaremos el mismo header general.
require_once __DIR__ . '/../includes/header.php'; // Accede al header general

$mensaje_login_admin = '';

// --- Definir credenciales de Admin (solo para este ejemplo simple) ---
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'condor123'); // ¡Cambia esto en un proyecto real!

// Procesar intento de login del admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_admin'])) {
    $username_ingresado = $_POST['username'] ?? '';
    $password_ingresada = $_POST['password'] ?? '';

    if ($username_ingresado === ADMIN_USER && $password_ingresada === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username_ingresado;
        registrarEnBitacora($pdo, null, 'Login Admin Exitoso', json_encode(['username' => $username_ingresado]));
        // Redirigir al mismo index.php que ahora mostrará el dashboard
        header("Location: " . obtener_url_base() . "admin/index.php");
        exit;
    } else {
        $mensaje_login_admin = "<p class='error'>Nombre de usuario o contraseña incorrectos.</p>";
        registrarEnBitacora($pdo, null, 'Login Admin Fallido', json_encode(['username' => $username_ingresado]));
    }
}

// Verificar si el admin ya está logueado para mostrar el dashboard o el login
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Admin está logueado, mostrar navegación de admin y dashboard
    require_once __DIR__ . '/../includes/nav_admin.php';
?>
    <div class="container">
        <div class="main-content admin-section">
            <h2>Panel de Administración de Entregas Cóndor Ltda.</h2>
            <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>.</p>
            <p>Desde aquí puedes gestionar los aspectos clave de la tienda:</p>
            <ul>
                <li><a href="<?php echo obtener_url_base(); ?>admin/pedidos.php">Ver Todos los Pedidos</a></li>
                <li><a href="<?php echo obtener_url_base(); ?>admin/productos.php">Gestionar Productos</a></li>
                <li><a href="<?php echo obtener_url_base(); ?>admin/inventario.php">Consultar Inventario</a></li>
                <li><a href="<?php echo obtener_url_base(); ?>admin/proveedores.php">Gestionar Proveedores</a></li>
            </ul>
            <p><a href="<?php echo obtener_url_base(); ?>admin/logout_admin.php"><button style="background-color:#dc3545;">Cerrar Sesión de Administrador</button></a></p>
        </div>
    </div>
<?php
} else {
    // Admin no está logueado, mostrar navegación de visitante (o ninguna específica) y formulario de login
    // Si un cliente está logueado, no debería ver el login de admin.
    // Podríamos redirigir al cliente a su index o mostrar un mensaje.
    // Por ahora, si hay sesión de cliente, no se muestra el login de admin.
    if (isset($_SESSION['cliente_id'])) {
        require_once __DIR__ . '/../includes/nav_cliente.php';
        echo "<div class='container'><p class='warning'>Ya has iniciado sesión como cliente. Para acceder como administrador, primero debes <a href='" . obtener_url_base() . "logout.php'>cerrar tu sesión de cliente</a>.</p></div>";
    } else {
        require_once __DIR__ . '/../includes/nav_visitante.php'; // O una navegación específica para admin login
        
         if (isset($_GET['mensaje_logout_admin']) && $_GET['mensaje_logout_admin'] == '1') {
                echo "<p class='success'>Has cerrado sesión de administrador exitosamente.</p>";
            }
            echo $mensaje_login_admin; // Este ya existía
?>
    <div class="container">
        <div class="main-content">
            <h2>Acceso de Administrador</h2>
            <?php echo $mensaje_login_admin; ?>
            <form action="<?php echo obtener_url_base(); ?>admin/index.php" method="post">
                <div>
                    <label for="username">Usuario:</label>
                    <input type="text" id="username" name="username" required value="admin">
                </div>
                <div>
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required value="condor123">
                </div>
                <button type="submit" name="login_admin">Ingresar como Administrador</button>
            </form>
            <p style="margin-top: 20px;"><a href="<?php echo obtener_url_base(); ?>index.php">&laquo; Volver al sitio principal</a></p>
        </div>
    </div>
<?php
    } // Fin del else para cliente_id
} // Fin del else para admin_logged_in

require_once __DIR__ . '/../includes/footer.php'; // Incluye el footer común
?>
