// CAMBALACHE - Script Simple de Publicación
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const MAX_PHOTOS = 5;
    let selectedFiles = [];

    const fileInput = document.getElementById('imagenes');
    const photoGrid = document.getElementById('photoGrid');

    if (!fileInput || !photoGrid) return;

    // Manejar selección de archivos
    fileInput.addEventListener('change', function() {
        const files = Array.from(this.files);
        
        // Validar y agregar archivos
        files.forEach(file => {
            if (selectedFiles.length >= MAX_PHOTOS) return;
            
            // Validar tipo
            if (!file.type.match(/^image\/(jpeg|jpg|png|webp)$/i)) {
                alert('Solo se permiten imágenes JPG, PNG o WEBP');
                return;
            }
            
            // Validar tamaño (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('La imagen es muy grande. Máximo 5MB');
                return;
            }
            
            selectedFiles.push(file);
        });
        
        renderPhotos();
        updateFileInput();
    });

    // Renderizar fotos
    function renderPhotos() {
        // Limpiar grid excepto el botón de agregar
        const addButton = photoGrid.querySelector('.photo-add');
        photoGrid.innerHTML = '';
        
        // Agregar fotos
        selectedFiles.forEach((file, index) => {
            const photoItem = document.createElement('div');
            photoItem.className = 'photo-item';
            photoItem.draggable = true;
            photoItem.dataset.index = index;
            
            // Crear preview
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            
            // Botón eliminar
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'photo-remove';
            removeBtn.innerHTML = '×';
            removeBtn.onclick = () => removePhoto(index);
            
            photoItem.appendChild(img);
            photoItem.appendChild(removeBtn);
            
            // Badge de principal
            if (index === 0) {
                const badge = document.createElement('div');
                badge.className = 'photo-badge';
                badge.textContent = 'Principal';
                photoItem.appendChild(badge);
            }
            
            // Drag & drop
            photoItem.addEventListener('dragstart', handleDragStart);
            photoItem.addEventListener('dragover', handleDragOver);
            photoItem.addEventListener('drop', handleDrop);
            photoItem.addEventListener('dragend', handleDragEnd);
            
            photoGrid.appendChild(photoItem);
        });
        
        // Re-agregar botón de añadir si hay espacio
        if (selectedFiles.length < MAX_PHOTOS) {
            photoGrid.appendChild(addButton);
        }
    }

    // Eliminar foto
    function removePhoto(index) {
        selectedFiles.splice(index, 1);
        renderPhotos();
        updateFileInput();
    }

    // Actualizar input file
    function updateFileInput() {
        try {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        } catch (e) {
            // Navegador no soporta DataTransfer
            console.log('DataTransfer no soportado');
        }
    }

    // Drag & Drop handlers
    let draggedElement = null;

    function handleDragStart(e) {
        draggedElement = this;
        this.style.opacity = '0.4';
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
    }

    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }

        if (draggedElement !== this) {
            const fromIndex = parseInt(draggedElement.dataset.index);
            const toIndex = parseInt(this.dataset.index);
            
            // Reordenar array
            const movedFile = selectedFiles.splice(fromIndex, 1)[0];
            selectedFiles.splice(toIndex, 0, movedFile);
            
            renderPhotos();
            updateFileInput();
        }

        return false;
    }

    function handleDragEnd(e) {
        this.style.opacity = '1';
    }

    // Cargar subcategorías (mismo que antes)
    window.cargarSubcategorias = function() {
        const categoriaId = document.getElementById('categoria_id').value;
        const subcategoriaSelect = document.getElementById('subcategoria_id');
        
        subcategoriaSelect.innerHTML = '<option value="">Cargando...</option>';
        subcategoriaSelect.disabled = true;
        
        if (!categoriaId) {
            subcategoriaSelect.innerHTML = '<option value="">Primero elige categoría</option>';
            return;
        }
        
        fetch('/api/subcategorias.php?categoria_id=' + categoriaId)
            .then(response => response.json())
            .then(data => {
                subcategoriaSelect.innerHTML = '<option value="">Seleccionar</option>';
                
                if (data.success && data.subcategorias) {
                    data.subcategorias.forEach(sub => {
                        const option = document.createElement('option');
                        option.value = sub.id;
                        option.textContent = sub.nombre;
                        subcategoriaSelect.appendChild(option);
                    });
                    subcategoriaSelect.disabled = false;
                } else {
                    subcategoriaSelect.innerHTML = '<option value="">Sin subcategorías</option>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                subcategoriaSelect.innerHTML = '<option value="">Error al cargar</option>';
            });
    };

    // Validación simple del formulario
    const form = document.getElementById('publishForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validar que haya al menos 1 foto
            if (selectedFiles.length === 0) {
                e.preventDefault();
                alert('Agrega al menos 1 foto de tu producto');
                return false;
            }
            
            // Todo OK, permitir envío
            return true;
        });
    }

})();
