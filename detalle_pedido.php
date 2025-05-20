<?php
// detalle_pedido.php (Página para que el cliente vea el detalle de un pedido específico)
require_once __DIR__ . '/includes/header.php'; // Incluye el header, session_start() y conexión $pdo

// Verificar login de cliente
if (!isset($_SESSION['cliente_id'])) {
    $_SESSION['mensaje_flash_login'] = "Debes iniciar sesión para ver los detalles de tus pedidos.";
    $_SESSION['mensaje_flash_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "login.php?redir=mis_pedidos");
    exit;
}

require_once __DIR__ . '/includes/nav_cliente.php'; // Navegación de cliente

$id_pedido_ver = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$pedido_info = null;
$detalles_del_pedido = [];
$mensaje_error_detalle = '';
$mensaje_accion_detalle = ''; // Para mensajes flash de cancelación

// Mostrar mensajes flash si existen (ej. de cancelar_pedido.php)
if (isset($_SESSION['mensaje_flash_detalle_pedido'])) {
    $mensaje_accion_detalle = "<p class='" . htmlspecialchars($_SESSION['mensaje_flash_detalle_pedido_tipo'] ?? 'success') . "'>" . htmlspecialchars($_SESSION['mensaje_flash_detalle_pedido']) . "</p>";
    unset($_SESSION['mensaje_flash_detalle_pedido'], $_SESSION['mensaje_flash_detalle_pedido_tipo']);
}


if (!$id_pedido_ver) {
    $mensaje_error_detalle = "No se especificó un ID de pedido válido.";
} else {
    $idCliente = $_SESSION['cliente_id'];
    try {
        // 1. Obtener la información principal del pedido y verificar que pertenece al cliente logueado
        $sql_pedido = "SELECT id_pedido, fecha_pedido, total, estado, id_cliente 
                       FROM Pedido 
                       WHERE id_pedido = :id_pedido";
        $stmt_pedido = $pdo->prepare($sql_pedido);
        $stmt_pedido->bindParam(':id_pedido', $id_pedido_ver, PDO::PARAM_INT);
        $stmt_pedido->execute();
        $pedido_info = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

        if (!$pedido_info) {
            $mensaje_error_detalle = "Pedido no encontrado.";
        } elseif ($pedido_info['id_cliente'] != $idCliente) {
            $mensaje_error_detalle = "No tienes permiso para ver este pedido.";
            $pedido_info = null; 
            registrarEnBitacora($pdo, $idCliente, 'Intento Acceso Pedido Ajeno', json_encode(['id_pedido_intentado' => $id_pedido_ver]));
        } else {
            // 2. Si el pedido es del cliente, obtener los detalles (productos)
            $sql_detalles = "SELECT dp.cantidad, dp.precio_unitario, dp.subtotal, 
                                    p.nombre_producto 
                             FROM DetallePedido dp
                             JOIN Producto p ON dp.id_producto = p.id_producto
                             WHERE dp.id_pedido = :id_pedido";
            $stmt_detalles = $pdo->prepare($sql_detalles);
            $stmt_detalles->bindParam(':id_pedido', $id_pedido_ver, PDO::PARAM_INT);
            $stmt_detalles->execute();
            $detalles_del_pedido = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

            if (empty($detalles_del_pedido) && $pedido_info['total'] > 0) {
                 $mensaje_error_detalle = "No se encontraron los productos para este pedido, aunque el pedido existe.";
            }
        }

    } catch (PDOException $e) {
        $mensaje_error_detalle = "Error al cargar los detalles del pedido. Por favor, inténtalo más tarde.";
        error_log("Error PDO en detalle_pedido.php para cliente ID {$idCliente}, pedido ID {$id_pedido_ver}: " . $e->getMessage());
        registrarEnBitacora($pdo, $idCliente, 'Error Carga Detalle Pedido Cliente', $e->getMessage());
    }
}
?>

<div class="container">
    <div class="main-content">
        <h2>Detalle del Pedido</h2>

        <?php echo $mensaje_accion_detalle; // Mostrar mensajes de cancelación ?>
        <?php if ($mensaje_error_detalle): ?>
            <p class="error"><?php echo $mensaje_error_detalle; ?></p>
            <p style="margin-top: 20px; display: flex; gap: 10px;">
                <a href="<?php echo obtener_url_base(); ?>mis_pedidos.php"><button type="button">Volver a Mis Pedidos</button></a>
                <?php 
                // Permitir cancelación solo si el estado es 'Pendiente' o 'Procesando'
                if ($pedido_info && ($pedido_info['estado'] === 'Pendiente' || $pedido_info['estado'] === 'Procesando')): 
                ?>
                    <form action="<?php echo obtener_url_base(); ?>cancelar_pedido.php" method="post" style="margin:0;">
                        <input type="hidden" name="id_pedido_cancelar" value="<?php echo htmlspecialchars($pedido_info['id_pedido']); ?>">
                        <button type="submit" name="confirmar_cancelacion" 
                                onclick="return confirm('¿Estás seguro de que quieres cancelar este pedido? Esta acción no se puede deshacer.');"
                                style="background-color: #dc3545;">Cancelar Pedido</button>
                    </form>
                <?php endif; ?>
            </p>
        <?php elseif ($pedido_info && (!empty($detalles_del_pedido) || $pedido_info['total'] == 0) ): // Modificado para mostrar info si hay detalles o si el total es 0 (pedido vacío) ?>
            <h3>Pedido Nº: <?php echo htmlspecialchars($pedido_info['id_pedido']); ?></h3>
            <p><strong>Fecha:</strong> <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($pedido_info['fecha_pedido']))); ?></p>
            <p><strong>Estado:</strong> <?php echo htmlspecialchars(ucfirst($pedido_info['estado'])); ?></p>
            <p><strong>Total del Pedido:</strong> $<?php echo htmlspecialchars(number_format($pedido_info['total'], 2)); ?></p>
            
            <?php if (!empty($detalles_del_pedido)): ?>
                <h4>Productos en este pedido:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles_del_pedido as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['nombre_producto']); ?></td>
                                <td><?php echo htmlspecialchars($item['cantidad']); ?></td>
                                <td>$<?php echo htmlspecialchars(number_format($item['precio_unitario'], 2)); ?></td>
                                <td>$<?php echo htmlspecialchars(number_format($item['subtotal'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($pedido_info['total'] == 0): ?>
                 <p>Este pedido no contiene productos (posiblemente fue un pedido vacío o cancelado antes de añadir ítems).</p>
            <?php endif; ?>

            <p style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
                <a href="<?php echo obtener_url_base(); ?>mis_pedidos.php"><button type="button">Volver a Mis Pedidos</button></a>
                <?php 
                // Permitir cancelación solo si el estado es 'Pendiente' o 'Procesando'
                if ($pedido_info && ($pedido_info['estado'] === 'Pendiente' || $pedido_info['estado'] === 'Procesando')): 
                ?>
                    <form action="<?php echo obtener_url_base(); ?>cancelar_pedido.php" method="post" style="margin:0;">
                        <input type="hidden" name="id_pedido_cancelar" value="<?php echo htmlspecialchars($pedido_info['id_pedido']); ?>">
                        <button type="submit" name="confirmar_cancelacion" 
                                onclick="return confirm('¿Estás seguro de que quieres cancelar este pedido? Esta acción no se puede deshacer y el stock no se repondrá automáticamente.');"
                                style="background-color: #dc3545;">Cancelar Pedido</button>
                    </form>
                <?php endif; ?>
            </p>
        <?php else: ?>
             <p>No se pudo cargar la información del pedido. <a href="<?php echo obtener_url_base(); ?>mis_pedidos.php">Intenta volver a tu lista de pedidos</a>.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php'; // Incluye el footer común
?>
