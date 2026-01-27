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
$contrato = $_GET['contrato'] ?? '';
$mes = $_GET['mes'] ?? date('n');
$anio = $_GET['anio'] ?? date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'registrar_pago') {
        $id_pago = $_POST['id_pago'];
        $fecha_pago = $_POST['fecha_pago'];
        
        try {
            $stmt = $db->prepare("
                UPDATE pagos_royalty 
                SET estado = 'CANCELADO', fecha_pago = ?
                WHERE id_pago = ?
            ");
            $stmt->execute([$fecha_pago, $id_pago]);
            
            logActividad('REGISTRAR_PAGO', 'pagos_royalty', "Pago registrado ID: $id_pago");
            setFlashMessage('success', 'Pago registrado exitosamente');
            header('Location: pagos.php' . ($contrato ? "?contrato=$contrato" : ''));
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al registrar pago: ' . $e->getMessage());
        }
    }
}

// Obtener pagos con filtros
$pagos = [];
$where = "WHERE 1=1";
$params = [];

if ($contrato) {
    $where .= " AND pr.id_contrato = ?";
    $params[] = $contrato;
}

if ($mes) {
    $where .= " AND pr.mes = ?";
    $params[] = $mes;
}

if ($anio) {
    $where .= " AND pr.anio = ?";
    $params[] = $anio;
}

