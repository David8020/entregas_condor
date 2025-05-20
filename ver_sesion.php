<?php
session_start(); // Necesario para acceder a las variables de sesión
echo "<pre>";   // La etiqueta <pre> ayuda a que la salida se vea más ordenada
print_r($_SESSION); // Esto imprime todo el contenido de la variable $_SESSION
echo "</pre>";
echo "<a href='catalogo.php'>Volver al catálogo</a><br>";
// El enlace a carrito.php aún no funcionará porque no hemos creado esa página
echo "<a href='carrito.php'>Ir al carrito (aún no creado)</a>"; 
?>