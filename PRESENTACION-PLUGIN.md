# Tureo
## Presentación del Plugin — Guía para el Usuario

**Versión 2.7.0** · Plugin WordPress para gestión de paquetes turísticos

---

## 1. ¿Qué es "Tureo"?

Tureo es un plugin de WordPress diseñado específicamente para **agencias de turismo**. Permite crear, organizar y publicar paquetes turísticos en el sitio web sin necesidad de conocimientos técnicos, todo desde un panel visual pensado para trabajar como en un portal de viajes.

Con este plugin podés:

- ✅ **Crear paquetes turísticos** con imágenes, precios, fechas de salida y ficha técnica completa.
- ✅ **Clasificarlos** por destinos y tipologías (playa, montaña, ciudad, etc.) para que el visitante encuentre rápido lo que busca.
- ✅ **Decidir qué mostrar** en la portada: slider principal, secciones de destacados o catálogo completo.
- ✅ **Configurar cómo se ve cada sección**: velocidad del slider, cantidad de columnas, filtros visibles, orden de presentación.
- ✅ **Trabajar más rápido**: duplicar paquetes similares, exportar/importar catálogo en JSON y filtrar el listado por cualquier criterio.

---

## 2. Acceso al Panel

Una vez activado el plugin, en el menú lateral de WordPress aparece la opción **✈ Tureo**, con estas secciones:

| Sección | Para qué sirve |
|---|---|
| **Tureo** | Vista general con estadísticas y accesos rápidos |
| **Paquetes** | Buscar, filtrar, duplicar, exportar/importar y eliminar paquetes |
| **+ Agregar Paquete** | Abre el editor dedicado para crear un paquete |
| **Calendario de Salidas** | Vista mensual de salidas, cupos, estados y precios |
| **Destinos** | Administrar los destinos del catálogo |
| **Tipologías** | Administrar las categorías de tipo de viaje |
| **Reservas** | Consultar y administrar las reservas |
| **Campos Personalizados** | Definir datos adicionales para todos los paquetes |
| **Ajustes** | Configuración general del plugin |
| **Guía de Shortcodes** | Consulta rápida de códigos para insertar en páginas |

---

## 3. El Panel Principal (Dashboard)

Al ingresar ves un resumen del estado del catálogo:

- **Tarjetas de estadísticas:** total de paquetes publicados, cuántos están en el slider y cuántos son destacados.
- **Últimos paquetes:** tabla con los 5 paquetes más recientes donde podés:
  - Activar/desactivar el **Slider** con un clic (✓ / ○).
  - Marcar/desmarcar como **Destacado** con un clic (⭐ / ☆).
  - Editar o eliminar directamente.
- **Shortcodes rápidos:** los códigos más usados, listos para copiar y pegar.

> 💡 Los interruptores de la tabla funcionan al instante: ideal para rotar la promoción de temporada sin abrir cada paquete.

---

## 4. Crear un Paquete Paso a Paso

Desde **Paquetes → + Agregar Paquete** se abre el editor visual dedicado, organizado en secciones con una barra de navegación rápida. Las secciones opcionales vacías comienzan plegadas y se expanden automáticamente cuando ya tienen información.
El título es obligatorio; si falta, el formulario vuelve a abrirse con los datos ingresados para que puedas corregirlo sin empezar de nuevo. La barra inferior mantiene a mano las acciones de guardado y vista previa.

### 4.1 Contenido

| Campo | Descripción |
|---|---|
| **Título** | Nombre comercial del paquete (ej: *"Tour por Europa 12 días"*) |
| **Descripción corta** | Resumen atractivo de hasta 200 caracteres (con contador incluido). Es la que se ve en las tarjetas y el slider |
| **Descripción completa** | Editor visual de WordPress (pestañas Visual/Texto) para cargar texto, HTML, videos embebidos o código |

### 4.2 Imágenes

- **Imagen principal:** foto de portada, se selecciona desde la biblioteca de medios.
- **Galería:** múltiples imágenes por paquete con vista previa en grilla.

### 4.3 Precios y seña

- **Precio adulto, niño y suplemento single** con su moneda: US$ Dólar · $ Peso Arg. · € Euro · R$ Real.
- **Seña / depósito:** sin seña, porcentaje del total o monto fijo, más una nota de saldo.
- **Variantes de precio adicionales (opcional):** sección plegable para categorías extra (ej: *Económico / Estándar / Premium*), cada una con monto, moneda, nota y opción de destacarla.

