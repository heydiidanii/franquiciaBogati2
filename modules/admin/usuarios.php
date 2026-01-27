<?php
require_once __DIR__ . '/../../config.php';      
require_once __DIR__ . '/../../includes/functions.php';  
require_once __DIR__ . '/../../db_connection.php';

startSession();
requireAuth();
requireAnyRole(['admin']);

$db = Database::getConnection();

// Procesar acciones
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'crear') {
        // Crear nuevo usuario
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $email = $_POST['email'];
        $rol = $_POST['rol'];
        $estado = $_POST['estado'];

        try {
            $stmt = $db->prepare("
                INSERT INTO usuarios_sistema 
                (username, password_hash, nombres, apellidos, email, rol, estado, ultimo_acceso) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $password, $nombres, $apellidos, $email, $rol, $estado]);
            
            // Log de actividad
            logActividad('CREAR_USUARIO', 'usuarios_sistema', "Usuario creado: $username");
            
            setFlashMessage('success', 'Usuario creado exitosamente');
            header('Location: usuarios.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al crear usuario: ' . $e->getMessage());
        }
    }
    elseif ($action === 'editar') {
        // Editar usuario existente
        $id_usuario = $_POST['id_usuario'];
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $email = $_POST['email'];
        $rol = $_POST['rol'];
        $estado = $_POST['estado'];

        try {
            $stmt = $db->prepare("
                UPDATE usuarios_sistema 
                SET nombres = ?, apellidos = ?, email = ?, rol = ?, estado = ?
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nombres, $apellidos, $email, $rol, $estado, $id_usuario]);
            
            // Si se proporcionó nueva contraseña
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE usuarios_sistema SET password_hash = ? WHERE id_usuario = ?");
                $stmt->execute([$password, $id_usuario]);
            }
            
            logActividad('ACTUALIZAR_USUARIO', 'usuarios_sistema', "Usuario actualizado ID: $id_usuario");
            
            setFlashMessage('success', 'Usuario actualizado exitosamente');
            header('Location: usuarios.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al actualizar usuario: ' . $e->getMessage());
        }
    }
    elseif ($action === 'eliminar') {
        // Eliminar usuario
        try {
            $stmt = $db->prepare("DELETE FROM usuarios_sistema WHERE id_usuario = ? AND id_usuario != ?");
            $stmt->execute([$id, $_SESSION['usuario_id']]);
            
            if ($stmt->rowCount() > 0) {
                logActividad('ELIMINAR_USUARIO', 'usuarios_sistema', "Usuario eliminado ID: $id");
                setFlashMessage('success', 'Usuario eliminado exitosamente');
            } else {
                setFlashMessage('warning', 'No se puede eliminar su propio usuario');
            }
            
            header('Location: usuarios.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al eliminar usuario: ' . $e->getMessage());
        }
    }
}

// Obtener lista de usuarios
$usuarios = [];
try {
    $stmt = $db->prepare("
        SELECT 
            us.*,
            f.nombres as franquiciado_nombre,
            f.apellidos as franquiciado_apellidos,
            e.nombres as empleado_nombre,
            e.apellidos as empleado_apellidos
        FROM usuarios_sistema us
        LEFT JOIN franquiciados f ON us.cedula_franquiciado = f.cedula
        LEFT JOIN empleados e ON us.id_empleado = e.id_empleado
        ORDER BY us.id_usuario DESC
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar usuarios: ' . $e->getMessage());
    setFlashMessage('error', 'Error al cargar usuarios');
}

// Obtener datos de usuario específico para editar
$usuarioEditar = null;
if ($action === 'editar' && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM usuarios_sistema WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $usuarioEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error al cargar usuario para editar: ' . $e->getMessage());
    }
}

$pageTitle = APP_NAME . ' - Gestión de Usuarios';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-users me-2"></i>Gestión de Usuarios
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                        <i class="fas fa-plus me-1"></i> Nuevo Usuario
                    </button>
                </div>
            </div>

            <!-- Mensajes Flash -->
            <?php displayFlashMessage(); ?>

            <!-- Tabla de Usuarios -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaUsuarios">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Nombre Completo</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Último Acceso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo $usuario['id_usuario']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($usuario['username']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $usuario['rol'] === 'ADMIN' ? 'danger' : 'primary'; ?>">
                                                <?php echo $usuario['rol']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $usuario['estado'] === 'ACTIVO' ? 'success' : 'secondary'; ?>">
                                                <?php echo $usuario['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="usuarios.php?action=editar&id=<?php echo $usuario['id_usuario']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                   data-bs-target="#modalUsuario" onclick="cargarUsuario(<?php echo $usuario['id_usuario']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($usuario['id_usuario'] != $_SESSION['usuario_id']): ?>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="confirmarEliminar(<?php echo $usuario['id_usuario']; ?>, '<?php echo htmlspecialchars($usuario['username']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal para crear/editar usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formUsuario" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_usuario" id="id_usuario" value="">
                    <input type="hidden" name="action" id="formAction" value="crear">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label" id="labelPassword">Contraseña *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="text-muted" id="passwordHelp">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombres" class="form-label">Nombres *</label>
                            <input type="text" class="form-control" id="nombres" name="nombres" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="apellidos" class="form-label">Apellidos *</label>
                            <input type="text" class="form-control" id="apellidos" name="apellidos" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="rol" class="form-label">Rol *</label>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="ADMIN">Administrador</option>
                                <option value="GERENTE">Gerente</option>
                                <option value="EMPLEADO">Empleado</option>
                                <option value="FRANQUICIADO">Franquiciado</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                                <option value="SUSPENDIDO">Suspendido</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Formulario oculto para eliminar -->
<form id="formEliminar" method="POST" style="display: none;">
    <input type="hidden" name="action" value="eliminar">
    <input type="hidden" name="id" id="idEliminar">
</form>

<script>
// Función para cargar datos de usuario en el modal
function cargarUsuario(id) {
    fetch(`ajax/get-usuario.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const usuario = data.usuario;
                document.getElementById('modalTitulo').textContent = 'Editar Usuario';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('id_usuario').value = usuario.id_usuario;
                document.getElementById('username').value = usuario.username;
                document.getElementById('username').readOnly = true;
                document.getElementById('password').required = false;
                document.getElementById('labelPassword').textContent = 'Nueva Contraseña (opcional)';
                document.getElementById('passwordHelp').textContent = 'Dejar en blanco para mantener la contraseña actual';
                document.getElementById('nombres').value = usuario.nombres;
                document.getElementById('apellidos').value = usuario.apellidos;
                document.getElementById('email').value = usuario.email;
                document.getElementById('rol').value = usuario.rol;
                document.getElementById('estado').value = usuario.estado;
            }
        })
        .catch(error => console.error('Error:', error));
}

// Función para confirmar eliminación
function confirmarEliminar(id, username) {
    if (confirm(`¿Está seguro de eliminar al usuario "${username}"?`)) {
        document.getElementById('idEliminar').value = id;
        document.getElementById('formEliminar').submit();
    }
}

// Resetear modal al cerrar
document.getElementById('modalUsuario').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitulo').textContent = 'Nuevo Usuario';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('formUsuario').reset();
    document.getElementById('username').readOnly = false;
    document.getElementById('password').required = true;
    document.getElementById('labelPassword').textContent = 'Contraseña *';
    document.getElementById('passwordHelp').textContent = 'Mínimo 6 caracteres';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>