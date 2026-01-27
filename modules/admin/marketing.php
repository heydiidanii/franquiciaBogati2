<?php
require_once __DIR__ . '/../../config/config.php';
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
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $tipo = $_POST['tipo'];
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        $presupuesto = $_POST['presupuesto'];
        $influencers = $_POST['influencers'];
        $estado = $_POST['estado'];
        $locales_seleccionados = $_POST['locales'] ?? [];

        try {
            $db->beginTransaction();
            
            // Insertar campaña
            $stmt = $db->prepare("
                INSERT INTO marketing_campanas 
                (nombre, descripcion, tipo, fecha_inicio, fecha_fin, presupuesto, 
                 influencers_involucrados, estado, id_franquicia) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$nombre, $descripcion, $tipo, $fecha_inicio, $fecha_fin, 
                          $presupuesto, $influencers, $estado]);
            
            $id_campana = $db->lastInsertId();
            
            // Asignar locales a la campaña
            if (!empty($locales_seleccionados)) {
                $stmt = $db->prepare("INSERT INTO campana_local (id_campana, codigo_local) VALUES (?, ?)");
                foreach ($locales_seleccionados as $codigo_local) {
                    $stmt->execute([$id_campana, $codigo_local]);
                }
            }
            
            $db->commit();
            logActividad('CREAR_CAMPANA', 'marketing_campanas', "Campaña creada: $nombre");
            setFlashMessage('success', 'Campaña creada exitosamente');
            header('Location: marketing.php');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            setFlashMessage('error', 'Error al crear campaña: ' . $e->getMessage());
        }
    }
}

