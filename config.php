<?php
// ==========================================
// CONFIGURACIÓN GENERAL
// ==========================================
define('APP_NAME', 'Bogati Franquicias');
define('APP_VERSION', '2.0.0');
define('ENVIRONMENT', 'development'); // development | production
define('BASE_URL', 'http://localhost/franquiciabogati2/');

// ==========================================
// ZONA HORARIA
// ==========================================
date_default_timezone_set('America/Guayaquil');

// ==========================================
// CONFIGURACIÓN DE ERRORES
// ==========================================
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// ==========================================
// BASE DE DATOS
// ==========================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'franquiciabogati2');
define('DB_CHARSET', 'utf8mb4');

// ==========================================
// ROLES DEL SISTEMA
// ==========================================
define('ROL_ADMIN', 'admin');
define('ROL_FRANQUICIADO', 'franquiciado');
define('ROL_EMPLEADO', 'empleado');

// ==========================================
// CONFIGURACIÓN DE SESIÓN
// ==========================================
define('SESSION_TIMEOUT', 3600); // 1 hora en segundos
define('SESSION_NAME', 'BOGATI_SESS');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

if (session_status() === PHP_SESSION_NONE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $sameSite = 'Strict';

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $sameSite
    ]);

    session_start();

    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 300) { // 5 minutos
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// ==========================================
// CONFIGURACIÓN DE SEGURIDAD
// ==========================================
define('PASSWORD_MIN_LENGTH', 8);

// ==========================================
// CONFIGURACIÓN DE ARCHIVOS
// ==========================================
define('MAX_FILE_SIZE', 2097152); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// ==========================================
// CONFIGURACIÓN DE CORREO (opcional)
// ==========================================
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USER', 'sistema@bogati.com');
define('MAIL_PASS', '');
define('MAIL_PORT', 587);

// ==========================================
// FUNCIONES ÚTILES
// ==========================================

/**
 * Obtiene la URL base con una ruta opcional
 */
function base_url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

/**
 * Verifica si la petición es AJAX
 */
function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Obtiene la IP del cliente
 */
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Sanitiza datos de entrada
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ==========================================
// HEADERS DE SEGURIDAD
// ==========================================
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if (ENVIRONMENT === 'production') {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:;");
    }
}
?>