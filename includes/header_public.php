<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? APP_NAME); ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Fuentes de Google -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">

    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>assets/images/favicon.ico">
</head>

<body class="public-page">

    <!-- Header/Navigation -->
    <header class="header">
        <div class="header-container">

            <div class="header-logo">
                <img src="imagenes/bogati-Ecuador.png" class="logo-img" alt="Bogati Ecuador">
            </div>

            <nav class="main-nav">
                <ul class="navbar-nav">
                    <li><a class="nav-link active" href="index.php">INICIO</a></li>
                    <li><a class="nav-link" href="pages/public/productos.php">PRODUCTOS</a></li>
                    <li><a class="nav-link" href="#nosotros">NOSOTROS</a></li>
                </ul>
            </nav>

            <div class="login-btn-container">
                <a class="login-btn" href="login.php">
                    <i class="fas fa-user"></i> INICIAR SESIÓN
                </a>
            </div>

        </div>
    </header>