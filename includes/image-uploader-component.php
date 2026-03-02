<?php
/**
 * Componente unificado de ImageUploader
 * Uso: <?php echo renderImageUploader($config); ?>
 */

function renderImageUploader($config) {
    $defaults = [
        'type' => 'image',
        'container_id' => '',
        'input_id' => '',
        'preview_id' => '',
        'placeholder_id' => '',
        'delete_btn_id' => '',
        'current_image' => '',
        'accept' => 'image/*',
        'multiple' => false,
        'class' => 'image-uploader',
        'style' => '',
        'show_delete' => true,
        'delete_confirm' => '¿Estás seguro de eliminar esta imagen?'
    ];
    
    $config = array_merge($defaults, $config);
    
    $has_image = !empty($config['current_image']);
    $container_class = $config['class'] . ($has_image ? ' has-image' : '');
    
    ob_start();
    ?>
    <div id="<?php echo $config['container_id']; ?>" 
         class="<?php echo $container_class; ?>" 
         data-type="<?php echo $config['type']; ?>"
         style="<?php echo $config['style']; ?>">
        
        <input type="file" 
               id="<?php echo $config['input_id']; ?>" 
               accept="<?php echo $config['accept']; ?>" 
               <?php echo $config['multiple'] ? 'multiple' : ''; ?>
               hidden>
        
        <?php if ($has_image): ?>
            <img id="<?php echo $config['preview_id']; ?>" 
                 src="<?php echo $config['current_image']; ?>" 
                 class="image-preview"
                 style="display: block;">
        <?php else: ?>
            <img id="<?php echo $config['preview_id']; ?>" 
                 class="image-preview"
                 style="display: none;">
        <?php endif; ?>
        
        <div id="<?php echo $config['placeholder_id']; ?>" 
             class="image-placeholder" 
             style="<?php echo $has_image ? 'display: none;' : 'display: flex;'; ?>">
            <i class="fas fa-cloud-upload-alt"></i>
            <span>Click para subir imagen</span>
        </div>
        
        <?php if ($config['show_delete']): ?>
            <button id="<?php echo $config['delete_btn_id']; ?>" 
                    class="btn-delete-image" 
                    data-confirm="<?php echo $config['delete_confirm']; ?>"
                    style="<?php echo $has_image ? 'display: flex;' : 'display: none;'; ?>"
                    title="Eliminar imagen">
                <i class="fas fa-times"></i>
            </button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Función para inicializar múltiples uploaders
function initImageUploaders($configs) {
    echo "<script>";
    echo "document.addEventListener('DOMContentLoaded', function() {";
    
    foreach ($configs as $config) {
        echo "new ImageUploader({";
        echo "container: '{$config['container_id']}',";
        echo "inputId: '{$config['input_id']}',";
        echo "previewId: '{$config['preview_id']}',";
        echo "placeholderId: '{$config['placeholder_id']}',";
        echo "deleteBtnId: '{$config['delete_btn_id']}',";
        echo "type: '{$config['type']}',";
        if (isset($config['onSuccess'])) {
            echo "onSuccess: {$config['onSuccess']},";
        }
        if (isset($config['onDelete'])) {
            echo "onDelete: {$config['onDelete']},";
        }
        echo "});";
    }
    
    echo "});";
    echo "</script>";
}
?>
