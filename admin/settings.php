<?php
$titulo = "Configuración";
require_once 'header.php';

// Guardar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    foreach ($_POST as $key => $value) {
        if ($key !== 'guardar') {
            actualizarConfiguracion($key, $value);
        }
    }
    registrarAccionAdmin('actualizar_configuracion', 'configuracion');
    $mensaje_exito = 'Configuración actualizada correctamente';
}

// Obtener configuración actual
$configs = $db->query("SELECT * FROM configuracion ORDER BY clave")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<?php if (isset($mensaje_exito)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $mensaje_exito; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST">
    <!-- General -->
    <div class="stat-card mb-4">
        <h5 class="mb-4"><i class="fas fa-cog me-2 text-primary"></i>Configuración General</h5>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><strong>Nombre del Sitio</strong></label>
                <input type="text" name="site_name" class="form-control"
                       value="<?php echo htmlspecialchars($configs['site_name'] ?? 'Done!'); ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label"><strong>Email de Contacto</strong></label>
                <input type="email" name="contact_email" class="form-control"
                       value="<?php echo htmlspecialchars($configs['contact_email'] ?? 'contacto@donebolivia.com'); ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label"><strong>Modo Mantenimiento</strong></label>
                <select name="maintenance_mode" class="form-select">
                    <option value="0" <?php echo ($configs['maintenance_mode'] ?? '0') == '0' ? 'selected' : ''; ?>>
                        Desactivado (sitio activo)
                    </option>
                    <option value="1" <?php echo ($configs['maintenance_mode'] ?? '0') == '1' ? 'selected' : ''; ?>>
                        Activado (sitio en mantenimiento)
                    </option>
                </select>
                <small class="text-muted">El sitio mostrará mensaje de mantenimiento a los usuarios</small>
            </div>

            <div class="col-md-6">
                <label class="form-label"><strong>Registros Públicos</strong></label>
                <select name="allow_registration" class="form-select">
                    <option value="1" <?php echo ($configs['allow_registration'] ?? '1') == '1' ? 'selected' : ''; ?>>
                        Permitir registros
                    </option>
                    <option value="0" <?php echo ($configs['allow_registration'] ?? '1') == '0' ? 'selected' : ''; ?>>
                        Bloquear registros
                    </option>
                </select>
            </div>
        </div>
    </div>

    <!-- Límites y Restricciones -->
    <div class="stat-card mb-4">
        <h5 class="mb-4"><i class="fas fa-sliders-h me-2 text-info"></i>Límites y Restricciones</h5>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><strong>Máximo de Productos por Usuario</strong></label>
                <input type="number" name="max_products_per_user" class="form-control"
                       value="<?php echo htmlspecialchars($configs['max_products_per_user'] ?? '50'); ?>"
                       min="1" max="1000">
            </div>

            <div class="col-md-4">
                <label class="form-label"><strong>Máximo de Imágenes por Producto</strong></label>
                <input type="number" name="max_images_per_product" class="form-control"
                       value="<?php echo htmlspecialchars($configs['max_images_per_product'] ?? '5'); ?>"
                       min="1" max="20">
            </div>

            <div class="col-md-4">
                <label class="form-label"><strong>Tamaño Máximo de Imagen (MB)</strong></label>
                <input type="number" name="max_image_size_mb" class="form-control" step="0.5"
                       value="<?php echo htmlspecialchars($configs['max_image_size_mb'] ?? '5'); ?>"
                       min="1" max="10">
            </div>

            <div class="col-md-6">
                <label class="form-label"><strong>Días de Vigencia de Productos</strong></label>
                <input type="number" name="product_expiry_days" class="form-control"
                       value="<?php echo htmlspecialchars($configs['product_expiry_days'] ?? '90'); ?>"
                       min="1">
                <small class="text-muted">Después de este período, los productos se desactivarán automáticamente</small>
            </div>

            <div class="col-md-6">
                <label class="form-label"><strong>Precio Mínimo Permitido (Bs.)</strong></label>
                <input type="number" name="min_price" class="form-control" step="0.01"
                       value="<?php echo htmlspecialchars($configs['min_price'] ?? '1'); ?>"
                       min="0">
            </div>
        </div>
    </div>

    <!-- Moderación -->
    <div class="stat-card mb-4">
        <h5 class="mb-4"><i class="fas fa-shield-alt me-2 text-warning"></i>Moderación</h5>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><strong>Aprobar Productos Antes de Publicar</strong></label>
                <select name="require_product_approval" class="form-select">
                    <option value="0" <?php echo ($configs['require_product_approval'] ?? '0') == '0' ? 'selected' : ''; ?>>
                        No (publicación inmediata)
                    </option>
                    <option value="1" <?php echo ($configs['require_product_approval'] ?? '0') == '1' ? 'selected' : ''; ?>>
                        Sí (requiere aprobación admin)
                    </option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label"><strong>Verificar Emails de Usuarios</strong></label>
                <select name="require_email_verification" class="form-select">
                    <option value="0" <?php echo ($configs['require_email_verification'] ?? '0') == '0' ? 'selected' : ''; ?>>
                        No verificar
                    </option>
                    <option value="1" <?php echo ($configs['require_email_verification'] ?? '0') == '1' ? 'selected' : ''; ?>>
                        Verificar email
                    </option>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label"><strong>Palabras Prohibidas</strong></label>
                <textarea name="banned_words" class="form-control" rows="3"
                          placeholder="Separadas por comas: palabra1, palabra2, palabra3"><?php echo htmlspecialchars($configs['banned_words'] ?? ''); ?></textarea>
                <small class="text-muted">Productos con estas palabras serán bloqueados automáticamente</small>
            </div>
        </div>
    </div>

    <!-- Botones -->
    <div class="stat-card">
        <div class="d-flex gap-2">
            <button type="submit" name="guardar" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Guardar Cambios
            </button>
            <a href="/admin/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i>Cancelar
            </a>
        </div>
    </div>
</form>

<!-- Información del Sistema -->
<div class="stat-card mt-4">
    <h5 class="mb-3"><i class="fas fa-info-circle me-2 text-success"></i>Información del Sistema</h5>
    <div class="row">
        <div class="col-md-4">
            <small class="text-muted">Versión de PHP:</small><br>
            <strong><?php echo phpversion(); ?></strong>
        </div>
        <div class="col-md-4">
            <small class="text-muted">Servidor:</small><br>
            <strong>Servidor Web</strong>
        </div>
        <div class="col-md-4">
            <small class="text-muted">Espacio en Disco:</small><br>
            <strong>
                <?php
                $bytes = disk_free_space(".");
                $gb = round($bytes / 1024 / 1024 / 1024, 2);
                echo $gb . ' GB libres';
                ?>
            </strong>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
