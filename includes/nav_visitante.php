<?php // includes/nav_visitante.php - Navegación para usuarios no logueados ?>
<nav>
    <div class="container">
        <ul>
            <li><a href="<?php echo obtener_url_base(); ?>index.php">Inicio</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>catalogo.php">Catálogo</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>login.php">Login</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>registro.php">Registro</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>carrito.php">Carrito</a></li>
            <li><a href="<?php echo obtener_url_base(); ?>admin/index.php">Admin</a></li> </ul>
    </div>
</nav>
