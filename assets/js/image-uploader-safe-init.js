/**
 * ImageUploader Safe Init
 * Inicializa los componentes de carga de imágenes de forma segura y limpia.
 * Reemplaza la lógica antigua dispersa en editor-tienda.js
 */

document.addEventListener('DOMContentLoaded', () => {
    initImageUploaders();
});

function initImageUploaders() {
    if (typeof ImageUploader === 'undefined') {
        console.error('ImageUploader class not found!');
        return;
    }

    console.log('Initializing Image Uploaders...');

    // 1. BANNERS (1, 2, 3, 4)
    // Usamos los IDs específicos de los elementos .image-uploader
    ['bannerUploader1', 'bannerUploader2', 'bannerUploader3', 'bannerUploader4'].forEach((uploaderId, index) => {
        const container = document.getElementById(uploaderId);
        if (container) {
            const bannerIndex = index + 1; // 1-based index
            const stateIndex = index;      // 0-based array index
            const type = index === 0 ? 'banner' : `banner_${bannerIndex}`;
            
            // Buscar input específico por ID o dentro del contenedor
            const input = document.getElementById(`bannerInput${bannerIndex}`) || container.querySelector('input[type="file"]');
            
            new ImageUploader({
                container: uploaderId, // ID correcto del elemento .image-uploader
                input: input,
                type: type,
                previewId: `bannerPreviewImg${bannerIndex}`,
                placeholderId: `bannerPlaceholder${bannerIndex}`,
                deleteBtnId: `btnDeleteBanner${bannerIndex}`, // ID correcto del botón
                onSuccess: (data) => {
                    // Update Global State
                    if (window.tiendaState && window.tiendaState.banner) {
                        const newUrl = `/uploads/${data.filename}?v=${Date.now()}`;
                        window.tiendaState.banner.imagenes[stateIndex] = newUrl;
                        
                        // Sync with Iframe
                        if (typeof postToFrame === 'function') {
                            postToFrame('updateTheme', { 
                                banner: { imagenes: window.tiendaState.banner.imagenes }
                            });
                        }
                    }
                    
                    if (typeof showNotif === 'function') showNotif(`Banner ${bannerIndex} actualizado`);
                    if (typeof markUnsaved === 'function') markUnsaved();
                },
                onDelete: (data) => {
                    // Update Global State on Delete
                    if (window.tiendaState && window.tiendaState.banner) {
                        window.tiendaState.banner.imagenes[stateIndex] = '';
                        
                        // Sync with Iframe
                        if (typeof postToFrame === 'function') {
                            postToFrame('updateTheme', { 
                                banner: { imagenes: window.tiendaState.banner.imagenes }
                            });
                        }
                    }
                    
                    if (typeof showNotif === 'function') showNotif(`Banner ${bannerIndex} eliminado`);
                    if (typeof markUnsaved === 'function') markUnsaved();
                }
            });
        }
    });

    // 2. LOGO PRINCIPAL (Identidad de Marca)
    const logoPrincipalContainer = document.getElementById('brand-logo-uploader');
    if (logoPrincipalContainer) {
        new ImageUploader({
            container: 'brand-logo-uploader',
            inputId: 'logoPrincipalInput',
            previewId: 'principalLogoPreview',
            placeholderId: 'principalLogoPlaceholder',
            deleteBtnId: 'deletePrincipalLogoBtn',
            type: 'logo_principal',
            onSuccess: (data) => {
                const newUrl = `/uploads/logos/${data.filename}?v=${Date.now()}`;
                
                // Update Global State
                if (window.tiendaState) {
                    window.tiendaState.logo_principal = data.filename;
                }
                
                // Sync with Iframe
                if (typeof postToFrame === 'function') {
                    postToFrame('updateLogoState', { 
                        visible: window.tiendaState.mostrar_logo,
                        url: newUrl 
                    });
                }
                
                if (typeof showNotif === 'function') showNotif('Logo Principal actualizado');
                if (typeof markUnsaved === 'function') markUnsaved();
            },
            onDelete: (data) => {
                if (window.tiendaState) {
                    window.tiendaState.logo_principal = null;
                }
                
                if (typeof postToFrame === 'function') {
                    postToFrame('updateLogoState', { 
                        visible: window.tiendaState.mostrar_logo,
                        url: null 
                    });
                }
                
                if (typeof showNotif === 'function') showNotif('Logo Principal eliminado');
                if (typeof markUnsaved === 'function') markUnsaved();
            }
        });
    }

    // 3. LOGO FERIA (Icono cuadrado)
    // Actualizado: Ahora usa ID explícito y sin conflictos de onclick inline
    const logoFeriaContainer = document.getElementById('feriaLogoContainer');
    
    if (logoFeriaContainer) {
        new ImageUploader({
            container: 'feriaLogoContainer', // ID correcto
            input: document.getElementById('logoUploadInput'), // Elemento o ID
            previewId: 'logoPreview',
            placeholderId: 'logoPlaceholder',
            deleteBtnId: 'btnDeleteFeriaLogo', // ID correcto
            type: 'logo',
            onSuccess: (data) => {
                const newUrl = `/uploads/logos/${data.filename}?t=${Date.now()}`;
                
                if (window.storeInfo) window.storeInfo.logo = newUrl;
                if (typeof postToFrame === 'function') postToFrame('updateLogo', { url: newUrl });
                if (typeof showNotif === 'function') showNotif('Icono de Feria actualizado');
                if (typeof markUnsaved === 'function') markUnsaved();
            },
            onDelete: (data) => {
                if (window.storeInfo) window.storeInfo.logo = '';
                if (typeof postToFrame === 'function') postToFrame('updateLogo', { url: '' });
                if (typeof showNotif === 'function') showNotif('Icono de Feria eliminado');
                if (typeof markUnsaved === 'function') markUnsaved();
            }
        });
    }
}
