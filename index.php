<?php
// index.php (Página Principal)
require_once __DIR__ . '/includes/header.php'; // Incluye el header común

// --- INICIO DE DEPURACIÓN PARA SELECCIÓN DE NAVEGACIÓN ---
echo "<div class='container' style='background-color: #ffc; border: 1px solid #dda; padding: 10px; margin-bottom:10px;'><strong>DEBUG NAV:</strong> ";
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    echo "Condición para NAV_ADMIN cumplida. Cargando nav_admin.php...";
    require_once __DIR__ . '/includes/nav_admin.php';
} elseif (isset($_SESSION['cliente_id'])) {
    echo "Condición para NAV_CLIENTE cumplida. Cargando nav_cliente.php...";
    require_once __DIR__ . '/includes/nav_cliente.php';
} else {
    echo "Ninguna condición de sesión cumplida (ni admin, ni cliente). Cargando nav_visitante.php...";
    require_once __DIR__ . '/includes/nav_visitante.php';
}
echo "</div>";
// --- FIN DE DEPURACIÓN PARA SELECCIÓN DE NAVEGACIÓN ---


// Mostrar mensaje de logout si viene por GET
if (isset($_GET['mensaje_logout']) && $_GET['mensaje_logout'] == '1') {
    echo "<div class='container'><p class='success'>Has cerrado sesión exitosamente.</p></div>";
}

?>
<div class="container">
    <div class="main-content">
        <h2>Bienvenido a Entregas Cóndor Ltda.</h2>
        <p>Su tienda de confianza para productos de aseo de la mejor calidad. Explore nuestro catálogo y realice sus pedidos de forma fácil y segura.</p>
        
        <h3>Productos Destacados</h3>
        <div class="product-list">
            <?php
            try {
                $stmt = $pdo->query("SELECT 
                                        p.id_producto, p.nombre_producto, p.descripcion, p.precio, 
                                        pr.nombre_proveedor, 
                                        i.cantidad_disponible
                                    FROM Producto p
                                    JOIN Proveedor pr ON p.id_proveedor = pr.id_proveedor
                                    LEFT JOIN Inventario i ON p.id_producto = i.id_producto
                                    WHERE (i.cantidad_disponible IS NULL OR i.cantidad_disponible > 0)
                                    ORDER BY p.fecha_creacion DESC
                                    LIMIT 3");
                $productos_destacados = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($productos_destacados) {
                    foreach ($productos_destacados as $producto) {
                        echo "<div class='product-item'>";
                        echo "<h3>" . htmlspecialchars($producto['nombre_producto']) . "</h3>";
                        echo "<p>" . nl2br(htmlspecialchars($producto['descripcion'] ?? 'Sin descripción.')) . "</p>";
                        echo "<p><strong>Precio:</strong> $" . htmlspecialchars(number_format($producto['precio'], 2)) . "</p>";
                        echo "<p><strong>Proveedor:</strong> " . htmlspecialchars($producto['nombre_proveedor']) . "</p>";
                        echo "<p><strong>Disponibles:</strong> " . htmlspecialchars($producto['cantidad_disponible'] ?? 'Consultar') . "</p>";
                        echo "<a href='" . obtener_url_base() . "catalogo.php#producto-" . htmlspecialchars($producto['id_producto']) . "'><button>Ver en Catálogo</button></a>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>No hay productos destacados en este momento.</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>Error al cargar productos destacados.</p>";
                 error_log("Error PDO en index.php (productos destacados): " . $e->getMessage());
            }
            ?>
        </div>

        <?php if (!isset($_SESSION['cliente_id']) && !(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)): ?>
            <p>Para realizar una compra, por favor <a href="<?php echo obtener_url_base(); ?>registro.php">regístrese</a> o <a href="<?php echo obtener_url_base(); ?>login.php">inicie sesión</a>.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php'; // Incluye el footer común
?>
