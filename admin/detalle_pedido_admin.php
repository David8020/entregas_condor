<?php
// admin/detalle_pedido_admin.php (Página para que el Admin vea el detalle de un pedido específico)
require_once __DIR__ . '/../includes/header.php'; // Accede al header general

// Verificar si el admin está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['mensaje_flash_admin_login'] = "Debes iniciar sesión como administrador para acceder a esta página.";
    $_SESSION['mensaje_flash_admin_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/index.php");
    exit;
}

require_once __DIR__ . '/../includes/nav_admin.php'; // Navegación de Admin

$id_pedido_ver = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$pedido_info = null;
$detalles_del_pedido = [];
$cliente_del_pedido = null;
$mensaje_error_detalle_admin = '';

if (!$id_pedido_ver) {
    $mensaje_error_detalle_admin = "No se especificó un ID de pedido válido.";
} else {
    try {
        // 1. Obtener la información principal del pedido
        $sql_pedido = "SELECT p.id_pedido, p.fecha_pedido, p.total, p.estado, p.id_cliente,
                              c.nombre AS nombre_cliente, c.apellidos AS apellidos_cliente, c.correo AS correo_cliente,
                              c.telefono AS telefono_cliente, c.direccion AS direccion_cliente
                       FROM Pedido p
                       JOIN Cliente c ON p.id_cliente = c.id_cliente
                       WHERE p.id_pedido = :id_pedido";
        $stmt_pedido = $pdo->prepare($sql_pedido);
        $stmt_pedido->bindParam(':id_pedido', $id_pedido_ver, PDO::PARAM_INT);
        $stmt_pedido->execute();
        $pedido_info = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

        if (!$pedido_info) {
            $mensaje_error_detalle_admin = "Pedido Nº{$id_pedido_ver} no encontrado.";
        } else {
            // 2. Obtener los detalles (productos) del pedido
            $sql_detalles = "SELECT dp.cantidad, dp.precio_unitario, dp.subtotal, 
                                    p.id_producto, p.nombre_producto 
                             FROM DetallePedido dp
                             JOIN Producto p ON dp.id_producto = p.id_producto
                             WHERE dp.id_pedido = :id_pedido";
            $stmt_detalles = $pdo->prepare($sql_detalles);
            $stmt_detalles->bindParam(':id_pedido', $id_pedido_ver, PDO::PARAM_INT);
            $stmt_detalles->execute();
            $detalles_del_pedido = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

            if (empty($detalles_del_pedido) && $pedido_info['total'] > 0) {
                 $mensaje_error_detalle_admin = "No se encontraron los productos para este pedido, aunque el pedido existe.";
            }
        }

    } catch (PDOException $e) {
        $mensaje_error_detalle_admin = "Error al cargar los detalles del pedido: " . $e->getMessage();
        error_log("Error PDO en admin/detalle_pedido_admin.php para pedido ID {$id_pedido_ver}: " . $e->getMessage());
        registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Error Carga Detalle Pedido Admin', $e->getMessage());
    }
}
?>

<div class="container">
    <div class="main-content admin-section">
        <h2>Detalle del Pedido (Admin)</h2>

        <?php if ($mensaje_error_detalle_admin): ?>
            <p class="error"><?php echo $mensaje_error_detalle_admin; ?></p>
            <p><a href="<?php echo obtener_url_base(); ?>admin/pedidos.php"><button type="button">Volver a Lista de Pedidos</button></a></p>
        <?php elseif ($pedido_info): ?>
            <h3>Pedido Nº: <?php echo htmlspecialchars($pedido_info['id_pedido']); ?></h3>
            <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                <div style="width: 48%;">
                    <h4>Información del Pedido</h4>
                    <p><strong>Fecha:</strong> <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($pedido_info['fecha_pedido']))); ?></p>
                    <p><strong>Estado Actual:</strong> <?php echo htmlspecialchars(ucfirst($pedido_info['estado'])); ?></p>
                    <p><strong>Total del Pedido:</strong> $<?php echo htmlspecialchars(number_format($pedido_info['total'], 2)); ?></p>
                </div>
                <div style="width: 48%; border-left: 1px solid #ccc; padding-left: 15px;">
                    <h4>Información del Cliente</h4>
                    <p><strong>ID Cliente:</strong> <?php echo htmlspecialchars($pedido_info['id_cliente']); ?></p>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pedido_info['nombre_cliente'] . ' ' . $pedido_info['apellidos_cliente']); ?></p>
                    <p><strong>Correo:</strong> <?php echo htmlspecialchars($pedido_info['correo_cliente']); ?></p>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($pedido_info['telefono_cliente'] ?? 'No proporcionado'); ?></p>
                    <p><strong>Dirección de Envío:</strong> <?php echo htmlspecialchars($pedido_info['direccion_cliente'] ?? 'No proporcionada'); ?></p>
                </div>
            </div>
            
            <h4>Productos en este pedido:</h4>
            <?php if (!empty($detalles_del_pedido)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Producto</th>
                            <th>Nombre Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles_del_pedido as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['id_producto']); ?></td>
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
            <?php else: ?>
                <p class="warning">No se encontraron detalles de productos para este pedido.</p>
            <?php endif; ?>

            <p style="margin-top: 20px;">
                <a href="<?php echo obtener_url_base(); ?>admin/pedidos.php"><button type="button">Volver a Lista de Pedidos</button></a>
            </p>
        
        <?php else: ?>
             <p>No se pudo cargar la información del pedido. <a href="<?php echo obtener_url_base(); ?>admin/pedidos.php">Intenta volver a la lista de pedidos</a>.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
