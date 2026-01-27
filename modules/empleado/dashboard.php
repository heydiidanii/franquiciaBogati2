<?php
// modules/empleado/dashboard.php

require_once '../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'empleado') {
    header('Location: ../../login.php');
    exit();
}

// Verificar tiempo de inactividad
$inactive_time = 1800;
if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > $inactive_time) {
        session_unset();
        session_destroy();
        header('Location: ../../login.php?expired=1');
        exit();
    }
}

$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Empleado - Bogati</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #FFD166 0%, #FFB347 100%);
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #2c2c2c;
        }
        .sidebar a {
            color: #fff;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            border-left: 3px solid transparent;
        }
        .sidebar a:hover {
            background-color: rgba(255,255,255,0.1);
            border-left: 3px solid #FFD166;
        }
        .sidebar a.active {
            background-color: rgba(255,255,255,0.1);
            border-left: 3px solid #FFD166;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user"></i> BOGATI EMPLEADO
            </a>
            
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['user_nombres'] . ' ' . $_SESSION['user_apellidos']); ?>
                </span>
                <a href="../../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link active">
                                <i class="fas fa-tachometer-alt me-2"></i> Inicio
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="ventas.php" class="nav-link">
                                <i class="fas fa-cash-register me-2"></i> Registrar Venta
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="inventario.php" class="nav-link">
                                <i class="fas fa-ice-cream me-2"></i> Ver Inventario
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="historial.php" class="nav-link">
                                <i class="fas fa-history me-2"></i> Historial Ventas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="turno.php" class="nav-link">
                                <i class="fas fa-clock me-2"></i> Mi Turno
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="perfil.php" class="nav-link">
                                <i class="fas fa-user me-2"></i> Mi Perfil
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Contenido principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h2 mb-4">Bienvenido Empleado</h1>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Información del Empleado</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <i class="fas fa-id-badge me-2 text-primary"></i>
                                                <strong>ID Empleado:</strong> <?php echo htmlspecialchars($_SESSION['empleado_id'] ?? 'No disponible'); ?>
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-user me-2 text-success"></i>
                                                <strong>Nombre:</strong> <?php echo htmlspecialchars($_SESSION['user_nombres'] . ' ' . $_SESSION['user_apellidos']); ?>
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-store me-2 text-info"></i>
                                                <strong>Local:</strong> <?php echo htmlspecialchars($_SESSION['local_codigo'] ?? 'No asignado'); ?>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <i class="fas fa-user-tag me-2 text-warning"></i>
                                                <strong>Rol:</strong> Empleado
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-envelope me-2 text-danger"></i>
                                                <strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?>
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-calendar-alt me-2 text-secondary"></i>
                                                <strong>Último acceso:</strong> <?php echo date('d/m/Y H:i:s'); ?>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Este es tu panel de empleado. Aquí podrás registrar ventas, revisar el inventario y ver tu historial.
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>