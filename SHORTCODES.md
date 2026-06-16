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
| `destino` | texto (slug) | "" | Filtrar por destino específico (ej: "europa") |
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

## Tips

- **Responsive**: Todos los shortcodes son completamente responsive
- **Caché**: Los shortcodes se cachean, actualiza la página si haces cambios
- **Múltiples sliders**: Puedes tener varios `[tp_slider]` en la misma página, cada uno con su propia configuración
- **Combinación de parámetros**: Puedes combinar cualquier parámetro según tus necesidades

## Notas Técnicas

- Los tiempos se especifican en **milisegundos** (1000ms = 1 segundo)
- Los valores de `true/false` se escriben en minúsculas
- Los slugs de destino son **case-sensitive** y deben coincidir con los creados en la administración
- El `id` en `[tp_paquete]` es el ID del post de WordPress, que ves en la URL del panel de administración
