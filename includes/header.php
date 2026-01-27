<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

// Mostrar errores mientras depuras
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Requerir autenticación
requireAuth();

// Establecer título de página si no está definido
if (!isset($page_title)) {
    $page_title = APP_NAME . ' - Panel';
}

// Obtener información del usuario
$user_role = $_SESSION['user_role'] ?? '';
$user_name = $_SESSION['user_name'] ?? 'Usuario';
$user_id = $_SESSION['user_id'] ?? 0;

// DEBUG: Si no hay rol, intentar obtenerlo de la BD
if (empty($user_role) && $user_id > 0) {
    try {
        require_once __DIR__ . '/../database/connection.php';
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT rol, nombres, apellidos FROM usuarios_sistema WHERE id_usuario = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();

        if ($result) {
            $_SESSION['user_role'] = $result['rol'];
            $_SESSION['user_name'] = $result['nombres'] . ' ' . $result['apellidos'];
            $user_role = $result['rol'];
            $user_name = $_SESSION['user_name'];
        }
    } catch (Exception $e) {
        error_log("Error obteniendo rol: " . $e->getMessage());
    }
}

// Normalizar rol para consistencia
$user_role = strtolower(trim($user_role));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? APP_NAME . ' - Sistema'); ?></title>

    <!-- Bootstrap CSS (última versión estable) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome (última versión estable) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <!-- Fuentes de Google -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo BASE_URL; ?>imagenes/favicon.ico" type="image/x-icon">

    <!-- Estilos adicionales de página -->
    <?php if (!empty($pageStyles)): ?>
        <?php foreach ($pageStyles as $style): ?>
            <link rel="stylesheet" href="<?php echo BASE_URL . 'css/' . $style; ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Scripts adicionales de página (cargados en <head> si es necesario) -->
    <?php if (!empty($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <?php if (strpos($script, 'http') === 0): ?>
                <script src="<?php echo $script; ?>"></script>
            <?php else: ?>
                <script src="<?php echo BASE_URL . 'js/' . $script; ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body>

    <!-- Navegación principal -->
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm fixed-top" 
     style="background: linear-gradient(90deg, #dfb59b, #d08e5e, #f5ab61); transition: all 0.3s;">
    <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>dashboard.php">
                <img src="<?php echo BASE_URL; ?>imagenes/Logo.png" alt="Bogati" height="80" class="me-2">
            </a>

            <!-- Botón para móviles -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>


        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- MENÚ IZQUIERDO -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>dashboard.php">
                       <i class="fas fa-home me-1"></i> Dashboard
                    </a>
                </li>

                    <!-- ADMINISTRACIÓN -->
                <?php if ($user_role === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo str_contains($_SERVER['REQUEST_URI'], 'modules/admin/') ? 'active' : ''; ?>" 
                       href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs me-1"></i> Administración
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark" style="background: linear-gradient(180deg, #7a5c42, #62391b);">
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>" href="usuarios.php">
                            <i class="fas fa-users me-2"></i> Usuarios</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'franquiciados.php' ? 'active' : ''; ?>" href="franquiciados.php">
                            <i class="fas fa-handshake me-2"></i> Franquiciados</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'locales.php' ? 'active' : ''; ?>" href="locales.php">
                            <i class="fas fa-store me-2"></i> Locales</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'empleados.php' ? 'active' : ''; ?>" href="empleados.php">
                            <i class="fas fa-user-tie me-2"></i> Empleados</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'productos.php' ? 'active' : ''; ?>" href="productos.php">
                            <i class="fas fa-ice-cream me-2"></i> Productos</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'inventario.php' ? 'active' : ''; ?>" href="inventario.php">
                            <i class="fas fa-boxes me-2"></i> Inventario</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'ventas.php' ? 'active' : ''; ?>" href="ventas.php">
                            <i class="fas fa-chart-line me-2"></i> Ventas</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'contratos.php' ? 'active' : ''; ?>" href="contratos.php">
                            <i class="fas fa-file-contract me-2"></i> Contratos</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'pagos.php' ? 'active' : ''; ?>" href="pagos.php">
                            <i class="fas fa-dollar-sign me-2"></i> Pagos</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'marketing.php' ? 'active' : ''; ?>" href="marketing.php">
                            <i class="fas fa-bullhorn me-2"></i> Marketing</a></li>
                        <li><a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>" href="reportes.php">
                            <i class="fas fa-chart-bar me-2"></i> Reportes</a></li>
                    </ul>
                </li>


                    <?php elseif ($user_role === 'franquiciado'): ?>
                        <!-- FRANQUICIADO -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo str_contains($_SERVER['REQUEST_URI'], 'pages/franquiciado/') ? 'active' : ''; ?>"
                                href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-store me-1"></i> Mis Locales
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'locales.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/franquiciado/locales.php">
                                        <i class="fas fa-store-alt me-2"></i>Mis Locales
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'ventas.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/franquiciado/ventas.php">
                                        <i class="fas fa-chart-line me-2"></i>Ventas
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'contratos.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/franquiciado/contratos.php">
                                        <i class="fas fa-file-signature me-2"></i>Contratos
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'pagos.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/franquiciado/pagos.php">
                                        <i class="fas fa-credit-card me-2"></i>Pagos
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'empleados.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/franquiciado/empleados.php">
                                        <i class="fas fa-users me-2"></i>Empleados
                                    </a>
                                </li>
                            </ul>
                        </li>

                    <?php elseif ($user_role === 'empleado'): ?>
                        <!-- EMPLEADO -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo str_contains($_SERVER['REQUEST_URI'], 'pages/empleado/') ? 'active' : ''; ?>"
                                href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-tie me-1"></i> Operaciones
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'ventas.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/empleado/ventas.php">
                                        <i class="fas fa-shopping-cart me-2"></i>Ventas
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'inventario.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/empleado/inventario.php">
                                        <i class="fas fa-box me-2"></i>Inventario
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'capacitaciones.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/empleado/capacitaciones.php">
                                        <i class="fas fa-book me-2"></i>Capacitaciones
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'horarios.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/empleado/horarios.php">
                                        <i class="fas fa-calendar-alt me-2"></i>Horarios
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>pages/empleado/perfil.php">
                                        <i class="fas fa-id-card me-2"></i>Mi Perfil
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>

<!-- Menú de usuario (derecha) -->
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Notificaciones -->
                    <?php
                    // Obtener notificaciones no leídas
                    $notificaciones_count = 0;
                    if ($user_id > 0) {
                        try {
                            require_once __DIR__ . '/../db_connection.php';
                            $db = Database::getConnection();
                            $stmt = $db->prepare("SELECT COUNT(*) as count FROM notificaciones WHERE id_usuario = ? AND leida = 0");
                            $stmt->execute([$user_id]);
                            $result = $stmt->fetch();
                            $notificaciones_count = $result['count'] ?? 0;
                        } catch (Exception $e) {
                            // Silenciar error
                        }
                    }
                    ?>
                    <li class="nav-item dropdown me-2">
                        <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-bell fa-lg"></i>
                            <?php if ($notificaciones_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
                                    <?php echo $notificaciones_count; ?>
                                    <span class="visually-hidden">notificaciones no leídas</span>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="width: 300px; background-color:#7a5c42;">
                            <li class="dropdown-header text-light">Notificaciones</li>
                            <?php if ($notificaciones_count > 0): ?>
                                <li><a class="dropdown-item text-light" href="<?php echo BASE_URL; ?>modules/notificaciones.php">Tienes <?php echo $notificaciones_count; ?> notificaciones nuevas</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item text-muted" href="#">No hay notificaciones</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center small text-warning" href="<?php echo BASE_URL; ?>modules/notificaciones.php">Ver todas</a></li>
                        </ul>
                    </li>

                    <!-- Usuario - ESTE SIEMPRE DEBE APARECER -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($user_name); ?></span>
                            <small class="badge bg-light text-primary ms-1"><?php echo htmlspecialchars(ucfirst($user_role)); ?></small>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header">
                                <small>Conectado como</small><br>
                                <strong><?php echo htmlspecialchars($user_name); ?></strong>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/<?php echo $user_role; ?>/perfil.php">
                                    <i class="fas fa-user me-2"></i> Mi Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/configuracion.php">
                                    <i class="fas fa-cog me-2"></i> Configuración
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/ayuda.php">
                                    <i class="fas fa-question-circle me-2"></i> Ayuda
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="py-4" style="margin-top: 70px;">
        <div class="container-fluid">
            <!-- DEBUG temporal -->
            <?php if (isset($_GET['debug']) || empty($user_role)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <h5>Información de Depuración</h5>
                    <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
                    <p><strong>User Role:</strong> <?php echo htmlspecialchars($user_role); ?></p>
                    <p><strong>User Name:</strong> <?php echo htmlspecialchars($user_name); ?></p>
                    <p><strong>Session Role:</strong> <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'NO'); ?></p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Breadcrumb -->
            <?php if (isset($show_breadcrumb) && $show_breadcrumb): ?>
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard.php">
                                <i class="fas fa-home"></i>
                            </a>
                        </li>
                        <?php if (isset($breadcrumb_items)): ?>
                            <?php foreach ($breadcrumb_items as $item): ?>
                                <?php if (isset($item['url'])): ?>
                                    <li class="breadcrumb-item">
                                        <a href="<?php echo $item['url']; ?>">
                                            <?php echo htmlspecialchars($item['text']); ?>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="breadcrumb-item active" aria-current="page">
                                        <?php echo htmlspecialchars($item['text']); ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </nav>
            <?php endif; ?>

            <!-- Mensajes de alerta -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['warning_message'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['warning_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['warning_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['info_message'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['info_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['info_message']); ?>
            <?php endif; ?>