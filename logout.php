<?php
// logout.php
require_once __DIR__ . '/config.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guardar información del usuario antes de destruir la sesión
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Desconocido';

// Registrar cierre de sesión en la BD si hay usuario
if ($user_id) {
    try {
         require_once __DIR__ . '/db_connection.php';
        $db = Database::getConnection();
        
        // Insertar en logs_sistema según tu tabla real
        $stmt = $db->prepare("
            INSERT INTO logs_sistema 
            (id_usuario, accion, tabla_afectada, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            'LOGOUT',
            'usuarios_sistema',
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    } catch (Exception $e) {
        // Si hay error con la BD, solo registrar en error_log
        error_log("Error al registrar logout en BD: " . $e->getMessage());
    }
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Redirigir al login con mensaje de éxito
session_start(); // Iniciar nueva sesión para el mensaje
$_SESSION['success_message'] = 'Sesión cerrada correctamente. ¡Hasta pronto!';
header('Location: ' . BASE_URL . 'login.php');
exit();
?>