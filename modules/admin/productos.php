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
        $codigo_producto = $_POST['codigo_producto'];
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio = $_POST['precio'];
        $costo = $_POST['costo'];
        $id_categoria = $_POST['id_categoria'];
        $sabores = $_POST['sabores'] ?? [];

        try {
            $db->beginTransaction();
            
            // Insertar producto
            $stmt = $db->prepare("
                INSERT INTO productos 
                (codigo_producto, nombre, descripcion, precio, costo, id_categoria, disponible) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$codigo_producto, $nombre, $descripcion, $precio, $costo, $id_categoria]);
            
            // Insertar sabores
            if (!empty($sabores)) {
                $stmt = $db->prepare("INSERT INTO producto_sabor (codigo_producto, id_sabor) VALUES (?, ?)");
                foreach ($sabores as $id_sabor) {
                    $stmt->execute([$codigo_producto, $id_sabor]);
                }
            }
            
            $db->commit();
            logActividad('CREAR_PRODUCTO', 'productos', "Producto creado: $codigo_producto");
            setFlashMessage('success', 'Producto creado exitosamente');
            header('Location: productos.php');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            setFlashMessage('error', 'Error al crear producto: ' . $e->getMessage());
        }
    }
}

// Obtener productos con categorías
$productos = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, c.nombre as categoria_nombre,
               GROUP_CONCAT(s.nombre SEPARATOR ', ') as sabores
        FROM productos p
        LEFT JOIN categorias_productos c ON p.id_categoria = c.id_categoria
        LEFT JOIN producto_sabor ps ON p.codigo_producto = ps.codigo_producto
        LEFT JOIN sabores s ON ps.id_sabor = s.id_sabor
        GROUP BY p.codigo_producto
        ORDER BY c.nombre, p.nombre
    ");
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar productos: ' . $e->getMessage());
}

// Obtener categorías
$categorias = [];
try {
    $stmt = $db->prepare("SELECT id_categoria, nombre FROM categorias_productos ORDER BY nombre");
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar categorías: ' . $e->getMessage());
}

