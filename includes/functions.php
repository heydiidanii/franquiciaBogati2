<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_connection.php';

/* ======================================================
   MANEJO DE SESIÓN
====================================================== */

/**
 * Inicia o reanuda una sesión
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configuración de sesión segura
        session_set_cookie_params([
            'lifetime' => 86400, // 24 horas
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
        session_regenerate_id(true);
    }
}

/**
 * Verifica si el usuario está autenticado
 */
function isAuthenticated() {
    return isset($_SESSION['user_id'], $_SESSION['is_authenticated']) 
           && $_SESSION['is_authenticated'] === true;
}

/**
 * Obliga a que el usuario esté autenticado
 */
function requireAuth() {
    if (!isAuthenticated()) {
        $redirect = isset($_SERVER['REQUEST_URI']) ? '?redirect=' . urlencode($_SERVER['REQUEST_URI']) : '';
        header('Location: ' . BASE_URL . 'login.php' . $redirect);
        exit();
    }
}

/**
 * Cierra la sesión del usuario
 */
function logout() {
    startSession();
    
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'Desconocido';
    
    // Registrar cierre de sesión si hay usuario
    if ($user_id) {
        try {
            $db = Database::getConnection();
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
            error_log("Error al registrar logout: " . $e->getMessage());
        }
    }
    
    // Destruir la sesión
    session_destroy();
    
    // Redirigir al login
    header('Location: ' . BASE_URL . 'login.php?logout=1');
    exit();
}

/**
 * Redirige al dashboard si el usuario ya está autenticado
 */
function redirectIfAuthenticated() {
    if (isAuthenticated()) {
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit();
    }
}

/* ======================================================
   FUNCIONES AUXILIARES GENERALES
====================================================== */

/**
 * Formatear fecha y hora
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($datetime));
}

/**
 * Calcular edad desde fecha de nacimiento
 */
function calculateAge($birthdate) {
    if (empty($birthdate)) return 'N/A';
    
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}

/**
 * Obtener días restantes hasta una fecha
 */
function daysRemaining($date) {
    if (empty($date)) return 0;
    
    $target = new DateTime($date);
    $today = new DateTime();
    
    if ($today > $target) {
        return 0; // Ya pasó la fecha
    }
    
    $diff = $today->diff($target);
    return $diff->days;
}

/**
 * Obtener estado como badge de Bootstrap
 */
