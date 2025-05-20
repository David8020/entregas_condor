<?php
// logout.php (Script para cerrar la sesión del cliente - Simplificado)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/backend/config.php'; 
$pdo = conectarDB(); 

$usuario_id_logout = $_SESSION['cliente_id'] ?? ($_SESSION['admin_id'] ?? null); // Para admin o cliente
$tipo_usuario_logout = isset($_SESSION['cliente_id']) ? 'Cliente' : (isset($_SESSION['admin_id']) ? 'Admin' : 'Desconocido');


// Eliminar todas las variables de sesión.
$_SESSION = array();

// Destruir la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Registrar en bitácora
if ($pdo && $usuario_id_logout) {
    registrarEnBitacora($pdo, $usuario_id_logout, "Logout {$tipo_usuario_logout} Exitoso", null);
}

// Función para obtener la URL base (copiada para autonomía)
if (!function_exists('obtener_url_base')) {
    function obtener_url_base() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $project_folder_name = 'entregas_condor';
        $path_to_project = '/';
        $script_name_parts = explode('/', $_SERVER['SCRIPT_NAME']);
        foreach ($script_name_parts as $part) {
            if (empty($part)) continue;
            if ($part == $project_folder_name) {
                $path_to_project .= $part . '/';
                break;
            }
            $path_to_project .= $part . '/';
        }
         if (strpos($_SERVER['SCRIPT_NAME'], '/' . $project_folder_name . '/') === false && $project_folder_name !== '') {
             $path_to_project = '/' . ($project_folder_name ? $project_folder_name . '/' : '');
        }
        return $protocol . $host . $path_to_project;
    }
}

// Redirigir al usuario a la página de inicio con un mensaje vía GET.
header("Location: " . obtener_url_base() . "index.php?mensaje_logout=1"); 
exit; 
?>

