<?php
$titulo = "Insignias (Badges)";
require_once 'header.php';

$db = getDB();

// Obtener todos los badges para la tabla principal
$badges = $db->query("SELECT * FROM badges ORDER BY orden ASC")->fetchAll();

// Calcular estadísticas
$total_badges = count($badges);
$badges_activos = count(array_filter($badges, fn($b) => $b['activo']));
$badges_inactivos = $total_badges - $badges_activos;
$uso_total = $db->query("SELECT COUNT(DISTINCT producto_id) FROM producto_badges")->fetchColumn();

?>

<style>
.badge-preview {
    width: 24px;
    height: 24px;
    vertical-align: middle;
    margin-right: 8px;
}

.modal-body .badge-preview {
    width: 48px;
    height: 48px;
}

.form-label {
    font-weight: 600;
}
</style>

<!-- Estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,107,26,0.1); color: var(--primary);">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="stat-card-title">Total de Insignias</div>
            <div class="stat-card-value"><?php echo $total_badges; ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-card-title">Insignias Activas</div>
            <div class="stat-card-value"><?php echo $badges_activos; ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(220,53,69,0.1); color: #dc3545;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-card-title">Insignias Inactivas</div>
            <div class="stat-card-value"><?php echo $badges_inactivos; ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-card-title">Productos con Insignias</div>
            <div class="stat-card-value"><?php echo $uso_total; ?></div>
        </div>
    </div>
</div>

<!-- Botón para abrir el modal de nueva insignia -->
<div class="d-flex justify-content-end mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBadge">
        <i class="fas fa-plus me-2"></i>Añadir Nueva Insignia
    </button>
</div>

<!-- Tabla de Insignias -->
<div class="admin-table">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Orden</th>
                <th>Visual</th>
                <th>Nombre</th>
                <th>Slug</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tabla-badges">
            <?php foreach ($badges as $badge): ?>
                <tr id="badge-<?php echo $badge['id']; ?>">
                    <td><?php echo $badge['orden']; ?></td>
                    <td>
                        <img src="/<?php echo htmlspecialchars($badge['svg_path']); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($badge['nombre']); ?>" class="badge-preview">
                    </td>
                    <td><strong><?php echo htmlspecialchars($badge['nombre']); ?></strong></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($badge['slug']); ?></span></td>
                    <td><?php echo htmlspecialchars($badge['descripcion']); ?></td>
                    <td>
                        <?php if ($badge['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <?php if ($badge['activo']): ?>
                                <button class="btn btn-warning" onclick="toggleActivo(<?php echo $badge['id']; ?>, 0)" title="Desactivar">
                                    <i class="fas fa-ban"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success" onclick="toggleActivo(<?php echo $badge['id']; ?>, 1)" title="Activar">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-info" onclick="editarBadge(<?php echo htmlspecialchars(json_encode($badge), ENT_QUOTES, 'UTF-8'); ?>)" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger" onclick="eliminarBadge(<?php echo $badge['id']; ?>)" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal para Añadir/Editar Insignia -->
<div class="modal fade" id="modalBadge" tabindex="-1" aria-labelledby="modalBadgeLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBadgeLabel">Gestionar Insignia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form-badge">
                    <input type="hidden" id="badge_id" name="id">
                    <input type="hidden" id="badge_svg_path_actual" name="svg_path">

                    <div class="mb-3">
                        <label for="badge_nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="badge_nombre" name="nombre" required>
                        <small class="text-muted">Ej: Envío Gratis, Oferta, Nuevo</small>
                    </div>
                    <div class="mb-3">
                        <label for="badge_slug" class="form-label">Slug</label>
                        <input type="text" class="form-control" id="badge_slug" name="slug" required>
                        <small class="text-muted">Identificador único en minúsculas y sin espacios. Ej: envio_gratis, oferta</small>
                    </div>
                    <div class="mb-3">
                        <label for="badge_descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="badge_descripcion" name="descripcion" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="badge_svg_file" class="form-label">Archivo de la Insignia (SVG)</label>
                        <input type="file" class="form-control" id="badge_svg_file" name="svg_file" accept="image/svg+xml">
                        <small class="text-muted">Sube un nuevo SVG. Si no seleccionas uno, se mantendrá el actual.</small>
                    </div>

                    <div class="mb-3 text-center">
                        <label class="form-label">Previsualización</label>
                        <div id="badge-preview-container" style="width: 100px; height: 100px; margin: auto; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center;">
                            <img id="badge-preview-img" src="" alt="Previsualización" style="max-width: 100%; max-height: 100%; display: none;">
                            <span id="badge-preview-text" class="text-muted">Sin imagen</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="badge_orden" class="form-label">Orden</label>
                            <input type="number" class="form-control" id="badge_orden" name="orden" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="badge_activo" class="form-label">Estado</label>
                            <select class="form-select" id="badge_activo" name="activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarBadge()">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<script>
// Resetear el modal al cerrarlo
document.getElementById('modalBadge').addEventListener('hidden.bs.modal', function () {
    document.getElementById('form-badge').reset();
    document.getElementById('badge_id').value = '';
    document.getElementById('modalBadgeLabel').textContent = 'Añadir Nueva Insignia';
    
    // Limpiar previsualización
    const previewImg = document.getElementById('badge-preview-img');
    const previewText = document.getElementById('badge-preview-text');
    previewImg.style.display = 'none';
    previewImg.src = '';
    previewText.style.display = 'block';
});

// Previsualizar SVG al seleccionarlo
document.getElementById('badge_svg_file').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('badge-preview-img');
            const previewText = document.getElementById('badge-preview-text');
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
            previewText.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
});

