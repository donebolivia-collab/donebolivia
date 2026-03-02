# CARPETA DE BADGES PERSONALIZABLES

Esta carpeta contiene los badges personalizados para los productos.

## 📁 ESTRUCTURA

```
assets/img/badges/
├── README.md                 # Este archivo
├── .gitkeep                 # Mantiene la carpeta en Git
├── envio_gratis.svg         # Badge de envío gratis (SVG优先)
├── envio_gratis.png         # Badge de envío gratis (PNG备选)
├── oferta.svg               # Badge de oferta (SVG优先)
├── oferta.png               # Badge de oferta (PNG备选)
├── novedad.svg              # Badge de novedad (SVG优先)
├── novedad.png              # Badge de novedad (PNG备选)
└── personalizados/          # Carpeta para badges personalizados
    ├── tu_badge_1.png
    ├── tu_badge_2.svg
    └── ...
```

## 🎨 ESPECIFICACIONES DE DISEÑO

### **Dimensiones recomendadas:**
- Ancho: 60-80px
- Alto: 20-24px
- Resolución: 72dpi (web)

### **Formatos soportados:**
- **SVG** (vectorial, prioridad máxima)
- PNG (con transparencia)
- WebP (moderno, optimizado)

### **Consideraciones de diseño:**
- Usar colores que contrasten bien
- Mantener texto legible (mínimo 11px)
- Considerar modo claro/oscuro
- Bordes redondeados (4-6px)

## 🔄 CÓMO FUNCIONA

### **Prioridad de detección:**
1. **SVG primero:** `assets/img/badges/[nombre].svg` 
2. **PNG después:** `assets/img/badges/[nombre].png`
3. **Diseño por defecto:** FontAwesome si no existe archivo

### **Nombres de archivo:**
- `envio_gratis.svg` o `envio_gratis.png` → Reemplaza badge de envío gratis
- `oferta.svg` o `oferta.png` → Reemplaza badge de oferta  
- `novedad.svg` o `novedad.png` → Reemplaza badge de novedad

## 🛠️ INTEGRACIÓN

El sistema busca automáticamente en este orden:
1. `assets/img/badges/[nombre].svg` (tu diseño SVG)
2. `assets/img/badges/[nombre].png` (tu diseño PNG)
3. Diseño por defecto con FontAwesome

## 📝 EJEMPLOS

### Badge personalizado con Adobe Illustrator:
1. Diseñar con 60x20px
2. **Exportar como SVG** (recomendado) o PNG con transparencia
3. Guardar como `novedad.svg` o `novedad.png`
4. Subir a `assets/img/badges/`

### Badge vectorial (SVG):
1. Diseñar en Illustrator
2. Exportar como SVG
3. Optimizar si es necesario
4. Guardar como `oferta.svg`

## 🎯 BENEFICIOS

- ✅ **SVG优先:** Los archivos SVG tienen prioridad sobre PNG
- ✅ Badges únicos para tu marca
- ✅ Control total sobre el diseño
- ✅ Compatible con el sistema existente
- ✅ Fácil de actualizar
- ✅ Soporte para múltiples formatos
- ✅ Calidad vectorial con SVG

## 🔧 TIPS

- **SVG es mejor:** Calidad infinita, peso ligero
- **Transparencia:** Usa fondos transparentes
- **Nombres exactos:** Usa los nombres de archivo exactos
- **Recarga:** Limpia caché del navegador si no ves cambios