> 💡 La web muestra automáticamente el **mejor precio**: primero el precio adulto o el destacado; si no hay, el más económico.

### 4.4 Publicación y clasificación

| Opción | Efecto |
|---|---|
| **Slider Home** ☑ | El paquete rota en el slider principal de la portada |
| **Destacado** ⭐ | Aparece en las secciones/grillas de destacados |
| **Estado** | Publicado (visible al público) o Borrador (oculto mientras se termina de cargar) |
| **Tipo de viaje** | Nacional o Internacional |
| **Destino** | Elegir de la lista o **crear uno nuevo al momento** (ej: Caribe, Europa, Mendoza) sin salir del formulario |
| **Tipología** | Categoría del tipo de experiencia (ej: Playa, Montaña, Ciudad), también creable al vuelo |

Las tipologías se muestran como **etiquetas doradas** en las tarjetas y generan su propia página de archivo (`/tipologia/playa/`).

### 4.5 Salidas y disponibilidad

Se pueden agregar **múltiples salidas** con selector de calendario. Cada salida admite cupos, cupos vendidos, mínimo/máximo de pasajeros, cierre de venta, estado y precio propio por adulto/niño. El sistema valida que las fechas sean coherentes.

### 4.6 Información adicional

- **Ficha técnica:** campos libres por paquete, fila por fila (ej: *"Duración → 12 días"*, *"Régimen → All Inclusive"*).
- **Campos personalizados:** en **Tureo → Campos Personalizados** se definen campos globales que aparecen en **todos** los paquetes. Tipos disponibles: Texto, Número, Área de texto, Fecha, Selección (con opciones) y Checkbox. Se pueden marcar como requeridos, ordenar arrastrando y eliminar los que no se usen.
- Vienen precargados con ejemplos útiles: *Traslado*, *Hotel* e *Itinerario*.

> 💡 Esta es la herramienta clave para estandarizar la carga: si tu agencia siempre informa "Aéreo", "Hotel" y "Traslados", los definís una vez y aparecen listos en cada paquete nuevo.

---

## 5. Gestionar el Catálogo con Agilidad

La pantalla **Paquetes** se enfoca en el listado; cada paquete se edita en una pantalla independiente. El buscador consulta todo el catálogo y conserva los filtros al paginar. Incluye:

- **Buscador por nombre** y filtros avanzados: destino, tipología, tipo de viaje, rango de precio mínimo/máximo, si está en slider, si es destacado y ordenamiento por fecha/título/precio (ascendente o descendente).
- **Editor separado:** crear o editar abre el formulario en una pantalla propia, con acceso rápido al listado y a la vista previa.
- **Duplicar paquete:** crea una copia para reutilizar estructura (ideal para paquetes similares entre temporadas).
- **Exportar:** descarga todo el catálogo en formato JSON (backup o traslado a otro sitio).
- **Importar:** carga masiva desde un archivo JSON previamente exportado.
- **Paginación:** 40 paquetes por página.

---

## 6. Mostrar los Paquetes en el Sitio Web

El contenido se inserta en cualquier página mediante **shortcodes** (códigos cortos que se pegan en el editor). Todos son **responsive** (se adaptan a celular y tablet).

### 6.1 `[tp_slider]` — Slider de portada

Muestra en carrusel los paquetes marcados con "Slider Home".

| Parámetro | Valor por defecto | Qué controla |
|---|---|---|
| `limit` | 5 | Cantidad máxima de paquetes |
| `autoplay` | true | Avance automático |
| `interval` | 5000 | Milisegundos entre slides (5000 = 5 seg) |
| `speed` | 300 | Velocidad de la transición |
| `pauseOnHover` | true | Pausar cuando el mouse está encima |
| `showArrows` | true | Flechas anterior/siguiente |
| `showDots` | true | Puntos de navegación |

Incluye **swipe táctil** para celulares.

```
[tp_slider]
[tp_slider limit="8" autoplay="true" interval="4000" speed="350"]
```

### 6.2 `[tp_grid]` — Grilla de tarjetas

| Parámetro | Valor por defecto | Qué controla |
|---|---|---|
| `limit` | 6 | Cantidad de paquetes |
| `columns` | 3 | Columnas: 1, 2, 3 o 4 |
| `destacados` | false | Solo paquetes marcados como destacados |
| `carousel` | false | Modo carrusel horizontal con scroll infinito |
| `desktop` | = columns | Ítems visibles por página en computadora |
| `movile` | = desktop | Ítems visibles por página en móvil (≤767px) |
| `destino` | — | Filtrar por destino (ej: `destino="europa"`) |
| `tipologia` | — | Filtrar por tipología (ej: `tipologia="playa"`) |
| `ids` | — | Mostrar paquetes puntuales (ej: `ids="10,25,33"`) |
| `orderby` / `order` | date / DESC | Orden por fecha o título |

