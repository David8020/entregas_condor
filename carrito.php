<?php
// carrito.php (Página del Carrito de Compras)
require_once __DIR__ . '/includes/header.php'; // Incluye el header, session_start() y conexión $pdo

// Determinar qué navegación mostrar
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    require_once __DIR__ . '/includes/nav_admin.php';
} elseif (isset($_SESSION['cliente_id'])) {
    require_once __DIR__ . '/includes/nav_cliente.php';
} else {
    header("Location: " . obtener_url_base() . "login.php?redir=carrito");
    exit;
}

$mensaje_accion_carrito = '';
// Recoger mensajes flash si existen (después de una redirección POST)
if (isset($_SESSION['mensaje_flash_carrito'])) {
    $mensaje_accion_carrito = "<p class='" . ($_SESSION['mensaje_flash_carrito_tipo'] ?? 'success') . "'>" . $_SESSION['mensaje_flash_carrito'] . "</p>";
    unset($_SESSION['mensaje_flash_carrito'], $_SESSION['mensaje_flash_carrito_tipo']); // Limpiar mensaje
}


// Procesar acciones del carrito: actualizar cantidad o eliminar producto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion_realizada = false;
    if (isset($_POST['actualizar_cantidad']) && isset($_POST['id_producto_carrito'])) {
        $id_producto_actualizar = filter_input(INPUT_POST, 'id_producto_carrito', FILTER_VALIDATE_INT);
        $nueva_cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);

        if ($id_producto_actualizar && isset($_SESSION['carrito'][$id_producto_actualizar])) {
            if ($nueva_cantidad !== null && $nueva_cantidad > 0) {
                $stmtStock = $pdo->prepare("SELECT cantidad_disponible FROM Inventario WHERE id_producto = :id_producto");
                $stmtStock->bindParam(':id_producto', $id_producto_actualizar, PDO::PARAM_INT);
                $stmtStock->execute();
                $stockInfo = $stmtStock->fetch(PDO::FETCH_ASSOC);

                if ($stockInfo && $stockInfo['cantidad_disponible'] >= $nueva_cantidad) {
                    $_SESSION['carrito'][$id_producto_actualizar]['cantidad'] = $nueva_cantidad;
                    $_SESSION['mensaje_flash_carrito'] = 'Cantidad actualizada.';
                    $_SESSION['mensaje_flash_carrito_tipo'] = 'success';
                } else {
                    $_SESSION['mensaje_flash_carrito'] = "Stock insuficiente para la cantidad solicitada. Máximo disponible: " . ($stockInfo['cantidad_disponible'] ?? 0);
                    $_SESSION['mensaje_flash_carrito_tipo'] = 'error';
                }
            } elseif ($nueva_cantidad !== null && $nueva_cantidad <= 0) {
                unset($_SESSION['carrito'][$id_producto_actualizar]);
                $_SESSION['mensaje_flash_carrito'] = 'Producto eliminado del carrito.';
                $_SESSION['mensaje_flash_carrito_tipo'] = 'success';
            }
            $accion_realizada = true;
        }
    } elseif (isset($_POST['eliminar_producto']) && isset($_POST['id_producto_carrito'])) {
        $id_producto_eliminar = filter_input(INPUT_POST, 'id_producto_carrito', FILTER_VALIDATE_INT);
        if ($id_producto_eliminar && isset($_SESSION['carrito'][$id_producto_eliminar])) {
            unset($_SESSION['carrito'][$id_producto_eliminar]);
            $_SESSION['mensaje_flash_carrito'] = 'Producto eliminado del carrito.';
            $_SESSION['mensaje_flash_carrito_tipo'] = 'success';
            $accion_realizada = true;
        }
    }
    
    if ($accion_realizada) {
        // Redirigir para evitar reenvío de formulario y para que los mensajes flash se muestren correctamente
        header("Location: " . obtener_url_base() . "carrito.php");
        exit;
    }
}

$productos_en_carrito_detalles = [];
$total_general = 0;

