<?php
// admin/inventario_editar.php (Página para que el Admin edite el stock de un producto)
require_once __DIR__ . '/../includes/header.php'; // Accede al header general

// Verificar si el admin está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['mensaje_flash_admin_login'] = "Debes iniciar sesión como administrador para acceder a esta página.";
    $_SESSION['mensaje_flash_admin_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/index.php");
    exit;
}

require_once __DIR__ . '/../includes/nav_admin.php'; // Navegación de Admin

$id_producto_stock = filter_input(INPUT_GET, 'id_producto', FILTER_VALIDATE_INT);
$producto_info_stock = null;
$mensaje_accion_stock = '';

// Mostrar mensajes flash si vienen de una redirección
if (isset($_SESSION['mensaje_flash_inventario_admin'])) {
    $mensaje_accion_stock = "<p class='" . htmlspecialchars($_SESSION['mensaje_flash_inventario_admin_tipo'] ?? 'success') . "'>" . htmlspecialchars($_SESSION['mensaje_flash_inventario_admin']) . "</p>";
    unset($_SESSION['mensaje_flash_inventario_admin'], $_SESSION['mensaje_flash_inventario_admin_tipo']);
}

if (!$id_producto_stock) {
    $_SESSION['mensaje_flash_inventario_admin'] = "No se especificó un ID de producto válido para editar el stock.";
    $_SESSION['mensaje_flash_inventario_admin_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/inventario.php");
    exit;
}

// --- Lógica para Procesar la Actualización del Stock ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_stock_manual'])) {
    $nueva_cantidad_disponible = filter_input(INPUT_POST, 'cantidad_disponible', FILTER_VALIDATE_INT);

    if ($nueva_cantidad_disponible === false || $nueva_cantidad_disponible < 0) {
        $_SESSION['mensaje_flash_inventario_admin'] = "Error: La nueva cantidad debe ser un número entero no negativo.";
        $_SESSION['mensaje_flash_inventario_admin_tipo'] = "error";
    } else {
        try {
            // Verificar si ya existe una entrada en inventario para este producto
            $stmtCheck = $pdo->prepare("SELECT id_producto FROM Inventario WHERE id_producto = :id_producto");
            $stmtCheck->bindParam(':id_producto', $id_producto_stock, PDO::PARAM_INT);
            $stmtCheck->execute();
            $existe_en_inventario = $stmtCheck->fetch();

            if ($existe_en_inventario) {
                $sql_update = "UPDATE Inventario 
                               SET cantidad_disponible = :cantidad_disponible,
                                   fecha_actualizacion = NOW() 
                               WHERE id_producto = :id_producto_stock";
            } else {
                // Si no existe, creamos la entrada (esto podría pasar si un producto se creó sin inventario inicial por alguna razón)
                $sql_update = "INSERT INTO Inventario (id_producto, cantidad_disponible, fecha_actualizacion) 
                               VALUES (:id_producto_stock, :cantidad_disponible, NOW())";
            }
            
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':cantidad_disponible', $nueva_cantidad_disponible, PDO::PARAM_INT);
            $stmt_update->bindParam(':id_producto_stock', $id_producto_stock, PDO::PARAM_INT);

            if ($stmt_update->execute()) {
                $_SESSION['mensaje_flash_inventario_admin'] = "Stock del producto ID {$id_producto_stock} actualizado a {$nueva_cantidad_disponible} unidades.";
                $_SESSION['mensaje_flash_inventario_admin_tipo'] = "success";
                registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Actualización Manual Stock', json_encode(['id_producto' => $id_producto_stock, 'nueva_cantidad' => $nueva_cantidad_disponible]));
                header("Location: " . obtener_url_base() . "admin/inventario.php"); // Redirigir a la lista de inventario
                exit;
            } else {
                $_SESSION['mensaje_flash_inventario_admin'] = "Error al actualizar el stock del producto ID {$id_producto_stock}.";
                $_SESSION['mensaje_flash_inventario_admin_tipo'] = "error";
                registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Error Actualización Manual Stock', json_encode(['id_producto' => $id_producto_stock, 'errorInfo' => $stmt_update->errorInfo()]));
            }
        } catch (PDOException $e) {
            $_SESSION['mensaje_flash_inventario_admin'] = "Error de base de datos al actualizar el stock: " . $e->getMessage();
            $_SESSION['mensaje_flash_inventario_admin_tipo'] = "error";
            error_log("Error PDO en admin/inventario_editar.php (actualizar stock): " . $e->getMessage());
            registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Excepción PDO Actualización Manual Stock', $e->getMessage());
        }
    }
    // Si hubo error y no se redirigió, redirigir de vuelta a esta página de edición
    header("Location: " . obtener_url_base() . "admin/inventario_editar.php?id_producto=" . $id_producto_stock);
    exit;
}

