<?php // includes/nav_cliente.php - Navegación para clientes logueados ?>
<nav>
    <div class="container">
        <ul>
            <li><a href="<?php echo obtener_url_base(); ?>index.php">Inicio</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>catalogo.php">Catálogo</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>mis_pedidos.php">Mis Pedidos</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>carrito.php">Carrito</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>logout.php">Logout (<?php echo htmlspecialchars($_SESSION['cliente_nombre'] ?? 'Cliente'); ?>)</a></li>
        </ul>
    </div>
</nav>
