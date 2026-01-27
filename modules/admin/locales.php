<?php
require_once __DIR__ . '/../../config.php';      
require_once __DIR__ . '/../../includes/functions.php';  
require_once __DIR__ . '/../../db_connection.php';

startSession();
requireAuth();
requireAnyRole(['admin']);

$db = Database::getConnection();

$action = $_GET['action'] ?? '';
$codigo = $_GET['codigo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'crear') {
        $codigo_local = $_POST['codigo_local'];
        $nombre_local = $_POST['nombre_local'];
        $direccion = $_POST['direccion'];
        $ciudad = $_POST['ciudad'];
        $provincia = $_POST['provincia'];
        $telefono = $_POST['telefono'];
        $fecha_apertura = $_POST['fecha_apertura'];
        $area_local = $_POST['area_local'];
        $cedula_franquiciado = $_POST['cedula_franquiciado'];
        $id_nivel = $_POST['id_nivel'];

        try {
            $stmt = $db->prepare("
                INSERT INTO locales 
                (codigo_local, nombre_local, direccion, ciudad, provincia, telefono, 
                 fecha_apertura, area_local, cedula_franquiciado, id_nivel) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$codigo_local, $nombre_local, $direccion, $ciudad, $provincia, 
                          $telefono, $fecha_apertura, $area_local, $cedula_franquiciado, $id_nivel]);
            
            logActividad('CREAR_LOCAL', 'locales', "Local creado: $codigo_local");
            setFlashMessage('success', 'Local creado exitosamente');
            header('Location: locales.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al crear local: ' . $e->getMessage());
        }
    }
}

