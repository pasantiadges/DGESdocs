# Plan de evolución — Plugin DGES Documentos (v3)

**Estado:** propuesta para revisar. No se tocó código todavía.
**Fecha:** 24/10/2026
**Rama actual:** `feature/api-filtros-avanzados`
**Documento complementario:** [`API-CONTRACT.md`](API-CONTRACT.md) — propuesta concreta para el equipo de backend.

---

## Cambios de esta revisión (v3)

- **Filtros:** solo **barra de búsqueda + categoría**. Se quitan año, tipo de documento, vigencia y número como filtros.
- **Búsqueda:** reactiva con *debounce* de 400 ms, más Enter y lupa para disparar al instante.
- **Admin:** listado de documentos con botón **“Copiar shortcode”** para el equipo de comunicación.
- **Estado vacío:** mensaje claro + botón **“Limpiar filtros”**, distinto del estado de error.
- **Contrato de API simplificado:** `txtbusqueda`, `categoria`, `limit`, `offset`, `total`, `list_categorias.php` y `archivos[].url`.

El año, el tipo, la vigencia y el número se siguen **mostrando** como metadata en las cards y el detalle, pero no se filtran.

---

## Decisiones preliminares (24/10)

Estas seis decisiones son el rumbo acordado, pero **pueden cambiar**. Si cambian, se actualizan este plan y el contrato.

| # | Decisión | Detalle |
|---|---|---|
| D2 | URL de detalle | `/documentos/documento/{id}/` + respaldo `?doc={id}` que redirige 301 a la URL linda. Rewrite en Fase 2; flush al activar/cambiar la página; probar con permalinks y caché. |
| D3 | Anexos | `archivos[]` = archivos del mismo acto (principal, anexos, planillas, fe de erratas sin número propio). `documentos_relacionados[]` = otros actos independientes. |
| D6 | No vigentes | Se muestran con badge “No vigente”; no hay filtro dedicado. |
| D7 | Card única | Por defecto linkea al detalle. `link="file"` opcional descarga el archivo principal; `file="idarchivo"` permite elegir uno. |
| D9 | Página principal | Reemplaza el índice de categorías de la vieja web por buscador + categoría (select y chips opcionales). |
| D11 | Permisos del admin | Settings y caché: `manage_options`. Listado y “Copiar shortcode”: `edit_posts`. El menú padre queda visible para editores; Configuración solo para admins. |

> Si la vieja web sigue online, coordinar links/redirects desde sus categorías; si se retira, mapear esas URLs.

---

## Estado de implementación (25/10)

**Fases 1 y 2 implementadas y probadas en el WordPress local**, pendiente la API real en producción:

- [x] `Source` con HTTP, mapeo, tipos, número/año, archivos y relacionados.
- [x] `Query` con búsqueda AND, categoría, orden y paginación.
- [x] `Repository` con índice cacheado, lock, stale y cron; modo API preparado.
- [x] REST `/documents`, `/documents/{id}` y `/categories`; cards HTML server-rendered.
- [x] Shortcode SSR con buscador, categoría, estados y “Ver más”.
- [x] JS con debounce 400 ms, Enter/lupa, `AbortController`, URL state y append.
- [x] CSS sin duplicados, responsive y badges.
- [x] Admin con modo, base de archivos, cantidades y estado del índice.
- [x] Card individual `[dges_documento]` breve, con insignia, título, una línea de descripción y botón de acción.
- [x] Ruta de detalle `/documentos/documento/{id}/` con rewrite, `?doc=` 301, página autocreada y fallback con permalinks simples.
- [x] Detalle server-rendered: breadcrumb, metadata, archivos, relacionados y volver.
- [x] SEO del detalle: title, meta description, canonical, OG/Twitter, JSON-LD `DigitalDocument`, 404 real y `noindex`.
- [x] REST `/documents/{id}` con archivos y relacionados; las cards del listado ya linkean al detalle.
- [x] 143 tests con stubs (`tests/`).
- [x] Probado en el sitio local con WordPress en ejecución.
- [ ] Probar contra la API real en producción.
- [ ] “Copiar shortcode” en el admin (Fase 3).

Las cards del listado ya linkean al detalle. El detalle es donde se listan los archivos y los relacionados; la card breve no repite esa información. La API real todavía no manda `archivos[].url` en todos los casos, así que el detalle puede mostrar “sin enlace” hasta configurar la base pública o recibir la URL desde el back.

---

## 0. Visión en una página

El plugin es la **capa de presentación e integración** de los documentos de la DGES. La API es la fuente de verdad y, cuando esté completa, también el motor de búsqueda.

Dos controles:

1. **Barra de búsqueda** (`txtbusqueda`): título, descripción y número.
2. **Select de categoría** (`categoria`): “Todas” + las categorías que devuelve `list_categorias.php`.

Tres recorridos:

1. **Listado/buscador** → cards con “Ver documento”.
2. **Detalle** → `/documentos/documento/{id}/` con metadata, archivos (principal + anexos) y relacionados.
3. **Card única** → `[dges_documento id="116"]` para pegar en una noticia, portada o sidebar.

