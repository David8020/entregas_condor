<?php
// checkout.php (Página para Confirmar y Procesar el Pedido)
require_once __DIR__ . '/includes/header.php'; // Incluye el header, session_start() y conexión $pdo

// Determinar qué navegación mostrar
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    require_once __DIR__ . '/includes/nav_admin.php';
    // Un admin no debería estar haciendo checkout de cliente. Podríamos redirigir.
} elseif (isset($_SESSION['cliente_id'])) {
    require_once __DIR__ . '/includes/nav_cliente.php';
} else {
    // Si el usuario no está logueado, redirigir al login.
    $_SESSION['mensaje_flash_login'] = "Debes iniciar sesión para finalizar tu compra.";
    $_SESSION['mensaje_flash_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "login.php?redir=checkout");
    exit;
}

// Verificar si el carrito está vacío
if (empty($_SESSION['carrito'])) {
    $_SESSION['mensaje_flash_catalogo'] = "Tu carrito está vacío. Añade productos antes de proceder al pago.";
    $_SESSION['mensaje_flash_catalogo_tipo'] = "warning";
    header("Location: " . obtener_url_base() . "catalogo.php");
    exit;
}

$mensaje_checkout = '';
$pedido_confirmado_id = null;
$productos_pedido_confirmado = [];
$total_pedido_confirmado = 0;

// Procesar la confirmación del pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pedido'])) {
    $idCliente = $_SESSION['cliente_id'];
    $carrito_actual = $_SESSION['carrito']; // Copia del carrito para procesar

    if (empty($carrito_actual)) {
        $mensaje_checkout = "<p class='error'>Error: Tu carrito está vacío.</p>";
    } else {
        $pdo->beginTransaction(); // Iniciar transacción
        try {
            $totalPedidoFinal = 0;
            $items_para_detalle = [];

            // 1. Pre-verificación de stock y cálculo de total (dentro de la transacción para consistencia)
            foreach ($carrito_actual as $id_producto => $item_carrito) {
                $cantidad_solicitada = $item_carrito['cantidad'];

                // Obtener precio y verificar stock (CON BLOQUEO PARA ACTUALIZACIÓN)
                $stmtProdInfo = $pdo->prepare(
                    "SELECT p.id_producto, p.nombre_producto, p.precio, i.cantidad_disponible 
                     FROM Producto p 
                     JOIN Inventario i ON p.id_producto = i.id_producto 
                     WHERE p.id_producto = :id_producto FOR UPDATE" // Bloquea la fila para esta transacción
                );
                $stmtProdInfo->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
                $stmtProdInfo->execute();
                $infoProducto = $stmtProdInfo->fetch(PDO::FETCH_ASSOC);

                if (!$infoProducto) {
                    throw new Exception("El producto '" . htmlspecialchars($id_producto) . "' ya no está disponible.");
                }
                if ($infoProducto['cantidad_disponible'] < $cantidad_solicitada) {
                    throw new Exception("Stock insuficiente para el producto: '" . htmlspecialchars($infoProducto['nombre_producto']) . "'. Disponible: {$infoProducto['cantidad_disponible']}, Solicitado: {$cantidad_solicitada}. Por favor, <a href='carrito.php'>actualiza tu carrito</a>.");
                }

                $subtotal = $infoProducto['precio'] * $cantidad_solicitada;
                $totalPedidoFinal += $subtotal;
                $items_para_detalle[] = [
                    'id_producto' => $id_producto,
                    'nombre_producto' => $infoProducto['nombre_producto'], // Para mostrar en confirmación
                    'cantidad' => $cantidad_solicitada,
                    'precio_unitario' => $infoProducto['precio'],
                    'subtotal' => $subtotal
                ];
            }
            
            if (empty($items_para_detalle)) { // Si el carrito quedó vacío después de verificar stock
                 throw new Exception("Todos los productos en tu carrito se han agotado. Por favor, <a href='catalogo.php'>vuelve al catálogo</a>.");
            }

            // 2. Crear la cabecera del Pedido
            $sqlPedido = "INSERT INTO Pedido (id_cliente, total, estado) VALUES (:id_cliente, :total, :estado)";
            $stmtPedido = $pdo->prepare($sqlPedido);
            $stmtPedido->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
            $stmtPedido->bindParam(':total', $totalPedidoFinal); // Total calculado
            $estado_pedido = 'Procesando'; // O 'Pendiente de Pago', etc.
            $stmtPedido->bindParam(':estado', $estado_pedido);
            $stmtPedido->execute();
            $idPedido = $pdo->lastInsertId();

            // 3. Insertar en DetallePedido y Actualizar Inventario
            foreach ($items_para_detalle as $item_detalle_info) {
                $sqlDetalle = "INSERT INTO DetallePedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
                               VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario, :subtotal)";
                $stmtDetalle = $pdo->prepare($sqlDetalle);
                $stmtDetalle->bindParam(':id_pedido', $idPedido, PDO::PARAM_INT);
                $stmtDetalle->bindParam(':id_producto', $item_detalle_info['id_producto'], PDO::PARAM_INT);
                $stmtDetalle->bindParam(':cantidad', $item_detalle_info['cantidad'], PDO::PARAM_INT);
                $stmtDetalle->bindParam(':precio_unitario', $item_detalle_info['precio_unitario']);
                $stmtDetalle->bindParam(':subtotal', $item_detalle_info['subtotal']);
                $stmtDetalle->execute();

                // Actualizar Inventario
                $sqlInvUpdate = "UPDATE Inventario SET cantidad_disponible = cantidad_disponible - :cantidad 
                                 WHERE id_producto = :id_producto";
                $stmtInvUpdate = $pdo->prepare($sqlInvUpdate);
                $stmtInvUpdate->bindParam(':cantidad', $item_detalle_info['cantidad'], PDO::PARAM_INT);
                $stmtInvUpdate->bindParam(':id_producto', $item_detalle_info['id_producto'], PDO::PARAM_INT);
                $stmtInvUpdate->execute();
            }

            $pdo->commit(); // Confirmar transacción si todo fue bien

            // Guardar información para mostrar en la confirmación
            $pedido_confirmado_id = $idPedido;
            $productos_pedido_confirmado = $items_para_detalle;
            $total_pedido_confirmado = $totalPedidoFinal;

            // Limpiar el carrito de la sesión
            unset($_SESSION['carrito']);
            
            registrarEnBitacora($pdo, $idCliente, 'Pedido Confirmado', json_encode(['id_pedido' => $idPedido, 'total' => $totalPedidoFinal]));
            $mensaje_checkout = "<p class='success'>¡Gracias por tu compra! Tu pedido ha sido confirmado.</p>";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack(); // Revertir transacción en caso de error
            }
            $mensaje_checkout = "<p class='error'>Error al procesar tu pedido: " . $e->getMessage() . "</p>";
            registrarEnBitacora($pdo, $idCliente, 'Error Checkout Pedido', $e->getMessage());
        }
    }
}

