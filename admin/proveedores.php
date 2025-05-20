<?php
// admin/proveedores.php (Página para que el Admin gestione los proveedores)
require_once __DIR__ . '/../includes/header.php'; // Accede al header general

// Verificar si el admin está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['mensaje_flash_admin_login'] = "Debes iniciar sesión como administrador para acceder a esta página.";
    $_SESSION['mensaje_flash_admin_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/index.php");
    exit;
}

require_once __DIR__ . '/../includes/nav_admin.php'; // Navegación de Admin

$mensaje_accion_proveedor = '';
// Mostrar mensajes flash de acciones sobre proveedores
if (isset($_SESSION['mensaje_flash_proveedores_admin'])) {
    $mensaje_accion_proveedor = "<p class='" . htmlspecialchars($_SESSION['mensaje_flash_proveedores_admin_tipo'] ?? 'success') . "' style='padding: 10px; border-radius: 5px; margin-bottom:15px;'>" . htmlspecialchars($_SESSION['mensaje_flash_proveedores_admin']) . "</p>";
    unset($_SESSION['mensaje_flash_proveedores_admin'], $_SESSION['mensaje_flash_proveedores_admin_tipo']);
}

// --- Lógica para Añadir un Nuevo Proveedor ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_proveedor'])) {
    $nombre_proveedor = trim($_POST['nombre_proveedor'] ?? '');
    $contacto_proveedor = trim($_POST['contacto_proveedor'] ?? ''); // Opcional
    $telefono_proveedor = trim($_POST['telefono_proveedor'] ?? ''); // Opcional
    $direccion_proveedor = trim($_POST['direccion_proveedor'] ?? ''); // Opcional

    if (empty($nombre_proveedor)) {
        $_SESSION['mensaje_flash_proveedores_admin'] = "Error: El nombre del proveedor es obligatorio.";
        $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
    } else {
        try {
            // Verificar si ya existe un proveedor con el mismo nombre (opcional, pero buena práctica)
            $stmtCheck = $pdo->prepare("SELECT id_proveedor FROM Proveedor WHERE nombre_proveedor = :nombre");
            $stmtCheck->bindParam(':nombre', $nombre_proveedor);
            $stmtCheck->execute();
            if ($stmtCheck->fetch()) {
                 $_SESSION['mensaje_flash_proveedores_admin'] = "Error: Ya existe un proveedor con el nombre '{$nombre_proveedor}'.";
                 $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
            } else {
                $sql = "INSERT INTO Proveedor (nombre_proveedor, contacto, telefono, direccion) 
                        VALUES (:nombre_proveedor, :contacto, :telefono, :direccion)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':nombre_proveedor', $nombre_proveedor);
                $stmt->bindParam(':contacto', $contacto_proveedor, $contacto_proveedor ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':telefono', $telefono_proveedor, $telefono_proveedor ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':direccion', $direccion_proveedor, $direccion_proveedor ? PDO::PARAM_STR : PDO::PARAM_NULL);

                if ($stmt->execute()) {
                    $idProveedorNuevo = $pdo->lastInsertId();
                    $_SESSION['mensaje_flash_proveedores_admin'] = "Proveedor '{$nombre_proveedor}' añadido exitosamente.";
                    $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "success";
                    registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Creación Nuevo Proveedor', json_encode(['id_proveedor' => $idProveedorNuevo, 'nombre' => $nombre_proveedor]));
                } else {
                    $_SESSION['mensaje_flash_proveedores_admin'] = "Error al añadir el proveedor.";
                    $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
                    registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Error Creación Proveedor', json_encode(['nombre' => $nombre_proveedor, 'errorInfo' => $stmt->errorInfo()]));
                }
            }
        } catch (PDOException $e) {
            $_SESSION['mensaje_flash_proveedores_admin'] = "Error de base de datos al añadir el proveedor: " . $e->getMessage();
            $_SESSION['mensaje_flash_proveedores_admin_tipo'] = "error";
            error_log("Error PDO en admin/proveedores.php (añadir proveedor): " . $e->getMessage());
            registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Excepción PDO Creación Proveedor', $e->getMessage());
        }
    }
    // Redirigir para evitar reenvío de formulario
    header("Location: " . obtener_url_base() . "admin/proveedores.php");
    exit;
}

// --- Lógica para Listar Proveedores Existentes ---
$lista_proveedores = [];
$mensaje_error_lista_proveedores = '';
try {
    $sql_lista_prov = "SELECT id_proveedor, nombre_proveedor, contacto, telefono, direccion, fecha_creacion
                       FROM Proveedor
                       ORDER BY nombre_proveedor ASC";
    $stmt_lista_prov = $pdo->query($sql_lista_prov);
    $lista_proveedores = $stmt_lista_prov->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje_error_lista_proveedores = "Error al cargar la lista de proveedores: " . $e->getMessage();
    error_log("Error PDO en admin/proveedores.php (listar proveedores): " . $e->getMessage());
}

?>

<div class="container">
    <div class="main-content admin-section">
        <h2>Gestión de Proveedores</h2>
        <?php echo $mensaje_accion_proveedor; ?>

        <div style="border: 1px solid #ccc; padding: 20px; margin-bottom: 30px; background-color: #f9f9f9;">
            <h3>Añadir Nuevo Proveedor</h3>
            <form action="proveedores.php" method="post">
                <div>
                    <label for="nombre_proveedor">Nombre del Proveedor (*):</label>
                    <input type="text" id="nombre_proveedor" name="nombre_proveedor" required>
                </div>
                <div>
                    <label for="contacto_proveedor">Nombre de Contacto (Opcional):</label>
                    <input type="text" id="contacto_proveedor" name="contacto_proveedor">
                </div>
                <div>
                    <label for="telefono_proveedor">Teléfono (Opcional):</label>
                    <input type="text" id="telefono_proveedor" name="telefono_proveedor">
                </div>
                <div>
                    <label for="direccion_proveedor">Dirección (Opcional):</label>
                    <textarea id="direccion_proveedor" name="direccion_proveedor" rows="2"></textarea>
                </div>
                <button type="submit" name="agregar_proveedor">Añadir Proveedor</button>
            </form>
        </div>

        <h3>Lista de Proveedores Actuales</h3>
        <?php if ($mensaje_error_lista_proveedores): ?>
            <p class="error"><?php echo $mensaje_error_lista_proveedores; ?></p>
        <?php endif; ?>

        <?php if (!empty($lista_proveedores)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Proveedor</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Dirección</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_proveedores as $proveedor_item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($proveedor_item['id_proveedor']); ?></td>
                            <td><?php echo htmlspecialchars($proveedor_item['nombre_proveedor']); ?></td>
                            <td><?php echo htmlspecialchars($proveedor_item['contacto'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($proveedor_item['telefono'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($proveedor_item['direccion'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($proveedor_item['fecha_creacion']))); ?></td>
                            <td>
                                <a href="<?php echo obtener_url_base(); ?>admin/proveedor_editar.php?id_proveedor=<?php echo htmlspecialchars($proveedor_item['id_proveedor']); ?>">
                                    <button type="button" style="font-size:0.9em; padding: 5px 8px;">Editar</button>
                                </a>
                            </td>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (empty($mensaje_error_lista_proveedores)): ?>
            <p>No hay proveedores registrados en el sistema.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
