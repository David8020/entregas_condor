<?php
// admin/logout_admin.php (Script para cerrar la sesión del Administrador)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/config.php'; 
$pdo = conectarDB(); 

$admin_username_logout = $_SESSION['admin_username'] ?? null;

// Específicamente eliminar las variables de sesión del admin
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_username']);
// No destruimos la sesión completa por si hay una sesión de cliente activa en otra pestaña (aunque es poco común)
// Si quisiéramos un logout total que afecte a cualquier usuario, usaríamos session_destroy().
// Pero para un logout específico de admin, solo quitamos sus variables.

// Registrar en bitácora
if ($pdo && $admin_username_logout) {
    registrarEnBitacora($pdo, null, 'Logout Admin Exitoso', json_encode(['username' => $admin_username_logout]));
}

// Función para obtener la URL base
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

// Redirigir al login de admin o al index principal
header("Location: " . obtener_url_base() . "admin/index.php?mensaje_logout_admin=1"); 
exit; 
?>
