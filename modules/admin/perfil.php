<?php
require_once __DIR__ . '/../../config.php';      
require_once __DIR__ . '/../../includes/functions.php';  
require_once __DIR__ . '/../../db_connection.php';

startSession();
requireAuth();
requireAnyRole(['admin']);

$db = Database::getConnection();

$action = $_GET['action'] ?? '';
$local = $_GET['local'] ?? '';
$producto = $_GET['producto'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'ajustar') {
        $codigo_local = $_POST['codigo_local'];
        $codigo_producto = $_POST['codigo_producto'];
        $cantidad = $_POST['cantidad'];
        $tipo_ajuste = $_POST['tipo_ajuste'];
        $motivo = $_POST['motivo'];

        try {
            // Obtener cantidad actual
            $stmt = $db->prepare("SELECT cantidad FROM inventario WHERE codigo_local = ? AND codigo_producto = ?");
            $stmt->execute([$codigo_local, $codigo_producto]);
            $inventario_actual = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $cantidad_actual = $inventario_actual ? $inventario_actual['cantidad'] : 0;
            $nueva_cantidad = $tipo_ajuste === 'ENTRADA' ? 
                $cantidad_actual + $cantidad : $cantidad_actual - $cantidad;
            
            // Actualizar inventario
            if ($inventario_actual) {
                $stmt = $db->prepare("
                    UPDATE inventario 
                    SET cantidad = ?, fecha_actualizacion = CURDATE(),
                        necesita_reabastecer = (cantidad <= cantidad_minima)
                    WHERE codigo_local = ? AND codigo_producto = ?
                ");
                $stmt->execute([$nueva_cantidad, $codigo_local, $codigo_producto]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO inventario 
                    (codigo_local, codigo_producto, cantidad, cantidad_minima, 
                     cantidad_maxima, fecha_actualizacion, necesita_reabastecer)
                    VALUES (?, ?, ?, 10, 100, CURDATE(), ?)
                ");
                $necesita_reabastecer = $nueva_cantidad <= 10;
                $stmt->execute([$codigo_local, $codigo_producto, $nueva_cantidad, $necesita_reabastecer]);
            }
            
            // Registrar movimiento
            $stmt = $db->prepare("
                INSERT INTO movimientos_inventario 
                (codigo_local, codigo_producto, tipo_movimiento, cantidad, 
                 cantidad_anterior, cantidad_nueva, motivo, fecha_movimiento)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$codigo_local, $codigo_producto, $tipo_ajuste, $cantidad, 
                          $cantidad_actual, $nueva_cantidad, $motivo]);
            
            logActividad('AJUSTAR_INVENTARIO', 'inventario', 
                "Ajuste: $tipo_ajuste de $cantidad unidades de $codigo_producto en $codigo_local");
            
            setFlashMessage('success', 'Inventario ajustado exitosamente');
            header('Location: inventario.php' . ($local ? "?local=$local" : ''));
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error al ajustar inventario: ' . $e->getMessage());
        }
    }
}

// Obtener inventario con filtros
$inventario = [];
$where = "WHERE 1=1";
$params = [];

if ($local) {
    $where .= " AND i.codigo_local = ?";
    $params[] = $local;
}

if ($producto) {
    $where .= " AND i.codigo_producto = ?";
    $params[] = $producto;
}

