<?php
// cancelar_pedido.php
// Script para que el cliente cancele un pedido.

require_once __DIR__ . '/includes/header.php'; // Para session_start(), $pdo, obtener_url_base()

// Verificar login de cliente
if (!isset($_SESSION['cliente_id'])) {
    $_SESSION['mensaje_flash_login'] = "Debes iniciar sesión para gestionar tus pedidos.";
    $_SESSION['mensaje_flash_login_tipo'] = "error";
    header("Location: " . obtener_url_base() . "login.php");
    exit;
}

$idCliente = $_SESSION['cliente_id'];
$url_redireccion_defecto = obtener_url_base() . "mis_pedidos.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pedido_cancelar'])) {
    $id_pedido_cancelar = filter_input(INPUT_POST, 'id_pedido_cancelar', FILTER_VALIDATE_INT);

    if (!$id_pedido_cancelar) {
        $_SESSION['mensaje_flash_detalle_pedido'] = "ID de pedido inválido para cancelar.";
        $_SESSION['mensaje_flash_detalle_pedido_tipo'] = "error";
        header("Location: " . $url_redireccion_defecto);
        exit;
    }

    // Construir la URL de redirección de vuelta al detalle del pedido específico
    $url_redireccion_detalle = obtener_url_base() . "detalle_pedido.php?id=" . $id_pedido_cancelar;

    try {
        $pdo->beginTransaction();

        // 1. Verificar que el pedido pertenece al cliente y está en un estado cancelable
        $sql_check = "SELECT estado, id_cliente FROM Pedido WHERE id_pedido = :id_pedido FOR UPDATE"; // FOR UPDATE para bloquear
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->bindParam(':id_pedido', $id_pedido_cancelar, PDO::PARAM_INT);
        $stmt_check->execute();
        $pedido_actual = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$pedido_actual) {
            $_SESSION['mensaje_flash_detalle_pedido'] = "Pedido Nº{$id_pedido_cancelar} no encontrado.";
            $_SESSION['mensaje_flash_detalle_pedido_tipo'] = "error";
            $pdo->rollBack();
            header("Location: " . $url_redireccion_detalle);
            exit;
        }

        if ($pedido_actual['id_cliente'] != $idCliente) {
            $_SESSION['mensaje_flash_detalle_pedido'] = "No tienes permiso para cancelar el pedido Nº{$id_pedido_cancelar}.";
            $_SESSION['mensaje_flash_detalle_pedido_tipo'] = "error";
            registrarEnBitacora($pdo, $idCliente, 'Intento Cancelar Pedido Ajeno', json_encode(['id_pedido_intentado' => $id_pedido_cancelar]));
            $pdo->rollBack();
            header("Location: " . $url_redireccion_detalle);
            exit;
        }

        $estados_cancelables = ['Pendiente', 'Procesando'];
        if (!in_array($pedido_actual['estado'], $estados_cancelables)) {
            $_SESSION['mensaje_flash_detalle_pedido'] = "El pedido Nº{$id_pedido_cancelar} ya no se puede cancelar (Estado actual: " . htmlspecialchars($pedido_actual['estado']) . ").";
            $_SESSION['mensaje_flash_detalle_pedido_tipo'] = "warning";
            $pdo->rollBack();
            header("Location: " . $url_redireccion_detalle);
            exit;
        }

        // 2. Actualizar el estado del pedido a 'Cancelado'
        $sql_update = "UPDATE Pedido SET estado = 'Cancelado' WHERE id_pedido = :id_pedido";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->bindParam(':id_pedido', $id_pedido_cancelar, PDO::PARAM_INT);
        
        if ($stmt_update->execute()) {
            // NOTA: En esta versión "novato", NO estamos revirtiendo el stock al inventario.
            // Eso sería una lógica adicional importante en un sistema real.
            // Ejemplo de cómo se podría hacer (requiere obtener los detalles del pedido primero):
            /*
            $sql_detalles_pedido = "SELECT id_producto, cantidad FROM DetallePedido WHERE id_pedido = :id_pedido";
            $stmt_detalles_canc = $pdo->prepare($sql_detalles_pedido);
            $stmt_detalles_canc->bindParam(':id_pedido', $id_pedido_cancelar, PDO::PARAM_INT);
            $stmt_detalles_canc->execute();
            $items_cancelados = $stmt_detalles_canc->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items_cancelados as $item_c) {
                $sql_reponer_stock = "UPDATE Inventario SET cantidad_disponible = cantidad_disponible + :cantidad WHERE id_producto = :id_producto";
                $stmt_reponer = $pdo->prepare($sql_reponer_stock);
                $stmt_reponer->bindParam(':cantidad', $item_c['cantidad'], PDO::PARAM_INT);
                $stmt_reponer->bindParam(':id_producto', $item_c['id_producto'], PDO::PARAM_INT);
                $stmt_reponer->execute();
            }
            */

            $pdo->commit();
            $_SESSION['mensaje_flash_detalle_pedido'] = "Pedido Nº{$id_pedido_cancelar} cancelado exitosamente.";
            $_SESSION['mensaje_flash_detalle_pedido_tipo'] = "success";
            registrarEnBitacora($pdo, $idCliente, 'Cliente Cancela Pedido', json_encode(['id_pedido' => $id_pedido_cancelar]));
        } else {
            $pdo->rollBack();
            $_SESSION['mensaje_flash_detalle_pedido'] = "Error al intentar cancelar el pedido Nº{$id_pedido_cancelar}.";
            $_SESSION['mensaje_flash_detalle_pedido_tipo'] = "error";
            registrarEnBitacora($pdo, $idCliente, 'Error Cliente Cancela Pedido', json_encode(['id_pedido' => $id_pedido_cancelar, 'errorInfo' => $stmt_update->errorInfo()]));
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['mensaje_flash_detalle_pedido'] = "Error de base de datos al cancelar el pedido: " . $e->getMessage();
        $_SESSION['mensaje_flash_detalle_pedido_tipo'] = "error";
        error_log("Error PDO en cancelar_pedido.php: " . $e->getMessage());
        registrarEnBitacora($pdo, $idCliente, 'Excepción PDO Cliente Cancela Pedido', $e->getMessage());
    }
    header("Location: " . $url_redireccion_detalle); // Redirigir de vuelta al detalle del pedido
    exit;

} else {
    // Si se accede directamente sin POST o faltan datos
    $_SESSION['mensaje_flash_mis_pedidos'] = "Acción no permitida o datos insuficientes."; // Mensaje para la lista de pedidos
    $_SESSION['mensaje_flash_mis_pedidos_tipo'] = "error";
    header("Location: " . $url_redireccion_defecto);
    exit;
}
?>