// --- Cargar Datos del Producto y su Stock Actual para Editar ---
if (empty($mensaje_accion_stock) || $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql_stock = "SELECT p.id_producto, p.nombre_producto, i.cantidad_disponible 
                      FROM Producto p
                      LEFT JOIN Inventario i ON p.id_producto = i.id_producto
                      WHERE p.id_producto = :id_producto_stock";
        $stmt_stock = $pdo->prepare($sql_stock);
        $stmt_stock->bindParam(':id_producto_stock', $id_producto_stock, PDO::PARAM_INT);
        $stmt_stock->execute();
        $producto_info_stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);

        if (!$producto_info_stock) {
            $_SESSION['mensaje_flash_inventario_admin'] = "Producto con ID {$id_producto_stock} no encontrado para editar stock.";
            $_SESSION['mensaje_flash_inventario_admin_tipo'] = "error";
            header("Location: " . obtener_url_base() . "admin/inventario.php");
            exit;
        }
        // Si no hay entrada en inventario, la cantidad_disponible será NULL, la convertimos a 0 para el formulario.
        if ($producto_info_stock['cantidad_disponible'] === null) {
            $producto_info_stock['cantidad_disponible'] = 0;
        }

    } catch (PDOException $e) {
        $_SESSION['mensaje_flash_inventario_admin'] = "Error al cargar datos del producto para editar stock: " . $e->getMessage();
        $_SESSION['mensaje_flash_inventario_admin_tipo'] = "error";
        error_log("Error PDO en admin/inventario_editar.php (cargar stock): " . $e->getMessage());
        header("Location: " . obtener_url_base() . "admin/inventario.php");
        exit;
    }
}
?>

<div class="container">
    <div class="main-content admin-section">
        <h2>Actualizar Stock de Producto</h2>
        <p><a href="<?php echo obtener_url_base(); ?>admin/inventario.php">&laquo; Volver a la consulta de inventario</a></p>

        <?php echo $mensaje_accion_stock; ?>

        <?php if ($producto_info_stock): ?>
            <h3>Producto: <?php echo htmlspecialchars($producto_info_stock['nombre_producto']); ?> (ID: <?php echo htmlspecialchars($producto_info_stock['id_producto']); ?>)</h3>
            <p>Stock Actual: <strong><?php echo htmlspecialchars($producto_info_stock['cantidad_disponible']); ?></strong> unidades.</p>
            
            <form action="inventario_editar.php?id_producto=<?php echo htmlspecialchars($id_producto_stock); ?>" method="post">
                <div>
                    <label for="cantidad_disponible">Nueva Cantidad Disponible (*):</label>
                    <input type="number" id="cantidad_disponible" name="cantidad_disponible" 
                           value="<?php echo htmlspecialchars($producto_info_stock['cantidad_disponible']); // Muestra la cantidad actual como valor por defecto ?>" 
                           min="0" required>
                </div>
                <button type="submit" name="actualizar_stock_manual">Actualizar Stock</button>
            </form>
        <?php elseif (empty($mensaje_accion_stock)) : ?>
            <p class="error">No se pudo cargar la información del producto para editar el stock.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
