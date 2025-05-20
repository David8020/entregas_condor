<?php
// admin/inventario.php (Página para que el Admin consulte el inventario)
require_once __DIR__ . '/../includes/header.php'; // Accede al header general

// Verificar si el admin está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['mensaje_flash_admin_login'] = "Debes iniciar sesión como administrador para acceder a esta página.";
    $_SESSION['mensaje_flash_admin_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/index.php");
    exit;
}

require_once __DIR__ . '/../includes/nav_admin.php'; // Navegación de Admin

if (isset($_SESSION['mensaje_flash_inventario_admin'])) {
    echo "<p class='" . htmlspecialchars($_SESSION['mensaje_flash_inventario_admin_tipo'] ?? 'success') . "' style='padding: 10px; border-radius: 5px; margin-bottom:15px;'>" . htmlspecialchars($_SESSION['mensaje_flash_inventario_admin']) . "</p>";
    unset($_SESSION['mensaje_flash_inventario_admin'], $_SESSION['mensaje_flash_inventario_admin_tipo']); // Limpiar mensaje
}
$lista_inventario = [];
$mensaje_error_inventario = '';

try {
    // Consultar el inventario uniéndolo con la tabla de productos para obtener el nombre del producto
    $sql = "SELECT p.id_producto, p.nombre_producto, i.cantidad_disponible, i.fecha_actualizacion
            FROM Inventario i
            JOIN Producto p ON i.id_producto = p.id_producto
            ORDER BY p.nombre_producto ASC";
    $stmt = $pdo->query($sql);
    $lista_inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mensaje_error_inventario = "Error al cargar los datos del inventario: " . $e->getMessage();
    error_log("Error PDO en admin/inventario.php: " . $e->getMessage());
    registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Error Carga Inventario Admin', $e->getMessage());
}
?>

<div class="container">
    <div class="main-content admin-section">
        <h2>Consulta de Inventario</h2>
        <p>Aquí puedes ver las cantidades actuales de cada producto en el inventario.</p>

        <?php if ($mensaje_error_inventario): ?>
            <p class="error"><?php echo $mensaje_error_inventario; ?></p>
        <?php endif; ?>

        <?php if (!empty($lista_inventario)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Producto</th>
                        <th>Nombre del Producto</th>
                        <th>Cantidad Disponible</th>
                        <th>Última Actualización</th>
                        <th>Acciones</th> </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_inventario as $item_inventario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item_inventario['id_producto']); ?></td>
                            <td><?php echo htmlspecialchars($item_inventario['nombre_producto']); ?></td>
                            <td><?php echo htmlspecialchars($item_inventario['cantidad_disponible']); ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y H:i:s", strtotime($item_inventario['fecha_actualizacion']))); ?></td>
                            <td>
                                <a href="<?php echo obtener_url_base(); ?>admin/inventario_editar.php?id_producto=<?php echo htmlspecialchars($item_inventario['id_producto']); ?>">
                                    <button type="button" style="font-size:0.9em; padding: 5px 8px;">Actualizar Stock</button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (empty($mensaje_error_inventario)): ?>
            <p>No hay información de inventario disponible o no hay productos con inventario registrado.</p>
            <p>Recuerda que el inventario se crea automáticamente al <a href="productos.php">añadir un nuevo producto</a>.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
