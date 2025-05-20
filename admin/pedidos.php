<?php
// admin/pedidos.php (Página para que el Admin vea todos los pedidos)
require_once __DIR__ . '/../includes/header.php'; // Accede al header general

// Verificar si el admin está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Si no está logueado, redirigir al login de admin
    $_SESSION['mensaje_flash_admin_login'] = "Debes iniciar sesión como administrador para acceder a esta página.";
    $_SESSION['mensaje_flash_admin_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/index.php");
    exit;
}

// Admin está logueado, mostrar navegación de admin
require_once __DIR__ . '/../includes/nav_admin.php';

// Mostrar mensajes flash de acciones sobre pedidos
if (isset($_SESSION['mensaje_flash_pedidos_admin'])) {
    echo "<p class='" . htmlspecialchars($_SESSION['mensaje_flash_pedidos_admin_tipo'] ?? 'success') . "' style='padding: 10px; border-radius: 5px; margin-bottom:15px;'>" . htmlspecialchars($_SESSION['mensaje_flash_pedidos_admin']) . "</p>";
    unset($_SESSION['mensaje_flash_pedidos_admin'], $_SESSION['mensaje_flash_pedidos_admin_tipo']); // Limpiar mensaje
}
$todos_los_pedidos = [];
$mensaje_error_lista_pedidos = '';
$filtro_estado = $_GET['filtro_estado'] ?? ''; // Para filtrar por estado

try {
    // Consulta base para obtener todos los pedidos
    // Unimos con la tabla Cliente para mostrar el nombre del cliente
    $sql = "SELECT p.id_pedido, p.fecha_pedido, p.total, p.estado, 
                   c.nombre AS nombre_cliente, c.apellidos AS apellidos_cliente, c.correo AS correo_cliente
            FROM Pedido p
            JOIN Cliente c ON p.id_cliente = c.id_cliente";

    // Aplicar filtro si se ha seleccionado uno
    $params = [];
    if (!empty($filtro_estado)) {
        $sql .= " WHERE p.estado = :estado";
        $params[':estado'] = $filtro_estado;
    }

    $sql .= " ORDER BY p.fecha_pedido DESC"; // Ordenar por fecha, los más recientes primero
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $todos_los_pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mensaje_error_lista_pedidos = "Error al cargar la lista de pedidos: " . $e->getMessage();
    error_log("Error PDO en admin/pedidos.php: " . $e->getMessage());
    registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Error Carga Lista Pedidos Admin', $e->getMessage());
}

// Lista de posibles estados para el filtro (podrías obtenerlos de la BD si fueran dinámicos)
$estados_posibles = ['Pendiente', 'Procesando', 'Enviado', 'Entregado', 'Cancelado'];

?>

<div class="container">
    <div class="main-content admin-section">
        <h2>Gestión de Pedidos</h2>
        <p>Aquí puedes ver y gestionar todos los pedidos realizados en la tienda.</p>

        <?php if ($mensaje_error_lista_pedidos): ?>
            <p class="error"><?php echo $mensaje_error_lista_pedidos; ?></p>
        <?php endif; ?>

        <form action="pedidos.php" method="get" style="margin-bottom: 20px;">
            <label for="filtro_estado">Filtrar por estado:</label>
            <select name="filtro_estado" id="filtro_estado">
                <option value="">Todos los estados</option>
                <?php foreach ($estados_posibles as $estado_opcion): ?>
                    <option value="<?php echo htmlspecialchars($estado_opcion); ?>" <?php echo ($filtro_estado === $estado_opcion) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucfirst($estado_opcion)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filtrar</button>
            <?php if (!empty($filtro_estado)): ?>
                 <a href="pedidos.php" style="margin-left: 10px;"><button type="button" style="background-color: #6c757d;">Limpiar Filtro</button></a>
            <?php endif; ?>
        </form>


        <?php if (!empty($todos_los_pedidos)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nº Pedido</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Correo Cliente</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todos_los_pedidos as $pedido): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pedido['id_pedido']); ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($pedido['fecha_pedido']))); ?></td>
                            <td><?php echo htmlspecialchars($pedido['nombre_cliente'] . ' ' . $pedido['apellidos_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($pedido['correo_cliente']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($pedido['total'], 2)); ?></td>
                            <td>
                                <form action="pedidos_actualizar_estado.php" method="post" style="display:inline;">
                                    <input type="hidden" name="id_pedido_actualizar" value="<?php echo $pedido['id_pedido']; ?>">
                                    <select name="nuevo_estado" onchange="this.form.submit()" title="Cambiar estado del pedido">
                                        <?php foreach ($estados_posibles as $estado_opcion): ?>
                                            <option value="<?php echo htmlspecialchars($estado_opcion); ?>" <?php echo ($pedido['estado'] === $estado_opcion) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucfirst($estado_opcion)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    </form>
                            </td>
                            <td>
                                <a href="<?php echo obtener_url_base(); ?>admin/detalle_pedido_admin.php?id=<?php echo htmlspecialchars($pedido['id_pedido']); ?>">
                                    <button type="button" style="font-size:0.9em; padding: 5px 8px;">Ver Detalle</button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (empty($mensaje_error_lista_pedidos)): ?>
            <p>No hay pedidos para mostrar<?php echo !empty($filtro_estado) ? " con el estado '" . htmlspecialchars($filtro_estado) . "'" : ""; ?>.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Modificación para admin/index.php para mostrar mensaje de login requerido
// Este mensaje se establece en esta página si el admin no está logueado.
// El archivo admin/index.php ya tiene una lógica para mostrar $_SESSION['mensaje_flash_admin_login']
// así que no es necesario añadir más aquí.

require_once __DIR__ . '/../includes/footer.php'; // Incluye el footer común
?>