// Obtener sabores
$sabores = [];
try {
    $stmt = $db->prepare("SELECT id_sabor, nombre, es_natural FROM sabores ORDER BY nombre");
    $stmt->execute();
    $sabores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar sabores: ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' - Gestión de Productos';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-ice-cream me-2"></i>Gestión de Productos
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                        <i class="fas fa-plus me-1"></i> Nuevo Producto
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Resumen por categoría -->
            <div class="row mb-4">
                <?php 
                // Contar productos por categoría
                $productosPorCategoria = [];
                foreach ($productos as $producto) {
                    $categoria = $producto['categoria_nombre'] ?? 'Sin categoría';
                    if (!isset($productosPorCategoria[$categoria])) {
                        $productosPorCategoria[$categoria] = 0;
                    }
                    $productosPorCategoria[$categoria]++;
                }
                
                $colors = ['primary', 'success', 'warning', 'info', 'danger'];
                $colorIndex = 0;
                ?>
                
                <?php foreach ($productosPorCategoria as $categoria => $cantidad): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-<?php echo $colors[$colorIndex % count($colors)]; ?> text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title"><?php echo htmlspecialchars($categoria); ?></h6>
                                        <h3 class="mb-0"><?php echo $cantidad; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-box fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $colorIndex++; ?>
                <?php endforeach; ?>
            </div>

            <!-- Tabla de Productos -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaProductos">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Descripción</th>
                                    <th>Precio</th>
                                    <th>Costo</th>
                                    <th>Margen</th>
                                    <th>Categoría</th>
                                    <th>Sabores</th>
                                    <th>Disponible</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $producto): 
                                    $margen = $producto['precio'] - $producto['costo'];
                                    $margen_porcentaje = $producto['costo'] > 0 ? ($margen / $producto['costo']) * 100 : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-dark"><?php echo htmlspecialchars($producto['codigo_producto']); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 50)); ?>...</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">$<?php echo number_format($producto['precio'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">$<?php echo number_format($producto['costo'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $margen_porcentaje >= 50 ? 'success' : ($margen_porcentaje >= 30 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($margen_porcentaje, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($producto['categoria_nombre']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($producto['sabores']): ?>
                                                <small><?php echo htmlspecialchars($producto['sabores']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Sin sabores</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $producto['disponible'] ? 'success' : 'danger'; ?>">
                                                <?php echo $producto['disponible'] ? 'Sí' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editarProducto('<?php echo $producto['codigo_producto']; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="verDetalles('<?php echo $producto['codigo_producto']; ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="inventario.php?producto=<?php echo $producto['codigo_producto']; ?>" 
                                                   class="btn btn-sm btn-outline-warning" title="Inventario">
                                                    <i class="fas fa-boxes"></i>
                                                </a>
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

<!-- Modal para crear producto -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?action=crear">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="codigo_producto" class="form-label">Código del Producto *</label>
                            <input type="text" class="form-control" id="codigo_producto" name="codigo_producto" 
                                   pattern="[A-Z]{3}-[0-9]{3}" placeholder="Ej: HEL-001" required>
                            <small class="text-muted">Formato: XXX-001</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="precio" class="form-label">Precio de Venta *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="precio" name="precio" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="costo" class="form-label">Costo *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="costo" name="costo" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="id_categoria" class="form-label">Categoría *</label>
                            <select class="form-select" id="id_categoria" name="id_categoria" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>">
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sabores Disponibles</label>
                        <div class="row">
                            <?php foreach ($sabores as $sabor): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="sabor_<?php echo $sabor['id_sabor']; ?>" 
                                               name="sabores[]" value="<?php echo $sabor['id_sabor']; ?>">
                                        <label class="form-check-label" for="sabor_<?php echo $sabor['id_sabor']; ?>">
                                            <?php echo htmlspecialchars($sabor['nombre']); ?>
                                            <?php if ($sabor['es_natural']): ?>
                                                <span class="badge bg-success small">Natural</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Producto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarProducto(codigo) {
    // Implementar lógica de edición
    alert('Función de edición para ' + codigo);
}

function verDetalles(codigo) {
    fetch(`ajax/get-producto.php?codigo=${codigo}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const producto = data.producto;
                const modal = new bootstrap.Modal(document.getElementById('modalDetalleProducto'));
                
                document.getElementById('detalleCodigo').textContent = producto.codigo_producto;
                document.getElementById('detalleNombre').textContent = producto.nombre;
                document.getElementById('detalleDescripcion').textContent = producto.descripcion || 'Sin descripción';
                document.getElementById('detallePrecio').textContent = '$' + parseFloat(producto.precio).toFixed(2);
                document.getElementById('detalleCosto').textContent = '$' + parseFloat(producto.costo).toFixed(2);
                document.getElementById('detalleCategoria').textContent = producto.categoria_nombre || 'Sin categoría';
                document.getElementById('detalleSabores').textContent = producto.sabores || 'Sin sabores';
                document.getElementById('detalleDisponible').textContent = producto.disponible ? 'Sí' : 'No';
                document.getElementById('detalleDisponible').className = producto.disponible ? 'badge bg-success' : 'badge bg-danger';
                
                // Calcular margen
                const margen = producto.precio - producto.costo;
                const margenPorcentaje = producto.costo > 0 ? (margen / producto.costo) * 100 : 0;
                document.getElementById('detalleMargen').textContent = '$' + margen.toFixed(2) + 
                    ' (' + margenPorcentaje.toFixed(1) + '%)';
                
                modal.show();
            }
        })
        .catch(error => console.error('Error:', error));
}

// Calcular margen en tiempo real
document.getElementById('precio')?.addEventListener('input', calcularMargen);
document.getElementById('costo')?.addEventListener('input', calcularMargen);

function calcularMargen() {
    const precio = parseFloat(document.getElementById('precio')?.value) || 0;
    const costo = parseFloat(document.getElementById('costo')?.value) || 0;
    
    if (costo > 0 && precio >= costo) {
        const margen = precio - costo;
        const porcentaje = (margen / costo) * 100;
        document.getElementById('margenCalculado')?.textContent = 
            `Margen: $${margen.toFixed(2)} (${porcentaje.toFixed(1)}%)`;
    }
}
</script>

<!-- Modal de detalles del producto -->
<div class="modal fade" id="modalDetalleProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-4">Código:</dt>
                    <dd class="col-sm-8"><strong id="detalleCodigo"></strong></dd>
                    
                    <dt class="col-sm-4">Nombre:</dt>
                    <dd class="col-sm-8" id="detalleNombre"></dd>
                    
                    <dt class="col-sm-4">Descripción:</dt>
                    <dd class="col-sm-8" id="detalleDescripcion"></dd>
                    
                    <dt class="col-sm-4">Precio:</dt>
                    <dd class="col-sm-8" id="detallePrecio"></dd>
                    
                    <dt class="col-sm-4">Costo:</dt>
                    <dd class="col-sm-8" id="detalleCosto"></dd>
                    
                    <dt class="col-sm-4">Margen:</dt>
                    <dd class="col-sm-8" id="detalleMargen"></dd>
                    
                    <dt class="col-sm-4">Categoría:</dt>
                    <dd class="col-sm-8" id="detalleCategoria"></dd>
                    
                    <dt class="col-sm-4">Sabores:</dt>
                    <dd class="col-sm-8" id="detalleSabores"></dd>
                    
                    <dt class="col-sm-4">Disponible:</dt>
                    <dd class="col-sm-8"><span id="detalleDisponible"></span></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>