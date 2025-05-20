<?php
// admin/proveedor_editar.php (Página para que el Admin edite un proveedor existente)
require_once __DIR__ . '/../includes/header.php'; // Accede al header general

// Verificar si el admin está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['mensaje_flash_admin_login'] = "Debes iniciar sesión como administrador para acceder a esta página.";
    $_SESSION['mensaje_flash_admin_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/index.php");
    exit;
}

require_once __DIR__ . '/../includes/nav_admin.php'; // Navegación de Admin

$id_proveedor_editar = filter_input(INPUT_GET, 'id_proveedor', FILTER_VALIDATE_INT);
$proveedor_actual = null;
$mensaje_accion_editar_proveedor = ''; // Mensaje local para esta página

// Mostrar mensajes flash si vienen de una redirección (ej. después de un error en el POST de esta misma página)
if (isset($_SESSION['mensaje_flash_proveedores_admin'])) { // Usamos el mismo nombre de sesión que en proveedores.php
    $mensaje_accion_editar_proveedor = "<p class='" . htmlspecialchars($_SESSION['mensaje_flash_proveedores_admin_tipo'] ?? 'success') . "'>" . htmlspecialchars($_SESSION['mensaje_flash_proveedores_admin']) . "</p>";
    unset($_SESSION['mensaje_flash_proveedores_admin'], $_SESSION['mensaje_flash_proveedores_admin_tipo']);
}

if (!$id_proveedor_editar) {
    $_SESSION['mensaje_flash_proveedores_admin'] = "No se especificó un ID de proveedor válido para editar.";
    $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/proveedores.php");
    exit;
}