// Obtener campañas
$campanas = [];
try {
    $stmt = $db->prepare("
        SELECT mc.*,
               COUNT(cl.codigo_local) as total_locales,
               (SELECT COUNT(*) FROM ventas v WHERE v.id_campana = mc.id_campana) as ventas_generadas
        FROM marketing_campanas mc
        LEFT JOIN campana_local cl ON mc.id_campana = cl.id_campana
        GROUP BY mc.id_campana
        ORDER BY mc.fecha_inicio DESC
    ");
    $stmt->execute();
    $campanas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar campañas: ' . $e->getMessage());
}

// Obtener locales para selección
$locales = [];
try {
    $stmt = $db->prepare("SELECT codigo_local, nombre_local, ciudad FROM locales ORDER BY nombre_local");
    $stmt->execute();
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar locales: ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' - Gestión de Marketing';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-bullhorn me-2"></i>Gestión de Marketing
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCampana">
                        <i class="fas fa-plus me-1"></i> Nueva Campaña
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
                                    <h6 class="card-title">Total Campañas</h6>
                                    <h3 class="mb-0"><?php echo count($campanas); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-bullhorn fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Activas</h6>
                                    <?php 
                                        $activas = array_filter($campanas, fn($c) => $c['estado'] === 'ACTIVO');
                                    ?>
                                    <h3 class="mb-0"><?php echo count($activas); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-play-circle fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Prospecto</h6>
                                    <?php 
                                        $prospecto = array_filter($campanas, fn($c) => $c['estado'] === 'PROSPECTO');
                                    ?>
                                    <h3 class="mb-0"><?php echo count($prospecto); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Presupuesto Total</h6>
                                    <?php 
                                        $presupuestoTotal = array_sum(array_column($campanas, 'presupuesto'));
                                    ?>
                                    <h3 class="mb-0">$<?php echo number_format($presupuestoTotal, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Campañas -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaCampanas">
                            <thead>
                                <tr>
                                    <th>Campaña</th>
                                    <th>Tipo</th>
                                    <th>Período</th>
                                    <th>Presupuesto</th>
                                    <th>Locales</th>
                                    <th>Ventas</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campanas as $campana): 
                                    $hoy = new DateTime();
                                    $inicio = new DateTime($campana['fecha_inicio']);
                                    $fin = new DateTime($campana['fecha_fin']);
                                    $estaActiva = $hoy >= $inicio && $hoy <= $fin;
                                ?>
                                    <tr class="<?php echo $campana['estado'] === 'ACTIVO' && $estaActiva ? 'table-success' : ''; ?>">
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($campana['nombre']); ?></strong>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars(substr($campana['descripcion'], 0, 50)); ?>...
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $campana['tipo'] === 'REDES_SOCIALES' ? 'primary' :
                                                     ($campana['tipo'] === 'VIRAL' ? 'info' :
                                                     ($campana['tipo'] === 'ESTACIONAL' ? 'warning' : 'success'));
                                            ?>">
                                                <?php echo $campana['tipo']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div>Inicio: <?php echo date('d/m/Y', strtotime($campana['fecha_inicio'])); ?></div>
                                                <div>Fin: <?php echo date('d/m/Y', strtotime($campana['fecha_fin'])); ?></div>
                                                <div class="text-muted">
                                                    <?php 
                                                        $diasRestantes = $fin->diff($hoy)->days;
                                                        echo $estaActiva ? "Activa ($diasRestantes días restantes)" : 
                                                               ($hoy > $fin ? "Finalizada" : "Próxima");
                                                    ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark">$<?php echo number_format($campana['presupuesto'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $campana['total_locales']; ?> locales</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $campana['ventas_generadas']; ?> ventas</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $campana['estado'] === 'ACTIVO' ? 'success' :
                                                     ($campana['estado'] === 'PROSPECTO' ? 'warning' :
                                                     ($campana['estado'] === 'FINALIZADO' ? 'secondary' : 'danger'));
                                            ?>">
                                                <?php echo $campana['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="verDetalles(<?php echo $campana['id_campana']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="verLocales(<?php echo $campana['id_campana']; ?>)">
                                                    <i class="fas fa-store"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="verResultados(<?php echo $campana['id_campana']; ?>)">
                                                    <i class="fas fa-chart-line"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="editarCampana(<?php echo $campana['id_campana']; ?>)">
                                                    <i class="fas fa-edit"></i>
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

<!-- Modal para crear campaña -->
<div class="modal fade" id="modalCampana" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?action=crear">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Campaña de Marketing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="nombre" class="form-label">Nombre de la Campaña *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="PROSPECTO">Prospecto</option>
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                                <option value="FINALIZADO">Finalizado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label">Tipo de Campaña *</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="REDES_SOCIALES">Redes Sociales</option>
                                <option value="VIRAL">Viral</option>
                                <option value="ESTACIONAL">Estacional</option>
                                <option value="INFLUENCER">Influencer</option>
                                <option value="EMAIL">Email Marketing</option>
                                <option value="TRADICIONAL">Tradicional</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="presupuesto" class="form-label">Presupuesto *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="presupuesto" name="presupuesto" 
                                       step="0.01" min="0" required>
                            </div>
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
                    
                    <div class="mb-3">
                        <label for="influencers" class="form-label">Influencers Involucrados</label>
                        <textarea class="form-control" id="influencers" name="influencers" rows="2" 
                                  placeholder="Separar por comas los nombres de los influencers"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Locales Participantes</label>
                        <div class="row" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($locales as $local): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="local_<?php echo $local['codigo_local']; ?>" 
                                               name="locales[]" value="<?php echo $local['codigo_local']; ?>">
                                        <label class="form-check-label" for="local_<?php echo $local['codigo_local']; ?>">
                                            <?php echo htmlspecialchars($local['nombre_local'] . ' - ' . $local['ciudad']); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Campaña</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function verDetalles(id) {
    fetch(`ajax/get-campana.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const campana = data.campana;
                const modal = new bootstrap.Modal(document.getElementById('modalDetalleCampana'));
                
                document.getElementById('detalleNombre').textContent = campana.nombre;
                document.getElementById('detalleDescripcion').textContent = campana.descripcion || 'Sin descripción';
                document.getElementById('detalleTipo').textContent = campana.tipo;
                document.getElementById('detalleEstado').textContent = campana.estado;
                document.getElementById('detallePresupuesto').textContent = '$' + parseFloat(campana.presupuesto).toFixed(2);
                document.getElementById('detalleInicio').textContent = campana.fecha_inicio;
                document.getElementById('detalleFin').textContent = campana.fecha_fin;
                document.getElementById('detalleInfluencers').textContent = campana.influencers_involucrados || 'No especificado';
                document.getElementById('detalleResultados').textContent = campana.resultados || 'No disponible';
                document.getElementById('detalleVentas').textContent = campana.ventas_generadas || 0;
                document.getElementById('detalleLocales').textContent = campana.total_locales || 0;
                
                // Calcular días restantes
                const hoy = new Date();
                const fin = new Date(campana.fecha_fin);
                const diasRestantes = Math.ceil((fin - hoy) / (1000 * 60 * 60 * 24));
                document.getElementById('detalleDiasRestantes').textContent = diasRestantes > 0 ? 
                    `${diasRestantes} días` : 'Finalizada';
                
                modal.show();
            }
        })
        .catch(error => console.error('Error:', error));
}

function verLocales(id) {
    fetch(`ajax/get-locales-campana.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const locales = data.locales;
                const tbody = document.getElementById('localesCampana');
                tbody.innerHTML = '';
                
                locales.forEach(local => {
                    const row = tbody.insertRow();
                    row.innerHTML = `
                        <td>${local.codigo_local}</td>
                        <td>${local.nombre_local}</td>
                        <td>${local.ciudad}</td>
                        <td>$${parseFloat(local.presupuesto_asignado || 0).toFixed(2)}</td>
                    `;
                });
                
                const modal = new bootstrap.Modal(document.getElementById('modalLocalesCampana'));
                modal.show();
            }
        })
        .catch(error => console.error('Error:', error));
}

function verResultados(id) {
    window.location.href = `reportes/campana.php?id=${id}`;
}

function editarCampana(id) {
    // Implementar lógica de edición
    alert('Función de edición para campaña ID: ' + id);
}

// Calcular automáticamente la fecha fin (1 mes después)
document.getElementById('fecha_inicio')?.addEventListener('change', function() {
    const fechaInicio = new Date(this.value);
    if (fechaInicio) {
        const fechaFin = new Date(fechaInicio);
        fechaFin.setMonth(fechaFin.getMonth() + 1);
        document.getElementById('fecha_fin').value = fechaFin.toISOString().split('T')[0];
    }
});
</script>

<!-- Modal de detalles de campaña -->
<div class="modal fade" id="modalDetalleCampana" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de la Campaña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <dl class="row">
                            <dt class="col-sm-3">Nombre:</dt>
                            <dd class="col-sm-9"><strong id="detalleNombre"></strong></dd>
                            
                            <dt class="col-sm-3">Descripción:</dt>
                            <dd class="col-sm-9" id="detalleDescripcion"></dd>
                            
                            <dt class="col-sm-3">Tipo:</dt>
                            <dd class="col-sm-9" id="detalleTipo"></dd>
                            
                            <dt class="col-sm-3">Estado:</dt>
                            <dd class="col-sm-9" id="detalleEstado"></dd>
                        </dl>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Estadísticas</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Presupuesto:</span>
                                    <strong id="detallePresupuesto"></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Ventas:</span>
                                    <strong id="detalleVentas"></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Locales:</span>
                                    <strong id="detalleLocales"></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Días Restantes:</span>
                                    <strong id="detalleDiasRestantes"></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Fecha Inicio:</dt>
                            <dd class="col-sm-8" id="detalleInicio"></dd>
                            
                            <dt class="col-sm-4">Fecha Fin:</dt>
                            <dd class="col-sm-8" id="detalleFin"></dd>
                            
                            <dt class="col-sm-4">Influencers:</dt>
                            <dd class="col-sm-8" id="detalleInfluencers"></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Resultados:</dt>
                            <dd class="col-sm-8" id="detalleResultados"></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de locales de campaña -->
<div class="modal fade" id="modalLocalesCampana" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Locales Participantes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Ciudad</th>
                                <th>Presupuesto Asignado</th>
                            </tr>
                        </thead>
                        <tbody id="localesCampana">
                            <!-- Locales se cargarán aquí -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>