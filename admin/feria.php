<?php
$titulo = "Gestión de Feria";
require_once 'header.php';

// Obtener conexión a BD
$db = getDB();

// --- AUTO-MIGRACIÓN: Verificar/Crear columna categoria_default_id ---
try {
    $check = $db->query("SHOW COLUMNS FROM feria_sectores LIKE 'categoria_default_id'");
    if ($check->rowCount() == 0) {
        $db->exec("ALTER TABLE feria_sectores ADD COLUMN categoria_default_id INT NULL AFTER descripcion");
        // Intentar poblar datos iniciales (Best effort)
        $map = [
            'tech' => 5, 'fashion' => 6, 'electro' => 3, 'home' => 7, 
            'tools' => 10, 'auto' => 1, 'realestate' => 8, 'kids' => 9
        ];
        $stmtUpd = $db->prepare("UPDATE feria_sectores SET categoria_default_id = ? WHERE slug = ?");
        foreach ($map as $s => $c) { $stmtUpd->execute([$c, $s]); }
    }
} catch (Exception $e) {
    // Silencioso o log
}

// Obtener Categorías para el selector
try {
    $stmtCat = $db->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre");
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categorias = [];
}

// Obtener sectores ordenados (ahora con categoria_nombre)
try {
    $stmt = $db->query("
        SELECT s.*, c.nombre as categoria_nombre 
        FROM feria_sectores s
        LEFT JOIN categorias c ON s.categoria_default_id = c.id
        ORDER BY s.orden ASC
    ");
    $sectores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sectores = [];
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Gestión de Sectores de la Feria</h1>
    <button class="btn btn-primary" onclick="abrirModalCrear()">
        <i class="fas fa-plus"></i> Nuevo Sector
    </button>
</div>

<!-- Modal Nuevo/Editar Sector -->
<div class="modal fade" id="modalNuevoSector" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Añadir Nuevo Sector</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNuevoSector">
                    <input type="hidden" name="id" id="sectorId"> <!-- ID oculto para edición -->
                    
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" class="form-control" name="titulo" id="inputTitulo" required placeholder="Ej: Mascotas">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slug (ID Único)</label>
                        <input type="text" class="form-control" name="slug" id="inputSlug" required placeholder="Ej: pets">
                        <small class="text-muted">Sin espacios, solo letras minúsculas.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="descripcion" id="inputDesc" required placeholder="Ej: Todo para tu amigo fiel">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color del Tema</label>
                        <input type="color" class="form-control form-control-color" name="color" id="inputColor" value="#007AFF">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Categoría de Productos Asociada</label>
                        <select class="form-select" name="categoria_default_id" id="inputCategoriaId">
                            <option value="">-- Ninguna (Libre) --</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Si seleccionas una categoría, las tiendas en este sector solo podrán publicar productos de esta categoría automáticamente.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Banner del Sector</label>
                        
                        <!-- Previsualización y Botón Eliminar -->
                        <div id="previewContainer" class="mb-2 d-none p-2 border rounded bg-light text-center position-relative">
                            <img id="imgPreview" src="" style="max-height: 150px; max-width: 100%; border-radius: 4px;">
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 shadow" onclick="eliminarImagenSector()" title="Eliminar Imagen">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>

                        <div class="input-group">
                            <input type="file" class="form-control" name="imagen_file" id="inputImagenFile" accept="image/*">
                        </div>
                        <input type="hidden" name="imagen_actual" id="inputImagenActual">
                        <div class="form-text text-muted">Sube una imagen (JPG/PNG/WebP). Se redimensionará automáticamente.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardar" onclick="guardarSector()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de sectores -->
<div class="admin-table">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th style="width: 50px;">Orden</th>
                <th style="width: 80px;">Banner</th>
                <th>Título</th>
                <th>Slug (ID)</th>
                <th>Color</th>
                <th>Capacidad</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sectores as $index => $sec): ?>
                <tr>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <?php if ($index > 0): ?>
                                <button class="btn btn-sm btn-outline-secondary" onclick="moverSector(<?php echo $sec['id']; ?>, 'up')" title="Subir">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($index < count($sectores) - 1): ?>
                                <button class="btn btn-sm btn-outline-secondary" onclick="moverSector(<?php echo $sec['id']; ?>, 'down')" title="Bajar">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <img src="<?php echo htmlspecialchars($sec['imagen_banner']); ?>" 
                             style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px; background: #eee;"
                             onerror="this.onerror=null; this.src='/assets/img/feria_banners/placeholder.png';">
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($sec['titulo']); ?></strong>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($sec['descripcion']); ?></small>
                    </td>
                    <td><code><?php echo htmlspecialchars($sec['slug']); ?></code></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width: 20px; height: 20px; border-radius: 50%; background-color: <?php echo htmlspecialchars($sec['color_hex']); ?>;"></div>
                            <small><?php echo htmlspecialchars($sec['color_hex']); ?></small>
                        </div>
                    </td>
                    <td><?php echo $sec['capacidad']; ?> puestos</td>
                    <td>
                        <?php if ($sec['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning" onclick="toggleEstado(<?php echo $sec['id']; ?>, <?php echo $sec['activo'] ? 0 : 1; ?>)">
                                <i class="fas fa-power-off"></i>
                            </button>
                            <!-- REINGENIERÍA: Pasamos parámetros primitivos, no objetos JSON -->
                            <button class="btn btn-primary" onclick="location.href='feria_puestos.php?id=<?php echo $sec['id']; ?>'" title="Gestionar Puestos">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="btn btn-info" onclick="prepararEdicion(
                                <?php echo $sec['id']; ?>, 
                                '<?php echo htmlspecialchars(addslashes($sec['titulo'])); ?>', 
                                '<?php echo htmlspecialchars(addslashes($sec['slug'])); ?>', 
                                '<?php echo htmlspecialchars(addslashes($sec['descripcion'])); ?>', 
                                '<?php echo htmlspecialchars($sec['color_hex']); ?>', 
                                '<?php echo htmlspecialchars(addslashes($sec['imagen_banner'])); ?>',
                                '<?php echo $sec['categoria_default_id']; ?>'
                            )">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger" onclick="eliminarSector(<?php echo $sec['id']; ?>, '<?php echo htmlspecialchars(addslashes($sec['titulo'])); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- JS Específico para esta página (Módulo) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Configuración Global ---
    const CSRF_TOKEN = "<?php echo auth_csrf_token(); ?>";
    // Usamos URL limpia para evitar redirecciones innecesarias
    const API_URL = '/admin/ajax/feria_actions';

    // --- Referencias al DOM ---
    const modalEl = document.getElementById('modalNuevoSector');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('formNuevoSector');
    const btnGuardar = document.getElementById('btnGuardar');

    // --- Funciones Core (CRUD) ---

    // 1. Abrir Modal (Crear)
    window.abrirModalCrear = function() {
        form.reset();
        document.getElementById('modalTitle').textContent = 'Añadir Nuevo Sector';
        document.getElementById('sectorId').value = '';
        modal.show();
    };

    // 2. Abrir Modal (Editar)
    window.prepararEdicion = function(id, titulo, slug, desc, color, imagen, categoriaId) {
        document.getElementById('modalTitle').textContent = 'Editar Sector';
        document.getElementById('sectorId').value = id;
        document.getElementById('inputTitulo').value = titulo;
        document.getElementById('inputSlug').value = slug;
        document.getElementById('inputDesc').value = desc;
        document.getElementById('inputColor').value = color;
        // Asignar Categoría
        document.getElementById('inputCategoriaId').value = categoriaId || '';

        // Guardamos la ruta actual por si no suben nada nuevo
        document.getElementById('inputImagenActual').value = imagen;
        
        // Manejo de Preview
        const previewContainer = document.getElementById('previewContainer');
        const imgPreview = document.getElementById('imgPreview');
        if (imagen && imagen.trim() !== '') {
            imgPreview.src = imagen + '?v=' + new Date().getTime(); // Evitar caché
            previewContainer.classList.remove('d-none');
        } else {
            previewContainer.classList.add('d-none');
        }

        // Limpiamos el input file
        document.getElementById('inputImagenFile').value = '';
        
        modal.show();
    };

    // Nueva función para borrar imagen
    window.eliminarImagenSector = function() {
        const id = document.getElementById('sectorId').value;
        if (!id) return; // Si es nuevo sector, no hay nada que borrar en servidor aún (solo limpiar input)

        Swal.fire({
            title: '¿Eliminar imagen?',
            text: "El sector se quedará sin banner hasta que subas uno nuevo.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, borrar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarAccion({ action: 'delete_image', id: id });
            }
        });
    };

    // 3. Guardar (Create/Update) - AHORA SOPORTA ARCHIVOS
    window.guardarSector = function() {
        const formData = new FormData(form);
        // No necesitamos convertir a JSON manualmente, FormData maneja archivos
        // Pero nuestro backend espera 'action' y 'csrf_token'
        formData.append('action', formData.get('id') ? 'edit' : 'create');
        formData.append('csrf_token', CSRF_TOKEN);

        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

        fetch(API_URL, {
            method: 'POST',
            body: formData // Enviamos FormData directo (multipart/form-data)
        })
        .then(procesarRespuesta)
        .catch(manejarError)
        .finally(() => {
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
        });
    };

    // 4. Cambiar Estado (Toggle)
    window.toggleEstado = function(id, nuevoEstado) {
        enviarAccion({ action: 'toggle', id: id, active: nuevoEstado });
    };

    // 5. Mover (Reorder)
    window.moverSector = function(id, direccion) {
        enviarAccion({ action: 'reorder', id: id, direction: direccion });
    };

    // 6. Eliminar (Delete)
    window.eliminarSector = function(id, titulo) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: `Se eliminará el sector "${titulo}"`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarAccion({ action: 'delete', id: id });
            }
        });
    };

    // --- Helpers ---

    function enviarAccion(payload) {
        payload.csrf_token = CSRF_TOKEN;
        fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(procesarRespuesta)
        .catch(manejarError);
    }

    function procesarRespuesta(response) {
        return response.text().then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', data.message || 'Error desconocido', 'error');
                    console.error('Server Error:', data);
                }
            } catch (e) {
                console.error('JSON Parse Error:', text);
                Swal.fire('Error Crítico', 'El servidor devolvió una respuesta inválida. Revisa la consola.', 'error');
            }
        });
    }

    function manejarError(err) {
        console.error('Network Error:', err);
        Swal.fire('Error de Conexión', 'No se pudo contactar con el servidor.', 'error');
    }

});
</script>

<?php require_once 'footer.php'; ?>