// --- Lógica para Procesar la Actualización del Proveedor ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_proveedor'])) {
    $nombre_proveedor = trim($_POST['nombre_proveedor'] ?? '');
    $contacto_proveedor = trim($_POST['contacto_proveedor'] ?? '');
    $telefono_proveedor = trim($_POST['telefono_proveedor'] ?? '');
    $direccion_proveedor = trim($_POST['direccion_proveedor'] ?? '');

    if (empty($nombre_proveedor)) {
        $_SESSION['mensaje_flash_proveedores_admin'] = "Error: El nombre del proveedor es obligatorio.";
        $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
    } else {
        try {
            // Opcional: Verificar si el nuevo nombre ya existe para otro proveedor
            $stmtCheck = $pdo->prepare("SELECT id_proveedor FROM Proveedor WHERE nombre_proveedor = :nombre AND id_proveedor != :id_actual");
            $stmtCheck->bindParam(':nombre', $nombre_proveedor);
            $stmtCheck->bindParam(':id_actual', $id_proveedor_editar, PDO::PARAM_INT);
            $stmtCheck->execute();
            if ($stmtCheck->fetch()) {
                 $_SESSION['mensaje_flash_proveedores_admin'] = "Error: Ya existe otro proveedor con el nombre '{$nombre_proveedor}'.";
                 $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
            } else {
                $sql_update = "UPDATE Proveedor 
                               SET nombre_proveedor = :nombre_proveedor, 
                                   contacto = :contacto, 
                                   telefono = :telefono, 
                                   direccion = :direccion
                               WHERE id_proveedor = :id_proveedor_editar";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':nombre_proveedor', $nombre_proveedor);
                $stmt_update->bindParam(':contacto', $contacto_proveedor, $contacto_proveedor ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt_update->bindParam(':telefono', $telefono_proveedor, $telefono_proveedor ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt_update->bindParam(':direccion', $direccion_proveedor, $direccion_proveedor ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt_update->bindParam(':id_proveedor_editar', $id_proveedor_editar, PDO::PARAM_INT);

                if ($stmt_update->execute()) {
                    $_SESSION['mensaje_flash_proveedores_admin'] = "Proveedor '{$nombre_proveedor}' actualizado exitosamente.";
                    $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "success";
                    registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Actualización Proveedor', json_encode(['id_proveedor' => $id_proveedor_editar, 'nombre' => $nombre_proveedor]));
                    header("Location: " . obtener_url_base() . "admin/proveedores.php"); // Redirigir a la lista
                    exit;
                } else {
                    $_SESSION['mensaje_flash_proveedores_admin'] = "Error al actualizar el proveedor.";
                    $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
                    registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Error Actualización Proveedor', json_encode(['id_proveedor' => $id_proveedor_editar, 'errorInfo' => $stmt_update->errorInfo()]));
                }
            }
        } catch (PDOException $e) {
            $_SESSION['mensaje_flash_proveedores_admin'] = "Error de base de datos al actualizar el proveedor: " . $e->getMessage();
            $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
            error_log("Error PDO en admin/proveedor_editar.php (actualizar): " . $e->getMessage());
            registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Excepción PDO Actualización Proveedor', $e->getMessage());
        }
    }
    // Si hubo error y no se redirigió a proveedores.php, redirigir de vuelta a esta página de edición
    header("Location: " . obtener_url_base() . "admin/proveedor_editar.php?id_proveedor=" . $id_proveedor_editar);
    exit;
}

// --- Cargar Datos del Proveedor para Editar ---
if (empty($mensaje_accion_editar_proveedor) || $_SERVER['REQUEST_METHOD'] === 'GET') { // Solo cargar si no hay mensaje de error de un POST previo en esta misma página
    try {
        $sql_proveedor = "SELECT id_proveedor, nombre_proveedor, contacto, telefono, direccion 
                          FROM Proveedor 
                          WHERE id_proveedor = :id_proveedor_editar";
        $stmt_proveedor = $pdo->prepare($sql_proveedor);
        $stmt_proveedor->bindParam(':id_proveedor_editar', $id_proveedor_editar, PDO::PARAM_INT);
        $stmt_proveedor->execute();
        $proveedor_actual = $stmt_proveedor->fetch(PDO::FETCH_ASSOC);

        if (!$proveedor_actual) {
            $_SESSION['mensaje_flash_proveedores_admin'] = "Proveedor con ID {$id_proveedor_editar} no encontrado.";
            $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
            header("Location: " . obtener_url_base() . "admin/proveedores.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['mensaje_flash_proveedores_admin'] = "Error al cargar datos del proveedor para editar: " . $e->getMessage();
        $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
        error_log("Error PDO en admin/proveedor_editar.php (cargar): " . $e->getMessage());
        header("Location: " . obtener_url_base() . "admin/proveedores.php");
        exit;
    }
}
?>

<div class="container">
    <div class="main-content admin-section">
        <h2>Editar Proveedor</h2>
        <p><a href="<?php echo obtener_url_base(); ?>admin/proveedores.php">&laquo; Volver a la lista de proveedores</a></p>

        <?php echo $mensaje_accion_editar_proveedor; // Muestra mensajes flash de esta página (ej. error de validación) ?>

        <?php if ($proveedor_actual): ?>
            <form action="proveedor_editar.php?id_proveedor=<?php echo htmlspecialchars($id_proveedor_editar); ?>" method="post">
                <div>
                    <label for="nombre_proveedor">Nombre del Proveedor (*):</label>
                    <input type="text" id="nombre_proveedor" name="nombre_proveedor" 
                           value="<?php echo htmlspecialchars($proveedor_actual['nombre_proveedor'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="contacto_proveedor">Nombre de Contacto (Opcional):</label>
                    <input type="text" id="contacto_proveedor" name="contacto_proveedor" 
                           value="<?php echo htmlspecialchars($proveedor_actual['contacto'] ?? ''); ?>">
                </div>
                <div>
                    <label for="telefono_proveedor">Teléfono (Opcional):</label>
                    <input type="text" id="telefono_proveedor" name="telefono_proveedor" 
                           value="<?php echo htmlspecialchars($proveedor_actual['telefono'] ?? ''); ?>">
                </div>
                <div>
                    <label for="direccion_proveedor">Dirección (Opcional):</label>
                    <textarea id="direccion_proveedor" name="direccion_proveedor" rows="2"><?php echo htmlspecialchars($proveedor_actual['direccion'] ?? ''); ?></textarea>
                </div>
                <button type="submit" name="actualizar_proveedor">Actualizar Proveedor</button>
            </form>
        <?php elseif (empty($mensaje_accion_editar_proveedor)) : ?>
            <p class="error">No se pudo cargar la información del proveedor para editar.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
