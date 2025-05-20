<?php // includes/nav_admin.php - Navegación para administradores logueados ?>
<nav>
    <div class="container">
        <ul>
            <li><a href="<?php echo obtener_url_base(); ?>admin/index.php">Dashboard Admin</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>admin/productos.php">Gestionar Productos</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>admin/inventario.php">Ver Inventario</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>admin/pedidos.php">Ver Pedidos</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>admin/proveedores.php">Gestionar Proveedores</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>admin/logout_admin.php">Logout Admin</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>index.php" target="_blank">Ver Sitio Cliente</a></li>
        </ul>
    </div>
</nav>