function editarBadge(badge) {
    document.getElementById('modalBadgeLabel').textContent = 'Editar Insignia';
    document.getElementById('badge_id').value = badge.id;
    document.getElementById('badge_nombre').value = badge.nombre;
    document.getElementById('badge_slug').value = badge.slug;
    document.getElementById('badge_descripcion').value = badge.descripcion;
    document.getElementById('badge_svg_path_actual').value = badge.svg_path; // Guardar ruta actual
    document.getElementById('badge_orden').value = badge.orden;
    document.getElementById('badge_activo').value = badge.activo;
    
    // Mostrar previsualización de la imagen actual
    const previewImg = document.getElementById('badge-preview-img');
    const previewText = document.getElementById('badge-preview-text');
    if (badge.svg_path) {
        previewImg.src = '/' + badge.svg_path + '?v=' + new Date().getTime();
        previewImg.style.display = 'block';
        previewText.style.display = 'none';
    } else {
        previewImg.style.display = 'none';
        previewText.style.display = 'block';
    }
    
    new bootstrap.Modal(document.getElementById('modalBadge')).show();
}

function guardarBadge() {
    const form = document.getElementById('form-badge');
    const formData = new FormData(form);
    
    // Añadir acción y token CSRF
    const id = formData.get('id');
    formData.append('accion', id ? 'actualizar' : 'crear');
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('/admin/ajax/badges_actions.php', {
        method: 'POST',
        body: formData // Ya no se usa JSON, se envía FormData directamente
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('¡Éxito!', 'La insignia ha sido guardada.', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'No se pudo guardar la insignia.', 'error');
        }
    });
}

function eliminarBadge(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¡No podrás revertir esto!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, ¡eliminar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('id', id);
            formData.append('csrf_token', CSRF_TOKEN);

            fetch('/admin/ajax/badges_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('¡Eliminado!', 'La insignia ha sido eliminada.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'No se pudo eliminar la insignia.', 'error');
                }
            });
        }
    });
}

function toggleActivo(id, nuevoEstado) {
    const texto = nuevoEstado === 1 ? 'activar' : 'desactivar';
    Swal.fire({
        title: `¿Seguro que quieres ${texto} esta insignia?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: `Sí, ${texto}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/admin/ajax/badges_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    accion: 'toggle_activo',
                    id: id,
                    activo: nuevoEstado,
                    csrf_token: CSRF_TOKEN
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('¡Actualizado!', `La insignia ha sido ${texto}da.`, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'No se pudo cambiar el estado.', 'error');
                }
            });
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>
