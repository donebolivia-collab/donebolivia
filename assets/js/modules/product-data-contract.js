/**
 * Product Data Contract - Interface JavaScript-PHP
 * Define la estructura exacta de datos para comunicación JS-PHP
 */
const ProductDataContract = {
  // Estructura mínima requerida
  required: {
    id: 'number|null', // null para nuevos productos
    titulo: 'string|min:10',
    descripcion: 'string|min:20',
    precio: 'number|gt:0',
    estado: 'string|in:Nuevo,Como Nuevo,Buen Estado,Aceptable',
    categoria_id: 'number|gt:0',
    subcategoria_id: 'number|gt:0',
    departamento: 'string|len:3',
    municipio: 'string|len:7'
  },

  // Campos opcionales
  optional: {
    categoria_tienda: 'string|null',
    badges: 'array',
    imagenes_nuevas: 'array|null',
    imagenes_eliminar: 'array|null'
  },

  // Tipos de estado permitidos
  estadosValidos: [
    'Nuevo', 'Como Nuevo', 'Buen Estado', 'Aceptable',
    'nuevo', 'como nuevo', 'buen estado', 'aceptable'
  ],

  /**
     * Valida que el objeto cumpla con el contrato
     * @param {Object} productData - Datos del producto
     * @returns {Object} {valid: boolean, errors: string[]}
     */
  validate: function(productData) {
    const errors = [];

    // Validar campos requeridos
    for (const [field, rule] of Object.entries(this.required)) {
      const value = productData[field];

      if (value === undefined || value === null || value === '') {
        errors.push(`Campo requerido faltante: ${field}`);
        continue;
      }

      // Validar tipo y reglas específicas
      const validation = this.validateField(field, value, rule);
      if (!validation.valid) {
        errors.push(...validation.errors);
      }
    }

    // Validar campos opcionales si existen
    for (const [field, rule] of Object.entries(this.optional)) {
      if (productData[field] !== undefined && productData[field] !== null) {
        const validation = this.validateField(field, productData[field], rule);
        if (!validation.valid) {
          errors.push(...validation.errors);
        }
      }
    }

    return {
      valid: errors.length === 0,
      errors
    };
  },

  /**
     * Valida un campo específico según su regla
     * @param {string} field - Nombre del campo
     * @param {*} value - Valor del campo
     * @param {string} rule - Regla de validación
     * @returns {Object} {valid: boolean, errors: string[]}
     */
  validateField: function(field, value, rule) {
    const errors = [];
    const rules = rule.split('|');

    for (const r of rules) {
      if (r.includes(':')) {
        const [ruleName, ruleValue] = r.split(':');

        switch (ruleName) {
        case 'min':
          if (typeof value === 'string' && value.length < parseInt(ruleValue)) {
            errors.push(`${field}: debe tener al menos ${ruleValue} caracteres`);
          }
          break;
        case 'max':
          if (typeof value === 'string' && value.length > parseInt(ruleValue)) {
            errors.push(`${field}: debe tener máximo ${ruleValue} caracteres`);
          }
          break;
        case 'len':
          if (typeof value === 'string' && value.length !== parseInt(ruleValue)) {
            errors.push(`${field}: debe tener exactamente ${ruleValue} caracteres`);
          }
          break;
        case 'gt':
          if (typeof value === 'number' && value <= parseFloat(ruleValue)) {
            errors.push(`${field}: debe ser mayor a ${ruleValue}`);
          }
          break;
        case 'in':
          const allowedValues = ruleValue.split(',');
          if (!allowedValues.includes(value)) {
            errors.push(`${field}: valor no permitido. Valores válidos: ${ruleValue}`);
          }
          break;
        }
      } else {
        switch (r) {
        case 'string':
          if (typeof value !== 'string') {
            errors.push(`${field}: debe ser texto`);
          }
          break;
        case 'number':
          if (typeof value !== 'number' && isNaN(parseFloat(value))) {
            errors.push(`${field}: debe ser número`);
          }
          break;
        case 'array':
          if (!Array.isArray(value)) {
            errors.push(`${field}: debe ser un array`);
          }
          break;
        case 'null':
          if (value !== null) {
            errors.push(`${field}: debe ser nulo`);
          }
          break;
        }
      }
    }

    return {
      valid: errors.length === 0,
      errors
    };
  },

  /**
     * Normaliza los datos del producto antes de enviar
     * @param {Object} productData - Datos crudos del formulario
     * @returns {Object} Datos normalizados
     */
  normalize: function(productData) {
    const normalized = {};

    // Normalizar estado
    if (productData.estado) {
      const estadoNormalizado = this.normalizarEstado(productData.estado);
      normalized.estado = estadoNormalizado;
    }

    // Normalizar números
    ['precio', 'categoria_id', 'subcategoria_id', 'id'].forEach(field => {
      if (productData[field] !== undefined && productData[field] !== null) {
        normalized[field] = parseFloat(productData[field]) || 0;
      }
    });

    // Normalizar strings
    ['titulo', 'descripcion', 'categoria_tienda', 'departamento', 'municipio'].forEach(field => {
      if (productData[field] !== undefined && productData[field] !== null) {
        normalized[field] = String(productData[field]).trim();
      }
    });

    // Mantener arrays tal como están
    ['badges', 'imagenes_nuevas', 'imagenes_eliminar'].forEach(field => {
      if (productData[field] !== undefined) {
        normalized[field] = productData[field];
      }
    });

    return normalized;
  },

  /**
     * Normaliza el estado del producto
     * @param {string} estado - Estado crudo
     * @returns {string} Estado normalizado
     */
  normalizarEstado: function(estado) {
    const mapa = {
      nuevo: 'Nuevo',
      'como nuevo': 'Como Nuevo',
      'buen estado': 'Buen Estado',
      aceptable: 'Aceptable',
      como_nuevo: 'Como Nuevo',
      buen_estado: 'Buen Estado'
    };

    const key = String(estado).toLowerCase().trim();
    return mapa[key] || estado;
  }
};

// Exponer globalmente
window.ProductDataContract = ProductDataContract;
