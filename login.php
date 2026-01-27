<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/includes/functions.php';


// Redirigir si ya está autenticado
redirectIfAuthenticated();


// Inicializar variables
$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rolSeleccionado = trim($_POST['rol'] ?? '');

    if (empty($username) || empty($password) || empty($rolSeleccionado)) {
        $error = 'Por favor, complete todos los campos';
    } else {
        $db = Database::getConnection();

        try {
            // Buscar usuario por username o email
            $stmt = $db->prepare("
                SELECT 
                    us.*,
                    e.codigo_local,
                    e.id_empleado,
                    f.cedula AS cedula_franquiciado,
                    f.nombres AS nombres_franquiciado,
                    f.apellidos AS apellidos_franquiciado
                FROM usuarios_sistema us
                LEFT JOIN empleados e ON us.id_empleado = e.id_empleado
                LEFT JOIN franquiciados f ON us.cedula_franquiciado = f.cedula
                WHERE (us.username = ? OR us.email = ?) AND us.rol = ?
                LIMIT 1
            ");
            $stmt->execute([$username, $username, $rolSeleccionado]);
            $user = $stmt->fetch();

            if ($user) {
                // Verificar contraseña
                if (password_verify($password, $user['password_hash']) || $password === $user['password_hash']) {
                    // Verificar estado del usuario
                    if ($user['estado'] !== 'ACTIVO') {
                        $error = 'Tu cuenta está ' . strtolower($user['estado']) . '. Contacta al administrador.';
                        logAction('LOGIN_FALLIDO', 'Usuario inactivo: ' . $username, 'usuarios_sistema');
                    } else {
                        // Configurar sesión
                        $_SESSION['user_id'] = $user['id_usuario'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_nombres'] = $user['nombres'] ?? ($user['nombres_franquiciado'] ?? '');
                        $_SESSION['user_apellidos'] = $user['apellidos'] ?? ($user['apellidos_franquiciado'] ?? '');
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['rol'];
                        $_SESSION['is_authenticated'] = true;
                        $_SESSION['last_activity'] = time();

                        // Local del usuario
                        if (!empty($user['cedula_franquiciado'])) {
                            $_SESSION['cedula_franquiciado'] = $user['cedula_franquiciado'];

                            // Obtener el primer local del franquiciado
                            $stmt_local = $db->prepare("SELECT codigo_local FROM locales WHERE cedula_franquiciado = ? LIMIT 1");
                            $stmt_local->execute([$user['cedula_franquiciado']]);
                            $local = $stmt_local->fetch();
                            $_SESSION['codigo_local'] = $local['codigo_local'] ?? null;
                        } elseif (!empty($user['id_empleado'])) {
                            $_SESSION['id_empleado'] = $user['id_empleado'];
                            $_SESSION['codigo_local'] = $user['codigo_local'];
                        } elseif ($user['rol'] === 'admin') {
                            $_SESSION['codigo_local'] = null; // admin no tiene local
                        }

                        // Actualizar último acceso
                        $updateStmt = $db->prepare("UPDATE usuarios_sistema SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id_usuario = ?");
                        $updateStmt->execute([$user['id_usuario']]);

                        // Registrar log
                        logAction('LOGIN_EXITOSO', 'Inicio de sesión desde ' . get_client_ip(), 'usuarios_sistema');

                        // Redirigir al dashboard
                        header('Location: ' . BASE_URL . 'dashboard.php');
                        exit();
                    }
                } else {
                    $error = 'Usuario o contraseña incorrectos';
                    logAction('LOGIN_FALLIDO', 'Contraseña incorrecta: ' . $username, 'usuarios_sistema');
                }
            } else {
                $error = 'Usuario no encontrado';
                logAction('LOGIN_FALLIDO', 'Usuario no encontrado: ' . $username, 'usuarios_sistema');
            }
        } catch (PDOException $e) {
            error_log('Error en login: ' . $e->getMessage());
            $error = 'Error en el sistema. Por favor, intente más tarde.';
        }
    }
}

// Incluir header público
$page_title = 'Iniciar Sesión - ' . APP_NAME;
include __DIR__ . '/includes/header_public.php';
?>

<!-- Contenedor centrado para el formulario -->
    <div class="login-container">
        <div class="login-card">
            
        <!-- MARCA -->
        <div class="brand-container">
            <span class="brand-text">Bogati</span>
        </div>

            <!-- Título -->
            <h2 class="login-title">Iniciar sesión</h2>

            <!-- Mostrar error si existe -->
            <?php if ($error): ?>
                <div class="alert-custom">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form method="POST" action="">
            <!-- Escoger Rol -->
            <div class="form-group">
                <label class="form-label">Escoger Rol</label>
                <select class="form-control-custom" name="rol" required>
                    <option value="">Seleccione un rol</option>
                    <option value="admin" <?= (($_POST['rol'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrador</option>
                    <option value="franquiciado" <?= (($_POST['rol'] ?? '') === 'franquiciado') ? 'selected' : '' ?>>Franquiciado</option>
                    <option value="empleado" <?= (($_POST['rol'] ?? '') === 'empleado') ? 'selected' : '' ?>>Empleado</option>
                </select>
            </div>

                <!-- Usuario -->
            <!-- Usuario o Email -->
            <div class="form-group">
                <label class="form-label">Usuario o Email</label>
                <input 
                    type="text"
                    class="form-control-custom"
                    name="username"
                    placeholder="Ingrese su usuario o email"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                    autofocus
                >
                <i class="fas fa-user input-icon"></i>
            </div>

                <!-- Contraseña -->
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input type="password" class="form-control-custom" name="password" placeholder="Ingrese su contraseña" required>
                    <i class="fas fa-lock input-icon"></i>
                </div>

                <!-- Botón -->
                <button type="submit" class="btn-acceso">Ingresar</button>

                <!-- Olvidó contraseña -->
                <div class="forgot-password">
                    <a href="#" class="forgot-link">¿Olvidó su contraseña?</a>
                </div>
            </form>
        </div>
    </div>
</div>
