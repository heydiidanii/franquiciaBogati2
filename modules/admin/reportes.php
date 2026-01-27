<?php
require_once __DIR__ . '/../../config.php';      
require_once __DIR__ . '/../../includes/functions.php';  
require_once __DIR__ . '/../../db_connection.php';

startSession();
requireAuth();
requireAnyRole(['admin']);

$db = Database::getConnection();

$tipo_reporte = $_GET['tipo'] ?? 'ventas';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$local = $_GET['local'] ?? '';

// Obtener locales para filtro
$locales = [];
try {
    $stmt = $db->prepare("SELECT codigo_local, nombre_local, ciudad FROM locales ORDER BY nombre_local");
    $stmt->execute();
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar locales: ' . $e->getMessage());
}

// Datos para reportes
$datos_reporte = [];

switch ($tipo_reporte) {
    case 'ventas':
        // Reporte de ventas por día
        try {
            $stmt = $db->prepare("
                SELECT 
                    DATE(fecha_venta) as fecha,
                    COUNT(*) as total_ventas,
                    SUM(subtotal) as subtotal,
                    SUM(iva) as iva,
                    SUM(total) as total
                FROM ventas
                WHERE DATE(fecha_venta) BETWEEN ? AND ?
                " . ($local ? " AND codigo_local = ?" : "") . "
                GROUP BY DATE(fecha_venta)
                ORDER BY fecha
            ");
            $params = [$fecha_inicio, $fecha_fin];
            if ($local) $params[] = $local;
            $stmt->execute($params);
            $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al cargar reporte de ventas: ' . $e->getMessage());
        }
        break;
        
    case 'productos':
        // Productos más vendidos
        try {
            $stmt = $db->prepare("
                SELECT 
                    p.nombre,
                    p.codigo_producto,
                    SUM(dv.cantidad) as total_vendido,
                    SUM(dv.cantidad * dv.precio_unitario) as ingresos
                FROM detalle_ventas dv
                INNER JOIN ventas v ON dv.id_venta = v.id_venta
                INNER JOIN productos p ON dv.codigo_producto = p.codigo_producto
                WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
                " . ($local ? " AND v.codigo_local = ?" : "") . "
                GROUP BY dv.codigo_producto
                ORDER BY total_vendido DESC
                LIMIT 10
            ");
            $params = [$fecha_inicio, $fecha_fin];
            if ($local) $params[] = $local;
            $stmt->execute($params);
            $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al cargar reporte de productos: ' . $e->getMessage());
        }
        break;
        
    case 'locales':
        // Desempeño por local
        try {
            $stmt = $db->prepare("
                SELECT 
                    l.codigo_local,
                    l.nombre_local,
                    l.ciudad,
                    COUNT(v.id_venta) as total_ventas,
                    SUM(v.subtotal) as subtotal,
                    SUM(v.iva) as iva,
                    SUM(v.total) as total,
                    AVG(v.total) as promedio_venta
                FROM locales l
                LEFT JOIN ventas v ON l.codigo_local = v.codigo_local 
                    AND DATE(v.fecha_venta) BETWEEN ? AND ?
                GROUP BY l.codigo_local
                ORDER BY total DESC
            ");
            $stmt->execute([$fecha_inicio, $fecha_fin]);
            $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al cargar reporte de locales: ' . $e->getMessage());
        }
        break;
        
    case 'franquiciados':
        // Reporte de franquiciados
        try {
            $stmt = $db->prepare("
                SELECT 
                    f.cedula,
                    CONCAT(f.nombres, ' ', f.apellidos) as nombre,
                    COUNT(l.codigo_local) as total_locales,
                    COUNT(cf.id_contrato) as total_contratos,
                    SUM(COALESCE(v.total, 0)) as total_ventas
                FROM franquiciados f
                LEFT JOIN locales l ON f.cedula = l.cedula_franquiciado
                LEFT JOIN contratos_franquicia cf ON f.cedula = cf.cedula_franquiciado
                LEFT JOIN ventas v ON l.codigo_local = v.codigo_local 
                    AND DATE(v.fecha_venta) BETWEEN ? AND ?
                GROUP BY f.cedula
                ORDER BY total_ventas DESC
            ");
            $stmt->execute([$fecha_inicio, $fecha_fin]);
            $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al cargar reporte de franquiciados: ' . $e->getMessage());
        }
        break;
        
    case 'pagos':
        // Reporte de pagos
        try {
            $stmt = $db->prepare("
                SELECT 
                    pr.mes,
                    pr.anio,
                    pr.estado,
                    COUNT(*) as total_pagos,
                    SUM(pr.monto_royalty + pr.monto_publicidad) as total_monto
                FROM pagos_royalty pr
                WHERE CONCAT(pr.anio, '-', LPAD(pr.mes, 2, '0'), '-01') BETWEEN ? AND ?
                GROUP BY pr.mes, pr.anio, pr.estado
                ORDER BY pr.anio DESC, pr.mes DESC
            ");
            $stmt->execute([$fecha_inicio, $fecha_fin]);
            $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al cargar reporte de pagos: ' . $e->getMessage());
        }
        break;
}

// Estadísticas generales
$estadisticas = [];
try {
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta) BETWEEN ? AND ?) as total_ventas,
            (SELECT SUM(total) FROM ventas WHERE DATE(fecha_venta) BETWEEN ? AND ?) as ingresos_totales,
            (SELECT AVG(total) FROM ventas WHERE DATE(fecha_venta) BETWEEN ? AND ?) as promedio_venta,
            (SELECT COUNT(DISTINCT id_cliente) FROM ventas WHERE DATE(fecha_venta) BETWEEN ? AND ? AND id_cliente IS NOT NULL) as clientes_unicos,
            (SELECT SUM(monto_royalty + monto_publicidad) FROM pagos_royalty WHERE estado = 'CANCELADO' 
             AND CONCAT(anio, '-', LPAD(mes, 2, '0'), '-01') BETWEEN ? AND ?) as pagos_recaudados
    ");
    $params = array_fill(0, 8, $fecha_inicio);
    for ($i = 0; $i < 8; $i++) {
        $params[$i] = $i % 2 == 0 ? $fecha_inicio : $fecha_fin;
    }
    $stmt->execute($params);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar estadísticas: ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' - Reportes y Estadísticas';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-chart-bar me-2"></i>Reportes y Estadísticas
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" onclick="exportarReporte()">
                        <i class="fas fa-download me-1"></i> Exportar
                    </button>
                    <button class="btn btn-success ms-2" onclick="imprimirReporte()">
                        <i class="fas fa-print me-1"></i> Imprimir
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="tipo" class="form-label">Tipo de Reporte</label>
                            <select class="form-select" id="tipo" name="tipo" onchange="this.form.submit()">
                                <option value="ventas" <?php echo $tipo_reporte === 'ventas' ? 'selected' : ''; ?>>Ventas Diarias</option>
                                <option value="productos" <?php echo $tipo_reporte === 'productos' ? 'selected' : ''; ?>>Productos Más Vendidos</option>
                                <option value="locales" <?php echo $tipo_reporte === 'locales' ? 'selected' : ''; ?>>Desempeño por Local</option>
                                <option value="franquiciados" <?php echo $tipo_reporte === 'franquiciados' ? 'selected' : ''; ?>>Franquiciados</option>
                                <option value="pagos" <?php echo $tipo_reporte === 'pagos' ? 'selected' : ''; ?>>Pagos de Royalty</option>
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
                        <div class="col-md-3">
                            <label for="local" class="form-label">Local</label>
                            <select class="form-select" id="local" name="local">
                                <option value="">Todos los locales</option>
                                <?php foreach ($locales as $loc): ?>
                                    <option value="<?php echo $loc['codigo_local']; ?>" 
                                            <?php echo $local === $loc['codigo_local'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['nombre_local']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Generar Reporte
                                </button>
                                <a href="reportes.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-1"></i> Limpiar
                                </a>
                                <button type="button" class="btn btn-outline-info" onclick="generarGrafico()">
                                    <i class="fas fa-chart-line me-1"></i> Ver Gráfico
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Estadísticas Rápidas -->
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
                                    <h3 class="mb-0">$<?php echo number_format($estadisticas['ingresos_totales'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Promedio Venta</h6>
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
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Clientes Únicos</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['clientes_unicos'] ?? 0; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Pagos Recaudados</h6>
                                    <h3 class="mb-0">$<?php echo number_format($estadisticas['pagos_recaudados'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
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
                                    <h6 class="card-title">Período</h6>
                                    <h3 class="mb-0"><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-alt fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reporte -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0" id="tituloReporte">
                        <?php 
                            $titulos = [
                                'ventas' => 'Reporte de Ventas Diarias',
                                'productos' => 'Productos Más Vendidos',
                                'locales' => 'Desempeño por Local',
                                'franquiciados' => 'Reporte de Franquiciados',
                                'pagos' => 'Reporte de Pagos de Royalty'
                            ];
                            echo $titulos[$tipo_reporte] ?? 'Reporte';
                        ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($datos_reporte)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No hay datos para mostrar con los filtros seleccionados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="tablaReporte">
                                <thead>
                                    <tr>
                                        <?php 
                                        // Definir columnas según tipo de reporte
                                        $columnas = [];
                                        switch ($tipo_reporte) {
                                            case 'ventas':
                                                $columnas = ['Fecha', 'Ventas', 'Subtotal', 'IVA', 'Total'];
                                                break;
                                            case 'productos':
                                                $columnas = ['Producto', 'Código', 'Cantidad Vendida', 'Ingresos'];
                                                break;
                                            case 'locales':
                                                $columnas = ['Local', 'Ciudad', 'Ventas', 'Subtotal', 'IVA', 'Total', 'Promedio'];
                                                break;
                                            case 'franquiciados':
                                                $columnas = ['Franquiciado', 'Cédula', 'Locales', 'Contratos', 'Ventas Totales'];
                                                break;
                                            case 'pagos':
                                                $columnas = ['Período', 'Estado', 'Pagos', 'Monto Total'];
                                                break;
                                        }
                                        foreach ($columnas as $columna): ?>
                                            <th><?php echo $columna; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($datos_reporte as $fila): ?>
                                        <tr>
                                            <?php switch ($tipo_reporte):
                                                case 'ventas': ?>
                                                    <td><?php echo date('d/m/Y', strtotime($fila['fecha'])); ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $fila['total_ventas']; ?></span></td>
                                                    <td><span class="badge bg-secondary">$<?php echo number_format($fila['subtotal'], 2); ?></span></td>
                                                    <td><span class="badge bg-warning">$<?php echo number_format($fila['iva'], 2); ?></span></td>
                                                    <td><span class="badge bg-success">$<?php echo number_format($fila['total'], 2); ?></span></td>
                                                    <?php break;
                                                    
                                                case 'productos': ?>
                                                    <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
                                                    <td><span class="badge bg-dark"><?php echo htmlspecialchars($fila['codigo_producto']); ?></span></td>
                                                    <td><span class="badge bg-info"><?php echo $fila['total_vendido']; ?></span></td>
                                                    <td><span class="badge bg-success">$<?php echo number_format($fila['ingresos'], 2); ?></span></td>
                                                    <?php break;
                                                    
                                                case 'locales': ?>
                                                    <td><strong><?php echo htmlspecialchars($fila['nombre_local']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($fila['ciudad']); ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $fila['total_ventas']; ?></span></td>
                                                    <td><span class="badge bg-secondary">$<?php echo number_format($fila['subtotal'], 2); ?></span></td>
                                                    <td><span class="badge bg-warning">$<?php echo number_format($fila['iva'], 2); ?></span></td>
                                                    <td><span class="badge bg-success">$<?php echo number_format($fila['total'], 2); ?></span></td>
                                                    <td><span class="badge bg-info">$<?php echo number_format($fila['promedio_venta'], 2); ?></span></td>
                                                    <?php break;
                                                    
                                                case 'franquiciados': ?>
                                                    <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
                                                    <td><?php echo htmlspecialchars($fila['cedula']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo $fila['total_locales']; ?></span></td>
                                                    <td><span class="badge bg-info"><?php echo $fila['total_contratos']; ?></span></td>
                                                    <td><span class="badge bg-success">$<?php echo number_format($fila['total_ventas'], 2); ?></span></td>
                                                    <?php break;
                                                    
                                                case 'pagos': ?>
                                                    <td><?php echo date('F', mktime(0, 0, 0, $fila['mes'], 1)) . ' ' . $fila['anio']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $fila['estado'] === 'CANCELADO' ? 'success' : 'danger'; ?>">
                                                            <?php echo $fila['estado']; ?>
                                                        </span>
                                                    </td>
                                                    <td><span class="badge bg-primary"><?php echo $fila['total_pagos']; ?></span></td>
                                                    <td><span class="badge bg-success">$<?php echo number_format($fila['total_monto'], 2); ?></span></td>
                                                    <?php break;
                                            endswitch; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <?php if ($tipo_reporte === 'ventas' || $tipo_reporte === 'locales'): ?>
                                    <tfoot>
                                        <tr class="table-active">
                                            <th colspan="<?php echo $tipo_reporte === 'ventas' ? 2 : 3; ?>">TOTALES</th>
                                            <?php 
                                                $total_ventas = array_sum(array_column($datos_reporte, 'total_ventas'));
                                                $total_subtotal = array_sum(array_column($datos_reporte, 'subtotal'));
                                                $total_iva = array_sum(array_column($datos_reporte, 'iva'));
                                                $total_total = array_sum(array_column($datos_reporte, 'total'));
                                            ?>
                                            <th><span class="badge bg-primary"><?php echo $total_ventas; ?></span></th>
                                            <th><span class="badge bg-secondary">$<?php echo number_format($total_subtotal, 2); ?></span></th>
                                            <th><span class="badge bg-warning">$<?php echo number_format($total_iva, 2); ?></span></th>
                                            <th><span class="badge bg-success">$<?php echo number_format($total_total, 2); ?></span></th>
                                            <?php if ($tipo_reporte === 'locales'): ?>
                                                <th></th>
                                            <?php endif; ?>
                                        </tr>
                                    </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Gráfico -->
            <div class="card mt-4" id="cardGrafico" style="display: none;">
                <div class="card-header">
                    <h5 class="mb-0">Visualización Gráfica</h5>
                </div>
                <div class="card-body">
                    <canvas id="graficoReporte" height="300"></canvas>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let chartInstance = null;

function exportarReporte() {
    const table = document.getElementById('tablaReporte');
    let csv = [];
    
    // Encabezados
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent);
    });
    csv.push(headers.join(','));
    
    // Datos
    table.querySelectorAll('tbody tr').forEach(row => {
        const rowData = [];
        row.querySelectorAll('td').forEach(cell => {
            // Extraer solo el texto sin etiquetas HTML
            const text = cell.textContent.replace(/\$/g, '').trim();
            rowData.push(`"${text}"`);
        });
        csv.push(rowData.join(','));
    });
    
    // Crear archivo
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `reporte_${document.getElementById('tipo').value}_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function imprimirReporte() {
    const printContent = `
        <html>
        <head>
            <title>${document.getElementById('tituloReporte').textContent}</title>
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { color: #333; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .total { font-weight: bold; background-color: #e9ecef; }
                .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
            </style>
        </head>
        <body>
            <h1>${document.getElementById('tituloReporte').textContent}</h1>
            <p><strong>Período:</strong> ${document.getElementById('fecha_inicio').value} al ${document.getElementById('fecha_fin').value}</p>
            ${document.getElementById('tablaReporte').outerHTML}
            <p style="margin-top: 30px; font-size: 12px; color: #666;">
                Generado el ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
            </p>
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.print();
}

function generarGrafico() {
    const cardGrafico = document.getElementById('cardGrafico');
    cardGrafico.style.display = 'block';
    
    // Destruir gráfico anterior si existe
    if (chartInstance) {
        chartInstance.destroy();
    }
    
    const tipo = document.getElementById('tipo').value;
    const ctx = document.getElementById('graficoReporte').getContext('2d');
    
    // Preparar datos según tipo de reporte
    let labels = [];
    let data = [];
    let label = '';
    let backgroundColor = '';
    
    switch (tipo) {
        case 'ventas':
            labels = <?php echo json_encode(array_map(function($item) {
                return date('d/m', strtotime($item['fecha']));
            }, $datos_reporte)); ?>;
            data = <?php echo json_encode(array_column($datos_reporte, 'total')); ?>;
            label = 'Ventas Totales ($)';
            backgroundColor = 'rgba(54, 162, 235, 0.5)';
            break;
            
        case 'productos':
            labels = <?php echo json_encode(array_map(function($item) {
                return $item['nombre'];
            }, $datos_reporte)); ?>;
            data = <?php echo json_encode(array_column($datos_reporte, 'total_vendido')); ?>;
            label = 'Cantidad Vendida';
            backgroundColor = 'rgba(255, 99, 132, 0.5)';
            break;
            
        case 'locales':
            labels = <?php echo json_encode(array_map(function($item) {
                return $item['nombre_local'];
            }, $datos_reporte)); ?>;
            data = <?php echo json_encode(array_column($datos_reporte, 'total')); ?>;
            label = 'Ventas Totales ($)';
            backgroundColor = 'rgba(75, 192, 192, 0.5)';
            break;
            
        case 'franquiciados':
            labels = <?php echo json_encode(array_map(function($item) {
                return $item['nombre'];
            }, $datos_reporte)); ?>;
            data = <?php echo json_encode(array_column($datos_reporte, 'total_ventas')); ?>;
            label = 'Ventas Totales ($)';
            backgroundColor = 'rgba(153, 102, 255, 0.5)';
            break;
            
        case 'pagos':
            labels = <?php echo json_encode(array_map(function($item) {
                return date('M', mktime(0, 0, 0, $item['mes'], 1)) + ' ' + $item['anio'];
            }, $datos_reporte)); ?>;
            data = <?php echo json_encode(array_column($datos_reporte, 'total_monto')); ?>;
            label = 'Monto Total ($)';
            backgroundColor = 'rgba(255, 159, 64, 0.5)';
            break;
    }
    
    // Configurar tipo de gráfico
    let chartType = 'bar';
    if (tipo === 'ventas') {
        chartType = 'line';
    }
    
    // Crear gráfico
    chartInstance = new Chart(ctx, {
        type: chartType,
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: backgroundColor,
                borderColor: backgroundColor.replace('0.5', '1'),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: document.getElementById('tituloReporte').textContent
                },
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            if (label.includes('$')) {
                                return '$' + value.toLocaleString();
                            }
                            return value;
                        }
                    }
                }
            }
        }
    });
    
    // Scroll al gráfico
    cardGrafico.scrollIntoView({ behavior: 'smooth' });
}

// Generar gráfico automáticamente si hay datos
document.addEventListener('DOMContentLoaded', function() {
    if (<?php echo !empty($datos_reporte) ? 'true' : 'false'; ?>) {
        setTimeout(generarGrafico, 1000);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>