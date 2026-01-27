<?php
require_once __DIR__ . '/../../config.php';      
require_once __DIR__ . '/../../includes/functions.php';  
require_once __DIR__ . '/../../db_connection.php';

startSession();
requireAuth();
requireAnyRole(['admin']);

$db = Database::getConnection();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$local = $_GET['local'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'crear') {
        $cedula = $_POST['cedula'];
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $telefono = $_POST['telefono'];
        $email = $_POST['email'];
        $cargo = $_POST['cargo'];
        $salario = $_POST['salario'];
        $codigo_local = $_POST['codigo_local'];
        $estado = $_POST['estado'];

        try {
            $stmt = $db->prepare("
                INSERT INTO empleados 
                (cedula, nombres, apellidos, fecha_nacimiento, telefono, email, 
                 cargo, salario, codigo_local, estado, fecha_contratacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
            ");
            $stmt->execute([$cedula, $nombres, $apellidos, $fecha_nacimiento, $telefono, 
                          $email, $cargo, $salario, $codigo_local, $estado]);
            
            logActividad('CREAR_EMPLEADO', 'empleados', "Empleado creado: $cedula");
            setFlashMessage('success', 'Empleado creado exitosamente');
            header('Location: empleados.php' . ($local ? "?local=$local" : ''));
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al crear empleado: ' . $e->getMessage());
        }
    }
}

// Obtener empleados
$empleados = [];
$where = $local ? "WHERE e.codigo_local = ?" : "";
$params = $local ? [$local] : [];

try {
    $stmt = $db->prepare("
        SELECT e.*, l.nombre_local, l.ciudad,
               COUNT(ec.id_capacitacion) as capacitaciones_completadas
        FROM empleados e
        LEFT JOIN locales l ON e.codigo_local = l.codigo_local
        LEFT JOIN empleado_capacitacion ec ON e.id_empleado = ec.id_empleado AND ec.aprobado = 1
        $where
        GROUP BY e.id_empleado
        ORDER BY e.estado DESC, e.fecha_contratacion DESC
    ");
    $stmt->execute($params);
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar empleados: ' . $e->getMessage());
}

// Obtener locales para dropdown
$locales = [];
try {
    $stmt = $db->prepare("SELECT codigo_local, nombre_local, ciudad FROM locales ORDER BY nombre_local");
    $stmt->execute();
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar locales: ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' - Gestión de Empleados';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-user-tie me-2"></i>Gestión de Empleados
                    <?php if ($local): ?>
                        <small class="text-muted"> - Local: <?php echo htmlspecialchars($local); ?></small>
                    <?php endif; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEmpleado">
                        <i class="fas fa-plus me-1"></i> Nuevo Empleado
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Empleados</h6>
                                    <h3 class="mb-0"><?php echo count($empleados); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Activos</h6>
                                    <?php 
                                        $activos = array_filter($empleados, fn($e) => $e['estado'] === 'ACTIVO');
                                    ?>
                                    <h3 class="mb-0"><?php echo count($activos); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Nómina Mensual</h6>
                                    <?php 
                                        $nomina = array_sum(array_column($empleados, 'salario'));
                                    ?>
                                    <h3 class="mb-0">$<?php echo number_format($nomina, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Prom. Capacitaciones</h6>
                                    <?php 
                                        $promCap = count($empleados) > 0 ? 
                                                   array_sum(array_column($empleados, 'capacitaciones_completadas')) / count($empleados) : 0;
                                    ?>
                                    <h3 class="mb-0"><?php echo number_format($promCap, 1); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-graduation-cap fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Empleados -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaEmpleados">
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Empleado</th>
                                    <th>Cargo</th>
                                    <th>Local</th>
                                    <th>Salario</th>
                                    <th>Contratación</th>
                                    <th>Estado</th>
                                    <th>Capacitaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empleados as $empleado): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($empleado['cedula']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($empleado['nombres'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['apellidos']); ?></strong>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($empleado['email']); ?>
                                                        <br>
                                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($empleado['telefono']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $empleado['cargo'] === 'GERENTE' ? 'danger' :
                                                     ($empleado['cargo'] === 'HELADERO' ? 'warning' :
                                                     ($empleado['cargo'] === 'CAJERO' ? 'success' : 'secondary'));
                                            ?>">
                                                <?php echo $empleado['cargo']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($empleado['nombre_local']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($empleado['ciudad']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">$<?php echo number_format($empleado['salario'], 2); ?></span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $empleado['estado'] === 'ACTIVO' ? 'success' :
                                                     ($empleado['estado'] === 'VACACIONES' ? 'warning' : 'secondary');
                                            ?>">
                                                <?php echo $empleado['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $empleado['capacitaciones_completadas']; ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="verDetalles(<?php echo $empleado['id_empleado']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="verCapacitaciones(<?php echo $empleado['id_empleado']; ?>)">
                                                    <i class="fas fa-graduation-cap"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="verTurnos(<?php echo $empleado['id_empleado']; ?>)">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </button>
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

<!-- Modal para crear empleado -->
<div class="modal fade" id="modalEmpleado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?action=crear">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Empleado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cedula" class="form-label">Cédula *</label>
                            <input type="text" class="form-control" id="cedula" name="cedula" 
                                   pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                                <option value="VACACIONES">Vacaciones</option>
                            </select>
                        </div>
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
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" 
                                   pattern="[0-9]{10}" maxlength="10">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cargo" class="form-label">Cargo *</label>
                            <select class="form-select" id="cargo" name="cargo" required>
                                <option value="GERENTE">Gerente</option>
                                <option value="HELADERO">Heladero</option>
                                <option value="CAJERO">Cajero</option>
                                <option value="AYUDANTE">Ayudante</option>
                                <option value="LIMPIEZA">Limpieza</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="salario" class="form-label">Salario *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="salario" name="salario" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="codigo_local" class="form-label">Local *</label>
                            <select class="form-select" id="codigo_local" name="codigo_local" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($locales as $local): ?>
                                    <option value="<?php echo $local['codigo_local']; ?>" 
                                            <?php echo ($_GET['local'] ?? '') === $local['codigo_local'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($local['nombre_local'] . ' - ' . $local['ciudad']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Empleado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function verDetalles(id) {
    fetch(`ajax/get-empleado.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const e = data.empleado;
                const modal = new bootstrap.Modal(document.getElementById('modalDetalleEmpleado'));
                
                document.getElementById('detalleNombre').textContent = e.nombres + ' ' + e.apellidos;
                document.getElementById('detalleCedula').textContent = e.cedula;
                document.getElementById('detalleEdad').textContent = calcularEdad(e.fecha_nacimiento);
                document.getElementById('detalleEmail').textContent = e.email || 'No especificado';
                document.getElementById('detalleTelefono').textContent = e.telefono || 'No especificado';
                document.getElementById('detalleCargo').textContent = e.cargo;
                document.getElementById('detalleSalario').textContent = '$' + parseFloat(e.salario).toFixed(2);
                document.getElementById('detalleLocal').textContent = e.nombre_local + ' - ' + e.ciudad;
                document.getElementById('detalleEstado').textContent = e.estado;
                document.getElementById('detalleContratacion').textContent = e.fecha_contratacion;
                document.getElementById('detalleCapacitaciones').textContent = e.capacitaciones_completadas;
                
                modal.show();
            }
        })
        .catch(error => console.error('Error:', error));
}

function verCapacitaciones(id) {
    window.location.href = `capacitaciones.php?empleado=${id}`;
}

function verTurnos(id) {
    window.location.href = `turnos.php?empleado=${id}`;
}

function calcularEdad(fechaNacimiento) {
    const hoy = new Date();
    const nacimiento = new Date(fechaNacimiento);
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const m = hoy.getMonth() - nacimiento.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nacimiento.getDate())) {
        edad--;
    }
    return edad;
}
</script>

<!-- Modal de detalles del empleado -->
<div class="modal fade" id="modalDetalleEmpleado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Empleado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-4">Nombre:</dt>
                    <dd class="col-sm-8"><strong id="detalleNombre"></strong></dd>
                    
                    <dt class="col-sm-4">Cédula:</dt>
                    <dd class="col-sm-8" id="detalleCedula"></dd>
                    
                    <dt class="col-sm-4">Edad:</dt>
                    <dd class="col-sm-8" id="detalleEdad"></dd>
                    
                    <dt class="col-sm-4">Email:</dt>
                    <dd class="col-sm-8" id="detalleEmail"></dd>
                    
                    <dt class="col-sm-4">Teléfono:</dt>
                    <dd class="col-sm-8" id="detalleTelefono"></dd>
                    
                    <dt class="col-sm-4">Cargo:</dt>
                    <dd class="col-sm-8" id="detalleCargo"></dd>
                    
                    <dt class="col-sm-4">Salario:</dt>
                    <dd class="col-sm-8" id="detalleSalario"></dd>
                    
                    <dt class="col-sm-4">Local:</dt>
                    <dd class="col-sm-8" id="detalleLocal"></dd>
                    
                    <dt class="col-sm-4">Estado:</dt>
                    <dd class="col-sm-8" id="detalleEstado"></dd>
                    
                    <dt class="col-sm-4">Contratación:</dt>
                    <dd class="col-sm-8" id="detalleContratacion"></dd>
                    
                    <dt class="col-sm-4">Capacitaciones:</dt>
                    <dd class="col-sm-8" id="detalleCapacitaciones"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>