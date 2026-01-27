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
$local = $_GET['local'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// Obtener ventas con filtros
$ventas = [];
$where = "WHERE 1=1";
$params = [];

if ($local) {
    $where .= " AND v.codigo_local = ?";
    $params[] = $local;
}

if ($fecha_inicio) {
    $where .= " AND DATE(v.fecha_venta) >= ?";
    $params[] = $fecha_inicio;
}

if ($fecha_fin) {
    $where .= " AND DATE(v.fecha_venta) <= ?";
    $params[] = $fecha_fin;
}

try {
    $stmt = $db->prepare("
        SELECT v.*, 
               l.nombre_local, l.ciudad,
               CONCAT(c.nombres, ' ', c.apellidos) as cliente_nombre,
               mc.nombre as campana_nombre,
               (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.id_venta = v.id_venta) as total_productos
        FROM ventas v
        LEFT JOIN locales l ON v.codigo_local = l.codigo_local
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        LEFT JOIN marketing_campanas mc ON v.id_campana = mc.id_campana
        $where
        ORDER BY v.fecha_venta DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar ventas: ' . $e->getMessage());
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

// Estadísticas
$estadisticas = [
    'total_ventas' => 0,
    'total_iva' => 0,
    'total_neto' => 0,
    'ventas_diarias' => 0,
    'promedio_venta' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            SUM(total) as total_ingresos,
            SUM(iva) as total_iva,
            SUM(subtotal) as total_neto,
            AVG(total) as promedio_venta
        FROM ventas
        $where
    ");
    $stmt->execute($params);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al calcular estadísticas: ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' - Gestión de Ventas';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-chart-line me-2"></i>Gestión de Ventas
                    <?php if ($local): ?>
                        <small class="text-muted"> - Local: <?php echo htmlspecialchars($local); ?></small>
                    <?php endif; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaVenta">
                        <i class="fas fa-plus me-1"></i> Nueva Venta
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
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
                        <div class="col-md-3">
                            <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                   value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_fin" class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                   value="<?php echo htmlspecialchars($fecha_fin); ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
                                <a href="ventas.php" class="btn btn-outline-secondary">
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
                                    <h6 class="card-title">Total Ventas</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['total_ventas'] ?? 0; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Ingresos</h6>
                                    <h3 class="mb-0">$<?php echo number_format($estadisticas['total_ingresos'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">IVA</h6>
                                    <h3 class="mb-0">$<?php echo number_format($estadisticas['total_iva'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-percentage fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Neto</h6>
                                    <h3 class="mb-0">$<?php echo number_format($estadisticas['total_neto'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-bar fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Promedio</h6>
                                    <h3 class="mb-0">$<?php echo number_format($estadisticas['promedio_venta'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calculator fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Productos</h6>
                                    <?php 
                                        $totalProductos = array_sum(array_column($ventas, 'total_productos'));
                                    ?>
                                    <h3 class="mb-0"><?php echo $totalProductos; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-box fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Ventas -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaVentas">
                            <thead>
                                <tr>
                                    <th>Factura</th>
                                    <th>Fecha</th>
                                    <th>Local</th>
                                    <th>Cliente</th>
                                    <th>Productos</th>
                                    <th>Subtotal</th>
                                    <th>IVA</th>
                                    <th>Total</th>
                                    <th>Pago</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas as $venta): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($venta['numero_factura']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></div>
                                            <div class="text-muted small"><?php echo date('H:i', strtotime($venta['fecha_venta'])); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($venta['nombre_local']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($venta['ciudad']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($venta['cliente_nombre']): ?>
                                                <div><?php echo htmlspecialchars($venta['cliente_nombre']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">Consumidor final</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $venta['total_productos']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">$<?php echo number_format($venta['subtotal'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">$<?php echo number_format($venta['iva'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">$<?php echo number_format($venta['total'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $venta['forma_pago'] === 'EFECTIVO' ? 'success' : 
                                                     ($venta['forma_pago'] === 'TARJETA' ? 'primary' : 'info');
                                            ?>">
                                                <?php echo $venta['forma_pago']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="verDetalles(<?php echo $venta['id_venta']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="imprimirFactura('<?php echo $venta['numero_factura']; ?>')">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="anularVenta(<?php echo $venta['id_venta']; ?>)">
                                                    <i class="fas fa-ban"></i>
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

<!-- Modal para nueva venta -->
<div class="modal fade" id="modalNuevaVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="formVenta" method="POST" action="ajax/crear-venta.php">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Información de la Venta</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="local_venta" class="form-label">Local *</label>
                                        <select class="form-select" id="local_venta" name="local_venta" required>
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($locales as $loc): ?>
                                                <option value="<?php echo $loc['codigo_local']; ?>">
                                                    <?php echo htmlspecialchars($loc['nombre_local'] . ' - ' . $loc['ciudad']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="cliente_venta" class="form-label">Cliente</label>
                                        <select class="form-select" id="cliente_venta" name="cliente_venta">
                                            <option value="">Consumidor final</option>
                                            <!-- Opciones de clientes se cargarán con AJAX -->
                                        </select>
                                        <small class="text-muted">Dejar vacío para consumidor final</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="campana_venta" class="form-label">Campaña</label>
                                        <select class="form-select" id="campana_venta" name="campana_venta">
                                            <option value="">Sin campaña</option>
                                            <!-- Opciones de campañas se cargarán con AJAX -->
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="forma_pago" class="form-label">Forma de Pago *</label>
                                        <select class="form-select" id="forma_pago" name="forma_pago" required>
                                            <option value="EFECTIVO">Efectivo</option>
                                            <option value="TARJETA">Tarjeta</option>
                                            <option value="TRANSFERENCIA">Transferencia</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tipo_consumo" class="form-label">Tipo de Consumo</label>
                                        <select class="form-select" id="tipo_consumo" name="tipo_consumo">
                                            <option value="LOCAL">En local</option>
                                            <option value="PARA LLEVAR">Para llevar</option>
                                            <option value="DOMICILIO">Domicilio</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Productos</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="buscar_producto" class="form-label">Buscar Producto</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="buscar_producto" 
                                                   placeholder="Código o nombre del producto">
                                            <button class="btn btn-outline-secondary" type="button" onclick="buscarProducto()">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div id="resultados_busqueda" class="mb-3" style="max-height: 200px; overflow-y: auto;">
                                        <!-- Resultados de búsqueda -->
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-sm" id="tablaProductosVenta">
                                            <thead>
                                                <tr>
                                                    <th>Producto</th>
                                                    <th>Cantidad</th>
                                                    <th>Precio</th>
                                                    <th>Subtotal</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody id="productosSeleccionados">
                                                <!-- Productos seleccionados -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Resumen</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Subtotal:</span>
                                            <span id="subtotal">$0.00</span>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>IVA (12%):</span>
                                            <span id="iva">$0.00</span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <strong>Total:</strong>
                                            <strong id="total">$0.00</strong>
                                        </div>
                                    </div>
                                    
                                    <input type="hidden" name="subtotal" id="inputSubtotal" value="0">
                                    <input type="hidden" name="iva" id="inputIva" value="0">
                                    <input type="hidden" name="total" id="inputTotal" value="0">
                                    <input type="hidden" name="productos" id="inputProductos">
                                    
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-success" onclick="finalizarVenta()">
                                            <i class="fas fa-check me-1"></i> Finalizar Venta
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="limpiarVenta()">
                                            <i class="fas fa-times me-1"></i> Cancelar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let productosVenta = [];

function verDetalles(id) {
    fetch(`ajax/get-venta.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const venta = data.venta;
                const detalles = data.detalles;
                
                const modal = new bootstrap.Modal(document.getElementById('modalDetalleVenta'));
                
                document.getElementById('detalleFactura').textContent = venta.numero_factura;
                document.getElementById('detalleFecha').textContent = venta.fecha_venta;
                document.getElementById('detalleLocal').textContent = venta.nombre_local + ' - ' + venta.ciudad;
                document.getElementById('detalleCliente').textContent = venta.cliente_nombre || 'Consumidor final';
                document.getElementById('detalleFormaPago').textContent = venta.forma_pago;
                document.getElementById('detalleTipoConsumo').textContent = venta.tipo_consumo;
                document.getElementById('detalleSubtotal').textContent = '$' + parseFloat(venta.subtotal).toFixed(2);
                document.getElementById('detalleIva').textContent = '$' + parseFloat(venta.iva).toFixed(2);
                document.getElementById('detalleTotal').textContent = '$' + parseFloat(venta.total).toFixed(2);
                
                // Limpiar tabla de detalles
                const tbody = document.getElementById('detallesProductos');
                tbody.innerHTML = '';
                
                // Agregar detalles
                detalles.forEach(detalle => {
                    const row = tbody.insertRow();
                    row.innerHTML = `
                        <td>${detalle.producto_nombre}</td>
                        <td>${detalle.cantidad}</td>
                        <td>$${parseFloat(detalle.precio_unitario).toFixed(2)}</td>
                        <td>$${parseFloat(detalle.cantidad * detalle.precio_unitario).toFixed(2)}</td>
                    `;
                });
                
                modal.show();
            }
        })
        .catch(error => console.error('Error:', error));
}

function imprimirFactura(numeroFactura) {
    window.open(`reportes/factura.php?numero=${numeroFactura}`, '_blank');
}

function anularVenta(id) {
    if (confirm('¿Está seguro de anular esta venta?')) {
        fetch(`ajax/anular-venta.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Venta anulada exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
    }
}

function buscarProducto() {
    const termino = document.getElementById('buscar_producto').value;
    if (termino.length < 2) return;
    
    fetch(`ajax/buscar-producto.php?q=${termino}`)
        .then(response => response.json())
        .then(data => {
            const resultados = document.getElementById('resultados_busqueda');
            resultados.innerHTML = '';
            
            if (data.success && data.productos.length > 0) {
                data.productos.forEach(producto => {
                    const div = document.createElement('div');
                    div.className = 'card mb-2';
                    div.innerHTML = `
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">${producto.nombre}</h6>
                                    <small class="text-muted">${producto.codigo_producto} - $${parseFloat(producto.precio).toFixed(2)}</small>
                                </div>
                                <button class="btn btn-sm btn-primary" onclick="agregarProducto('${producto.codigo_producto}', '${producto.nombre}', ${producto.precio})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    resultados.appendChild(div);
                });
            } else {
                resultados.innerHTML = '<div class="alert alert-warning">No se encontraron productos</div>';
            }
        })
        .catch(error => console.error('Error:', error));
}

function agregarProducto(codigo, nombre, precio) {
    // Verificar si el producto ya está en la lista
    const index = productosVenta.findIndex(p => p.codigo === codigo);
    
    if (index !== -1) {
        // Incrementar cantidad
        productosVenta[index].cantidad++;
    } else {
        // Agregar nuevo producto
        productosVenta.push({
            codigo: codigo,
            nombre: nombre,
            precio: precio,
            cantidad: 1
        });
    }
    
    actualizarTablaProductos();
    calcularTotales();
}

function actualizarTablaProductos() {
    const tbody = document.getElementById('productosSeleccionados');
    tbody.innerHTML = '';
    
    productosVenta.forEach((producto, index) => {
        const subtotal = producto.precio * producto.cantidad;
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>
                <div class="fw-bold">${producto.nombre}</div>
                <small class="text-muted">${producto.codigo} - $${parseFloat(producto.precio).toFixed(2)} c/u</small>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <button class="btn btn-outline-secondary" type="button" onclick="modificarCantidad(${index}, -1)">-</button>
                    <input type="number" class="form-control text-center" value="${producto.cantidad}" min="1" 
                           onchange="actualizarCantidad(${index}, this.value)">
                    <button class="btn btn-outline-secondary" type="button" onclick="modificarCantidad(${index}, 1)">+</button>
                </div>
            </td>
            <td>$${parseFloat(producto.precio).toFixed(2)}</td>
            <td>$${parseFloat(subtotal).toFixed(2)}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="eliminarProducto(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
    });
}

function modificarCantidad(index, cambio) {
    productosVenta[index].cantidad = Math.max(1, productosVenta[index].cantidad + cambio);
    actualizarTablaProductos();
    calcularTotales();
}

function actualizarCantidad(index, cantidad) {
    productosVenta[index].cantidad = Math.max(1, parseInt(cantidad) || 1);
    calcularTotales();
}

function eliminarProducto(index) {
    productosVenta.splice(index, 1);
    actualizarTablaProductos();
    calcularTotales();
}

function calcularTotales() {
    let subtotal = 0;
    
    productosVenta.forEach(producto => {
        subtotal += producto.precio * producto.cantidad;
    });
    
    const iva = subtotal * 0.12;
    const total = subtotal + iva;
    
    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('iva').textContent = '$' + iva.toFixed(2);
    document.getElementById('total').textContent = '$' + total.toFixed(2);
    
    document.getElementById('inputSubtotal').value = subtotal.toFixed(2);
    document.getElementById('inputIva').value = iva.toFixed(2);
    document.getElementById('inputTotal').value = total.toFixed(2);
    document.getElementById('inputProductos').value = JSON.stringify(productosVenta);
}

function finalizarVenta() {
    if (productosVenta.length === 0) {
        alert('Debe agregar al menos un producto');
        return;
    }
    
    if (!document.getElementById('local_venta').value) {
        alert('Debe seleccionar un local');
        return;
    }
    
    if (!document.getElementById('forma_pago').value) {
        alert('Debe seleccionar una forma de pago');
        return;
    }
    
    if (confirm('¿Desea finalizar la venta?')) {
        document.getElementById('formVenta').submit();
    }
}

function limpiarVenta() {
    productosVenta = [];
    actualizarTablaProductos();
    calcularTotales();
    document.getElementById('formVenta').reset();
}

// Cargar clientes y campañas al abrir el modal
document.getElementById('modalNuevaVenta')?.addEventListener('show.bs.modal', function () {
    // Cargar clientes
    fetch('ajax/get-clientes.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('cliente_venta');
            select.innerHTML = '<option value="">Consumidor final</option>';
            
            if (data.success) {
                data.clientes.forEach(cliente => {
                    const option = document.createElement('option');
                    option.value = cliente.id_cliente;
                    option.textContent = `${cliente.nombres} ${cliente.apellidos} - ${cliente.cedula || ''}`;
                    select.appendChild(option);
                });
            }
        });
    
    // Cargar campañas activas
    fetch('ajax/get-campanas-activas.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('campana_venta');
            select.innerHTML = '<option value="">Sin campaña</option>';
            
            if (data.success) {
                data.campanas.forEach(campana => {
                    const option = document.createElement('option');
                    option.value = campana.id_campana;
                    option.textContent = campana.nombre;
                    select.appendChild(option);
                });
            }
        });
});
</script>

<!-- Modal de detalles de venta -->
<div class="modal fade" id="modalDetalleVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Factura:</dt>
                            <dd class="col-sm-8"><strong id="detalleFactura"></strong></dd>
                            
                            <dt class="col-sm-4">Fecha:</dt>
                            <dd class="col-sm-8" id="detalleFecha"></dd>
                            
                            <dt class="col-sm-4">Local:</dt>
                            <dd class="col-sm-8" id="detalleLocal"></dd>
                            
                            <dt class="col-sm-4">Cliente:</dt>
                            <dd class="col-sm-8" id="detalleCliente"></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Forma de Pago:</dt>
                            <dd class="col-sm-8" id="detalleFormaPago"></dd>
                            
                            <dt class="col-sm-4">Tipo de Consumo:</dt>
                            <dd class="col-sm-8" id="detalleTipoConsumo"></dd>
                            
                            <dt class="col-sm-4">Subtotal:</dt>
                            <dd class="col-sm-8" id="detalleSubtotal"></dd>
                            
                            <dt class="col-sm-4">IVA:</dt>
                            <dd class="col-sm-8" id="detalleIva"></dd>
                            
                            <dt class="col-sm-4">Total:</dt>
                            <dd class="col-sm-8" id="detalleTotal"></dd>
                        </dl>
                    </div>
                </div>
                
                <h6 class="mb-3">Productos</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="detallesProductos">
                            <!-- Detalles se cargarán aquí -->
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