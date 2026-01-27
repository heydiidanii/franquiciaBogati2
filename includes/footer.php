</div><!-- container-fluid -->
</main>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (necesario para DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<!-- Scripts personalizados -->
<script src="<?php echo BASE_URL; ?>js/scripts.js"></script>

<!-- Scripts específicos de página -->
<?php if (isset($page_scripts)): ?>
    <?php foreach ($page_scripts as $script): ?>
        <script src="<?php echo BASE_URL . 'js/' . $script; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<script>
// Actualizar hora en tiempo real
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('es-EC', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    document.getElementById('current-time').textContent = timeString;
}

// Actualizar cada segundo
setInterval(updateTime, 1000);
updateTime();

// Auto-ocultar alertas después de 5 segundos
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Inicializar tooltips
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Función para confirmar eliminaciones
function confirmDelete(message = '¿Está seguro de eliminar este registro?') {
    return confirm(message);
}

// Función para formatear moneda
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2
    }).format(amount);
}

// Función para validar formularios
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }
    return true;
}

// Manejar carga de datos para modales
document.addEventListener('DOMContentLoaded', function() {
    const modals = document.querySelectorAll('[data-load-url]');
    modals.forEach(modal => {
        modal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const loadUrl = modal.getAttribute('data-load-url');
            const targetElement = modal.querySelector(modal.getAttribute('data-target-element'));
            
            if (loadUrl && targetElement && button.dataset.id) {
                fetch(loadUrl + '?id=' + button.dataset.id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const form = modal.querySelector('form');
                            Object.keys(data.data).forEach(key => {
                                const input = form.querySelector(`[name="${key}"]`);
                                if (input) {
                                    input.value = data.data[key];
                                }
                            });
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        });
    });
});
</script>

</body>
</html>