Principios:

- **API-first** si la API busca y pagina; **índice local** solo como transición.
- **No duplicar datos** ni bajar todo el dataset al navegador.
- **Server-rendered** para el detalle y la card única (SEO + sin JS).
- **No romper la página** si la API falla: cache/stale y estados claros.
- **Contrato explícito** con el back y tests de aceptación.

---

## 1. Evidencia (probado el 24/10/2026)

### 1.1 API

| Caso | URL | Resultado |
|---|---|---|
| Listado | `list_documentos.php` | `200`, `{"documentos":[...]}`, 5 documentos de prueba |
| Límite | `?limit=2` | Funciona: 2 documentos |
| Offset | `?limit=2&offset=2` | **Ignorado**: devuelve los primeros 2 |
| Página | `?limit=2&page=2` | **Ignorado** |
| Búsqueda | `?txtbusqueda=Resoluci` | **Ignorado**: devuelve todo |
| Categoría | `?categoria=1` | **Ignorado** |
| Detalle | `?iddocumento=1` | `200`, objeto con `archivos` y `documentos_relacionados` |
| Detalle inexistente | `?iddocumento=999` | `200` + texto plano, no 404 JSON |
| Categorías | `list_categorias.php` y variantes | `404` |
| Archivo | `…/bd_dges/archivos/2026/...` y variantes | `404` en todas las bases probadas |

El `.docx` del back confirma que el detalle y `limit` son el camino, y que la búsqueda server-side con `txtbusqueda`, `categoria` y `anio` está en evaluación. **Para esta etapa solo pedimos `txtbusqueda` y `categoria`.**

### 1.2 Modelo de la vieja web

