=== Tureo ===
Contributors: tureo
Tags: tureo, turismo, viajes, paquetes, agencia, slider
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.7.2
License: GPLv2 or later

Tureo — plugin completo para gestión de paquetes de viaje en agencias de turismo.

== Descripción ==

Tureo es un plugin WordPress completo para agencias de turismo.

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
* Video por YouTube, Vimeo o archivo MP4/WebM en la ficha
* Itinerario día por día tipo acordeón
* Botón y consulta directa por WhatsApp (número general o por paquete)
* Formulario público de consulta/reserva con aviso por email
* Módulo opcional de reservas con control de cupos
* 8 paletas cromáticas con nombres temáticos y presets tipográficos configurables
* Etiquetas Open Graph, Twitter Card y JSON-LD para SEO

== Instalación ==

1. Subí la carpeta `travel-packages` al directorio `/wp-content/plugins/`
2. Activá el plugin desde el menú "Plugins" en WordPress
3. Navegá a "Tureo" en el menú lateral para comenzar

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

El carrusel es de scroll infinito: envuelve al llegar al final sin saltos visibles.

**Paquete individual:**
`[tp_paquete id="123"]`

Ver SHORTCODES.md para documentación completa.

== Changelog ==

= 2.7.2 =
* Nuevo campo "Ubicación del hotel en Google Maps" en la ficha del paquete.
* El mapa se muestra debajo de la galería de fotos, con diseño responsive.
* Acepta dirección, coordenadas, enlace de Google Maps o código iframe.
* Identidad visual Tureo aplicada a todas las pantallas del panel: paquetes, calendario, ajustes, reservas, campos personalizados y shortcodes.

= 2.7.1 =
* Nuevo shortcode [tp_buscador] con sugerencias en vivo vía AJAX.
* Búsqueda precisa por título, descripción corta, contenido y destinos/tipologías.
* El buscador respeta la paleta y la tipografía elegidas en Ajustes.
* Nueva configuración del buscador en Ajustes (página de resultados, textos y filtros).

= 2.7.0 =
* Rebranding del plugin a Tureo.
* Nuevo nombre, tagline e identidad visual en el panel de administración.

= 2.6.0 =
* Botón "Enviar consulta" con tipografía blanca en todos sus estados.
* Número de WhatsApp por paquete, con fallback al número general de Ajustes.
* El número de contacto se muestra en la ficha del paquete y se usa en todos los botones de WhatsApp.

= 2.5.0 =
* Cuatro paletas nuevas: Selva Esmeralda, Dunas al Amanecer, Aurora Boreal y Ruta del Vino.
* Paletas renombradas con nombres temáticos de viajes: Noche Índigo, Brisa Marina, Atardecer en la Playa y Cumbre Nublada.
* Nuevo selector de tipografías con presets Moderna, Elegante, Aventura y Editorial.

= 2.4.1 =
* Corregido el color del botón "Enviar consulta" para que respete la paleta elegida y no sea sobrescrito por el tema.

= 2.4.0 =
* Selector de paleta cromática del frontend desde Ajustes: actual, fríos, cálidos y escala de grises.
* La paleta elegida se aplica globalmente a sliders, grillas, fichas, formularios y reservas.

= 2.3.0 =
* Nuevo módulo opcional de reservas con control de cupos y aviso de últimos lugares.
* Formulario público de consulta/reserva con protección antispam y aviso por email.
* Ajustes globales de WhatsApp y email de consultas.
* Video embebido e itinerario día por día en la ficha del paquete.
* Botones de compartir, copiar enlace y exportar a PDF.
* Metadatos Open Graph, Twitter Card y JSON-LD para SEO.
* Nueva paleta visual del frontend (índigo, rosa, aqua y porcelana).
* Bloque "Destinos similares" con carrusel de paquetes y selección editable desde el admin.

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
