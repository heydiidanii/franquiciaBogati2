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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'crear') {
        $numero_contrato = $_POST['numero_contrato'];
        $cedula_franquiciado = $_POST['cedula_franquiciado'];
        $codigo_local = $_POST['codigo_local'];
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        $inversion_total = $_POST['inversion_total'];
        $royalty = $_POST['royalty'];
        $canon_publicidad = $_POST['canon_publicidad'];
        $estado = $_POST['estado'];

        try {
            $stmt = $db->prepare("
                INSERT INTO contratos_franquicia 
                (numero_contrato, cedula_franquiciado, codigo_local, fecha_inicio, fecha_fin, 
                 inversion_total, royalty, canon_publicidad, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$numero_contrato, $cedula_franquiciado, $codigo_local, $fecha_inicio, 
                          $fecha_fin, $inversion_total, $royalty, $canon_publicidad, $estado]);
            
            logActividad('CREAR_CONTRATO', 'contratos_franquicia', "Contrato creado: $numero_contrato");
            setFlashMessage('success', 'Contrato creado exitosamente');
            header('Location: contratos.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al crear contrato: ' . $e->getMessage());
        }
    }
}

// Obtener contratos
$contratos = [];
try {
    $stmt = $db->prepare("
        SELECT cf.*, 
               CONCAT(f.nombres, ' ', f.apellidos) as franquiciado_nombre,
               l.nombre_local, l.ciudad,
               DATEDIFF(cf.fecha_fin, CURDATE()) as dias_restantes,
               (SELECT COUNT(*) FROM pagos_royalty pr WHERE pr.id_contrato = cf.id_contrato AND pr.estado = 'PENDIENTE') as pagos_pendientes
        FROM contratos_franquicia cf
        LEFT JOIN franquiciados f ON cf.cedula_franquiciado = f.cedula
        LEFT JOIN locales l ON cf.codigo_local = l.codigo_local
        ORDER BY cf.fecha_inicio DESC
    ");
    $stmt->execute();
    $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar contratos: ' . $e->getMessage());
}

// Obtener franquiciados activos
$franquiciados = [];
try {
    $stmt = $db->prepare("SELECT cedula, nombres, apellidos FROM franquiciados WHERE estado = 'ACTIVO' ORDER BY nombres");
    $stmt->execute();
    $franquiciados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar franquiciados: ' . $e->getMessage());
}

// Obtener locales sin contrato activo
$locales = [];
try {
    $stmt = $db->prepare("
        SELECT l.* 
        FROM locales l
        LEFT JOIN contratos_franquicia cf ON l.codigo_local = cf.codigo_local AND cf.estado = 'ACTIVO'
        WHERE cf.id_contrato IS NULL
        ORDER BY l.nombre_local
    ");
    $stmt->execute();
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar locales: ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' - Gestión de Contratos';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-file-contract me-2"></i>Gestión de Contratos
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalContrato">
                        <i class="fas fa-plus me-1"></i> Nuevo Contrato
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Tarjetas de estado -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Contratos</h6>
                                    <h3 class="mb-0"><?php echo count($contratos); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-file-contract fa-2x opacity-50"></i>
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
                                        $activos = array_filter($contratos, fn($c) => $c['estado'] === 'ACTIVO');
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
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Por Vencer</h6>
                                    <?php 
                                        $porVencer = array_filter($contratos, fn($c) => 
                                            $c['estado'] === 'ACTIVO' && $c['dias_restantes'] <= 90 && $c['dias_restantes'] > 0);
                                    ?>
                                    <h3 class="mb-0"><?php echo count($porVencer); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Vencidos</h6>
                                    <?php 
                                        $vencidos = array_filter($contratos, fn($c) => 
                                            $c['estado'] === 'ACTIVO' && $c['dias_restantes'] < 0);
                                    ?>
                                    <h3 class="mb-0"><?php echo count($vencidos); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Contratos -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaContratos">
                            <thead>
                                <tr>
                                    <th>N° Contrato</th>
                                    <th>Franquiciado</th>
                                    <th>Local</th>
                                    <th>Inversión</th>
                                    <th>Royalty</th>
                                    <th>Vigencia</th>
                                    <th>Días Restantes</th>
                                    <th>Pagos Pend.</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contratos as $contrato): ?>
                                    <tr class="<?php echo $contrato['dias_restantes'] < 0 ? 'table-danger' : 
                                               ($contrato['dias_restantes'] <= 30 ? 'table-warning' : ''); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($contrato['numero_contrato']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($contrato['franquiciado_nombre'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div><?php echo htmlspecialchars($contrato['franquiciado_nombre']); ?></div>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($contrato['cedula_franquiciado']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($contrato['nombre_local']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($contrato['ciudad']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark">$<?php echo number_format($contrato['inversion_total'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $contrato['royalty']; ?>%</span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div>Inicio: <?php echo date('d/m/Y', strtotime($contrato['fecha_inicio'])); ?></div>
                                                <div>Fin: <?php echo date('d/m/Y', strtotime($contrato['fecha_fin'])); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($contrato['estado'] === 'ACTIVO'): ?>
                                                <span class="badge bg-<?php 
                                                    echo $contrato['dias_restantes'] < 0 ? 'danger' : 
                                                         ($contrato['dias_restantes'] <= 30 ? 'warning' : 'success');
                                                ?>">
                                                    <?php echo $contrato['dias_restantes']; ?> días
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Finalizado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($contrato['pagos_pendientes'] > 0): ?>
                                                <span class="badge bg-danger"><?php echo $contrato['pagos_pendientes']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $contrato['estado'] === 'ACTIVO' ? 'success' : 
                                                     ($contrato['estado'] === 'FINALIZADO' ? 'secondary' : 'warning');
                                            ?>">
                                                <?php echo $contrato['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="verDetalles(<?php echo $contrato['id_contrato']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="pagos.php?contrato=<?php echo $contrato['id_contrato']; ?>" 
                                                   class="btn btn-sm btn-outline-info" title="Pagos">
                                                    <i class="fas fa-dollar-sign"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="renovarContrato(<?php echo $contrato['id_contrato']; ?>)">
                                                    <i class="fas fa-sync"></i>
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

<!-- Modal para crear contrato -->
<div class="modal fade" id="modalContrato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?action=crear">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Contrato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="numero_contrato" class="form-label">Número de Contrato *</label>
                            <input type="text" class="form-control" id="numero_contrato" name="numero_contrato" 
                                   pattern="CONTR-[0-9]{3}-[0-9]{4}" placeholder="Ej: CONTR-001-2024" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                                <option value="FINALIZADO">Finalizado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cedula_franquiciado" class="form-label">Franquiciado *</label>
                            <select class="form-select" id="cedula_franquiciado" name="cedula_franquiciado" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($franquiciados as $franquiciado): ?>
                                    <option value="<?php echo $franquiciado['cedula']; ?>">
                                        <?php echo htmlspecialchars($franquiciado['nombres'] . ' ' . $franquiciado['apellidos'] . ' (' . $franquiciado['cedula'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="codigo_local" class="form-label">Local *</label>
                            <select class="form-select" id="codigo_local" name="codigo_local" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($locales as $local): ?>
                                    <option value="<?php echo $local['codigo_local']; ?>">
                                        <?php echo htmlspecialchars($local['nombre_local'] . ' - ' . $local['ciudad']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_inicio" class="form-label">Fecha Inicio *</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_fin" class="form-label">Fecha Fin *</label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="inversion_total" class="form-label">Inversión Total *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="inversion_total" name="inversion_total" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="royalty" class="form-label">Royalty (%) *</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="royalty" name="royalty" 
                                       step="0.01" min="0" max="100" value="3.00" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="canon_publicidad" class="form-label">Canon Publicidad (%) *</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="canon_publicidad" name="canon_publicidad" 
                                       step="0.01" min="0" max="100" value="1.00" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="generar_primer_pago" name="generar_primer_pago" value="1">
                            <label class="form-check-label" for="generar_primer_pago">
                                Generar primer pago de royalty para el mes actual
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Contrato</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function verDetalles(id) {
    fetch(`ajax/get-contrato.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const c = data.contrato;
                const modal = new bootstrap.Modal(document.getElementById('modalDetalleContrato'));
                
                document.getElementById('detalleNumero').textContent = c.numero_contrato;
                document.getElementById('detalleFranquiciado').textContent = c.franquiciado_nombre;
                document.getElementById('detalleLocal').textContent = c.nombre_local + ' - ' + c.ciudad;
                document.getElementById('detalleInversion').textContent = '$' + parseFloat(c.inversion_total).toFixed(2);
                document.getElementById('detalleRoyalty').textContent = c.royalty + '%';
                document.getElementById('detalleCanon').textContent = c.canon_publicidad + '%';
                document.getElementById('detalleInicio').textContent = c.fecha_inicio;
                document.getElementById('detalleFin').textContent = c.fecha_fin;
                document.getElementById('detalleDias').textContent = c.dias_restantes + ' días';
                document.getElementById('detalleEstado').textContent = c.estado;
                document.getElementById('detallePagosPend').textContent = c.pagos_pendientes;
                
                modal.show();
            }
        })
        .catch(error => console.error('Error:', error));
}

function renovarContrato(id) {
    if (confirm('¿Desea renovar este contrato por 1 año más?')) {
        fetch(`ajax/renovar-contrato.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Contrato renovado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
    }
}

// Auto-calcular fecha fin (1 año después)
document.getElementById('fecha_inicio')?.addEventListener('change', function() {
    const fechaInicio = new Date(this.value);
    if (fechaInicio) {
        const fechaFin = new Date(fechaInicio);
        fechaFin.setFullYear(fechaFin.getFullYear() + 1);
        document.getElementById('fecha_fin').value = fechaFin.toISOString().split('T')[0];
    }
});
</script>

<!-- Modal de detalles del contrato -->
<div class="modal fade" id="modalDetalleContrato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Contrato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-4">N° Contrato:</dt>
                    <dd class="col-sm-8"><strong id="detalleNumero"></strong></dd>
                    
                    <dt class="col-sm-4">Franquiciado:</dt>
                    <dd class="col-sm-8" id="detalleFranquiciado"></dd>
                    
                    <dt class="col-sm-4">Local:</dt>
                    <dd class="col-sm-8" id="detalleLocal"></dd>
                    
                    <dt class="col-sm-4">Inversión:</dt>
                    <dd class="col-sm-8" id="detalleInversion"></dd>
                    
                    <dt class="col-sm-4">Royalty:</dt>
                    <dd class="col-sm-8" id="detalleRoyalty"></dd>
                    
                    <dt class="col-sm-4">Canon Publicidad:</dt>
                    <dd class="col-sm-8" id="detalleCanon"></dd>
                    
                    <dt class="col-sm-4">Fecha Inicio:</dt>
                    <dd class="col-sm-8" id="detalleInicio"></dd>
                    
                    <dt class="col-sm-4">Fecha Fin:</dt>
                    <dd class="col-sm-8" id="detalleFin"></dd>
                    
                    <dt class="col-sm-4">Días Restantes:</dt>
                    <dd class="col-sm-8" id="detalleDias"></dd>
                    
                    <dt class="col-sm-4">Estado:</dt>
                    <dd class="col-sm-8" id="detalleEstado"></dd>
                    
                    <dt class="col-sm-4">Pagos Pendientes:</dt>
                    <dd class="col-sm-8" id="detallePagosPend"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>