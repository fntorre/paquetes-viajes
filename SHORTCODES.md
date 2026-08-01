# Travel Packages - Shortcodes Reference v2.0.0

## [tp_slider]

Muestra un slider carousel con los paquetes de viaje marcados para mostrar en el slider.

### Props (Atributos)

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `limit` | número | 5 | Cantidad máxima de paquetes a mostrar |
| `autoplay` | true/false | true | Activar reproducción automática |
| `interval` | número (ms) | 5000 | Milisegundos entre cambios de slide (ej: 5000 = 5 segundos) |
| `speed` | número (ms) | 300 | Velocidad de transición en milisegundos (ej: 300 = 0.3 segundos) |
| `pauseOnHover` | true/false | true | Pausar autoplay cuando el mouse entra al slider |
| `showArrows` | true/false | true | Mostrar botones de navegación (flecha anterior/siguiente) |
| `showDots` | true/false | true | Mostrar puntos de navegación debajo del slider |

### Ejemplos

#### Slider básico (defaults)
```
[tp_slider]
```

#### Slider rápido sin pausa
```
[tp_slider autoplay="true" interval="3000" speed="200" pauseOnHover="false"]
```

#### Slider lento y sin autoplay
```
[tp_slider autoplay="false" speed="500"]
```

#### Slider sin controles visuales
```
[tp_slider showArrows="false" showDots="false"]
```

#### Slider personalizado completo
```
[tp_slider limit="8" autoplay="true" interval="4000" speed="350" pauseOnHover="true" showArrows="true" showDots="true"]
```

---

## [tp_grid]

Muestra una grilla de paquetes con filtros y opciones de layout.

### Props (Atributos)

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `limit` | número | 6 | Cantidad máxima de paquetes a mostrar |
| `columns` | 1/2/3/4 | 3 | Número de columnas en la grilla |
| `destacados` | true/false | false | Mostrar solo paquetes marcados como destacados |
| `carousel` | true/false | false | Modo carrusel horizontal con swipe y flechas |
| `desktop` | número | columns | (carrusel) Paquetes visibles por página en desktop |
| `movile` | número | desktop | (carrusel) Paquetes visibles por página en mobile |
| `destino` | texto (slug) | "" | Filtrar por destino específico (ej: "europa") |
| `tipologia` | texto (slug) | "" | Filtrar por tipología específica (ej: "playa") |
| `orderby` | date/title | date | Ordenar por fecha o título |
| `order` | ASC/DESC | DESC | Orden ascendente o descendente |
| `ids` | lista de IDs | "" | Mostrar paquetes específicos (ej: "1,3,5") |

### Ejemplos

#### Grilla básica de 6 paquetes
```
[tp_grid]
```

#### Grilla de paquetes destacados
```
[tp_grid destacados="true" columns="3" limit="9"]
```

#### Grilla por destino
```
[tp_grid destino="europa" columns="2" limit="8"]
```

#### Grilla con paquetes específicos
```
[tp_grid ids="10,25,33,42" columns="2"]
```

#### Grilla por tipología (playa, montaña, etc.)
```
[tp_grid tipologia="playa" limit="12" columns="3"]
```

#### Carrusel de paquetes destacados
```
[tp_grid destacados="true" carousel="true" limit="8" columns="4"]
```

#### Carrusel general
```
[tp_grid carousel="true" limit="10" columns="3"]
```

#### Carrusel responsive (desktop vs mobile)
`desktop` indica cuántos paquetes se ven por página en desktop y `movile` cuántos en pantallas chicas (≤767px). En mobile los paquetes se desplazan horizontalmente con flechas/swipe.
```
[tp_grid destacados="true" carousel="true" limit="12" desktop="3" movile="1"]
```

---

## [tp_paquete]

Muestra un paquete individual incrustado en una página.

### Props (Atributos)

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `id` | número | - | ID del paquete a mostrar (REQUERIDO) |

### Ejemplos

#### Mostrar paquete específico
```
[tp_paquete id="123"]
```

---

## [tp_catalogo]

Catálogo completo con buscador y filtros. Soporta pre-filtrado vía URL.

### Props (Atributos)

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `limit` | número | -1 | Cantidad máxima de paquetes (-1 = todos) |
| `columns` | 1/2/3/4 | 3 | Número de columnas en la grilla |
| `show_search` | true/false | true | Mostrar/ocultar buscador por texto |
| `show_filters` | true/false | true | Mostrar/ocultar filtros (destino, tipología, orden) |
| `orderby` | date/title | date | Ordenar por fecha o título |
| `order` | ASC/DESC | DESC | Orden ascendente o descendente |

### Pre-filtrado por URL

Podés enlazar directamente a un catálogo pre-filtrado usando parámetros en la URL:

| Parámetro | Ejemplo | Descripción |
|-----------|---------|-------------|
| `?destino=` | `?destino=caribe` | Filtra por destino |
| `?tipologia=` | `?tipologia=playa` | Filtra por tipología |

### Ejemplos

```
[tp_catalogo]
```

```
[tp_catalogo columns="4" show_filters="false"]
```

Enlace a catálogo filtrado por tipología:
```
<a href="/paquetes/?tipologia=playa">Ver paquetes de playa</a>
```

## Tipologías (taxonomía)

La taxonomía `tipologia` permite agrupar paquetes por tipo (playa, montaña, aventura, nacionales, internacionales, etc.).

- Los términos se administran desde el menú **Paquetes de Viaje → Tipologías**
- Cada tipología tiene su propia página de archivo en `/tipologia/[slug]/`
- Se muestran como badges dorados en las tarjetas de paquetes
- Se pueden usar en shortcodes (`[tp_grid tipologia="playa"]`) o en URL (`?tipologia=playa`)

## Tips

- **Responsive**: Todos los shortcodes son completamente responsive
- **Caché**: Los shortcodes se cachean, actualiza la página si haces cambios
- **Múltiples sliders**: Puedes tener varios `[tp_slider]` en la misma página, cada uno con su propia configuración
- **Combinación de parámetros**: Puedes combinar cualquier parámetro según tus necesidades

## Notas Técnicas

- Los tiempos se especifican en **milisegundos** (1000ms = 1 segundo)
- Los valores de `true/false` se escriben en minúsculas
- Los slugs de destino/tipología son **case-sensitive** y deben coincidir con los creados en la administración
- El `id` en `[tp_paquete]` es el ID del post de WordPress, que ves en la URL del panel de administración
- Al activar el plugin por primera vez después de agregar la tipología, hay que ir a **Ajustes → Enlaces permanentes** y hacer clic en "Guardar cambios" para regenerar las reglas de reescritura
