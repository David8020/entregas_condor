<?php
// admin/pedidos_actualizar_estado.php
// Script para procesar la actualización del estado de un pedido.

require_once __DIR__ . '/../includes/header.php'; // Para session_start(), $pdo, obtener_url_base()

// Verificar si el admin está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Guardar mensaje para mostrar en la página de login de admin
    $_SESSION['mensaje_flash_admin_login'] = "Acceso no autorizado.";
    $_SESSION['mensaje_flash_admin_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "admin/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pedido_actualizar']) && isset($_POST['nuevo_estado'])) {
    $id_pedido = filter_input(INPUT_POST, 'id_pedido_actualizar', FILTER_VALIDATE_INT);
    $nuevo_estado = trim($_POST['nuevo_estado']);

    // Lista de estados válidos (debería coincidir con la de pedidos.php y la lógica de negocio)
    $estados_validos = ['Pendiente', 'Procesando', 'Enviado', 'Entregado', 'Cancelado'];

    if ($id_pedido && !empty($nuevo_estado) && in_array($nuevo_estado, $estados_validos)) {
        try {
            $sql = "UPDATE Pedido SET estado = :nuevo_estado WHERE id_pedido = :id_pedido";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nuevo_estado', $nuevo_estado, PDO::PARAM_STR);
            $stmt->bindParam(':id_pedido', $id_pedido, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['mensaje_flash_pedidos_admin'] = "Estado del pedido Nº{$id_pedido} actualizado a '{$nuevo_estado}'.";
                $_SESSION['mensaje_flash_pedidos_admin_tipo'] = "success";
                registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Actualización Estado Pedido', json_encode(['id_pedido' => $id_pedido, 'nuevo_estado' => $nuevo_estado]));
            } else {
                $_SESSION['mensaje_flash_pedidos_admin'] = "Error al actualizar el estado del pedido Nº{$id_pedido}.";
                $_SESSION['mensaje_flash_pedidos_admin_tipo'] = "error";
                registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Error Actualización Estado Pedido', json_encode(['id_pedido' => $id_pedido, 'errorInfo' => $stmt->errorInfo()]));
            }
        } catch (PDOException $e) {
            $_SESSION['mensaje_flash_pedidos_admin'] = "Error de base de datos al actualizar el estado: " . $e->getMessage();
            $_SESSION['mensaje_flash_pedidos_admin_tipo'] = "error";
            error_log("Error PDO en admin/pedidos_actualizar_estado.php: " . $e->getMessage());
            registrarEnBitacora($pdo, $_SESSION['admin_username'] ?? null, 'Excepción PDO Actualización Estado Pedido', $e->getMessage());
        }
    } else {
        $_SESSION['mensaje_flash_pedidos_admin'] = "Datos inválidos para actualizar el estado del pedido.";
        $_SESSION['mensaje_flash_pedidos_admin_tipo'] = "error";
    }
} else {
    // Si se accede directamente sin POST, o faltan datos.
    $_SESSION['mensaje_flash_pedidos_admin'] = "Acción no permitida o datos insuficientes.";
    $_SESSION['mensaje_flash_pedidos_admin_tipo'] = "error";
}

// Redirigir siempre de vuelta a la lista de pedidos
header("Location: " . obtener_url_base() . "admin/pedidos.php");
exit;
?>
