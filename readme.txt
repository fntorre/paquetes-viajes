=== Paquetes de Viaje ===
Contributors: tuagencia
Tags: turismo, viajes, paquetes, agencia, slider
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Plugin completo para gestión de paquetes de viaje en agencias de turismo.

== Descripción ==

Paquetes de Viaje es un plugin WordPress completo para agencias de turismo.

**Características:**

* Custom Post Type "Paquetes de Viaje" con soporte de imágenes, galería, precio y fechas
* Ficha técnica con campos personalizables por paquete
* Toggle para destacar paquetes en el slider del home
* Toggle para destacar paquetes en secciones especiales
* Panel de administración amigable con estadísticas
* Shortcode [tp_slider] para el slider principal
* Shortcode [tp_grid] para grilla de paquetes
* Shortcode [tp_paquete id="X"] para paquete individual
* Taxonomía "Destinos" para categorizar paquetes
* Slider con autoplay, swipe táctil y navegación por puntos

== Instalación ==

1. Subí la carpeta `travel-packages` al directorio `/wp-content/plugins/`
2. Activá el plugin desde el menú "Plugins" en WordPress
3. Navegá a "Paquetes de Viaje" en el menú lateral para comenzar

== Shortcodes ==

**Slider:**
`[tp_slider limit="5" autoplay="true" interval="5000" speed="300" pauseOnHover="true" showArrows="true" showDots="true"]`

Props disponibles para tp_slider:
- limit: cantidad de paquetes (default: 5)
- autoplay: activar autoplay (true/false, default: true)
- interval: milisegundos entre slides (default: 5000)
- speed: velocidad de transición en ms (default: 300)
- pauseOnHover: pausar autoplay al pasar mouse (true/false, default: true)
- showArrows: mostrar botones prev/next (true/false, default: true)
- showDots: mostrar puntos de navegación (true/false, default: true)

**Grilla:**
`[tp_grid limit="6" columns="3" destacados="false" destino="" ids=""]`

Props carrusel para tp_grid:
- carousel: activar modo carrusel (true/false)
- desktop: paquetes visibles por página en desktop (default: columns)
- movile: paquetes visibles por página en mobile (default: desktop)

Ejemplo: `[tp_grid destacados="true" carousel="true" limit="12" desktop="3" movile="1"]`

**Paquete individual:**
`[tp_paquete id="123"]`

Ver SHORTCODES.md para documentación completa.

== Changelog ==

= 2.0.0 =
* Nueva interfaz de administración para crear y editar paquetes desde un panel visual.
* Página de detalle de paquete con precio, fechas y ficha técnica personalizada.
* Soporte para props configurables en [tp_slider]: autoplay, interval, speed, pauseOnHover, showArrows, showDots
* Editor WYSIWYG (wp_editor) para descripción completa con soporte de HTML, videos, iframes y código
* Corrección: imagen destacada ya no se borra al editar paquetes existentes
* Validación mejorada de rangos de fechas (salida/regreso)
* Gestión de destinos desde la misma pantalla de paquetes.
* Nuevos campos editables: precios dinámicos, galerías, fechas y campos técnicos por paquete.

= 1.0.0 =
* Lanzamiento inicial