Ejemplos:

```
[tp_grid destacados="true" columns="3" limit="9"]              → grilla de destacados
[tp_grid destino="caribe" columns="2"]                          → solo Caribe
[tp_grid destacados="true" carousel="true" limit="12"
         desktop="3" movile="1"]                                → carrusel responsive
```

> 💡 El carrusel tiene **scroll infinito**: al llegar al final vuelve a empezar sin cortes.

### 6.3 `[tp_paquete]` — Paquete individual

Inserta un paquete puntual dentro de cualquier página o landing:

```
[tp_paquete id="123"]
```

*(El ID se ve en la URL al editar el paquete desde el panel.)*

### 6.4 `[tp_catalogo]` — Catálogo completo con buscador

Landing lista para usar: buscador por texto, filtros por destino y ordenamiento.

```
[tp_catalogo]                              → catálogo completo
[tp_catalogo columns="4" limit="12"]       → personalizado
```

Además admite **enlaces pre-filtrados** para campañas o menús:

```
<a href="/paquetes/?tipologia=playa">Ver paquetes de playa</a>
<a href="/paquetes/?destino=caribe">Escapadas al Caribe</a>
```

> 📋 Todo esto también está disponible dentro de WordPress en **Guía de Shortcodes**, con ejemplos copiables.

---

## 7. Escenarios de Uso Típicos

| Necesidad | Solución con el plugin |
|---|---|
| Promocionar ofertas de temporada en la portada | Marcar los paquetes con **Slider Home** desde el dashboard, con un clic |
| Armar una sección "Los más elegidos" | Marcar paquetes como **Destacados** y usar `[tp_grid destacados="true"]` |
| Landing de campaña para una tipología | Página nueva con `[tp_catalogo]` y enlace pre-filtrado `?tipologia=playa` |
| Cargar 20 paquetes similares | Completar uno bien hecho y **duplicarlo**, editando solo precios y fechas |
| Cambiar de proveedor o renovar el sitio | **Exportar** el catálogo a JSON e **importarlo** en la nueva instalación |
| Información uniforme en todas las fichas | Definir **Campos Personalizados** globales (hotel, régimen, aéreo…) |
| Salidas grupales con varias fechas | Agregar múltiples rangos salida/regreso en el mismo paquete |
| Precios por categoría de habitación | Múltiples precios por paquete, destacando el promocional |

---

## 8. Buenas Prácticas y Recomendaciones

1. **Imagen principal:** usar fotos horizontales de buena calidad; es la cara del paquete en sliders y tarjetas.
2. **Descripción corta:** aprovechar los 200 caracteres con un gancho comercial; es lo primero que lee el visitante.
3. **Marcar un precio destacado** cuando haya varios: es el que verá el cliente primero.
4. **Usar Borrador** mientras se completa la carga; publicar recién cuando el paquete esté listo para vender.
5. **Destinos y tipologías consistentes:** mantener nombres cortos y reutilizables (mejor "Caribe" que "Islas del Caribe Mexicano y Riviera Maya").
6. Si se agregan tipologías nuevas y las URLs no responden, ir a **Ajustes → Enlaces permanentes → Guardar cambios** para refrescar las rutas.
7. Tras editar contenido, **refrescar la página** del sitio: los shortcodes usan caché para cargar más rápido.

---

## 9. Requisitos Técnicos

| Requisito | Detalle |
|---|---|
| Plataforma | WordPress 5.8 o superior |
| PHP | 7.4 o superior |

---

## 10. En Resumen

**Tureo** convierte al WordPress en un mini-portal de turismo autogestionado:

- 🧳 Carga de paquetes **completa y guiada** (precios, fechas, galerías, fichas).
- ⚡ Operación diaria **rápida**: toggles de visibilidad, duplicado, filtros y exportación.
- 🎨 Exhibición **totalmente configurable** desde shortcodes, sin tocar código.
- 🔎 Catálogo público con **buscador y filtros** que mejora la experiencia del viajero.

*Documentación técnica complementaria: `SHORTCODES.md` y `readme.txt`.*
