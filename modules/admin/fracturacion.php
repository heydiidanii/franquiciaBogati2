<?php
require_once __DIR__ . '/../../config.php';      
require_once __DIR__ . '/../../includes/functions.php';  
require_once __DIR__ . '/../../db_connection.php';

startSession();
requireAuth();
requireAnyRole(['admin']);

$db = Database::getConnection();

$action = $_GET['action'] ?? '';
$cedula = $_GET['cedula'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'crear') {
        // Crear nuevo franquiciado
        $cedula = $_POST['cedula'];
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $telefono = $_POST['telefono'];
        $email = $_POST['email'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $capital_inicial = $_POST['capital_inicial'];
        $experiencia = isset($_POST['experiencia']) ? 1 : 0;
        $estado = $_POST['estado'];

        try {
            $stmt = $db->prepare("
                INSERT INTO franquiciados 
                (cedula, nombres, apellidos, telefono, email, fecha_nacimiento, 
                 capital_inicial, experiencia, estado, fecha_registro) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
            ");
            $stmt->execute([$cedula, $nombres, $apellidos, $telefono, $email, 
                          $fecha_nacimiento, $capital_inicial, $experiencia, $estado]);
            
            logActividad('CREAR_FRANQUICIADO', 'franquiciados', "Franquiciado creado: $cedula");
            setFlashMessage('success', 'Franquiciado creado exitosamente');
            header('Location: franquiciados.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al crear franquiciado: ' . $e->getMessage());
        }
    }
    elseif ($action === 'editar') {
        // Editar franquiciado
        $cedula_original = $_POST['cedula_original'];
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $telefono = $_POST['telefono'];
        $email = $_POST['email'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $capital_inicial = $_POST['capital_inicial'];
        $experiencia = isset($_POST['experiencia']) ? 1 : 0;
        $estado = $_POST['estado'];

        try {
            $stmt = $db->prepare("
                UPDATE franquiciados 
                SET nombres = ?, apellidos = ?, telefono = ?, email = ?, 
                    fecha_nacimiento = ?, capital_inicial = ?, experiencia = ?, estado = ?
                WHERE cedula = ?
            ");
            $stmt->execute([$nombres, $apellidos, $telefono, $email, 
                          $fecha_nacimiento, $capital_inicial, $experiencia, $estado, $cedula_original]);
            
            logActividad('ACTUALIZAR_FRANQUICIADO', 'franquiciados', "Franquiciado actualizado: $cedula_original");
            setFlashMessage('success', 'Franquiciado actualizado exitosamente');
            header('Location: franquiciados.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al actualizar franquiciado: ' . $e->getMessage());
        }
    }
    elseif ($action === 'eliminar') {
        // Eliminar franquiciado
        try {
            $stmt = $db->prepare("DELETE FROM franquiciados WHERE cedula = ?");
            $stmt->execute([$cedula]);
            
            logActividad('ELIMINAR_FRANQUICIADO', 'franquiciados', "Franquiciado eliminado: $cedula");
            setFlashMessage('success', 'Franquiciado eliminado exitosamente');
            header('Location: franquiciados.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al eliminar franquiciado: ' . $e->getMessage());
        }
    }
}

// Obtener lista de franquiciados
$franquiciados = [];
try {
    $stmt = $db->prepare("
        SELECT f.*, 
               COUNT(l.codigo_local) as total_locales,
               COUNT(cf.id_contrato) as total_contratos
        FROM franquiciados f
        LEFT JOIN locales l ON f.cedula = l.cedula_franquiciado
        LEFT JOIN contratos_franquicia cf ON f.cedula = cf.cedula_franquiciado
        GROUP BY f.cedula
        ORDER BY f.fecha_registro DESC
    ");
    $stmt->execute();
    $franquiciados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar franquiciados: ' . $e->getMessage());
    setFlashMessage('error', 'Error al cargar franquiciados');
}

// Obtener franquiciado específico para editar
$franquiciadoEditar = null;
if ($action === 'editar' && $cedula) {
    try {
        $stmt = $db->prepare("SELECT * FROM franquiciados WHERE cedula = ?");
        $stmt->execute([$cedula]);
        $franquiciadoEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error al cargar franquiciado para editar: ' . $e->getMessage());
    }
}

$pageTitle = APP_NAME . ' - Gestión de Franquiciados';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-handshake me-2"></i>Gestión de Franquiciados
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFranquiciado">
                        <i class="fas fa-plus me-1"></i> Nuevo Franquiciado
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form class="row g-3">
                        <div class="col-md-3">
                            <label for="filtroEstado" class="form-label">Estado</label>
                            <select class="form-select" id="filtroEstado" onchange="filtrarTabla()">
                                <option value="">Todos</option>
                                <option value="ACTIVO">Activos</option>
                                <option value="PROSPECTO">Prospectos</option>
                                <option value="INACTIVO">Inactivos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filtroExperiencia" class="form-label">Experiencia</label>
                            <select class="form-select" id="filtroExperiencia" onchange="filtrarTabla()">
                                <option value="">Todos</option>
                                <option value="1">Con experiencia</option>
                                <option value="0">Sin experiencia</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="filtroBusqueda" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="filtroBusqueda" 
                                   placeholder="Buscar por nombre, cédula, email..." onkeyup="filtrarTabla()">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de Franquiciados -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaFranquiciados">
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Franquiciado</th>
                                    <th>Contacto</th>
                                    <th>Capital</th>
                                    <th>Experiencia</th>
                                    <th>Locales</th>
                                    <th>Estado</th>
                                    <th>Registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($franquiciados as $franquiciado): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($franquiciado['cedula']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($franquiciado['nombres'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($franquiciado['nombres'] . ' ' . $franquiciado['apellidos']); ?></strong>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($franquiciado['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($franquiciado['telefono']); ?></div>
                                            <div class="text-muted small">
                                                <i class="fas fa-birthday-cake me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($franquiciado['fecha_nacimiento'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                $<?php echo number_format($franquiciado['capital_inicial'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $franquiciado['experiencia'] ? 'info' : 'warning'; ?>">
                                                <?php echo $franquiciado['experiencia'] ? 'Sí' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $franquiciado['total_locales']; ?> locales</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $franquiciado['estado'] === 'ACTIVO' ? 'success' : 
                                                     ($franquiciado['estado'] === 'PROSPECTO' ? 'warning' : 'secondary');
                                            ?>">
                                                <?php echo $franquiciado['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($franquiciado['fecha_registro'])); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="franquiciados.php?action=editar&cedula=<?php echo $franquiciado['cedula']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                   data-bs-target="#modalFranquiciado" onclick="cargarFranquiciado('<?php echo $franquiciado['cedula']; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="locales.php?franquiciado=<?php echo $franquiciado['cedula']; ?>" 
                                                   class="btn btn-sm btn-outline-info" title="Ver locales">
                                                    <i class="fas fa-store"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmarEliminar('<?php echo $franquiciado['cedula']; ?>', '<?php echo htmlspecialchars($franquiciado['nombres'] . ' ' . $franquiciado['apellidos']); ?>')">
                                                    <i class="fas fa-trash"></i>
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

<!-- Modal para crear/editar franquiciado -->
<div class="modal fade" id="modalFranquiciado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formFranquiciado" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nuevo Franquiciado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="cedula_original" id="cedula_original" value="">
                    <input type="hidden" name="action" id="formAction" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cedula" class="form-label">Cédula *</label>
                            <input type="text" class="form-control" id="cedula" name="cedula" 
                                   pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="PROSPECTO">Prospecto</option>
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
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
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" 
                                   pattern="[0-9]{10}" maxlength="10">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="capital_inicial" class="form-label">Capital Inicial *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="capital_inicial" 
                                       name="capital_inicial" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="experiencia" name="experiencia" value="1">
                            <label class="form-check-label" for="experiencia">
                                Tiene experiencia en franquicias o negocios similares
                            </label>
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
    <input type="hidden" name="cedula" id="cedulaEliminar">
</form>

<script>
function cargarFranquiciado(cedula) {
    fetch(`ajax/get-franquiciado.php?cedula=${cedula}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const f = data.franquiciado;
                document.getElementById('modalTitulo').textContent = 'Editar Franquiciado';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('cedula_original').value = f.cedula;
                document.getElementById('cedula').value = f.cedula;
                document.getElementById('cedula').readOnly = true;
                document.getElementById('nombres').value = f.nombres;
                document.getElementById('apellidos').value = f.apellidos;
                document.getElementById('telefono').value = f.telefono;
                document.getElementById('email').value = f.email;
                document.getElementById('fecha_nacimiento').value = f.fecha_nacimiento;
                document.getElementById('capital_inicial').value = f.capital_inicial;
                document.getElementById('estado').value = f.estado;
                document.getElementById('experiencia').checked = f.experiencia == 1;
            }
        })
        .catch(error => console.error('Error:', error));
}

function confirmarEliminar(cedula, nombre) {
    if (confirm(`¿Está seguro de eliminar al franquiciado "${nombre}" (${cedula})?`)) {
        document.getElementById('cedulaEliminar').value = cedula;
        document.getElementById('formEliminar').submit();
    }
}

function filtrarTabla() {
    const estado = document.getElementById('filtroEstado').value;
    const experiencia = document.getElementById('filtroExperiencia').value;
    const busqueda = document.getElementById('filtroBusqueda').value.toLowerCase();
    
    const rows = document.querySelectorAll('#tablaFranquiciados tbody tr');
    
    rows.forEach(row => {
        const estadoRow = row.cells[6].textContent;
        const experienciaRow = row.cells[4].textContent.includes('Sí') ? '1' : '0';
        const textoRow = row.textContent.toLowerCase();
        
        const matchEstado = !estado || estadoRow.includes(estado);
        const matchExperiencia = !experiencia || experienciaRow === experiencia;
        const matchBusqueda = !busqueda || textoRow.includes(busqueda);
        
        row.style.display = (matchEstado && matchExperiencia && matchBusqueda) ? '' : 'none';
    });
}

// Resetear modal al cerrar
document.getElementById('modalFranquiciado').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitulo').textContent = 'Nuevo Franquiciado';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('formFranquiciado').reset();
    document.getElementById('cedula').readOnly = false;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>