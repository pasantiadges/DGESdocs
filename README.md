# DGESdocs

Plugin de WordPress para mostrar, buscar y filtrar los documentos institucionales de la DGES.

## Estado: Fase 2

Implementado en esta rama:

- Buscador por texto (título, descripción y número) con *debounce* de 400 ms, Enter y lupa.
- Filtro por categoría con datos de la API o del índice local.
- Primera página renderizada en PHP (funciona sin JavaScript).
- Paginación “Ver más” con `AbortController` y estado en la URL (`?buscar=&categoria=&pagina=`).
- Estados de carga, vacío (“Limpiar filtros”), categoría sin documentos y error con reintento.
- Índice local cacheado con lock, *stale-while-revalidate* y cron horario.
- Modo API preparado para cuando el backend implemente `txtbusqueda`, `categoria`, `offset` y `total`.
- Panel de administración con URL de la API, modo de búsqueda, base de archivos, cantidades y estado del índice.
- Cards con tipo de documento, año, número, categoría, “Destacado” y “No vigente”.
- Card individual `[dges_documento id="…"]` breve, con insignia, título, una línea de descripción y botón de acción.
- Ruta de detalle `/documentos/documento/{id}/` (y fallback `?doc={id}`) con archivos, relacionados, breadcrumb, SEO y 404.
- Página de detalle autocreada y configurable; página del listado para el breadcrumb/volver.

Pendiente (Fase 3):

- “Copiar shortcode” en el admin.

## Requisitos

- WordPress 6.0 o superior.
- PHP 7.4 o superior.
- Salida HTTP desde el servidor hacia la API de documentos de la DGES.

## Instalación para desarrollo

1. Cloná el repositorio dentro de `wp-content/plugins/` de tu entorno local de WordPress.
2. Activá el plugin desde el panel de administración.
3. Entrá a **Documentos DGES → Configuración** y revisá la URL del listado.
4. En **Documentos DGES → Índice y caché**, actualizá el índice para calentarlo.

## Configuración

- **URL del listado:** endpoint que devuelve `{ "documentos": [...] }`.
- **Modo de búsqueda:**
  - `Índice local` (default): baja el listado, lo cachea y filtra en WordPress. Es el modo recomendado mientras el backend no implemente búsqueda.
  - `API`: envía `txtbusqueda`, `categoria`, `limit` y `offset` al backend. Activalo solo cuando el back confirme esos parámetros y devuelva `total`.
- **Base pública de archivos:** opcional, solo si la API no manda `url` por archivo. Se concatena con `carpeta`/`nombre_archivo`.
- **Documentos por página:** máximo 50.
- **Cantidad de destacados:** máximo 20.
- **Página de detalle:** página que contiene `[dges_documento_detalle]`; se crea automáticamente si no existe.
- **Página del listado:** se usa para el breadcrumb y “Volver a documentos”; se detecta sola si contiene `[dges_documentos]`.

## Shortcodes

- `[dges_documentos]` — buscador con filtro por categoría y paginación.
- `[dges_documentos_destacados]` — tarjetas de documentos marcados como destacados.
- `[dges_documento id="116"]` — card breve de un documento. Atributos: `variant="compact|horizontal"`, `link="detail|file"`, `show_description="0"`, `show_meta="0"`, `file="idarchivo"`. La acción muestra “Ver documento” si hay detalle o “Descargar PDF” si hay archivo; sin enlace, queda deshabilitada.
- `[dges_documento_detalle]` — contenido de la página de detalle. La crea el plugin automáticamente; se puede cambiar en Configuración.

## Tests

Los tests no necesitan WordPress; usan stubs mínimos:

```bash
php tests/test-phase1.php
php tests/smoke-render.php
php tests/smoke-wiring.php
php tests/smoke-source.php
php tests/smoke-repository.php
```

También se puede correr `php -l` sobre los archivos PHP y `node --check` sobre los módulos JS.

## Documentación

- [`docs/PLAN.md`](docs/PLAN.md) — plan de evolución, decisiones y fases.
- [`docs/API-CONTRACT.md`](docs/API-CONTRACT.md) — contrato propuesto para el backend.
