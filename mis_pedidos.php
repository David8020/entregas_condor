<?php
// mis_pedidos.php (Página para que el cliente vea su historial de pedidos)
require_once __DIR__ . '/includes/header.php'; // Incluye el header, session_start() y conexión $pdo

// Determinar qué navegación mostrar y verificar login
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Un admin podría tener su propia vista de "todos los pedidos" en la sección admin,
    // pero no esta página de "mis pedidos" de cliente.
    require_once __DIR__ . '/includes/nav_admin.php';
    // Podríamos redirigir al dashboard de admin o mostrar un mensaje.
    // Por ahora, si un admin llega aquí, no se mostrarán pedidos de cliente.
} elseif (isset($_SESSION['cliente_id'])) {
    require_once __DIR__ . '/includes/nav_cliente.php';
} else {
    // Si el usuario no está logueado, redirigir al login.
    $_SESSION['mensaje_flash_login'] = "Debes iniciar sesión para ver tu historial de pedidos.";
    $_SESSION['mensaje_flash_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "login.php?redir=mis_pedidos");
    exit;
}

$pedidos_cliente = [];
$mensaje_error_pedidos = '';

if (isset($_SESSION['cliente_id'])) {
    $idCliente = $_SESSION['cliente_id'];
    try {
        $sql = "SELECT id_pedido, fecha_pedido, total, estado 
                FROM Pedido 
                WHERE id_cliente = :id_cliente 
                ORDER BY fecha_pedido DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
        $stmt->execute();
        $pedidos_cliente = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $mensaje_error_pedidos = "Error al cargar tu historial de pedidos. Por favor, inténtalo más tarde.";
        // En producción, loguear el error detallado.
        error_log("Error PDO en mis_pedidos.php para cliente ID {$idCliente}: " . $e->getMessage());
        registrarEnBitacora($pdo, $idCliente, 'Error Carga Mis Pedidos', $e->getMessage());
    }
}
?>

<div class="container">
    <div class="main-content">
        <h2>Mis Pedidos</h2>

        <?php if ($mensaje_error_pedidos): ?>
            <p class="error"><?php echo $mensaje_error_pedidos; ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['cliente_id'])): ?>
            <?php if (!empty($pedidos_cliente)): ?>
                <p>Aquí puedes ver un resumen de los pedidos que has realizado.</p>
                <table>
                    <thead>
                        <tr>
                            <th>Nº Pedido</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th> </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos_cliente as $pedido): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pedido['id_pedido']); ?></td>
                                <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($pedido['fecha_pedido']))); ?></td>
                                <td>$<?php echo htmlspecialchars(number_format($pedido['total'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($pedido['estado']); ?></td>
                                <td>
                                    <button type="button" onclick="alert('Funcionalidad de ver detalle aún no implementada.');" style="font-size:0.9em; padding: 5px 8px;">Ver Detalle</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (!$mensaje_error_pedidos): ?>
                <p>Aún no has realizado ningún pedido.</p>
                <p><a href="<?php echo obtener_url_base(); ?>catalogo.php">¡Explora nuestro catálogo y haz tu primer pedido!</a></p>
            <?php endif; ?>
        <?php else: ?>
            <p class="error">No se pudo identificar al cliente para mostrar los pedidos.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php'; // Incluye el footer común
?>
