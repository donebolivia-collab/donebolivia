module.exports = {
  root: true,
  env: {
    browser: true,
    es2021: true,
    jquery: true
  },
  plugins: [
    'html',
    'import',
    'promise',
    'jquery'
  ],
  parserOptions: {
    ecmaVersion: 2021,
    sourceType: 'module'
  },
  globals: {
    // Variables globales de PHP usadas en frontend
    'tiendaState': 'readonly',
    'menuItems': 'readonly',
    'allProducts': 'readonly',
    'availableBadges': 'readonly',
    'storeInfo': 'readonly',
    // Funciones globales del sistema
    'toggleAccordion': 'writable',
    'openProductDrawer': 'writable',
    'closeProductDrawer': 'writable',
    'openHomeDrawer': 'writable',
    'closeHomeDrawer': 'writable',
    'openSectionsDrawer': 'writable',
    'closeSectionsDrawer': 'writable',
    'openInventoryDrawer': 'writable',
    'openFeriaDrawer': 'writable',
    'toggleUI': 'writable',
    'selectUIOption': 'writable',
    'setPhotoStyle': 'writable',
    'setGridDensity': 'writable',
    'setFont': 'writable',
    'setTextSize': 'writable',
    'setFeaturedSectionsStyle': 'writable',
    'updateBannerState': 'writable',
    'toggleFeaturedSections': 'writable',
    'handleLocation': 'writable',
    'editSocial': 'writable',
    'guardarProducto': 'writable',
    'navigateBannerCarousel': 'writable',
    // Módulos del sistema
    'ImageManager': 'writable',
    'BadgeSystem': 'writable',
    'ProductEditorCore': 'writable',
    'ProductDataContract': 'writable',
    'ProductSyncManager': 'writable',
    'ProductUIController': 'writable',
    'RealtimeCommunicator': 'writable',
    'IframeReceiver': 'writable',
    'IframeCommunicator': 'writable',
    'UIComponents': 'writable',
    // Funciones de notificación
    'showNotif': 'writable',
    'syncContact': 'writable',
    'syncAllSettings': 'writable',
    'markUnsaved': 'writable',
    'updateGhostCard': 'writable',
    'renderSidebarProducts': 'writable',
    'renderInventoryList': 'writable',
    'renderSectionsList': 'writable',
    'updateSectionCounts': 'writable',
    'updateMenuInFrame': 'writable',
    'postToFrame': 'writable',
    // Variables de estado
    'currentCategoryTienda': 'writable',
    'currentSectionFilter': 'writable',
    // Funciones específicas del editor
    'initBadgesMultiSelect': 'writable',
    'detectLocationGPS': 'writable',
    'closeInventoryDrawer': 'writable',
    'updateBannerSlotsUI': 'writable',
    'updateInventoryFilters': 'writable',
    'renderMasterFilter': 'writable',
    'saveInlineEdit': 'writable',
    'applySectionFilter': 'writable',
    'initSectionsManager': 'writable',
    'cargarSectores': 'writable',
    'cargarBloques': 'writable',
    'cargarGridPuestos': 'writable',
    'ocuparPuesto': 'writable',
    'closeFeriaModal': 'writable',
    'eliminarProducto': 'writable',
    'toggleProductoActivo': 'writable',
    'UIMultiSelect': 'writable',
    'tippy': 'writable',
    // Funciones específicas del editor tienda
    'TiendaGuard': 'writable',
    'initAccordions': 'writable',
    'saveFeaturedSectionsState': 'writable',
    'renderProductImagePreview': 'writable',
    'cargarSubcategorias': 'writable',
    'showToast': 'writable',
    'initProductImageUploader': 'writable',
    // Variables específicas de utilidades
    'puter': 'writable',
    'initGhostCardListeners': 'writable',
    'debounce': 'writable',
    'throttle': 'writable',
    'formatNumber': 'writable',
    'formatPhone': 'writable',
    'isMobile': 'writable',
    // Clases globales de componentes
    'ImageUploader': 'writable',
    'tom': 'readonly',
    // Variables de módulos
    'module': 'readonly',
    // jQuery y globals del navegador
    '$': 'readonly',
    'jQuery': 'readonly',
    'document': 'readonly',
    'window': 'readonly',
    'console': 'readonly',
    'setTimeout': 'readonly',
    'setInterval': 'readonly',
    'clearTimeout': 'readonly',
    'clearInterval': 'readonly'
  },
  overrides: [
    {
      files: ['*.php'],
      parser: '@html-eslint/parser',
      plugins: ['html'],
      rules: {
        'html/indent': 'off',
        'html/no-duplicate-id': 'error',
        'html/no-obsolete-tags': 'error',
        'html/quotes': 2,
        'html/html-req-lang': 'off'
      }
    },
    {
      files: ['assets/js/**/*.js'],
      rules: {
        'no-console': 'off',
        'no-unused-vars': 'warn',
        'prefer-const': 'error',
        'no-var': 'error'
      }
    }
  ],
  rules: {
    // Reglas específicas para el proyecto
    'no-console': 'off',
    'no-unused-vars': 'warn',
    'no-undef': 'error',
    'semi': ['error', 'always'],
    'quotes': ['error', 'single'],
    'indent': ['error', 2],
    'comma-dangle': ['error', 'never'],
    'object-curly-spacing': ['error', 'always'],
    'array-bracket-spacing': ['error', 'never'],
    'space-before-function-paren': ['error', 'never'],
    'keyword-spacing': 'error',
    'space-infix-ops': 'error',
    'eol-last': 'error',
    'no-trailing-spaces': 'error'
  }
};
