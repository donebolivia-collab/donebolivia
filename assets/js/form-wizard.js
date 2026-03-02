// Wizard de formulario dinámico (4 pasos actual):
// 1) Categoría 2) Detalles 3) Fotos 4) Publicar (Precio/Ubicación + Revisión)
(function(){
  function qs(sel,root){return (root||document).querySelector(sel)}
  function qsa(sel,root){return Array.from((root||document).querySelectorAll(sel))}

  document.addEventListener('DOMContentLoaded', function(){
    const form = qs('form.needs-validation');
    if(!form) return;

    const steps = qsa('[data-step]');
    const indicators = qsa('.wizard-step');
    const btnPrev = qs('#wizardPrev');
    const btnNext = qs('#wizardNext');
    const btnSubmit = qs('#wizardSubmit');
    const dropzone = qs('.image-upload-container');
    const fileInput = qs('#imagenes');
    const previewGrid = qs('#imagePreview');
    const MAX_FILES = 5;

    let selectedFiles = [];
    let canSyncInputFiles = true; // si no se puede usar DataTransfer, quedará en false

    let current = 1;
    const lastStep = Math.max(...steps.map(s=>parseInt(s.getAttribute('data-step')||'0')));

    function clearVisualValidation(root){
      // Quitar marcas visuales para no mostrar rojo al entrar
      try{ form.classList.remove('was-validated'); }catch(e){}
      qsa('.is-invalid', root||form).forEach(el=> el.classList.remove('is-invalid'));
    }

    function showStep(n){
      clearVisualValidation();
      current = n;
      steps.forEach(s=>{ s.style.display = (parseInt(s.getAttribute('data-step'))===n)?'block':'none'; });
      indicators.forEach(i=>{
        const num = parseInt(i.getAttribute('data-step'));
        i.classList.toggle('active', num===n);
        i.classList.toggle('done', num<n);
      });
      btnPrev.style.display = (n===1)?'none':'inline-flex';
      btnNext.style.display = (n===lastStep)?'none':'inline-flex';
      btnSubmit.style.display = (n===lastStep)?'inline-flex':'none';
      if(n===lastStep){ fillReview(); }
      try { sessionStorage.setItem('wizard_step', String(n)); } catch(e){}
    }

    function validateCurrent(){
      const section = qs('[data-step="'+current+'"]');
      if(!section) return true;
      const inputs = qsa('input, select, textarea', section);
      let valid = true;
      
      // Marcar formulario como validado para mostrar feedback visual
      form.classList.add('was-validated');
      
      inputs.forEach(el=>{
        // Verificar validación nativa del navegador
        if(!el.checkValidity()){
          valid = false;
          el.classList.add('is-invalid');
        }
        
        // Validaciones adicionales específicas
        if(el.hasAttribute('required') && !el.value.trim()){
          valid = false;
          el.classList.add('is-invalid');
        }
        
        // Validación mínima de caracteres para título y descripción
        if(el.id === 'titulo' && el.value.trim().length < 10){
          valid = false;
          el.classList.add('is-invalid');
        }
        
        if(el.id === 'descripcion' && el.value.trim().length < 20){
          valid = false;
          el.classList.add('is-invalid');
        }
      });
      
      // Validación especial para Paso 1 (categoría y subcategoría)
      if(current === 1){
        const cat = qs('select[name="categoria_id"]');
        const sub = qs('select[name="subcategoria_id"]');
        if(!cat || !cat.value || cat.value === ''){
          valid = false;
          if(cat) cat.classList.add('is-invalid');
        }
        if(!sub || !sub.value || sub.value === ''){
          valid = false;
          if(sub) sub.classList.add('is-invalid');
        }
      }
      
      // Validación especial para Paso 2 (título y descripción)
      if(current === 2){
        const titulo = qs('input[name="titulo"]');
        const descripcion = qs('textarea[name="descripcion"]');
        
        // Validar título
        if(!titulo || !titulo.value || titulo.value.trim().length < 10){
          valid = false;
          if(titulo) titulo.classList.add('is-invalid');
        }
        
        // Validar descripción
        if(!descripcion || !descripcion.value || descripcion.value.trim().length < 20){
          valid = false;
          if(descripcion) descripcion.classList.add('is-invalid');
        }
      }
      
      // Validación especial para Paso 4 (precio, estado, ciudad, términos)
      if(current === 4){
        const precio = qs('input[name="precio"]');
        const estado = qs('select[name="estado"]');
        const ciudad = qs('select[name="ciudad_id"]');
        const terms = qs('input[name="terms"]');
        
        if(precio && (parseFloat(precio.value) <= 0 || !precio.value)){
          valid = false;
          precio.classList.add('is-invalid');
        }
        
        if(!estado || !estado.value || estado.value === ''){
          valid = false;
          if(estado) estado.classList.add('is-invalid');
        }
        
        if(!ciudad || !ciudad.value || ciudad.value === ''){
          valid = false;
          if(ciudad) ciudad.classList.add('is-invalid');
        }
        
        if(terms && !terms.checked){
          valid = false;
          terms.classList.add('is-invalid');
        }
      }
      
      // Si no es válido, hacer scroll al primer campo con error
      if(!valid){
        const firstInvalid = qs('.is-invalid', section);
        if(firstInvalid){
          firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
          // Intentar hacer focus si es posible
          setTimeout(() => firstInvalid.focus(), 300);
        }
        
        // NO mostrar alerta redundante - los mensajes por campo son suficientes
        // El usuario ve claramente el campo en rojo + mensaje específico
      }
      
      return valid;
    }
    
    // FUNCIÓN DESACTIVADA - Ya no se usa alerta redundante
    // Los mensajes específicos por campo son suficientes y más claros
    /*
    function showValidationAlert(message){
      const oldAlert = qs('.wizard-validation-alert');
      if(oldAlert) oldAlert.remove();
      
      const alert = document.createElement('div');
      alert.className = 'alert alert-warning alert-dismissible fade show wizard-validation-alert';
      alert.setAttribute('role', 'alert');
      alert.innerHTML = `
        <i class="fas fa-exclamation-triangle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      `;
      
      const formContainer = qs('.form-container');
      if(formContainer){
        const firstSection = qs('.form-section');
        if(firstSection){
          firstSection.insertAdjacentElement('beforebegin', alert);
          setTimeout(() => {
            if(alert && alert.parentNode){
              alert.classList.remove('show');
              setTimeout(() => alert.remove(), 300);
            }
          }, 5000);
        }
      }
    }
    */

    function fillReview(){
      const map = [
        ['#rev-titulo','input[name="titulo"]'],
        ['#rev-categoria','select[name="categoria_id"]'],
        ['#rev-subcategoria','select[name="subcategoria_id"]'],
        ['#rev-precio','input[name="precio"]'],
        ['#rev-estado','select[name="estado"]'],
        ['#rev-ciudad','select[name="ciudad_id"]'],
        ['#rev-descripcion','textarea[name="descripcion"]']
      ];
      map.forEach(([outSel,inSel])=>{
        const out = qs(outSel); const input = qs(inSel);
        if(out && input){
          let val = '';
          if(input.tagName==='SELECT'){
            val = input.options[input.selectedIndex]?.text || '';
          } else {
            val = input.value;
          }
          out.textContent = val;
        }
      });
      // Previews: contar imágenes
      const files = qs('input[type="file"][name="imagenes[]"]')?.files;
      const revImgs = qs('#rev-imagenes');
      if(revImgs){ revImgs.textContent = files && files.length ? (files.length+" imagen(es) seleccionada(s)") : 'Sin imágenes'; }
    }

    btnPrev && btnPrev.addEventListener('click', function(e){ 
      e.preventDefault(); 
      if(current>1) showStep(current-1); 
    });
    
    btnNext && btnNext.addEventListener('click', function(e){ 
      e.preventDefault(); 
      
      // Validar el paso actual antes de avanzar
      if(validateCurrent()){
        // Solo avanzar si la validación es exitosa
        if(current < lastStep){
          showStep(current+1);
        }
      }
      // Si la validación falla, no hacer nada (los errores ya se muestran)
      
      // Actualizar estado del botón Next en paso 1
      updateStep1NextState();
    });

    // Permitir navegación por indicadores hacia atrás o al actual si ya está validado
    indicators.forEach(ind=>{
      ind.addEventListener('click', function(){
        const target = parseInt(this.getAttribute('data-step'));
        if(target<=current){ showStep(target); }
      });
    });

    // Marcar inválidos en input
    form.addEventListener('input', function(e){ if(e.target.classList.contains('is-invalid')) e.target.classList.remove('is-invalid'); });

    // Restaurar paso de sessionStorage
    let start = 1; try { const s = parseInt(sessionStorage.getItem('wizard_step')); if(s) start = Math.min(Math.max(1,s), lastStep); } catch(e){}
    showStep(start);

    // --------- Dropzone y manejo de imágenes ---------
    function rebuildFromInput(){
      selectedFiles = Array.from(fileInput?.files || []);
      renderPreviews();
    }

    function updateInputFiles(){
      try{
        const dt = new DataTransfer();
        selectedFiles.slice(0,MAX_FILES).forEach(f=>dt.items.add(f));
        fileInput.files = dt.files;
      }catch(err){
        // algunos navegadores no soportan DataTransfer programático
        canSyncInputFiles = false;
      }
    }

    function ensureAuxNodes(){
      if(!previewGrid) return;
      let counter = qs('#imageCounter');
      if(!counter){
        counter = document.createElement('div');
        counter.id = 'imageCounter';
        counter.className = 'form-text';
        previewGrid.insertAdjacentElement('afterend', counter);
      }
      let errors = qs('#imageErrors');
      if(!errors){
        errors = document.createElement('div');
        errors.id = 'imageErrors';
        errors.className = 'text-danger';
        counter.insertAdjacentElement('afterend', errors);
      }
      return {counter, errors};
    }

    function renderPreviews(){
      if(!previewGrid) return;
      previewGrid.innerHTML = '';
      selectedFiles.slice(0,MAX_FILES).forEach((file, index)=>{
        const url = URL.createObjectURL(file);
        const item = document.createElement('div');
        item.className = 'image-preview-item';
        item.draggable = true;
        item.dataset.index = String(index);
        item.innerHTML = `
          ${index===0 ? '<div class="badge-principal" style="position:absolute;top:5px;left:5px;background:#28a745;color:white;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:700;z-index:2;">PRINCIPAL</div>' : ''}
          <img src="${url}" alt="Imagen ${index+1}">
          <button type="button" class="remove-image" aria-label="Eliminar" title="Eliminar imagen">
            <i class="fas fa-times"></i>
          </button>
        `;
        // Drag & drop reordenación
        item.addEventListener('dragstart', (e)=>{ item.classList.add('dragging'); e.dataTransfer.setData('text/plain', String(index)); });
        item.addEventListener('dragend', ()=> item.classList.remove('dragging'));
        item.addEventListener('dragover', (e)=> e.preventDefault());
        item.addEventListener('drop', (e)=>{
          e.preventDefault();
          const from = parseInt(e.dataTransfer.getData('text/plain'));
          const to = parseInt(item.dataset.index);
          if(isNaN(from) || isNaN(to) || from===to) return;
          const moved = selectedFiles.splice(from,1)[0];
          selectedFiles.splice(to,0,moved);
          updateInputFiles();
          renderPreviews();
        });
        // Eliminar
        item.querySelector('.remove-image').addEventListener('click', ()=>{
          selectedFiles.splice(index,1);
          updateInputFiles();
          renderPreviews();
        });
        // Doble clic para marcar como portada (mover a índice 0)
        item.addEventListener('dblclick', ()=>{
          if(index>0){
            const f = selectedFiles.splice(index,1)[0];
            selectedFiles.unshift(f);
            updateInputFiles();
            renderPreviews();
          }
        });
        previewGrid.appendChild(item);
      });
      // Actualizar contador visual
      const counterEl = qs('#imageCount');
      if(counterEl){
        const count = Math.min(selectedFiles.length, MAX_FILES);
        counterEl.textContent = `${count}/${MAX_FILES} imagen(es)`;
        counterEl.className = 'image-count';
        if(count >= 1 && count <= MAX_FILES){
          counterEl.classList.add('valid');
        } else if(count === 0){
          counterEl.classList.remove('valid', 'invalid');
        } else {
          counterEl.classList.add('invalid');
        }
      }
    }

    function acceptFiles(files){
      const valid = [];
      const rejected = [];
      Array.from(files||[]).forEach(f=>{
        const typeOk = /^image\/(jpeg|jpg|png|webp)$/i.test(f.type);
        const sizeOk = f.size <= 5*1024*1024; // 5MB
        if(typeOk && sizeOk){ valid.push(f); } else { rejected.push(f); }
      });
      // Mezclar con existentes, respetando máximo
      selectedFiles = selectedFiles.concat(valid).slice(0,MAX_FILES);
      if(canSyncInputFiles){ updateInputFiles(); }
      renderPreviews();
      const aux = ensureAuxNodes();
      if(aux){
        aux.errors.textContent = rejected.length ? `${rejected.length} archivo(s) rechazado(s) por tipo/tamaño.` : '';
      }
    }

    if(fileInput){
      fileInput.addEventListener('change', ()=>{ canSyncInputFiles = true; rebuildFromInput(); });
      rebuildFromInput();
    }

    if(dropzone){
      ['dragenter','dragover'].forEach(evt=> dropzone.addEventListener(evt,(e)=>{ e.preventDefault(); dropzone.classList.add('drag-over'); }));
      ['dragleave','dragend','drop'].forEach(evt=> dropzone.addEventListener(evt,()=> dropzone.classList.remove('drag-over')));
      dropzone.addEventListener('drop', (e)=>{ e.preventDefault(); const dt = e.dataTransfer; if(dt && dt.files){ acceptFiles(dt.files); } });
    }

    // ---------- Bloqueo de avance en Paso 1 si faltan campos ----------
    function updateStep1NextState(){
      if(current!==1 || !btnNext) return;
      const cat = qs('select[name="categoria_id"]');
      const sub = qs('select[name="subcategoria_id"]');
      const ok = !!(cat && sub && cat.value && sub.value);
      btnNext.disabled = !ok;
    }
    ['change','input'].forEach(evt=>{
      document.addEventListener(evt, (e)=>{
        const id = e.target?.id;
        if(id==='categoria_id' || id==='subcategoria_id'){ updateStep1NextState(); }
      });
    });
  });
})();