function getStatusBadge($status) {
    $badges = [
        'ACTIVO' => 'success',
        'INACTIVO' => 'secondary',
        'PENDIENTE' => 'warning',
        'APROBADO' => 'info',
        'VIGENTE' => 'success',
        'VENCIDO' => 'danger',
        'SUSPENDIDO' => 'warning',
        'PROSPECTO' => 'info',
        'PAGADO' => 'success',
        'MORA' => 'danger',
        'BLOQUEADO' => 'danger',
        'VACACIONES' => 'info'
    ];
    
    $color = $badges[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . $status . '</span>';
}

/**
 * Obtener cargo formateado
 */
function formatCargo($cargo) {
    $cargos = [
        'GERENTE' => 'Gerente',
        'HELADERO' => 'Heladero',
        'CAJERO' => 'Cajero',
        'AYUDANTE' => 'Ayudante',
        'LIMPIEZA' => 'Limpieza'
    ];
    
    return $cargos[$cargo] ?? $cargo;
}

/**
 * Obtener tipo de consumo formateado
 */
function formatTipoConsumo($tipo) {
    $tipos = [
        'LOCAL' => 'Consumo en local',
        'PARA_LLEVAR' => 'Para llevar'
    ];
    
    return $tipos[$tipo] ?? $tipo;
}

/**
 * Obtener forma de pago formateada
 */
function formatFormaPago($forma) {
    $formas = [
        'EFECTIVO' => 'Efectivo',
        'TARJETA' => 'Tarjeta',
        'TRANSFERENCIA' => 'Transferencia'
    ];
    
    return $formas[$forma] ?? $forma;
}

/**
 * Obtener nivel de franquicia formateado
 */
function formatNivelFranquicia($nivel) {
    $niveles = [
        'STANDARD' => 'Standard',
        'PREMIUM' => 'Premium'
    ];
    
    return $niveles[$nivel] ?? $nivel;
}

/**
 * Obtener tipo de capacitación formateado
 */
function formatTipoCapacitacion($tipo) {
    $tipos = [
        'INICIAL' => 'Inicial',
        'CONTINUA' => 'Continua',
        'ESPECIALIZADA' => 'Especializada'
    ];
    
    return $tipos[$tipo] ?? $tipo;
}

/**
 * Obtener tipo de campaña de marketing formateado
 */
function formatTipoCampana($tipo) {
    $tipos = [
        'REDES_SOCIALES' => 'Redes Sociales',
        'INFLUENCERS' => 'Influencers',
        'EVENTOS' => 'Eventos',
        'PUBLICIDAD_TRADICIONAL' => 'Publicidad Tradicional'
    ];
    
    return $tipos[$tipo] ?? $tipo;
}

/* ======================================================
   FUNCIONES DE INVENTARIO Y VENTAS
====================================================== */

/**
 * Verificar si un producto está disponible en un local
 */
function checkProductAvailability($codigo_local, $producto_id) {
    $db = Database::getConnection();
    
    try {
        $stmt = $db->prepare("
            SELECT cantidad, necesita_reabastecer 
            FROM inventario 
            WHERE codigo_local = ? AND codigo_producto = ?
        ");
        $stmt->execute([$codigo_local, $producto_id]);
        $inventario = $stmt->fetch();
        
        if (!$inventario) return false;
        
        return [
            'available' => $inventario['cantidad'] > 0,
            'quantity' => $inventario['cantidad'],
            'needs_restock' => $inventario['necesita_reabastecer']
        ];
    } catch (PDOException $e) {
        error_log("Error al verificar disponibilidad: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener ventas del día por local
 */
function getDailySales($codigo_local = null) {
    $db = Database::getConnection();
    
    try {
        if ($codigo_local) {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as cantidad_ventas,
                    SUM(total) as total_ventas,
                    AVG(total) as promedio_venta
                FROM ventas 
                WHERE codigo_local = ? AND DATE(fecha_venta) = CURDATE()
            ");
            $stmt->execute([$codigo_local]);
        } else {
            $stmt = $db->query("
                SELECT 
                    COUNT(*) as cantidad_ventas,
                    SUM(total) as total_ventas,
                    AVG(total) as promedio_venta
                FROM ventas 
                WHERE DATE(fecha_venta) = CURDATE()
            ");
        }
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error al obtener ventas del día: " . $e->getMessage());
        return ['cantidad_ventas' => 0, 'total_ventas' => 0, 'promedio_venta' => 0];
    }
}

/**
 * Obtener productos más vendidos
 */
function getTopProducts($limit = 5, $codigo_local = null) {
    $db = Database::getConnection();
    
    try {
        if ($codigo_local) {
            $stmt = $db->prepare("
                SELECT 
                    p.nombre,
                    p.codigo_producto,
                    SUM(dv.cantidad) as total_vendido,
                    SUM(dv.precio_unitario * dv.cantidad) as ingresos
                FROM detalle_ventas dv
                JOIN productos p ON dv.codigo_producto = p.codigo_producto
                JOIN ventas v ON dv.id_venta = v.id_venta
                WHERE v.codigo_local = ?
                GROUP BY p.codigo_producto
                ORDER BY total_vendido DESC
                LIMIT ?
            ");
            $stmt->execute([$codigo_local, $limit]);
        } else {
            $stmt = $db->prepare("
                SELECT 
                    p.nombre,
                    p.codigo_producto,
                    SUM(dv.cantidad) as total_vendido,
                    SUM(dv.precio_unitario * dv.cantidad) as ingresos
                FROM detalle_ventas dv
                JOIN productos p ON dv.codigo_producto = p.codigo_producto
                GROUP BY p.codigo_producto
                ORDER BY total_vendido DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error al obtener productos más vendidos: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener clientes frecuentes
 */
function getFrequentCustomers($limit = 5, $codigo_local = null) {
    $db = Database::getConnection();
    
    try {
        if ($codigo_local) {
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    COUNT(v.id_venta) as compras,
                    SUM(v.total) as total_gastado
                FROM clientes c
                JOIN ventas v ON c.id_cliente = v.id_cliente
                WHERE v.codigo_local = ?
                GROUP BY c.id_cliente
                ORDER BY compras DESC
                LIMIT ?
            ");
            $stmt->execute([$codigo_local, $limit]);
        } else {
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    COUNT(v.id_venta) as compras,
                    SUM(v.total) as total_gastado
                FROM clientes c
                JOIN ventas v ON c.id_cliente = v.id_cliente
                GROUP BY c.id_cliente
                ORDER BY compras DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error al obtener clientes frecuentes: " . $e->getMessage());
        return [];
    }
}

/**
 * Enviar notificación al usuario
 */
function sendNotification($user_id, $titulo, $mensaje, $tipo = 'INFO') {
    $db = Database::getConnection();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO notificaciones 
            (id_usuario, titulo, mensaje, tipo) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $titulo, $mensaje, $tipo]);
    } catch (PDOException $e) {
        error_log("Error al enviar notificación: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener reporte de ventas por período
 */
function getSalesReport($start_date, $end_date, $codigo_local = null) {
    $db = Database::getConnection();
    
    try {
        if ($codigo_local) {
            $stmt = $db->prepare("
                SELECT 
                    DATE(v.fecha_venta) as fecha,
                    COUNT(*) as cantidad_ventas,
                    SUM(v.total) as total_ventas,
                    AVG(v.total) as promedio_venta,
                    GROUP_CONCAT(DISTINCT v.forma_pago) as formas_pago
                FROM ventas v
                WHERE v.codigo_local = ? 
                    AND DATE(v.fecha_venta) BETWEEN ? AND ?
                GROUP BY DATE(v.fecha_venta)
                ORDER BY fecha
            ");
            $stmt->execute([$codigo_local, $start_date, $end_date]);
        } else {
            $stmt = $db->prepare("
                SELECT 
                    DATE(v.fecha_venta) as fecha,
                    l.nombre_local,
                    l.ciudad,
                    COUNT(*) as cantidad_ventas,
                    SUM(v.total) as total_ventas,
                    AVG(v.total) as promedio_venta
                FROM ventas v
                JOIN locales l ON v.codigo_local = l.codigo_local
                WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
                GROUP BY DATE(v.fecha_venta), v.codigo_local
                ORDER BY fecha, total_ventas DESC
            ");
            $stmt->execute([$start_date, $end_date]);
        }
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error al obtener reporte de ventas: " . $e->getMessage());
        return [];
    }
}

/* ======================================================
   FUNCIONES DE VALIDACIÓN
====================================================== */

/**
 * Validar RUC ecuatoriano
 */
function validateRUC($ruc) {
    if (strlen($ruc) != 13) {
        return false;
    }
    
    // Validar formato básico
    if (!preg_match('/^[0-9]{13}$/', $ruc)) {
        return false;
    }
    
    // Los primeros dos dígitos deben ser válidos para Ecuador
    $provincia = substr($ruc, 0, 2);
    if ($provincia < 1 || $provincia > 24) {
        return false;
    }
    
    // El tercer dígito debe ser entre 0 y 6
    $tercer_digito = substr($ruc, 2, 1);
    if ($tercer_digito < 0 || $tercer_digito > 6) {
        return false;
    }
    
    return true;
}

/**
 * Validar cédula ecuatoriana
 */
function validateCedula($cedula) {
    if (strlen($cedula) != 10) {
        return false;
    }
    
    // Validar que sean solo números
    if (!preg_match('/^[0-9]{10}$/', $cedula)) {
        return false;
    }
    
    // Los primeros dos dígitos deben ser válidos para Ecuador
    $provincia = substr($cedula, 0, 2);
    if ($provincia < 1 || $provincia > 24) {
        return false;
    }
    
    // El tercer dígito debe ser menor a 6
    $tercer_digito = substr($cedula, 2, 1);
    if ($tercer_digito > 5) {
        return false;
    }
    
    // Algoritmo de validación de cédula ecuatoriana
    $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
    $suma = 0;
    
    for ($i = 0; $i < 9; $i++) {
        $valor = $cedula[$i] * $coeficientes[$i];
        if ($valor >= 10) {
            $valor -= 9;
        }
        $suma += $valor;
    }
    
    $ultimo_digito = $cedula[9];
    $resultado = 10 - ($suma % 10);
    if ($resultado == 10) {
        $resultado = 0;
    }
    
    return $resultado == $ultimo_digito;
}

/**
 * Formatear moneda
 */
function formatCurrency(float $amount, string $currency = 'USD'): string {
    return number_format($amount, 2) . ' ' . $currency;
}

/**
 * Generar código único
 */
function generateUniqueCode(string $prefix = '', int $length = 6): string {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = $prefix;
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $code;
}

/**
 * Validar email
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitizar entrada de datos
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/* ======================================================
   FUNCIONES ESPECÍFICAS DEL SISTEMA
====================================================== */

/**
 * Obtener información del usuario actual
 */
function getCurrentUserInfo() {
    startSession();
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $db = Database::getConnection();
    
    try {
        $stmt = $db->prepare("
            SELECT 
                us.*,
                e.codigo_local as empleado_codigo_local,
                e.id_empleado,
                e.cargo,
                f.cedula as franquiciado_cedula,
                f.nombres as franquiciado_nombres,
                f.apellidos as franquiciado_apellidos
            FROM usuarios_sistema us
            LEFT JOIN empleados e ON us.id_empleado = e.id_empleado
            LEFT JOIN franquiciados f ON us.cedula_franquiciado = f.cedula
            WHERE us.id_usuario = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error obteniendo información del usuario: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtener nombre del usuario actual
 */
function getCurrentUserName() {
    $userInfo = getCurrentUserInfo();
    
    if (!$userInfo) {
        return 'Usuario';
    }
    
    if (!empty($userInfo['nombres']) && !empty($userInfo['apellidos'])) {
        return trim($userInfo['nombres'] . ' ' . $userInfo['apellidos']);
    }
    
    if (!empty($userInfo['franquiciado_nombres']) && !empty($userInfo['franquiciado_apellidos'])) {
        return trim($userInfo['franquiciado_nombres'] . ' ' . $userInfo['franquiciado_apellidos']);
    }
    
    return $userInfo['username'] ?? 'Usuario';
}

/**
 * Obtener rol del usuario actual
 */
function getCurrentUserRole() {
    $userInfo = getCurrentUserInfo();
    return $userInfo['rol'] ?? $_SESSION['user_role'] ?? null;
}

/**
 * Verificar si el usuario tiene un rol específico
 */
function hasRole($role) {
    return getCurrentUserRole() === $role;
}

/**
 * Verificar si el usuario tiene al menos uno de los roles especificados
 */
function hasAnyRole(array $roles) {
    $userRole = getCurrentUserRole();
    return in_array($userRole, $roles);
}

/**
 * Verificar si el usuario es administrador
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Verificar si el usuario es franquiciado
 */
function isFranquiciado() {
    return hasRole('franquiciado');
}

/**
 * Verificar si el usuario es empleado
 */
function isEmpleado() {
    return hasRole('empleado');
}

/**
 * Requiere que el usuario tenga un rol específico
 */
function requireRole($role) {
    requireAuth();
    
    if (!hasRole($role)) {
        header('Location: ' . BASE_URL . 'unauthorized.php');
        exit();
    }
}

/**
 * Requiere que el usuario tenga al menos uno de los roles especificados
 */
function requireAnyRole(array $roles) {
    requireAuth();
    
    if (!hasAnyRole($roles)) {
        header('Location: ' . BASE_URL . 'unauthorized.php');
        exit();
    }
}

/**
 * Registrar acción en el sistema
 */
function logAction($accion, $detalle = '', $tabla = null, $id_registro = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO logs_sistema (id_usuario, accion, detalle, tabla_afectada, id_registro_afectado, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $accion, $detalle, $tabla, $id_registro, $ip]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logAction: " . $e->getMessage());
        return false;
    }
}

/**
 * Función redirect para redirecciones
 */
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit();
}

/**
 * Establecer mensaje flash
 */
function setFlashMessage($type, $message) {
    startSession();
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

/**
 * Mostrar mensaje flash
 */
function displayFlashMessage() {
    startSession();
    if (!empty($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $typeClass = '';
        switch ($flash['type']) {
            case 'success':
                $typeClass = 'alert-success';
                break;
            case 'error':
                $typeClass = 'alert-danger';
                break;
            case 'warning':
                $typeClass = 'alert-warning';
                break;
            case 'info':
                $typeClass = 'alert-info';
                break;
            default:
                $typeClass = 'alert-primary';
        }
        
        echo "<div class='alert $typeClass alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($flash['message']);
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
        echo "</div>";
    }
}
?>