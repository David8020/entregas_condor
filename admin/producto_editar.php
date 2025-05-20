    <?php
    // admin/producto_editar.php (Página para que el Admin edite un producto existente)
    require_once __DIR__ . '/../includes/header.php'; // Accede al header general

    // Verificar si el admin está logueado
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        $_SESSION['mensaje_flash_admin_login'] = "Debes iniciar sesión como administrador para acceder a esta página.";
        $_SESSION['mensaje_flash_admin_login_tipo'] = "error";
        header("Location: " . obtener_url_base() . "admin/index.php");
        exit;
    }

    require_once __DIR__ . '/../includes/nav_admin.php'; // Navegación de Admin

    $id_producto_editar = filter_input(INPUT_GET, 'id_producto', FILTER_VALIDATE_INT);
    $producto_actual = null;
    $mensaje_accion_editar_producto = ''; // Esta variable es local para esta página

    // Mostrar mensajes flash si vienen de una redirección (ej. después de un error en el POST de esta misma página)
    if (isset($_SESSION['mensaje_flash_productos_admin'])) { // Nombre de sesión corregido/estandarizado
        $mensaje_accion_editar_producto = "<p class='" . htmlspecialchars($_SESSION['mensaje_flash_productos_admin_tipo'] ?? 'success') . "'>" . htmlspecialchars($_SESSION['mensaje_flash_productos_admin']) . "</p>";
        unset($_SESSION['mensaje_flash_productos_admin'], $_SESSION['mensaje_flash_productos_admin_tipo']);
    }


    if (!$id_producto_editar) {
        $_SESSION['mensaje_flash_productos_admin'] = "No se especificó un ID de producto válido para editar."; // Estandarizado
        $_SESSION['mensaje_flash_productos_admin_tipo'] = "error"; // Estandarizado
        header("Location: " . obtener_url_base() . "admin/productos.php");
        exit;
    }

    // --- Lógica para Procesar la Actualización del Producto ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_producto'])) {
        $nombre_producto = trim($_POST['nombre_producto'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
        $id_proveedor = filter_input(INPUT_POST, 'id_proveedor', FILTER_VALIDATE_INT);

        if (empty($nombre_producto) || $precio === false || $precio < 0 || $id_proveedor === false) {
            $_SESSION['mensaje_flash_productos_admin'] = "Error: Nombre, precio y proveedor son obligatorios y deben ser válidos."; // Estandarizado
            $_SESSION['mensaje_flash_productos_admin_tipo'] = "error"; // Estandarizado
        } else {
            try {
                $stmtProv = $pdo->prepare("SELECT id_proveedor FROM Proveedor WHERE id_proveedor = :id_proveedor");
                $stmtProv->bindParam(':id_proveedor', $id_proveedor, PDO::PARAM_INT);
                $stmtProv->execute();
                if (!$stmtProv->fetch()) {
                    $_SESSION['mensaje_flash_productos_admin'] = "Error: El proveedor seleccionado no existe."; // Estandarizado
                    $_SESSION['mensaje_flash_productos_admin_tipo'] = "error"; // Estandarizado
                } else {
                    $sql_update = "UPDATE Producto 
                                   SET nombre_producto = :nombre_producto, 
                                       descripcion = :descripcion, 
                                       precio = :precio, 
                                       id_proveedor = :id_proveedor
                                   WHERE id_producto = :id_producto_editar";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->bindParam(':nombre_producto', $nombre_producto);
                    $stmt_update->bindParam(':descripcion', $descripcion);
                    $stmt_update->bindParam(':precio', $precio);
                    $stmt_update->bindParam(':id_proveedor', $id_proveedor, PDO::PARAM_INT);
                    $stmt_update->bindParam(':id_producto_editar', $id_producto_editar, PDO::PARAM_INT);

                    if ($stmt_update->execute()) {
                        $_SESSION['mensaje_flash_productos_admin'] = "Producto '{$nombre_producto}' actualizado exitosamente."; // Estandarizado
                        $_SESSION['mensaje_flash_productos_admin_tipo'] = "success"; // Estandarizado
                        registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Actualización Producto', json_encode(['id_producto' => $id_producto_editar, 'nombre' => $nombre_producto]));
                        
                        header("Location: " . obtener_url_base() . "admin/productos.php"); // Redirige a la lista
                        exit;
                    } else {
                        $_SESSION['mensaje_flash_productos_admin'] = "Error al actualizar el producto."; // Estandarizado
                        $_SESSION['mensaje_flash_productos_admin_tipo'] = "error"; // Estandarizado
                        registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Error Actualización Producto', json_encode(['id_producto' => $id_producto_editar, 'errorInfo' => $stmt_update->errorInfo()]));
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['mensaje_flash_productos_admin'] = "Error de base de datos al actualizar el producto: " . $e->getMessage(); // Estandarizado
                $_SESSION['mensaje_flash_productos_admin_tipo'] = "error"; // Estandarizado
                error_log("Error PDO en admin/producto_editar.php (actualizar): " . $e->getMessage());
                registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Excepción PDO Actualización Producto', $e->getMessage());
            }
        }
        // Si hubo error y no se redirigió a productos.php, redirigir de vuelta a esta página de edición
        header("Location: " . obtener_url_base() . "admin/producto_editar.php?id_producto=" . $id_producto_editar);
        exit;
    }


    // --- Cargar Datos del Producto para Editar (si no es un POST de actualización que ya falló y está recargando) ---
    // Esta lógica solo se ejecuta si la página se carga por GET, o si es un POST pero no el de 'actualizar_producto'
    // o si el POST de 'actualizar_producto' resultó en una redirección a productos.php (éxito).
    // Si el POST falló, el mensaje flash ya se estableció y la redirección a esta misma página mostrará ese mensaje.
    // No necesitamos recargar $producto_actual si el POST falló porque los datos del formulario se perderían.
    // El mensaje flash es suficiente.
    // Sin embargo, si $mensaje_accion_editar_producto está vacío (es decir, no hay un mensaje flash de un POST fallido en esta misma página),
    // entonces sí cargamos los datos del producto.
    if (empty($mensaje_accion_editar_producto) || $_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $sql_producto = "SELECT id_producto, nombre_producto, descripcion, precio, id_proveedor 
                             FROM Producto 
                             WHERE id_producto = :id_producto_editar";
            $stmt_producto = $pdo->prepare($sql_producto);
            $stmt_producto->bindParam(':id_producto_editar', $id_producto_editar, PDO::PARAM_INT);
            $stmt_producto->execute();
            $producto_actual = $stmt_producto->fetch(PDO::FETCH_ASSOC);

            if (!$producto_actual) {
                $_SESSION['mensaje_flash_productos_admin'] = "Producto con ID {$id_producto_editar} no encontrado."; // Estandarizado
                $_SESSION['mensaje_flash_productos_admin_tipo'] = "error"; // Estandarizado
                header("Location: " . obtener_url_base() . "admin/productos.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['mensaje_flash_productos_admin'] = "Error al cargar datos del producto para editar: " . $e->getMessage(); // Estandarizado
            $_SESSION['mensaje_flash_productos_admin_tipo'] = "error"; // Estandarizado
            error_log("Error PDO en admin/producto_editar.php (cargar): " . $e->getMessage());
            header("Location: " . obtener_url_base() . "admin/productos.php");
            exit;
        }
    }


    // Obtener lista de proveedores para el select
    $proveedores_disponibles = [];
    try {
        $stmt_prov = $pdo->query("SELECT id_proveedor, nombre_proveedor FROM Proveedor ORDER BY nombre_proveedor ASC");
        $proveedores_disponibles = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Añadir al mensaje local, no a la sesión flash, ya que esto es un problema de carga de la página de edición en sí.
        $mensaje_accion_editar_producto .= "<p class='error'>Error al cargar lista de proveedores.</p>";
    }

    ?>

    <div class="container">
        <div class="main-content admin-section">
            <h2>Editar Producto</h2>
            <p><a href="<?php echo obtener_url_base(); ?>admin/productos.php">&laquo; Volver a la lista de productos</a></p>

            <?php echo $mensaje_accion_editar_producto; // Muestra mensajes flash de esta página (ej. error de validación) ?>

            <?php if ($producto_actual): ?>
                <form action="producto_editar.php?id_producto=<?php echo htmlspecialchars($id_producto_editar); ?>" method="post">
                    <div>
                        <label for="nombre_producto">Nombre del Producto (*):</label>
                        <input type="text" id="nombre_producto" name="nombre_producto" 
                               value="<?php echo htmlspecialchars($producto_actual['nombre_producto'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="descripcion">Descripción:</label>
                        <textarea id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($producto_actual['descripcion'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label for="precio">Precio (*):</label>
                        <input type="number" id="precio" name="precio" step="0.01" min="0" 
                               value="<?php echo htmlspecialchars($producto_actual['precio'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="id_proveedor">Proveedor (*):</label>
                        <select id="id_proveedor" name="id_proveedor" required>
                            <option value="">Seleccione un proveedor</option>
                            <?php foreach ($proveedores_disponibles as $proveedor): ?>
                                <option value="<?php echo htmlspecialchars($proveedor['id_proveedor']); ?>" 
                                        <?php echo (isset($producto_actual['id_proveedor']) && $producto_actual['id_proveedor'] == $proveedor['id_proveedor']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($proveedor['nombre_proveedor']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p><small>Nota: La cantidad en inventario se gestiona desde la sección de Inventario o se actualiza con los pedidos.</small></p>
                    <button type="submit" name="actualizar_producto">Actualizar Producto</button>
                </form>
            <?php elseif (empty($mensaje_accion_editar_producto)) : ?>
                <p class="error">No se pudo cargar la información del producto para editar.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/../includes/footer.php';
    ?>
    
