<?php
// backend/config.php (Versión Novato)

// --- Configuración de la Base de Datos ---
// Estos son los valores por defecto para XAMPP.
// Si tu configuración de MySQL es diferente, ajústalos.

define('DB_HOST', 'localhost'); // El servidor donde está tu base de datos (usualmente localhost)
define('DB_NAME', 'entregas_condor_simple'); // El nombre de tu base de datos (el que creaste con schema.sql)
define('DB_USER', 'root');      // Tu nombre de usuario de MySQL (por defecto en XAMPP es 'root')
define('DB_PASS', '');          // Tu contraseña de MySQL (por defecto en XAMPP está vacía)
define('DB_CHARSET', 'utf8mb4'); // El conjunto de caracteres para la conexión

// --- Otras Configuraciones (Opcional) ---
// Define la zona horaria para las funciones de fecha y hora de PHP.
// Puedes encontrar la lista de zonas horarias soportadas en: https://www.php.net/manual/es/timezones.php
date_default_timezone_set('America/Bogota');

// Habilitar el reporte de todos los errores de PHP (útil durante el desarrollo)
// En un entorno de producción, esto debería configurarse de forma diferente (ej. loguear errores a un archivo).
error_reporting(E_ALL);
ini_set('display_errors', 1); // Muestra los errores en el navegador. ¡Desactiva esto en producción!

/**
 * Función para obtener una conexión a la base de datos usando PDO.
 * PDO (PHP Data Objects) es una forma moderna y segura de interactuar con bases de datos en PHP.
 *
 * @return PDO|null Retorna un objeto PDO si la conexión es exitosa, o null si falla.
 */
function conectarDB() {
    // DSN (Data Source Name) - Define la conexión a la base de datos.
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    // Opciones para la conexión PDO:
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en caso de errores SQL.
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve los resultados como arrays asociativos.
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa preparaciones nativas del SGBD para más seguridad.
    ];

    try {
        // Intenta crear una nueva instancia de PDO (establecer la conexión).
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        return $pdo; // Devuelve el objeto de conexión.
    } catch (PDOException $e) {
        // Si hay un error durante la conexión, muestra un mensaje y termina el script.
        // En una aplicación real, deberías manejar este error de forma más elegante (ej. loguearlo).
        // No expongas detalles del error al usuario final en producción.
        http_response_code(500); // Error interno del servidor
        echo json_encode([
            'error' => true,
            'mensaje' => 'Error de conexión a la base de datos.',
            'detalle_tecnico' => $e->getMessage() // Comentado para no exponer detalles en producción
        ]);
        // Para este ejemplo simple, terminamos la ejecución.
        // En un sistema más robusto, podrías querer loguear el error y mostrar una página amigable.
        exit; // Detiene la ejecución del script.
    }
}

/**
 * Función simple para registrar una acción en la bitácora.
 *
 * @param PDO $pdo La conexión a la base de datos.
 * @param int|null $usuario_id El ID del usuario que realiza la acción (puede ser null para acciones del sistema).
 * @param string $accion Descripción de la acción.
 * @param string|null $detalles Detalles adicionales (ej. datos JSON).
 * @return bool True si se insertó, false en caso contrario.
 */
function registrarEnBitacora(PDO $pdo, $usuario_id, string $accion, $detalles = null): bool {
    $sql = "INSERT INTO Bitacora (usuario_id, accion, detalles, fecha) VALUES (:usuario_id, :accion, :detalles, NOW())";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id, $usuario_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
        $stmt->bindParam(':detalles', $detalles, $detalles === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        return $stmt->execute();
    } catch (PDOException $e) {
        // En un caso real, loguear este error.
        // error_log("Error al registrar en bitácora: " . $e->getMessage());
        return false;
    }
}

?>
