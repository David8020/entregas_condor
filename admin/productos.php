<?php
// admin/productos.php (Página para que el Admin gestione los productos)
require_once __DIR__ . '/../includes/header.php'; // Accede al header general

// Verificar si el admin está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['mensaje_flash_admin_login'] = "Debes iniciar sesión como administrador para acceder a esta página.";
    $_SESSION['mensaje_flash_admin_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/index.php");
    exit;
}

require_once __DIR__ . '/../includes/nav_admin.php'; // Navegación de Admin

$mensaje_accion_producto = '';
// Mostrar mensajes flash de acciones sobre productos
if (isset($_SESSION['mensaje_flash_productos_admin'])) { // <--- ¡AQUÍ ESTÁ LA POSIBLE DIFERENCIA!
    $mensaje_accion_producto = "<p class='" . htmlspecialchars($_SESSION['mensaje_flash_productos_admin_tipo'] ?? 'success') . "' style='padding: 10px; border-radius: 5px; margin-bottom:15px;'>" . htmlspecialchars($_SESSION['mensaje_flash_productos_admin']) . "</p>";
    unset($_SESSION['mensaje_flash_productos_admin'], $_SESSION['mensaje_flash_productos_admin_tipo']);
}

// --- Lógica para Añadir un Nuevo Producto ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_producto'])) {
    $nombre_producto = trim($_POST['nombre_producto'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
    $id_proveedor = filter_input(INPUT_POST, 'id_proveedor', FILTER_VALIDATE_INT);
    $cantidad_inicial_inventario = filter_input(INPUT_POST, 'cantidad_inicial_inventario', FILTER_VALIDATE_INT);

    // Validaciones
    if (empty($nombre_producto) || $precio === false || $precio < 0 || $id_proveedor === false || $cantidad_inicial_inventario === false || $cantidad_inicial_inventario < 0) {
        $_SESSION['mensaje_flash_productos_admin'] = "Error: Todos los campos son obligatorios y deben tener valores válidos. El precio y la cantidad no pueden ser negativos.";
        $_SESSION['mensaje_flash_productos_admin_tipo'] = "error";
    } else {
        $pdo->beginTransaction();
        try {
            // Verificar que el proveedor exista
            $stmtProv = $pdo->prepare("SELECT id_proveedor FROM Proveedor WHERE id_proveedor = :id_proveedor");
            $stmtProv->bindParam(':id_proveedor', $id_proveedor, PDO::PARAM_INT);
            $stmtProv->execute();
            if (!$stmtProv->fetch()) {
                $pdo->rollBack();
                $_SESSION['mensaje_flash_productos_admin'] = "Error: El proveedor seleccionado no existe.";
                $_SESSION['mensaje_flash_productos_admin_tipo'] = "error";
            } else {
                // Insertar en la tabla Producto
                $sqlProducto = "INSERT INTO Producto (nombre_producto, descripcion, precio, id_proveedor) 
                                VALUES (:nombre_producto, :descripcion, :precio, :id_proveedor)";
                $stmtProducto = $pdo->prepare($sqlProducto);
                $stmtProducto->bindParam(':nombre_producto', $nombre_producto);
                $stmtProducto->bindParam(':descripcion', $descripcion);
                $stmtProducto->bindParam(':precio', $precio);
                $stmtProducto->bindParam(':id_proveedor', $id_proveedor, PDO::PARAM_INT);
                $stmtProducto->execute();
                $idProductoNuevo = $pdo->lastInsertId();

                // Insertar en la tabla Inventario
                $sqlInventario = "INSERT INTO Inventario (id_producto, cantidad_disponible) 
                                  VALUES (:id_producto, :cantidad_disponible)";
                $stmtInventario = $pdo->prepare($sqlInventario);
                $stmtInventario->bindParam(':id_producto', $idProductoNuevo, PDO::PARAM_INT);
                $stmtInventario->bindParam(':cantidad_disponible', $cantidad_inicial_inventario, PDO::PARAM_INT);
                $stmtInventario->execute();

                $pdo->commit();
                $_SESSION['mensaje_flash_productos_admin'] = "Producto '{$nombre_producto}' añadido exitosamente con {$cantidad_inicial_inventario} unidades en inventario.";
                $_SESSION['mensaje_flash_productos_admin_tipo'] = "success";
                registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Creación Nuevo Producto', json_encode(['id_producto' => $idProductoNuevo, 'nombre' => $nombre_producto, 'inventario_inicial' => $cantidad_inicial_inventario]));
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['mensaje_flash_productos_admin'] = "Error de base de datos al añadir el producto: " . $e->getMessage();
            $_SESSION['mensaje_flash_productos_admin_tipo'] = "error";
            error_log("Error PDO en admin/productos.php (añadir producto): " . $e->getMessage());
            registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Error Creación Producto', $e->getMessage());
        }
    }
    // Redirigir para evitar reenvío de formulario
    header("Location: " . obtener_url_base() . "admin/productos.php");
    exit;
}


// --- Lógica para Listar Productos Existentes ---
$lista_productos = [];
$mensaje_error_lista_productos = '';
try {
    $sql_lista = "SELECT p.id_producto, p.nombre_producto, p.descripcion, p.precio, 
                         pr.nombre_proveedor, i.cantidad_disponible, p.fecha_creacion
                  FROM Producto p
                  JOIN Proveedor pr ON p.id_proveedor = pr.id_proveedor
                  LEFT JOIN Inventario i ON p.id_producto = i.id_producto
                  ORDER BY p.id_producto DESC";
    $stmt_lista = $pdo->query($sql_lista);
    $lista_productos = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje_error_lista_productos = "Error al cargar la lista de productos: " . $e->getMessage();
    error_log("Error PDO en admin/productos.php (listar productos): " . $e->getMessage());
}

// Obtener lista de proveedores para el formulario de añadir producto
$proveedores_disponibles = [];
try {
    $stmt_prov = $pdo->query("SELECT id_proveedor, nombre_proveedor FROM Proveedor ORDER BY nombre_proveedor ASC");
    $proveedores_disponibles = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejar error si no se pueden cargar proveedores, aunque el formulario no será completamente funcional.
    $mensaje_accion_producto .= "<p class='error'>Error al cargar lista de proveedores para el formulario.</p>";
}

?>

<div class="container">
    <div class="main-content admin-section">
        <h2>Gestión de Productos</h2>
        <?php echo $mensaje_accion_producto; ?>

        <div style="border: 1px solid #ccc; padding: 20px; margin-bottom: 30px; background-color: #f9f9f9;">
            <h3>Añadir Nuevo Producto</h3>
            <form action="productos.php" method="post">
                <div>
                    <label for="nombre_producto">Nombre del Producto (*):</label>
                    <input type="text" id="nombre_producto" name="nombre_producto" required>
                </div>
                <div>
                    <label for="descripcion">Descripción:</label>
                    <textarea id="descripcion" name="descripcion" rows="3"></textarea>
                </div>
                <div>
                    <label for="precio">Precio (*):</label>
                    <input type="number" id="precio" name="precio" step="0.01" min="0" required>
                </div>
                <div>
                    <label for="id_proveedor">Proveedor (*):</label>
                    <select id="id_proveedor" name="id_proveedor" required>
                        <option value="">Seleccione un proveedor</option>
                        <?php foreach ($proveedores_disponibles as $proveedor): ?>
                            <option value="<?php echo htmlspecialchars($proveedor['id_proveedor']); ?>">
                                <?php echo htmlspecialchars($proveedor['nombre_proveedor']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="cantidad_inicial_inventario">Cantidad Inicial en Inventario (*):</label>
                    <input type="number" id="cantidad_inicial_inventario" name="cantidad_inicial_inventario" min="0" required>
                </div>
                <button type="submit" name="agregar_producto">Añadir Producto</button>
            </form>
        </div>

        <h3>Lista de Productos Actuales</h3>
        <?php if ($mensaje_error_lista_productos): ?>
            <p class="error"><?php echo $mensaje_error_lista_productos; ?></p>
        <?php endif; ?>

        <?php if (!empty($lista_productos)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Precio</th>
                        <th>Proveedor</th>
                        <th>Stock Actual</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_productos as $producto_item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($producto_item['id_producto']); ?></td>
                            <td><?php echo htmlspecialchars($producto_item['nombre_producto']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($producto_item['precio'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($producto_item['nombre_proveedor']); ?></td>
                            <td><?php echo htmlspecialchars($producto_item['cantidad_disponible'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($producto_item['fecha_creacion']))); ?></td>
                            <td>
                                <a href="<?php echo obtener_url_base(); ?>admin/producto_editar.php?id_producto=<?php echo htmlspecialchars($producto_item['id_producto']); ?>">
                                    <button type="button" style="font-size:0.9em; padding: 5px 8px;">Editar</button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (empty($mensaje_error_lista_productos)): ?>
            <p>No hay productos registrados en el sistema.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
