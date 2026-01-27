<?php
// dashboard.php - SIMPLIFICADO
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php'; // Solo este

// DEBUG: Activar para ver errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión
startSession();

// DEBUG: Mostrar sesión actual
echo '<pre>SESSION ANTES DE VERIFICAR: ';
print_r($_SESSION);
echo '</pre>';

// Verificar autenticación
if (!isAuthenticated()) {
    echo '<p>DEBUG: No autenticado. Redirigiendo a login...</p>';
    header('Location: ' . BASE_URL . 'login.php?error=no_autenticado');
    exit();
}

echo '<p>DEBUG: Usuario autenticado. SESSION data:</p>';
echo '<pre>';
print_r($_SESSION);
echo '</pre>';

// Redirigir según rol
$user_role = $_SESSION['user_role'] ?? '';


switch ($user_role) {
    case 'admin':
        header('Location: modules/admin/dashboardA.php');
        exit();
    case 'franquiciado':
        header('Location: modules/franquiciado/dashboardF.php');
        exit();
    case 'empleado':
        header('Location: modules/empleado/dashboardE.php');
        exit();
    default:
        $_SESSION['error'] = 'Rol de usuario no válido';
        header('Location: login.php');
        exit();
}
?>