La página actual [`/sitio/documentos/`](https://dges-cba.infd.edu.ar/sitio/documentos/) es un **índice de categorías** (~25), y cada categoría abre una tabla de documentos. Las filas tienen:

- Título y descripción.
- Uno o más archivos: principal + anexos + instructivos + planillas.
- A veces un documento relacionado (Fe de Erratas, otro acto que complementa).

Ejemplos: “Memorandum 005/21” tiene tres archivos; “Memorandum 001/22” tiene principal + instructivo. Eso valida que `archivos[]` debe ser una lista, no un solo PDF, y que hay que distinguir **anexo del mismo acto** de **documento relacionado**.

### 1.3 Bugs/deudas del plugin actual

| # | Hallazgo | Impacto |
|---|---|---|
| 1 | `numero` no se mapea | El buscador no encuentra por número |
| 2 | Destacados muestra los primeros N, no los `destacado` | Contenido incorrecto |
| 3 | `?refresh=1` es público | Cualquiera fuerza un fetch al origen |
| 4 | `per_page` sin tope | Un request puede bajar todo |
| 5 | Eventos globales (`document.querySelector`) | Con dos shortcodes, el segundo busca en el primero |
| 6 | URL de archivo mal armada | Links rotos; la base real no está confirmada |
| 7 | Sin orden estable | Paginación con duplicados/saltos |
| 8 | Cache sin lock ni stale | Cache stampede; si el origen falla, la sección queda vacía |
| 9 | Card duplicada PHP/JS | Se desincronizan |
| 10 | CSS duplicado | Reglas que se pisan |
| 11 | `class-dges-docs-rest.php` vacío | Capa REST sin separar |
| 12 | Admin desactualizado | El “Formato JSON Esperado” no refleja el detalle real |
| 13 | Sin tests | Cada cambio es a ciegas |

---

## 2. Alcance

### 2.1 Incluye

- Buscador por texto con debounce + Enter/lupa.
- Filtro por categoría.
- Estado vacío, error y “Limpiar filtros”.
- Detalle server-rendered con archivos y relacionados.
- Card única por shortcode.
- Admin con listado y “Copiar shortcode”.
- API-first con índice local de transición.
- Cache, 404, SEO, accesibilidad y tests.

### 2.2 No incluye (por ahora)

- Filtros por año, tipo, vigencia o número.
- Facets/conteos por año/tipo.
- Tabla propia o CPT (solo contingencia).
- Bloque Gutenberg (el shortcode copiado alcanza; se puede sumar después).
- Motor de búsqueda externo (Elastic, etc.).

---

## 3. Modelo de datos

### 3.1 Mapa viejo → API → plugin

| Concepto | Vieja web | API (hoy) | Plugin |
|---|---|---|---|
| ID | — | `id` | `id` string |
| Título | link | `nombre` | `title` |
| Descripción | texto tras “:” | `observacion` | `description` limpia |
| Número | en el título | `numero` | `number` (visible y buscable) |
| Año | en el título/fecha | `anio` | `year` (visible, no filtro) |
| Vigencia | — | `vigente` | `current` (badge) |
| Destacado | — | `destacado` | `featured` (badge/shortcode) |
| Categoría | sección | `categorias[]` | `categories[]` (filtro) |
| Tipo | inferido | no existe | `document_type` (visible, inferido) |
| Archivos | links de la fila | `archivos[]` (solo detalle) | `files[]` con URL, tipo, tamaño |
| Relacionados | links “Anexo”, “Fe de Erratas” | `documentos_relacionados[]` (solo detalle) | `related[]` con tipo |

### 3.2 Archivos vs relacionados

- `archivos[]` = todos los archivos del **mismo acto**: principal, anexos, planillas, instructivos, fe de erratas sin número propio.
- `documentos_relacionados[]` = **otros actos** con número/año propio que complementan, modifican, derogan o sustituyen.

La card del listado puede decir “3 archivos”; el detalle los lista con sus etiquetas.

### 3.3 Categorías

- Filtrar por `idcategoria`, no por nombre.
- Soportar múltiples categorías por documento; mostrar la primera como principal y todas como chips.
- Poblar el select desde `list_categorias.php`, no desde el listado.
- Los conteos son opcionales.

### 3.4 Búsqueda

- Campos: `nombre`, `observacion`, `numero`.
- Sin acentos, multi-palabra (AND), trim.
- Vacío = todos.
- El número se busca como texto (“116”, “116/2026”, “116-2026”).
- No se busca por categoría desde la barra: para eso está el select.

### 3.5 Normalización

- Limpiar `observacion`: entidades HTML, espacios, comas finales, prefijos “y :”.
- Unificar “Memorandum”/“Memorándum”, “Resolucion”/“Resolución”.
- Booleanos: aceptar `Si`/`No`, `1`/`0`, `true`/`false`.
- Relacionados: `iddocumento` → `id`, `destacada` → `destacado`.
- Validar URLs (`http`/`https`), tamaño y tipo de archivo.
- No inferir el año desde `carpeta` (es la fecha de subida).

---

## 4. Arquitectura

### 4.1 API-first con fallback de transición

```
UI (shortcodes / JS)
        │
        ▼
REST del plugin  ──►  Repository  ──►  Source  ──►  API DGES
                         │
                         ├─ Remote_Repository  (búsqueda/paginación en la API) ← final
                         └─ Index_Repository   (baja todo y filtra en PHP)     ← transición
```

- **`Source`**: HTTP, validación de JSON, normalización, detección de errores (incluido el texto plano del `iddocumento` inexistente).
- **`Repository`**: interfaz única (`query`, `find`, `featured`, `categories`).
- **`Remote_Repository`**: traduce los filtros a `txtbusqueda`, `categoria`, `limit`, `offset`; cachea por consulta; usa `total`.
- **`Index_Repository`**: baja el listado, normaliza y filtra/pagina en PHP. Es lo que permite trabajar **hoy**, antes de que el back implemente búsqueda.
- **`Query`**: filtros y orden para el modo índice (funciones puras, testeables).
- **`Rest`**: `GET /documents`, `GET /documents/{id}`.
- **`Router`**: rewrite + query var + página de detalle.
- **`Render`**: cards, detalle y shortcodes (una sola fuente de verdad).
- **`Settings`**: URL de la API, modo de búsqueda, página de detalle, cantidades, caché y estado.

**Modo de búsqueda:** `dges_documentos_search_mode` con `index` (default hoy), `api` (cuando el back lo confirme) y `auto` (prueba una vez al día). Recomendación: `index` ahora, `api` cuando pasen los tests del contrato.

### 4.2 Flujos

- **Listado:** JS o SSR → REST del plugin → Repository → API o índice → JSON/HTML.
- **Detalle:** página de WP → `Repository::find(id)` → API `?iddocumento=` → Render SSR.
- **Card única:** shortcode → `Repository::find(id)` → Render SSR; cache por id.
- **Destacados:** `Repository::featured(limit)` → API `?destacado=Si&limit=N` (o índice mientras tanto).
- **Relacionados:** vienen embebidos en el detalle; no hay N+1.

### 4.3 Cache

| Dato | TTL | Stale | Notas |
|---|---|---|---|
| Listado remoto | 5 min | 1 h | Clave por hash de `txtbusqueda` + `categoria` + página |
| Detalle | 1 h | 24 h | Clave por id |
| Índice completo | 1 h | 24 h | Lock + cron; nunca bloquea al visitante |
| Categorías | 1 h | 24 h | Se limpian con el botón de admin |

- Lock con `wp_cache_add`/`add_option` para evitar fetches simultáneos.
- Si la API falla y hay stale, se sirve stale y se loguea; si no hay nada, estado de error.
- `refresh` y “limpiar caché” solo para `manage_options`.

### 4.4 Lo que NO haría

- **No** espejar todos los documentos si la API ya busca y pagina.
- **No** convertir los documentos en un CPT.
- **No** crear una tabla propia salvo contingencia (API sin búsqueda y volumen > 2.000).
- **No** bajar el dataset al navegador ni filtrar en el front.
- **No** hardcodear la base de archivos.
- **No** agregar filtros que el cliente no pidió: año, tipo, vigencia y número quedan como metadata.

---

## 5. UX

### 5.1 Buscador

La página principal **reemplaza el índice de categorías** de la vieja web: no se muestran tablas de secciones, sino buscador + categoría. Se pueden sumar chips con las categorías más usadas (opcional; el select sigue estando para todas).

**Controles:**

- Input de búsqueda con placeholder “Buscar por título, descripción o número…”.
- Select de categoría: “Todas” + categorías.
- Chips opcionales de categorías frecuentes (si el cliente los quiere).
- Botón “Limpiar” (visible si hay texto o categoría).
- Contador de resultados.

**Disparo de búsqueda:**

- **Reactiva con debounce de 400 ms** mientras se escribe.
- **Enter** dispara inmediatamente (cancela el debounce pendiente).
- **Lupa** (botón) dispara inmediatamente.
- **Esc** limpia el texto (opcional, recomendado).
- Sin mínimo de caracteres: vacío = todos. Si el back necesita un mínimo, se acuerda en 2.
- Al cambiar de categoría, se recarga al instante (sin debounce).

**Detalles de implementación:**

- Cancelar la petición anterior con `AbortController`; ignorar respuestas viejas.
- No re-renderizar el input ni el select en cada respuesta (mantener foco y cursor).
- Spinner sutil dentro del input + `aria-busy="true"`; deshabilitar “Ver más” mientras carga.
- No repetir la petición si el texto/categoría no cambió.
- Estado en la URL: `?buscar=&categoria=&pagina=`. Leer al cargar; `replaceState` mientras se escribe y `pushState` al confirmar con Enter (o al cambiar categoría). Se usa `pagina` y no `page` para no chocar con la paginación nativa de WordPress.
- Primera página server-rendered para SEO y no-JS; JS mejora la interacción.
- En móvil, el input ocupa el ancho completo y el select va debajo.

**Card del listado:**

- Tipo (badge), título, descripción corta, categoría, año y estado (“No vigente” si aplica).
- Acción: **“Ver documento”** (detalle). Sin botón de descarga directa, porque el listado no trae `archivos`.
- Si el back agrega `url` al listado, se puede ofrecer descarga directa como extra.

### 5.2 Estados vacío / error / carga

| Situación | Mensaje | Acción |
|---|---|---|
| Sin resultados con búsqueda o categoría | “No encontramos documentos para ‘X’ en ‘Categoría’.” | **Limpiar filtros** |
| Sin resultados sin búsqueda ni categoría | “Todavía no hay documentos disponibles.” | Reintentar (si hubo error) |
| Categoría sin documentos | “No hay documentos en esta categoría.” | **Ver todas** |
| Error de API | “No pudimos cargar los documentos.” | **Reintentar** |
| Cargando | Spinner/skeleton, `aria-busy` | — |
| Detalle inexistente | “No encontramos ese documento.” | Volver al listado |

- **“Limpiar filtros”** resetea texto, categoría, página y URL; enfoca el input; anuncia “Filtros limpiados” con `aria-live`.
- El error nunca se muestra como “sin resultados”: son estados distintos.
- Para administradores, el error puede incluir el detalle técnico; para visitantes, no.
- El estado vacío no debe dejar la pantalla en blanco: siempre hay mensaje y acción.

### 5.3 Detalle

**Estado: implementado.** URL `/documentos/documento/{id}/` (base ajustable con el filtro `dges_docs_detail_base`), server-rendered.

- La página de detalle se crea automáticamente con `[dges_documento_detalle]`; se puede cambiar en Configuración. La página del listado se detecta por `[dges_documentos]` y se usa para breadcrumb/volver.
- El rewrite se registra en `init` con `add_rewrite_tag()` + `add_rewrite_rule()`; se hace flush al activar, al cambiar la página de detalle y al subir la versión de rewrite. Esto evita que el query var `dges_doc_id` se pierda.
- La página de detalle no muestra el título genérico del tema: se oculta con `dges-doc-detail` + CSS y con `astra_the_title_enabled`.
- Página de WP con `[dges_documento_detalle]`; rewrite + query var `dges_doc_id`.
- Respaldo: `?doc={id}` en la página de documentos redirige con **301** a la URL linda. Sirve si la caché de permalinks o un plugin de caché no toman el rewrite.
- Rewrite: `add_rewrite_rule()` en `init`; `flush_rewrite_rules()` al activar y al cambiar la página de detalle. Probar con permalinks “simple”, “entrada” y con el plugin de caché activo; evitar loops de redirección.
- Si no hay página de detalle configurada, el plugin la crea automáticamente. Si no puede crearla, el detalle no se enlaza y queda el aviso en Configuración.
- El redirect de `?doc=` se engancha en `template_redirect` (prioridad temprana) antes de `redirect_canonical`, para que WordPress no lo descarte; validar con `absint` y 404 si no existe.
- Contenido:
  - Breadcrumb “Documentos / Resolución 116/2026”.
  - Título y metadata: tipo, número/año, categoría(s), vigencia (badge **“No vigente”** si aplica), destacado.
  - Descripción.
  - **Archivos**: principal destacado + lista de anexos con `nombre`/`descripcion` como etiqueta, `tipo` y `tamanio`; botón de descarga por archivo. Todos son del mismo acto.
  - **Relacionados**: cards con el tipo de relación (“Complementa”, “Modifica”, “Deroga”), título, año y vigencia; link al detalle de cada uno. Solo otros actos, no anexos.
  - Volver al listado conservando la búsqueda.
- SEO: title, meta description, canonical propio, OG/Twitter, JSON-LD `DigitalDocument`; 404 real y `noindex` si no existe.
- Si no hay archivos: “Sin archivo disponible” y, si existe, link a la vieja web.

### 5.4 Card única

- `[dges_documento id="116"]`, server-rendered.
- Diseño breve: insignia de tipo (o categoría si el tipo es genérico), título en negrita, **una línea** de descripción, pie minimalista y botón de acción. Sin bloque de archivos separado.
- Atributos: `id`, `variant` (`default|compact|horizontal`), `show_description`, `show_meta`, `link` (`detail|file`), `file` (`idarchivo`), `class`.
- Solo se busca por `id`; el admin muestra el ID y copia el shortcode, así no hay que adivinarlo.
- La metadata del pie solo muestra año/número si **no** están ya en el título; evita repetir “Año 2026 · N° 045” cuando el título ya los tiene.
- `link="detail"` (default): botón **“Ver documento”** si hay URL de detalle; si no, cae al archivo principal y muestra **“Descargar PDF”**.
- `link="file"`: botón **“Descargar PDF”** (o el tipo real) si hay URL de archivo; si no, cae al detalle.
- Sin detalle ni archivo: botón deshabilitado **“Sin enlace disponible”**.
- Muestra badge **“No vigente”** si aplica; no muestra el badge de destacado en la card breve.
- Si el id no existe: no rompe la página; en preview de admin, aviso discreto.
- Casos de uso: noticia, portada, sidebar, bloque de “documento relacionado”.

### 5.5 Admin

**Menú y permisos:**

- Menú padre “Documentos DGES” con capability `edit_posts` para que el equipo de comunicación lo vea.
- Submenú **“Documentos”** (listado + copiar shortcode) con `edit_posts`.
- Submenú **“Configuración”** con `manage_options`; no visible para editores.
- La URL de la API, el modo de búsqueda y el vaciado de caché quedan solo en Configuración.

**Listado de documentos** (submenú “Documentos”):

- Buscador + select de categoría (reusa el Repository).
- Tabla: ID, título, categoría, año, estado, acciones.
- Acción **“Copiar shortcode”** por fila.
- También mostrar el shortcode en texto (`[dges_documento id="116"]`) por si el portapapeles falla.
- Paginación (20 por página).
- `navigator.clipboard.writeText` + fallback con `textarea` y `execCommand('copy')`.
- Toast/aviso “Shortcode copiado”.
- Permisos: `edit_posts` para ver/copiar; `manage_options` para Configuración. Filtro opcional `dges_docs_admin_capability` si quieren moverlo.
- Si la API está caída, usar el índice/cache y mostrar un aviso.

**Configuración:**

- URL de la API.
- Modo de búsqueda (`index`/`api`/`auto`).
- Página de detalle.
- Documentos por página.
- Límite de relacionados.
- Botón “Limpiar caché” + estado (último sync, error, cantidad).

### 5.6 Accesibilidad y responsive

- `aria-live` para el contador y el estado.
- Foco visible; al limpiar, foco al input; al abrir el detalle, foco al título.
- Navegación por teclado en cards y select.
- Mobile: una columna, filtros apilados, botones grandes.
- Respetar `prefers-reduced-motion` si hay animaciones.

---

## 6. Contrato con el back

El detalle completo está en [`API-CONTRACT.md`](API-CONTRACT.md). Resumen:

### P0 — bloqueantes

1. `archivos[].url` absoluta o base documentada.
2. `offset` + `total` en el listado.
3. `txtbusqueda` y `categoria` funcionando.
4. 404 JSON para `iddocumento` inexistente.

### P1 — necesario para buena UX

5. `list_categorias.php` con `idcategoria` y `nombre` (y `cantidad` si es barato).
6. Consistencia de claves (`id`, `destacado`) y booleanos.
7. Orden estable (`anio_desc` por defecto), si es posible.

### P2 — mejoras futuras

8. `destacado` como parámetro (para el shortcode de destacados).
9. `tipo_documento` como campo (o confirmar que lo inferimos).
10. `fecha` para ordenar por fecha real.
11. Relacionados con `categorias` y, si es barato, `archivos`.
12. Batch `?iddocumentos=2,3,4`.
13. `ETag`/`Last-Modified`.

**No pedimos** `anio` como filtro ni `list_filtros.php`: no están en el alcance.

---

## 7. Plan por fases

Estimación gruesa para 1 dev full-time; no incluye tiempos de espera del back.

### Fase 0 — Acordar contrato y mock (1–2 días)

- Enviar [`API-CONTRACT.md`](API-CONTRACT.md) al back.
- Cerrar P0/P1; actualizar el fixture `assets/documentos.json` con el contrato final.
- Confirmar las decisiones preliminares (URL, anexos, no vigentes, card, página principal, permisos) y si la vieja web se retira o convive.
- Definir el modo de búsqueda inicial (`index`) y el flag para pasar a `api`.
- Auditar la vieja web: cantidad por categoría, archivos múltiples, enlaces que son páginas y no archivos.

**Salida:** contrato aprobado, fixture actualizado, lista de faltantes de migración.

### Fase 1 — Adaptador, caché, buscador y categoría (4–6 días)

1. Separar `Source`, `Repository`, `Query`, `Rest`; dejar `Plugin` como wiring.
2. `Index_Repository`: bajar el listado, normalizar y filtrar; cache con lock/stale; cron.
3. `Remote_Repository`: preparado para `txtbusqueda`, `categoria`, `limit`, `offset`, `total`; se activa cuando el back lo confirme.
4. Mapear `numero`, `anio`, `vigente`, `destacado`, `categorias[]`, `tipo_documento` (inferido), `archivos[]` y `relacionados[]`.
5. Normalizar relacionados (`iddocumento`/`id`, `destacada`/`destacado`, `tipo`).
6. Listado: búsqueda por tokens AND (título, descripción, número), filtro de categoría, orden estable, `per_page` clamp.
7. Categorías: `list_categorias.php` con cache y fallback a las categorías del índice.
8. Destacados: filtrar `destacado`, ordenar, limitar.
9. Front: debounce 400 ms, Enter/lupa, `AbortController`, estado en URL, no re-renderizar el input, spinner, foco.
10. Estados: vacío con “Limpiar filtros”, categoría sin docs, error con “Reintentar”, carga.
11. Seguridad: validar URLs, escapar, sacar nonce del GET público, rate limit.
12. Tests de normalización, búsqueda, categoría, paginación y 404.

**Aceptación:** con la API actual (sin búsqueda) el listado funciona por índice; con la API caída y cache tibio sigue andando; la búsqueda reacciona con debounce y Enter; el filtro de categoría funciona; el estado vacío muestra “Limpiar filtros”; ningún anónimo puede forzar refresh.

### Fase 2 — Detalle (3–4 días)

1. `GET /documents/{id}` en el REST del plugin + 404.
2. Setting “Página de detalle” + rewrite + query var + flush; fallback `?doc={id}` con **301**; si no hay página configurada, aviso en admin y fallback sin rewrite; probar permalinks “simple”/“entrada” y con caché.
3. `[dges_documento_detalle]`: breadcrumb, título, metadata, descripción, archivos, relacionados, volver.
4. Archivos: principal + anexos, etiquetas, tamaños, botón por archivo; manejo de “sin archivo”.
5. Relacionados: cards con tipo de relación, link al detalle, cap y dedupe.
6. SEO: title, description, canonical, OG, JSON-LD, 404/noindex.
7. Cards del listado → detalle.

**Aceptación:** `/documentos/documento/116/` anda sin JS, se comparte con preview, un id inexistente da 404, `?doc=116` redirige 301 a la URL linda sin loop, los archivos descargan y los relacionados no se autoreferencian.

### Fase 3 — Card única y admin (3–4 días)

**Estado:** el shortcode `[dges_documento]` y su diseño breve ya están implementados. Falta el listado con “Copiar shortcode” en el admin.

1. `[dges_documento id="..."]` + atributos + variantes; `link="detail"` por defecto y `link="file"`/`file` opcionales.
2. Renderer único para listado, detalle y card.
3. Admin: listado con buscador, categoría, ID y “Copiar shortcode” + toast + fallback de portapapeles.
4. Menú padre `edit_posts`; “Documentos” `edit_posts`; “Configuración” `manage_options`.
5. CSS de variantes + docs.

**Aceptación:** se pega en una noticia/sidebar y funciona sin JS; si el doc no existe no rompe; el equipo de comunicación puede buscar, copiar y pegar el shortcode sin ayuda; si cambia el origen, la card se actualiza sola.

### Fase 4 — Pasar a API-first (1–2 días, cuando el back entregue)

1. Activar `Remote_Repository` con los parámetros nuevos.
2. Probar los casos de [`API-CONTRACT.md`](API-CONTRACT.md): acentos, multi-palabra, número, categoría, offset/total, 404.
3. Medir y comparar con el índice; si anda, dejar el índice como fallback apagado.
4. Si el back no entrega, mantener el índice y evaluar contingencia.

**Aceptación:** el listado no baja todo el dataset; `total` y `offset` funcionan; la búsqueda devuelve los mismos resultados que el índice en los casos de prueba.

### Fase 5 — QA, migración y documentación (2 días)

- Matriz de navegadores/mobile, teclado, lector, sin JS.
- API lenta/caída, cache frío/tibio, 500 docs.
- Dos shortcodes en la misma página.
- Probar redirects de `?doc=` y, si la vieja web se retira, mapear sus URLs de categorías.
- Auditar la migración desde la vieja web (documentos faltantes, archivos, categorías, duplicados).
- Actualizar README y ayuda del admin.
- Revisar ramas: `main`/`develop`/features; el branch `fix/descripciones-y-orden` tiene lógica útil de número/año, pero está hecho para el scraper viejo: cherry-pick selectivo, no merge.

### Contingencia — tabla propia (3–5 días, solo si hace falta)

Si el back no implementa búsqueda/paginación y el volumen supera ~2.000 documentos o el índice tarda > 500 ms:

- Tabla `wp_dges_documents` con índices (`year`, `document_type`, `featured`, `current`, `number`).
- Sync upsert/delete; full e incremental si hay `fecha_modificacion`.
- Query SQL; swap del repository sin tocar REST/JS.
- WP-CLI `wp dges-docs sync|status`.

No es el plan principal; es la red de seguridad.

---

## 8. Decisiones

| # | Decisión | Estado | Detalle |
|---|---|---|---|
| D1 | ¿API-first o espejo local? | Recomendada | API-first; índice solo como transición/fallback |
| D2 | URL del detalle | **Decidida (preliminar)** | `/documentos/documento/{id}/`; fallback `?doc={id}` con 301 |
| D3 | ¿Anexos como archivos o relacionados? | **Decidida (preliminar)** | Archivos del mismo acto; relacionados solo otros actos |
| D4 | Categorías | Abierta (back) | `list_categorias.php` + filtro por `idcategoria` |
| D5 | URL de archivos | Abierta (back) | `archivos[].url` absoluta o base documentada |
| D6 | No vigentes | **Decidida (preliminar)** | Badge “No vigente”; sin filtro |
| D7 | Card única | **Decidida (preliminar)** | Link al detalle por defecto; `link="file"`/`file` opcionales |
| D8 | Relacionados | Pendiente | 6 cards, con tipo de relación; confirmar cantidad |
| D9 | Página principal | **Decidida (preliminar)** | Reemplaza el índice de categorías por buscador + categoría |
| D10 | Disparo de búsqueda | Recomendada | Debounce 400 ms + Enter/lupa; confirmar con el cliente |
| D11 | Permisos del admin | **Decidida (preliminar)** | `edit_posts` para listar/copiar; `manage_options` para settings |
| D12 | SEO | Pendiente | Detalle indexable; listado filtrado `noindex` |
| D13 | Textos del estado vacío | Recomendada | “No encontramos documentos…” + “Limpiar filtros” |

---

## 9. Refactors y deuda técnica

| Prioridad | Cambio | Beneficio |
|---|---|---|
| Alta | Separar `Source`/`Repository`/`Query`/`Rest`; llenar o eliminar `class-dges-docs-rest.php` | Capas claras |
| Alta | `Repository` intercambiable (API/índice) | Escala sin reescribir |
| Alta | Mapear `numero`, `vigente`, `categorias`, `archivos`, `relacionados` | Funciones prometidas |
| Alta | Usar `archivos[].url`; nunca concatenar a ciegas | Descargas funcionando |
| Alta | Detectar texto plano/404 del detalle | 404 correcto |
| Alta | Normalizar `iddocumento`/`id`, `destacada`/`destacado` | Datos consistentes |
| Alta | Cache con lock + stale; `refresh` admin | No se cae si el origen falla |
| Alta | `events.js` scopeado a `root` + `AbortController` | Múltiples instancias y sin carreras |
| Alta | `per_page` clamp | Seguridad |
| Alta | Orden estable | Paginación correcta |
| Alta | Estado vacío + “Limpiar filtros” | UX |
| Media | Unificar card PHP/JS | Evita drift |
| Media | Deduplicar CSS | Mantenimiento |
| Media | Estado en URL | Compartir/back |
| Media | Admin: listado, copiar shortcode, estado, modo de búsqueda | Operación |
| Media | Menú padre `edit_posts` + Configuración `manage_options` | Permisos mixtos |
| Media | Tests de normalización/filtros/estados | Confianza |
| Baja | Bloque Gutenberg para card | UX editor |
| Baja | JSON-LD, resaltado de búsqueda, paginación numerada | SEO/UX |
| Baja | `uninstall.php` | Limpieza |

---

## 10. QA

### Buscador y categoría

- [ ] Debounce: escribir rápido no dispara una petición por tecla.
- [ ] Enter dispara al instante y no duplica la petición.
- [ ] Lupa dispara al instante.
- [ ] Vaciar el input vuelve a “todos”.
- [ ] Búsqueda con acentos y sin acentos.
- [ ] Multi-palabra (“resolucion 116”).
- [ ] Búsqueda por número (“116”, “116/2026”).
- [ ] Categoría “Todas” y una categoría.
- [ ] Combinación búsqueda + categoría.
- [ ] Cambiar categoría no dispara debounce.
- [ ] URL compartida reproduce el estado.
- [ ] Atrás/adelante del navegador.
- [ ] Sin resultados → estado vacío + “Limpiar filtros”.
- [ ] “Limpiar filtros” resetea todo y enfoca el input.
- [ ] Error de API → “Reintentar”, no “sin resultados”.
- [ ] Categoría sin docs → “Ver todas”.

### Detalle y card

- [ ] Detalle ok, 404, sin archivos, sin relacionados, relacionados rotos.
- [ ] URL linda `/documentos/documento/{id}/` con permalinks “simple” y “entrada”.
- [ ] `?doc={id}` redirige 301 a la URL linda, sin loop, con plugins de caché activos.
- [ ] `?doc=abc` o `?doc=999` no genera redirect abierto: da 404 o limpia el parámetro.
- [ ] Canonical propio del detalle; el `?doc=` no genera contenido duplicado.
- [ ] Archivos principal + anexos; etiquetas `nombre`/`descripcion`; tamaños; descargas.
- [ ] Badge “No vigente” visible en card y detalle; no hay filtro de vigencia.
- [ ] Card única ok, id inexistente, variantes, en noticia/sidebar.
- [ ] `link="file"` descarga el archivo principal; `file="45"` descarga ese archivo; sin archivo cae al detalle.
- [ ] Dos shortcodes en la misma página.
- [ ] Sin JS: primera página y detalle.
- [ ] La página principal reemplaza el índice de categorías; el select (y chips opcionales) funciona.

### Admin

- [ ] Listado con buscador y categoría.
- [ ] “Copiar shortcode” con Clipboard API y fallback.
- [ ] Toast/aviso “Shortcode copiado”.
- [ ] Permisos: el editor ve “Documentos” y puede copiar; **no** ve “Configuración”; el admin ve ambos.
- [ ] Menú padre visible para editores (`edit_posts`), sin exponer la URL de la API ni el vaciado de caché.
- [ ] API caída: usa cache/índice y avisa.

### Performance, seguridad y migración

- [ ] 500 docs en caliente < 300 ms.
- [ ] Sin N+1 en detalle/relacionados.
- [ ] `per_page` grande, `refresh` anónimo, URLs `javascript:`.
- [ ] Mobile, teclado, foco, `aria-live`.
- [ ] Cantidad por categoría vs vieja web; archivos múltiples; páginas que no son archivos.

---

## 11. Riesgos

- **El back no implementa búsqueda/paginación**: el plugin queda en modo índice; a 500 docs funciona, a 5.000 hay que pasar a tabla. Mitigación: contrato P0 y contingencia.
- **URL de archivos sin confirmar**: sin `url` no hay descargas. Mitigación: bloquear la Fase 2 hasta tenerla; fallback temporal a la vieja web si el cliente lo acepta.
- **Anexos vs relacionados mal modelados**: cards y conteos incorrectos. Mitigación: definición D3 antes de la Fase 2; si el back los manda al revés, se ajusta el mapeo y se avisa.
- **Búsqueda del back pobre** (solo `LIKE` exacto, sin acentos): la UX se siente rota. Mitigación: casos de prueba del contrato y, si hace falta, normalización de la consulta en el plugin.
- **Rewrite/permalinks/caché**: con permalinks simples o un plugin de caché agresivo, la URL linda puede fallar. Mitigación: `?doc={id}` con 301, `flush_rewrite_rules()` al activar/cambiar la página, canonical y tests con caché.
- **Vieja web**: si se retira, sus URLs de categorías quedan huérfanas. Mitigación: mapear redirects; si convive, no tocarla.
- **Card `link="file"`**: con varios archivos, descargar el primero puede ser ambiguo. Mitigación: default al detalle; `file="idarchivo"` para elegir; documentarlo.
- **Contenido incompleto**: la API de prueba tiene 5 docs; la vieja web tiene cientos. Mitigación: auditoría de migración en Fase 0/5.
- **Portapapeles**: navegadores sin permiso o contextos no seguros. Mitigación: fallback y shortcode visible.
- **Permisos/menú**: si el menú padre queda en `manage_options`, el equipo de comunicación no lo ve. Mitigación: padre `edit_posts`, Configuración `manage_options`.
- **Ramas**: `main`, `develop`, `dev`, features y un branch viejo con lógica útil. Mitigación: cherry-pick selectivo y flujo de PR.

---

## 12. Anexo: archivos afectados

**Nuevos:** `class-dges-docs-source.php`, `class-dges-docs-repository.php`, `class-dges-docs-query.php`, `class-dges-docs-router.php`, `assets/js/modules/url-state.js`, `tests/`.

**A modificar:** `class-dges-docs-api.php`, `class-dges-docs-plugin.php`, `class-dges-docs-rest.php`, `class-dges-docs-render.php`, `class-dges-docs-settings.php`, `docs.js`, `api.js`, `events.js`, `render.js`, `state.js`, `utils.js`, `docs.css`, `dges-docs.php`, `README.md`.

**Fixture:** `assets/documentos.json` (modo demo, actualizado al contrato).

**Documentos de este plan:** `docs/PLAN.md`, `docs/API-CONTRACT.md`.