// Obtener lista de locales con información relacionada
$locales = [];
try {
    $stmt = $db->prepare("
        SELECT l.*, 
               f.nombres as franquiciado_nombres,
               f.apellidos as franquiciado_apellidos,
               n.nombre as nivel_nombre,
               COUNT(e.id_empleado) as total_empleados,
               (SELECT COUNT(*) FROM ventas v WHERE v.codigo_local = l.codigo_local) as total_ventas
        FROM locales l
        LEFT JOIN franquiciados f ON l.cedula_franquiciado = f.cedula
        LEFT JOIN nivel_franquicia n ON l.id_nivel = n.id_nivel
        LEFT JOIN empleados e ON l.codigo_local = e.codigo_local AND e.estado = 'ACTIVO'
        GROUP BY l.codigo_local
        ORDER BY l.fecha_apertura DESC
    ");
    $stmt->execute();
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar locales: ' . $e->getMessage());
}

// Obtener franquiciados para dropdown
$franquiciados = [];
try {
    $stmt = $db->prepare("SELECT cedula, nombres, apellidos FROM franquiciados WHERE estado = 'ACTIVO' ORDER BY nombres");
    $stmt->execute();
    $franquiciados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar franquiciados: ' . $e->getMessage());
}

// Obtener niveles de franquicia
$niveles = [];
try {
    $stmt = $db->prepare("SELECT id_nivel, nombre, costo FROM nivel_franquicia ORDER BY costo DESC");
    $stmt->execute();
    $niveles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar niveles: ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' - Gestión de Locales';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-store me-2"></i>Gestión de Locales
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalLocal">
                        <i class="fas fa-plus me-1"></i> Nuevo Local
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Tarjetas de resumen -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Locales</h6>
                                    <h3 class="mb-0"><?php echo count($locales); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-store fa-2x opacity-50"></i>
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
                                    <h3 class="mb-0"><?php echo count($locales); ?></h3>
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
                                    <h6 class="card-title">Empleados</h6>
                                    <?php 
                                        $totalEmpleados = array_sum(array_column($locales, 'total_empleados'));
                                    ?>
                                    <h3 class="mb-0"><?php echo $totalEmpleados; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Total Ventas</h6>
                                    <?php 
                                        $totalVentasLocales = array_sum(array_column($locales, 'total_ventas'));
                                    ?>
                                    <h3 class="mb-0"><?php echo $totalVentasLocales; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-line fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Locales -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaLocales">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Ubicación</th>
                                    <th>Franquiciado</th>
                                    <th>Nivel</th>
                                    <th>Apertura</th>
                                    <th>Empleados</th>
                                    <th>Ventas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locales as $local): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-dark"><?php echo htmlspecialchars($local['codigo_local']); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($local['nombre_local']); ?></strong>
                                            <div class="text-muted small">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($local['telefono']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($local['direccion']); ?></div>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($local['ciudad'] . ', ' . $local['provincia']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($local['cedula_franquiciado']): ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        <?php echo strtoupper(substr($local['franquiciado_nombres'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div><?php echo htmlspecialchars($local['franquiciado_nombres'] . ' ' . $local['franquiciado_apellidos']); ?></div>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($local['cedula_franquiciado']); ?></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Sin asignar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $local['id_nivel'] == 1 ? 'warning' : 
                                                     ($local['id_nivel'] == 2 ? 'secondary' : 'info');
                                            ?>">
                                                <?php echo htmlspecialchars($local['nivel_nombre']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($local['fecha_apertura'])); ?>
                                            <div class="text-muted small">
                                                <?php echo $local['area_local']; ?> m²
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $local['total_empleados']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $local['total_ventas']; ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="empleados.php?local=<?php echo $local['codigo_local']; ?>" 
                                                   class="btn btn-sm btn-outline-info" title="Empleados">
                                                    <i class="fas fa-users"></i>
                                                </a>
                                                <a href="inventario.php?local=<?php echo $local['codigo_local']; ?>" 
                                                   class="btn btn-sm btn-outline-warning" title="Inventario">
                                                    <i class="fas fa-boxes"></i>
                                                </a>
                                                <a href="ventas.php?local=<?php echo $local['codigo_local']; ?>" 
                                                   class="btn btn-sm btn-outline-success" title="Ventas">
                                                    <i class="fas fa-chart-line"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="verDetalleLocal('<?php echo $local['codigo_local']; ?>')"
                                                        title="Detalles">
                                                    <i class="fas fa-eye"></i>
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

<!-- Modal para crear local -->
<div class="modal fade" id="modalLocal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?action=crear">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Local</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="codigo_local" class="form-label">Código del Local *</label>
                            <input type="text" class="form-control" id="codigo_local" name="codigo_local" 
                                   pattern="[A-Z]{3}-[A-Z]{3}-[0-9]{3}" 
                                   placeholder="Ej: BGT-UIO-001" required>
                            <small class="text-muted">Formato: XXX-CIU-001</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nombre_local" class="form-label">Nombre del Local</label>
                            <input type="text" class="form-control" id="nombre_local" name="nombre_local" 
                                   placeholder="Ej: Bogati Quito Centro" value="Bogati">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección *</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ciudad" class="form-label">Ciudad *</label>
                            <input type="text" class="form-control" id="ciudad" name="ciudad" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="provincia" class="form-label">Provincia *</label>
                            <select class="form-select" id="provincia" name="provincia" required>
                                <option value="">Seleccionar...</option>
                                <option value="PICHINCHA">Pichincha</option>
                                <option value="GUAYAS">Guayas</option>
                                <option value="AZUAY">Azuay</option>
                                <option value="MANABI">Manabí</option>
                                <option value="EL_ORO">El Oro</option>
                                <option value="LOJA">Loja</option>
                                <option value="TUNGURAHUA">Tungurahua</option>
                                <option value="IMBABURA">Imbabura</option>
                                <option value="COTOPAXI">Cotopaxi</option>
                                <option value="CHIMBORAZO">Chimborazo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" 
                                   pattern="[0-9]{10}" maxlength="10">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_apertura" class="form-label">Fecha de Apertura</label>
                            <input type="date" class="form-control" id="fecha_apertura" name="fecha_apertura" 
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="area_local" class="form-label">Área (m²)</label>
                            <input type="number" class="form-control" id="area_local" name="area_local" 
                                   step="0.01" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_nivel" class="form-label">Nivel de Franquicia</label>
                            <select class="form-select" id="id_nivel" name="id_nivel">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($niveles as $nivel): ?>
                                    <option value="<?php echo $nivel['id_nivel']; ?>">
                                        <?php echo htmlspecialchars($nivel['nombre']); ?> - $<?php echo number_format($nivel['costo'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cedula_franquiciado" class="form-label">Franquiciado</label>
                        <select class="form-select" id="cedula_franquiciado" name="cedula_franquiciado">
                            <option value="">Sin asignar</option>
                            <?php foreach ($franquiciados as $franquiciado): ?>
                                <option value="<?php echo $franquiciado['cedula']; ?>">
                                    <?php echo htmlspecialchars($franquiciado['nombres'] . ' ' . $franquiciado['apellidos'] . ' (' . $franquiciado['cedula'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Local</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function verDetalleLocal(codigo) {
    fetch(`ajax/get-local.php?codigo=${codigo}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar modal con detalles
                const local = data.local;
                const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
                
                document.getElementById('detalleCodigo').textContent = local.codigo_local;
                document.getElementById('detalleNombre').textContent = local.nombre_local;
                document.getElementById('detalleDireccion').textContent = local.direccion;
                document.getElementById('detalleCiudad').textContent = local.ciudad + ', ' + local.provincia;
                document.getElementById('detalleTelefono').textContent = local.telefono || 'No especificado';
                document.getElementById('detalleApertura').textContent = local.fecha_apertura;
                document.getElementById('detalleArea').textContent = local.area_local + ' m²';
                document.getElementById('detalleFranquiciado').textContent = local.franquiciado_nombres 
                    ? local.franquiciado_nombres + ' ' + local.franquiciado_apellidos 
                    : 'Sin asignar';
                document.getElementById('detalleNivel').textContent = local.nivel_nombre;
                
                modal.show();
            }
        })
        .catch(error => console.error('Error:', error));
}
</script>

<!-- Modal de detalles -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Local</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-4">Código:</dt>
                    <dd class="col-sm-8"><strong id="detalleCodigo"></strong></dd>
                    
                    <dt class="col-sm-4">Nombre:</dt>
                    <dd class="col-sm-8" id="detalleNombre"></dd>
                    
                    <dt class="col-sm-4">Dirección:</dt>
                    <dd class="col-sm-8" id="detalleDireccion"></dd>
                    
                    <dt class="col-sm-4">Ciudad:</dt>
                    <dd class="col-sm-8" id="detalleCiudad"></dd>
                    
                    <dt class="col-sm-4">Teléfono:</dt>
                    <dd class="col-sm-8" id="detalleTelefono"></dd>
                    
                    <dt class="col-sm-4">Apertura:</dt>
                    <dd class="col-sm-8" id="detalleApertura"></dd>
                    
                    <dt class="col-sm-4">Área:</dt>
                    <dd class="col-sm-8" id="detalleArea"></dd>
                    
                    <dt class="col-sm-4">Franquiciado:</dt>
                    <dd class="col-sm-8" id="detalleFranquiciado"></dd>
                    
                    <dt class="col-sm-4">Nivel:</dt>
                    <dd class="col-sm-8" id="detalleNivel"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>