try {
    $stmt = $db->prepare("
        SELECT i.*, 
               p.nombre as producto_nombre,
               p.precio,
               l.nombre_local,
               l.ciudad,
               ROUND((i.cantidad / i.cantidad_minima) * 100) as porcentaje_stock,
               CASE 
                   WHEN i.cantidad <= i.cantidad_minima THEN 'CRITICO'
                   WHEN i.cantidad <= (i.cantidad_minima * 2) THEN 'BAJO'
                   WHEN i.cantidad >= i.cantidad_maxima THEN 'EXCESO'
                   ELSE 'NORMAL'
               END as estado_stock
        FROM inventario i
        INNER JOIN productos p ON i.codigo_producto = p.codigo_producto
        INNER JOIN locales l ON i.codigo_local = l.codigo_local
        $where
        ORDER BY i.necesita_reabastecer DESC, i.cantidad ASC
    ");
    $stmt->execute($params);
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar inventario: ' . $e->getMessage());
}

// Obtener locales para filtro
$locales = [];
try {
    $stmt = $db->prepare("SELECT codigo_local, nombre_local, ciudad FROM locales ORDER BY nombre_local");
    $stmt->execute();
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar locales: ' . $e->getMessage());
}

// Obtener productos para filtro
$productos = [];
try {
    $stmt = $db->prepare("SELECT codigo_producto, nombre FROM productos WHERE disponible = 1 ORDER BY nombre");
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar productos: ' . $e->getMessage());
}

// Estadísticas
$estadisticas = [
    'total_productos' => count($inventario),
    'productos_criticos' => 0,
    'productos_bajos' => 0,
    'productos_exceso' => 0,
    'valor_total' => 0
];

foreach ($inventario as $item) {
    switch ($item['estado_stock']) {
        case 'CRITICO':
            $estadisticas['productos_criticos']++;
            break;
        case 'BAJO':
            $estadisticas['productos_bajos']++;
            break;
        case 'EXCESO':
            $estadisticas['productos_exceso']++;
            break;
    }
    $estadisticas['valor_total'] += $item['cantidad'] * $item['precio'];
}

$pageTitle = APP_NAME . ' - Gestión de Inventario';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-boxes me-2"></i>Gestión de Inventario
                    <?php if ($local): ?>
                        <small class="text-muted"> - Local: <?php echo htmlspecialchars($local); ?></small>
                    <?php endif; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjusteInventario">
                        <i class="fas fa-exchange-alt me-1"></i> Ajustar Inventario
                    </button>
                    <button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#modalPedido">
                        <i class="fas fa-truck me-1"></i> Nuevo Pedido
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="local" class="form-label">Local</label>
                            <select class="form-select" id="local" name="local">
                                <option value="">Todos los locales</option>
                                <?php foreach ($locales as $loc): ?>
                                    <option value="<?php echo $loc['codigo_local']; ?>" 
                                            <?php echo $local === $loc['codigo_local'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['nombre_local'] . ' - ' . $loc['ciudad']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="producto" class="form-label">Producto</label>
                            <select class="form-select" id="producto" name="producto">
                                <option value="">Todos los productos</option>
                                <?php foreach ($productos as $prod): ?>
                                    <option value="<?php echo $prod['codigo_producto']; ?>" 
                                            <?php echo $producto === $prod['codigo_producto'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prod['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado" onchange="filtrarPorEstado(this.value)">
                                <option value="">Todos</option>
                                <option value="CRITICO">Crítico</option>
                                <option value="BAJO">Bajo</option>
                                <option value="NORMAL">Normal</option>
                                <option value="EXCESO">Exceso</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
                                <a href="inventario.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-1"></i> Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Productos</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['total_productos']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-box fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Crítico</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['productos_criticos']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Bajo</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['productos_bajos']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-arrow-down fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Normal</h6>
                                    <?php 
                                        $productos_normales = $estadisticas['total_productos'] - 
                                                            $estadisticas['productos_criticos'] - 
                                                            $estadisticas['productos_bajos'] - 
                                                            $estadisticas['productos_exceso'];
                                    ?>
                                    <h3 class="mb-0"><?php echo $productos_normales; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Exceso</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['productos_exceso']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-arrow-up fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-secondary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Valor Total</h6>
                                    <h3 class="mb-0">$<?php echo number_format($estadisticas['valor_total'], 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Inventario -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaInventario">
                            <thead>
                                <tr>
                                    <th>Local</th>
                                    <th>Producto</th>
                                    <th>Stock Actual</th>
                                    <th>Mínimo</th>
                                    <th>Máximo</th>
                                    <th>Estado</th>
                                    <th>Valor</th>
                                    <th>Última Actualización</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventario as $item): ?>
                                    <tr class="<?php 
                                        echo $item['estado_stock'] === 'CRITICO' ? 'table-danger' : 
                                               ($item['estado_stock'] === 'BAJO' ? 'table-warning' : 
                                               ($item['estado_stock'] === 'EXCESO' ? 'table-info' : ''));
                                    ?>">
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($item['nombre_local']); ?></strong></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($item['ciudad']); ?></div>
                                        </td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($item['producto_nombre']); ?></strong></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($item['codigo_producto']); ?></div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                                    <div class="progress-bar bg-<?php 
                                                        echo $item['porcentaje_stock'] <= 20 ? 'danger' : 
                                                             ($item['porcentaje_stock'] <= 50 ? 'warning' : 'success');
                                                    ?>" style="width: <?php echo min($item['porcentaje_stock'], 100); ?>%"></div>
                                                </div>
                                                <span class="badge bg-dark"><?php echo $item['cantidad']; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $item['cantidad_minima']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $item['cantidad_maxima']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $item['estado_stock'] === 'CRITICO' ? 'danger' : 
                                                     ($item['estado_stock'] === 'BAJO' ? 'warning' : 
                                                     ($item['estado_stock'] === 'EXCESO' ? 'info' : 'success'));
                                            ?>">
                                                <?php echo $item['estado_stock']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">$<?php echo number_format($item['cantidad'] * $item['precio'], 2); ?></span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($item['fecha_actualizacion'])); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="ajustarInventario('<?php echo $item['codigo_local']; ?>', '<?php echo $item['codigo_producto']; ?>', '<?php echo $item['producto_nombre']; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="verMovimientos('<?php echo $item['codigo_local']; ?>', '<?php echo $item['codigo_producto']; ?>')">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="hacerPedido('<?php echo $item['codigo_local']; ?>', '<?php echo $item['codigo_producto']; ?>', '<?php echo $item['producto_nombre']; ?>')">
                                                    <i class="fas fa-truck"></i>
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

<!-- Modal para ajustar inventario -->
<div class="modal fade" id="modalAjusteInventario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=ajustar">
                <div class="modal-header">
                    <h5 class="modal-title">Ajustar Inventario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="codigo_local" id="codigo_local_ajuste">
                    <input type="hidden" name="codigo_producto" id="codigo_producto_ajuste">
                    
                    <div class="mb-3">
                        <label for="producto_seleccionado" class="form-label">Producto</label>
                        <input type="text" class="form-control" id="producto_seleccionado" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipo_ajuste" class="form-label">Tipo de Ajuste *</label>
                        <select class="form-select" id="tipo_ajuste" name="tipo_ajuste" required>
                            <option value="ENTRADA">Entrada (Agregar)</option>
                            <option value="SALIDA">Salida (Retirar)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cantidad" class="form-label">Cantidad *</label>
                        <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo *</label>
                        <select class="form-select" id="motivo" name="motivo" required>
                            <option value="">Seleccionar...</option>
                            <option value="COMPRA">Compra</option>
                            <option value="VENTA">Venta</option>
                            <option value="DONACION">Donación</option>
                            <option value="PERDIDA">Pérdida</option>
                            <option value="DANADO">Dañado</option>
                            <option value="AJUSTE">Ajuste de inventario</option>
                            <option value="TRANSFERENCIA">Transferencia entre locales</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Este ajuste afectará el stock disponible del producto.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Aplicar Ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filtrarPorEstado(estado) {
    const rows = document.querySelectorAll('#tablaInventario tbody tr');
    rows.forEach(row => {
        const estadoRow = row.cells[5].textContent;
        if (!estado || estadoRow.includes(estado)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function ajustarInventario(codigoLocal, codigoProducto, productoNombre) {
    document.getElementById('codigo_local_ajuste').value = codigoLocal;
    document.getElementById('codigo_producto_ajuste').value = codigoProducto;
    document.getElementById('producto_seleccionado').value = productoNombre;
    
    const modal = new bootstrap.Modal(document.getElementById('modalAjusteInventario'));
    modal.show();
}

function verMovimientos(codigoLocal, codigoProducto) {
    fetch(`ajax/get-movimientos.php?local=${codigoLocal}&producto=${codigoProducto}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const movimientos = data.movimientos;
                const tbody = document.getElementById('movimientosProducto');
                tbody.innerHTML = '';
                
                movimientos.forEach(mov => {
                    const row = tbody.insertRow();
                    row.innerHTML = `
                        <td>${mov.fecha_movimiento}</td>
                        <td>
                            <span class="badge bg-${mov.tipo_movimiento === 'ENTRADA' ? 'success' : 'danger'}">
                                ${mov.tipo_movimiento}
                            </span>
                        </td>
                        <td>${mov.cantidad}</td>
                        <td>${mov.cantidad_anterior}</td>
                        <td>${mov.cantidad_nueva}</td>
                        <td>${mov.motivo}</td>
                    `;
                });
                
                const modal = new bootstrap.Modal(document.getElementById('modalMovimientos'));
                modal.show();
            }
        })
        .catch(error => console.error('Error:', error));
}

function hacerPedido(codigoLocal, codigoProducto, productoNombre) {
    document.getElementById('codigo_local_pedido').value = codigoLocal;
    document.getElementById('codigo_producto_pedido').value = codigoProducto;
    document.getElementById('producto_pedido').value = productoNombre;
    
    const modal = new bootstrap.Modal(document.getElementById('modalPedido'));
    modal.show();
}
</script>

<!-- Modal de movimientos -->
<div class="modal fade" id="modalMovimientos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Movimientos de Inventario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Anterior</th>
                                <th>Nuevo</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody id="movimientosProducto">
                            <!-- Movimientos se cargarán aquí -->
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

<!-- Modal para hacer pedido -->
<div class="modal fade" id="modalPedido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="ajax/crear-pedido.php">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="codigo_local" id="codigo_local_pedido">
                    <input type="hidden" name="codigo_producto" id="codigo_producto_pedido">
                    
                    <div class="mb-3">
                        <label for="producto_pedido" class="form-label">Producto</label>
                        <input type="text" class="form-control" id="producto_pedido" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cantidad_pedido" class="form-label">Cantidad a Pedir *</label>
                        <input type="number" class="form-control" id="cantidad_pedido" name="cantidad" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="proveedor" class="form-label">Proveedor *</label>
                        <input type="text" class="form-control" id="proveedor" name="proveedor" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_entrega" class="form-label">Fecha Esperada de Entrega</label>
                        <input type="date" class="form-control" id="fecha_entrega" name="fecha_entrega">
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Este pedido será registrado y deberá ser aprobado por el administrador.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear Pedido</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>