try {
    $stmt = $db->prepare("
        SELECT pr.*, 
               cf.numero_contrato,
               CONCAT(f.nombres, ' ', f.apellidos) as franquiciado_nombre,
               l.nombre_local, l.ciudad,
               (pr.monto_royalty + pr.monto_publicidad) as total_pagar
        FROM pagos_royalty pr
        LEFT JOIN contratos_franquicia cf ON pr.id_contrato = cf.id_contrato
        LEFT JOIN franquiciados f ON cf.cedula_franquiciado = f.cedula
        LEFT JOIN locales l ON cf.codigo_local = l.codigo_local
        $where
        ORDER BY pr.estado ASC, pr.anio DESC, pr.mes DESC
    ");
    $stmt->execute($params);
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar pagos: ' . $e->getMessage());
}

// Obtener contratos para filtro
$contratos = [];
try {
    $stmt = $db->prepare("
        SELECT cf.id_contrato, cf.numero_contrato, 
               CONCAT(f.nombres, ' ', f.apellidos) as franquiciado_nombre
        FROM contratos_franquicia cf
        LEFT JOIN franquiciados f ON cf.cedula_franquiciado = f.cedula
        WHERE cf.estado = 'ACTIVO'
        ORDER BY cf.numero_contrato
    ");
    $stmt->execute();
    $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar contratos: ' . $e->getMessage());
}

// Estadísticas
$estadisticas = [
    'total_pendientes' => 0,
    'total_cancelados' => 0,
    'monto_pendiente' => 0,
    'monto_cancelado' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as total_pendientes,
            SUM(CASE WHEN estado = 'CANCELADO' THEN 1 ELSE 0 END) as total_cancelados,
            SUM(CASE WHEN estado = 'PENDIENTE' THEN (monto_royalty + monto_publicidad) ELSE 0 END) as monto_pendiente,
            SUM(CASE WHEN estado = 'CANCELADO' THEN (monto_royalty + monto_publicidad) ELSE 0 END) as monto_cancelado
        FROM pagos_royalty
        $where
    ");
    $stmt->execute($params);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al calcular estadísticas: ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' - Gestión de Pagos';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-dollar-sign me-2"></i>Gestión de Pagos
                    <?php if ($contrato): ?>
                        <small class="text-muted"> - Contrato: <?php echo htmlspecialchars($contrato); ?></small>
                    <?php endif; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalGenerarPagos">
                        <i class="fas fa-calculator me-1"></i> Generar Pagos
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="contrato" class="form-label">Contrato</label>
                            <select class="form-select" id="contrato" name="contrato">
                                <option value="">Todos los contratos</option>
                                <?php foreach ($contratos as $cont): ?>
                                    <option value="<?php echo $cont['id_contrato']; ?>" 
                                            <?php echo $contrato == $cont['id_contrato'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cont['numero_contrato'] . ' - ' . $cont['franquiciado_nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="mes" class="form-label">Mes</label>
                            <select class="form-select" id="mes" name="mes">
                                <option value="">Todos</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $mes == $i ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="anio" class="form-label">Año</label>
                            <select class="form-select" id="anio" name="anio">
                                <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $anio == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado" onchange="filtrarPorEstado(this.value)">
                                <option value="">Todos</option>
                                <option value="PENDIENTE">Pendientes</option>
                                <option value="CANCELADO">Cancelados</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
                                <a href="pagos.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-1"></i> Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Pendientes</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['total_pendientes'] ?? 0; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Cancelados</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['total_cancelados'] ?? 0; ?></h3>
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
                                    <h6 class="card-title">Monto Pendiente</h6>
                                    <h3 class="mb-0">$<?php echo number_format($estadisticas['monto_pendiente'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Monto Cancelado</h6>
                                    <h3 class="mb-0">$<?php echo number_format($estadisticas['monto_cancelado'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Pagos -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaPagos">
                            <thead>
                                <tr>
                                    <th>Contrato</th>
                                    <th>Franquiciado</th>
                                    <th>Local</th>
                                    <th>Período</th>
                                    <th>Ventas</th>
                                    <th>Royalty</th>
                                    <th>Publicidad</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagos as $pago): ?>
                                    <tr class="<?php echo $pago['estado'] === 'PENDIENTE' ? 'table-warning' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($pago['numero_contrato']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($pago['franquiciado_nombre']); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($pago['nombre_local']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($pago['ciudad']); ?></div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div><?php echo date('F', mktime(0, 0, 0, $pago['mes'], 1)); ?></div>
                                                <div class="text-muted"><?php echo $pago['anio']; ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">$<?php echo number_format($pago['ventas_mes'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">$<?php echo number_format($pago['monto_royalty'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">$<?php echo number_format($pago['monto_publicidad'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">$<?php echo number_format($pago['total_pagar'], 2); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($pago['estado'] === 'PENDIENTE'): ?>
                                                <span class="badge bg-danger">PENDIENTE</span>
                                                <?php if ($pago['fecha_pago']): ?>
                                                    <div class="text-muted small">
                                                        Vence: <?php echo date('d/m/Y', strtotime($pago['fecha_pago'] . ' +5 days')); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-success">CANCELADO</span>
                                                <div class="text-muted small">
                                                    <?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($pago['estado'] === 'PENDIENTE'): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="registrarPago(<?php echo $pago['id_pago']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="verDetalles(<?php echo $pago['id_pago']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="eliminarPago(<?php echo $pago['id_pago']; ?>)">
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

<!-- Modal para generar pagos -->
<div class="modal fade" id="modalGenerarPagos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="ajax/generar-pagos.php">
                <div class="modal-header">
                    <h5 class="modal-title">Generar Pagos Mensuales</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="mes_generar" class="form-label">Mes *</label>
                        <select class="form-select" id="mes_generar" name="mes" required>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == date('n') ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="anio_generar" class="form-label">Año *</label>
                        <select class="form-select" id="anio_generar" name="anio" required>
                            <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == date('Y') ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="forzar_generacion" name="forzar_generacion" value="1">
                            <label class="form-check-label" for="forzar_generacion">
                                Forzar generación (sobreescribir pagos existentes)
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Se generarán pagos para todos los contratos activos del mes seleccionado.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Generar Pagos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para registrar pago -->
<div class="modal fade" id="modalRegistrarPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=registrar_pago">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_pago" id="id_pago_registrar">
                    
                    <div class="mb-3">
                        <label for="fecha_pago" class="form-label">Fecha de Pago *</label>
                        <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Al registrar el pago, el estado cambiará a "CANCELADO".
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filtrarPorEstado(estado) {
    const rows = document.querySelectorAll('#tablaPagos tbody tr');
    rows.forEach(row => {
        const estadoRow = row.cells[8].textContent;
        if (!estado || estadoRow.includes(estado)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function registrarPago(id) {
    document.getElementById('id_pago_registrar').value = id;
    const modal = new bootstrap.Modal(document.getElementById('modalRegistrarPago'));
    modal.show();
}

function verDetalles(id) {
    fetch(`ajax/get-pago.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const pago = data.pago;
                const modal = new bootstrap.Modal(document.getElementById('modalDetallePago'));
                
                document.getElementById('detalleContrato').textContent = pago.numero_contrato;
                document.getElementById('detalleFranquiciado').textContent = pago.franquiciado_nombre;
                document.getElementById('detalleLocal').textContent = pago.nombre_local + ' - ' + pago.ciudad;
                document.getElementById('detallePeriodo').textContent = 
                    date('F', mktime(0, 0, 0, pago.mes, 1)) + ' ' + pago.anio;
                document.getElementById('detalleVentas').textContent = '$' + parseFloat(pago.ventas_mes).toFixed(2);
                document.getElementById('detalleRoyalty').textContent = '$' + parseFloat(pago.monto_royalty).toFixed(2);
                document.getElementById('detallePublicidad').textContent = '$' + parseFloat(pago.monto_publicidad).toFixed(2);
                document.getElementById('detalleTotal').textContent = '$' + parseFloat(pago.total_pagar).toFixed(2);
                document.getElementById('detalleEstado').textContent = pago.estado;
                document.getElementById('detalleFechaPago').textContent = pago.fecha_pago || 'Pendiente';
                document.getElementById('detalleRoyaltyPorcentaje').textContent = 
                    (pago.monto_royalty / pago.ventas_mes * 100).toFixed(2) + '%';
                document.getElementById('detallePublicidadPorcentaje').textContent = 
                    (pago.monto_publicidad / pago.ventas_mes * 100).toFixed(2) + '%';
                
                modal.show();
            }
        })
        .catch(error => console.error('Error:', error));
}

function eliminarPago(id) {
    if (confirm('¿Está seguro de eliminar este pago?')) {
        fetch(`ajax/eliminar-pago.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pago eliminado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
    }
}

// Función para formatear fecha
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES');
}
</script>

<!-- Modal de detalles del pago -->
<div class="modal fade" id="modalDetallePago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-4">Contrato:</dt>
                    <dd class="col-sm-8"><strong id="detalleContrato"></strong></dd>
                    
                    <dt class="col-sm-4">Franquiciado:</dt>
                    <dd class="col-sm-8" id="detalleFranquiciado"></dd>
                    
                    <dt class="col-sm-4">Local:</dt>
                    <dd class="col-sm-8" id="detalleLocal"></dd>
                    
                    <dt class="col-sm-4">Período:</dt>
                    <dd class="col-sm-8" id="detallePeriodo"></dd>
                    
                    <dt class="col-sm-4">Ventas del Mes:</dt>
                    <dd class="col-sm-8" id="detalleVentas"></dd>
                    
                    <dt class="col-sm-4">Royalty:</dt>
                    <dd class="col-sm-8">
                        <span id="detalleRoyalty"></span>
                        <small class="text-muted"> (<span id="detalleRoyaltyPorcentaje"></span>)</small>
                    </dd>
                    
                    <dt class="col-sm-4">Publicidad:</dt>
                    <dd class="col-sm-8">
                        <span id="detallePublicidad"></span>
                        <small class="text-muted"> (<span id="detallePublicidadPorcentaje"></span>)</small>
                    </dd>
                    
                    <dt class="col-sm-4">Total a Pagar:</dt>
                    <dd class="col-sm-8"><strong id="detalleTotal"></strong></dd>
                    
                    <dt class="col-sm-4">Estado:</dt>
                    <dd class="col-sm-8" id="detalleEstado"></dd>
                    
                    <dt class="col-sm-4">Fecha de Pago:</dt>
                    <dd class="col-sm-8" id="detalleFechaPago"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>