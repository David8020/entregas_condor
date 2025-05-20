<?php
// catalogo.php (Página del Catálogo de Productos)
require_once __DIR__ . '/includes/header.php'; // Incluye el header, session_start() y conexión $pdo

// Determinar qué navegación mostrar
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    require_once __DIR__ . '/includes/nav_admin.php';
} elseif (isset($_SESSION['cliente_id'])) {
    require_once __DIR__ . '/includes/nav_cliente.php';
} else {
    require_once __DIR__ . '/includes/nav_visitante.php';
}

// Procesamiento para añadir al carrito (lo haremos más adelante)
$mensaje_carrito = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_al_carrito'])) {
    if (!isset($_SESSION['cliente_id'])) {
        // Si el usuario no está logueado, redirigir al login.
        // Podríamos guardar el intento de añadir al carrito para después del login.
        header("Location: " . obtener_url_base() . "login.php?redir=catalogo&accion=agregar&producto_id=" . $_POST['id_producto']);
        exit;
    }

    $id_producto_carrito = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $cantidad_carrito = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);

    if ($id_producto_carrito && $cantidad_carrito && $cantidad_carrito > 0) {
        // Inicializar el carrito en la sesión si no existe
        if (!isset($_SESSION['carrito'])) {
            $_SESSION['carrito'] = [];
        }

        // Verificar stock antes de añadir (simplificado, una verificación más robusta se haría en el checkout)
        $stmtStock = $pdo->prepare("SELECT cantidad_disponible FROM Inventario WHERE id_producto = :id_producto");
        $stmtStock->bindParam(':id_producto', $id_producto_carrito, PDO::PARAM_INT);
        $stmtStock->execute();
        $stockInfo = $stmtStock->fetch(PDO::FETCH_ASSOC);

        if ($stockInfo && $stockInfo['cantidad_disponible'] >= $cantidad_carrito) {
            // Si el producto ya está en el carrito, actualizar cantidad
            if (isset($_SESSION['carrito'][$id_producto_carrito])) {
                // No permitir que la cantidad en carrito exceda el stock
                if (($_SESSION['carrito'][$id_producto_carrito]['cantidad'] + $cantidad_carrito) <= $stockInfo['cantidad_disponible']) {
                     $_SESSION['carrito'][$id_producto_carrito]['cantidad'] += $cantidad_carrito;
                     $mensaje_carrito = "<p class='success'>Cantidad actualizada en el carrito.</p>";
                } else {
                    $mensaje_carrito = "<p class='error'>No puedes añadir más unidades de este producto, stock insuficiente.</p>";
                }
            } else {
                // Añadir nuevo producto al carrito
                 $_SESSION['carrito'][$id_producto_carrito] = [
                    'id_producto' => $id_producto_carrito,
                    'cantidad' => $cantidad_carrito
                    // Podríamos guardar nombre y precio aquí también para mostrar en el carrito fácilmente,
                    // pero es mejor obtenerlos frescos de la BD al mostrar el carrito para evitar precios desactualizados.
                ];
                $mensaje_carrito = "<p class='success'>Producto añadido al carrito.</p>";
            }
        } else {
            $mensaje_carrito = "<p class='error'>Stock insuficiente para añadir el producto al carrito.</p>";
        }
    } else {
        $mensaje_carrito = "<p class='error'>Error al añadir producto al carrito. Datos inválidos.</p>";
    }
}
?>

<div class="container">
    <div class="main-content">
        <h2>Nuestro Catálogo de Productos</h2>
        <p>Explora nuestra selección de productos de aseo de alta calidad.</p>

        <?php echo $mensaje_carrito; // Mostrar mensajes relacionados con el carrito ?>

        <div class="product-list">
            <?php
            try {
                // Consultar todos los productos disponibles
                // Unir con Inventario para obtener cantidad_disponible y Proveedor para nombre_proveedor
                $stmt = $pdo->query("SELECT 
                                        p.id_producto, p.nombre_producto, p.descripcion, p.precio, 
                                        pr.nombre_proveedor, 
                                        i.cantidad_disponible
                                    FROM Producto p
                                    JOIN Proveedor pr ON p.id_proveedor = pr.id_proveedor
                                    LEFT JOIN Inventario i ON p.id_producto = i.id_producto
                                    ORDER BY p.nombre_producto ASC");
                
                $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($productos) {
                    foreach ($productos as $producto) {
                        echo "<div class='product-item' id='producto-" . htmlspecialchars($producto['id_producto']) . "'>";
                        echo "<h3>" . htmlspecialchars($producto['nombre_producto']) . "</h3>";
                        echo "<p>" . nl2br(htmlspecialchars($producto['descripcion'] ?? 'Sin descripción detallada.')) . "</p>";
                        echo "<p><strong>Precio:</strong> $" . htmlspecialchars(number_format($producto['precio'], 2)) . "</p>";
                        echo "<p><strong>Proveedor:</strong> " . htmlspecialchars($producto['nombre_proveedor']) . "</p>";
                        
                        $stock_disponible = (int)($producto['cantidad_disponible'] ?? 0);
                        echo "<p><strong>Disponibles:</strong> " . $stock_disponible . "</p>";

                        // Formulario para añadir al carrito
                        if ($stock_disponible > 0) {
                            echo "<form action='catalogo.php#producto-" . htmlspecialchars($producto['id_producto']) . "' method='post'>"; // Enviar al mismo catalogo para mostrar mensaje
                            echo "<input type='hidden' name='id_producto' value='" . htmlspecialchars($producto['id_producto']) . "'>";
                            echo "<div>";
                            echo "<label for='cantidad-" . htmlspecialchars($producto['id_producto']) . "'>Cantidad:</label>";
                            echo "<input type='number' id='cantidad-" . htmlspecialchars($producto['id_producto']) . "' name='cantidad' value='1' min='1' max='" . $stock_disponible . "' style='width: 70px; margin-right: 10px;'>";
                            echo "<button type='submit' name='agregar_al_carrito'>Añadir al Carrito</button>";
                            echo "</div>";
                            echo "</form>";
                        } else {
                            echo "<p style='color: red;'>Producto agotado temporalmente.</p>";
                        }
                        echo "</div>";
                    }
                } else {
                    echo "<p>No hay productos disponibles en el catálogo en este momento.</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>Error al cargar el catálogo de productos: " . $e->getMessage() . "</p>";
                // En producción, loguear este error en lugar de mostrarlo al usuario.
                error_log("Error PDO en catalogo.php: " . $e->getMessage());
            }
            ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php'; // Incluye el footer común
?>
