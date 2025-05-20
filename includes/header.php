<?php
// includes/header.php
// Iniciar sesión si no está iniciada. Debe ser lo primero.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Incluir configuración de la base de datos para tener acceso a $pdo y funciones
require_once __DIR__ . '/../backend/config.php';
$pdo = conectarDB(); // Establecer conexión

// Función para obtener la URL base del sitio
// La definimos aquí para que esté disponible en todos los archivos que incluyan el header.
if (!function_exists('obtener_url_base')) {
    function obtener_url_base() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        // Asumimos que el proyecto está en una carpeta llamada 'entregas_condor' directamente bajo htdocs
        $project_folder_name = 'entregas_condor'; // Nombre de la carpeta de tu proyecto
        $script_name_parts = explode('/', $_SERVER['SCRIPT_NAME']);
        $path_to_project = '/';
        foreach ($script_name_parts as $part) {
            if (empty($part)) continue;
            if ($part == $project_folder_name) {
                $path_to_project .= $part . '/';
                break;
            }
            $path_to_project .= $part . '/';
        }
        // Si no se encuentra la carpeta del proyecto en SCRIPT_NAME (ej. si está en la raíz de htdocs)
        // y el nombre del proyecto es el esperado, se usa directamente.
        // Esto es una simplificación. Una solución más robusta podría requerir configuración.
        if (strpos($_SERVER['SCRIPT_NAME'], '/' . $project_folder_name . '/') === false && $project_folder_name !== '') {
             // Si no está en una subcarpeta con el nombre del proyecto, asumimos que está en la raíz o una carpeta diferente.
             // Para este ejemplo, si la carpeta es 'entregas_condor', la URL base será /entregas_condor/
             // Si tu proyecto está en htdocs/ (sin subcarpeta 'entregas_condor'), $project_folder_name debería ser ''
             $path_to_project = '/' . ($project_folder_name ? $project_folder_name . '/' : '');
        }


        return $protocol . $host . $path_to_project;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregas Cóndor Ltda.</title>
    <link rel="stylesheet" href="<?php echo obtener_url_base(); ?>css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div id="branding">
                <h1><a href="<?php echo obtener_url_base(); ?>index.php">Entregas Cóndor Ltda.</a></h1>
            </div>
            <div style="float: right; color: white; padding-top: 10px;">
                <?php 
                if (isset($_SESSION['cliente_id'])) {
                    echo "Sesión Cliente: " . htmlspecialchars($_SESSION['cliente_nombre'] ?? 'Activa');
                } elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                    echo "Sesión Admin: Activa";
                } else {
                    echo "Sin Sesión Activa";
                }
                ?>
            </div>
        </div>
    </header>
    <?php // La navegación se incluye después en cada página ?>