if (isset($_SESSION['carrito']) && !empty($_SESSION['carrito'])) {
    $ids_productos_carrito = array_keys($_SESSION['carrito']);
    
    if (!empty($ids_productos_carrito)) {
        $placeholders = implode(',', array_fill(0, count($ids_productos_carrito), '?'));
        
        // Consulta para obtener la información de todos los productos en el carrito
        $sql_productos = "SELECT p.id_producto, p.nombre_producto, p.precio, i.cantidad_disponible 
                          FROM Producto p
                          LEFT JOIN Inventario i ON p.id_producto = i.id_producto
                          WHERE p.id_producto IN ($placeholders)";
        $stmt_productos = $pdo->prepare($sql_productos);
        $stmt_productos->execute($ids_productos_carrito);
        
        // Crear un array indexado por id_producto para fácil acceso
        $productos_info_db = [];
        while ($row = $stmt_productos->fetch(PDO::FETCH_ASSOC)) {
            $productos_info_db[$row['id_producto']] = $row;
        }

        // Iterar sobre el carrito en sesión y construir el array de detalles
        foreach ($_SESSION['carrito'] as $id_producto => $item_carrito) {
            if (isset($productos_info_db[$id_producto])) {
                $producto_info = $productos_info_db[$id_producto];
                $cantidad_en_carrito = $item_carrito['cantidad'];
                $stock_actual = (int)($producto_info['cantidad_disponible'] ?? 0);

                // Ajustar cantidad si el stock es menor o eliminar si es 0
                if ($cantidad_en_carrito > $stock_actual) {
                    if ($stock_actual > 0) {
                        $_SESSION['carrito'][$id_producto]['cantidad'] = $stock_actual;
                        $cantidad_en_carrito = $stock_actual;
                        if (!isset($_SESSION['mensaje_flash_carrito_ajuste'])) { // Evitar múltiples mensajes de ajuste
                           $_SESSION['mensaje_flash_carrito_ajuste'] = "Algunas cantidades se ajustaron por stock disponible.";
                        }
                    } else {
                        unset($_SESSION['carrito'][$id_producto]); // Eliminar si ya no hay stock
                         if (!isset($_SESSION['mensaje_flash_carrito_ajuste'])) {
                           $_SESSION['mensaje_flash_carrito_ajuste'] = "Algunos productos fueron eliminados por falta de stock.";
                        }
                        continue; // Saltar al siguiente item del carrito
                    }
                }
                
                // Si después del ajuste, el producto sigue en el carrito
                if(isset($_SESSION['carrito'][$id_producto])) {
                    $subtotal = $producto_info['precio'] * $cantidad_en_carrito;
                    $total_general += $subtotal;

                    $productos_en_carrito_detalles[] = [
                        'id_producto' => $id_producto,
                        'nombre_producto' => $producto_info['nombre_producto'],
                        'precio_unitario' => $producto_info['precio'],
                        'cantidad' => $cantidad_en_carrito,
                        'subtotal' => $subtotal,
                        'stock_disponible' => $stock_actual
                    ];
                }
            } else {
                // Si un producto en el carrito ya no existe en la BD, eliminarlo del carrito
                unset($_SESSION['carrito'][$id_producto]);
                 if (!isset($_SESSION['mensaje_flash_carrito_ajuste'])) {
                    $_SESSION['mensaje_flash_carrito_ajuste'] = "Un producto fue eliminado del carrito porque ya no está disponible.";
                }
            }
        }
         // Si hubo ajustes, mostrar el mensaje de ajuste
        if (isset($_SESSION['mensaje_flash_carrito_ajuste']) && empty($mensaje_accion_carrito)) {
            $mensaje_accion_carrito = "<p class='warning'>" . $_SESSION['mensaje_flash_carrito_ajuste'] . "</p>";
            unset($_SESSION['mensaje_flash_carrito_ajuste']);
        }
    }
}
?>

<div class="container">
    <div class="main-content">
        <h2>Tu Carrito de Compras</h2>

        <?php echo $mensaje_accion_carrito; ?>

        <?php if (!empty($productos_en_carrito_detalles)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio Unitario</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos_en_carrito_detalles as $item_detalle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item_detalle['nombre_producto']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($item_detalle['precio_unitario'], 2)); ?></td>
                            <td>
                                <form action="carrito.php" method="post" style="display: flex; align-items: center;">
                                    <input type="hidden" name="id_producto_carrito" value="<?php echo $item_detalle['id_producto']; ?>">
                                    <input type="number" name="cantidad" value="<?php echo $item_detalle['cantidad']; ?>" 
                                           min="1" 
                                           max="<?php echo $item_detalle['stock_disponible'] > 0 ? $item_detalle['stock_disponible'] : 1; ?>" 
                                           style="width: 60px; margin-right: 5px;" 
                                           <?php if ($item_detalle['stock_disponible'] == 0) echo 'disabled'; ?>> 
                                    <button type="submit" name="actualizar_cantidad" style="padding: 5px 8px; font-size: 0.9em;" <?php if ($item_detalle['stock_disponible'] == 0) echo 'disabled'; ?>>Actualizar</button>
                                </form>
                            </td>
                            <td>$<?php echo htmlspecialchars(number_format($item_detalle['subtotal'], 2)); ?></td>
                            <td>
                                <form action="carrito.php" method="post" style="display: inline;">
                                    <input type="hidden" name="id_producto_carrito" value="<?php echo $item_detalle['id_producto']; ?>">
                                    <button type="submit" name="eliminar_producto" onclick="return confirm('¿Estás seguro de que quieres eliminar este producto del carrito?');" style="background-color: #dc3545; padding: 5px 8px; font-size: 0.9em;">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong>Total General:</strong></td>
                        <td colspan="2"><strong>$<?php echo htmlspecialchars(number_format($total_general, 2)); ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <div style="text-align: right; margin-top: 20px;">
                <a href="<?php echo obtener_url_base(); ?>catalogo.php" style="margin-right: 15px;"><button type="button" style="background-color: #6c757d;">Seguir Comprando</button></a>
                <?php if (!empty($_SESSION['carrito']) && $total_general > 0): // Solo mostrar si el carrito (después de ajustes) no está vacío y hay algo que pagar ?>
                <a href="<?php echo obtener_url_base(); ?>checkout.php"><button type="button">Proceder al Pago</button></a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <p>Tu carrito de compras está vacío.</p>
            <p><a href="<?php echo obtener_url_base(); ?>catalogo.php">Haz clic aquí para ver nuestros productos.</a></p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php'; // Incluye el footer común
?>