// Si no es un POST de confirmación, mostrar resumen del carrito para confirmar
$productos_resumen_carrito = [];
$total_general_resumen = 0;
if (!$pedido_confirmado_id && isset($_SESSION['carrito']) && !empty($_SESSION['carrito'])) {
    $ids_productos_carrito_resumen = array_keys($_SESSION['carrito']);
    if (!empty($ids_productos_carrito_resumen)) {
        $placeholders_resumen = implode(',', array_fill(0, count($ids_productos_carrito_resumen), '?'));
        $sql_resumen = "SELECT p.id_producto, p.nombre_producto, p.precio FROM Producto p WHERE p.id_producto IN ($placeholders_resumen)";
        $stmt_resumen = $pdo->prepare($sql_resumen);
        $stmt_resumen->execute($ids_productos_carrito_resumen);
        $productos_db_resumen = [];
        while ($row = $stmt_resumen->fetch(PDO::FETCH_ASSOC)) {
            $productos_db_resumen[$row['id_producto']] = $row;
        }

        foreach ($_SESSION['carrito'] as $id_prod => $item_c) {
            if (isset($productos_db_resumen[$id_prod])) {
                $prod_info = $productos_db_resumen[$id_prod];
                $cantidad_c = $item_c['cantidad'];
                $subtotal_c = $prod_info['precio'] * $cantidad_c;
                $total_general_resumen += $subtotal_c;
                $productos_resumen_carrito[] = [
                    'nombre_producto' => $prod_info['nombre_producto'],
                    'precio_unitario' => $prod_info['precio'],
                    'cantidad' => $cantidad_c,
                    'subtotal' => $subtotal_c
                ];
            }
        }
    }
     if (empty($productos_resumen_carrito)) { // Si el carrito se vació por alguna razón (ej. productos eliminados de BD)
        $_SESSION['mensaje_flash_catalogo'] = "Parece que los productos en tu carrito ya no están disponibles. Por favor, revisa el catálogo.";
        $_SESSION['mensaje_flash_catalogo_tipo'] = "warning";
        header("Location: " . obtener_url_base() . "catalogo.php");
        exit;
    }
}
?>

<div class="container">
    <div class="main-content">
        <h2>Finalizar Compra</h2>

        <?php echo $mensaje_checkout; ?>

        <?php if ($pedido_confirmado_id): ?>
            <h3>Detalles de tu Pedido Confirmado (Nº: <?php echo htmlspecialchars($pedido_confirmado_id); ?>)</h3>
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
                    <?php foreach ($productos_pedido_confirmado as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['nombre_producto']); ?></td>
                            <td><?php echo htmlspecialchars($item['cantidad']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($item['precio_unitario'], 2)); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($item['subtotal'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong>Total Pagado:</strong></td>
                        <td><strong>$<?php echo htmlspecialchars(number_format($total_pedido_confirmado, 2)); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            <p><a href="<?php echo obtener_url_base(); ?>catalogo.php"><button type="button">Seguir Comprando</button></a></p>
            <p><a href="<?php echo obtener_url_base(); ?>mis_pedidos.php"><button type="button" style="background-color: #28a745;">Ver Mis Pedidos</button></a></p>
        
        <?php elseif (!empty($productos_resumen_carrito)): ?>
            <h3>Resumen de tu Pedido</h3>
            <p>Por favor, revisa los productos en tu carrito antes de confirmar.</p>
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
                    <?php foreach ($productos_resumen_carrito as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['nombre_producto']); ?></td>
                            <td><?php echo htmlspecialchars($item['cantidad']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($item['precio_unitario'], 2)); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($item['subtotal'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong>Total a Pagar:</strong></td>
                        <td><strong>$<?php echo htmlspecialchars(number_format($total_general_resumen, 2)); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            <form action="checkout.php" method="post" style="text-align: right; margin-top: 20px;">
                <a href="<?php echo obtener_url_base(); ?>carrito.php" style="margin-right: 15px;"><button type="button" style="background-color: #6c757d;">Volver al Carrito</button></a>
                <button type="submit" name="confirmar_pedido">Confirmar Pedido y Pagar</button>
            </form>
        <?php elseif (empty($mensaje_checkout)) : // Si no hay pedido confirmado Y el carrito está vacío (y no hay mensaje de error previo) ?>
             <p>Tu carrito está vacío o los productos ya no están disponibles. <a href="<?php echo obtener_url_base(); ?>catalogo.php">Vuelve al catálogo</a>.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php'; // Incluye el footer común
?>
