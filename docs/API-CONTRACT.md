# Contrato de API propuesto — Documentos DGES (v2)

**Para:** equipo de backend de `bd.dges-cba.edu.ar`
**De:** equipo del plugin DGESdocs
**Fecha:** 24/10/2026
**Estado:** propuesta para acordar. Lo que ya existe se marca como “hoy”; lo que pedimos, como “propuesto”.

> **Alcance de esta versión:** el plugin solo necesita **búsqueda por texto** y **filtro por categoría**. No vamos a filtrar por año, tipo, vigencia ni número. El año, el tipo y el estado se muestran como metadata, pero no son filtros. Por eso no hace falta un endpoint de años/tipos ni un `list_filtros.php`.

---

## 0. Resumen

El plugin necesita tres cosas del backend:

1. **Buscar y paginar en el servidor** (`txtbusqueda`, `categoria`, `limit`, `offset`, `total`).
2. **Un endpoint de categorías** (`list_categorias.php`) para poblar el select sin bajar todo el listado.
3. **URL pública de cada archivo** (`archivos[].url`) o la base documentada. Hoy no la podemos resolver.

Además, hay cuatro detalles de contrato para unificar: respuesta de “no encontrado”, claves de `documentos_relacionados`, `destacada`/`destacado`, y el significado de `carpeta`.

---

## 1. Convenciones

- **Formato:** JSON UTF-8, `Content-Type: application/json; charset=utf-8`.
- **HTTP:** `200` OK, `400` parámetros inválidos, `404` documento inexistente, `500` error interno.
- **Booleanos:** ideal `true`/`false`; si se mantiene `"Si"`/`"No"`, documentarlo y ser consistente.
- **IDs:** strings estables y únicos. No reutilizar IDs de documentos borrados.
- **Paginación:** `limit` + `offset`, con `total` siempre en la respuesta.
- **Nombres:** `snake_case`; no mezclar `id`/`iddocumento` ni `destacado`/`destacada` en el mismo nivel.
- **Compatibilidad:** mantener `{"documentos": [...]}` en el listado y el objeto plano en el detalle si ya hay clientes; se pueden agregar campos, no quitarlos.

---

## 2. Estado actual (probado el 24/10/2026)

| Caso | URL probada | Resultado |
|---|---|---|
| Listado | `list_documentos.php` | `200`, `{"documentos":[...]}` con 5 documentos de prueba |
| Límite | `?limit=2` / `?limit=1` | Funciona: devuelve 2 / 1 |
| Offset | `?limit=2&offset=2` | **Ignorado**: devuelve los primeros 2 |
| Página | `?limit=2&page=2` / `?pagina=2` | **Ignorado** |
| Búsqueda | `?txtbusqueda=Resoluci` | **Ignorado**: devuelve todo |
| Categoría | `?categoria=1` / `?categorias=1` | **Ignorado** |
| Detalle | `?iddocumento=1` | `200`, objeto con `archivos` y `documentos_relacionados` |
| Detalle inexistente | `?iddocumento=999` | `200` con texto plano: `Exterior-CarrInst-List_Carreras: Codigo5: No se encontro el ID` |
| Categorías | `list_categorias.php`, `get_categorias.php`, `categorias.php` | `404` |
| Archivo | `…/bd_dges/archivos/2026/09/08/2026_09_08_12_36_49_485.pdf` y variantes | `404` en todas las bases probadas |

**Conclusión:** el detalle ya sirve; la búsqueda, la paginación real, el endpoint de categorías y la URL de archivos todavía no.

---

## 3. Endpoints propuestos

### 3.1 `GET list_documentos.php` — listado y búsqueda

**Parámetros:**

| Parámetro | Tipo | Default | Descripción |
|---|---|---|---|
| `txtbusqueda` | string | `""` | Busca en `nombre`, `observacion` y `numero`. Sin acentos, multi-palabra (AND), trim. Vacío = todos. |
| `categoria` | string | `""` | `idcategoria` exacto. Vacío = todas. Si un documento tiene varias categorías, alcanza con que coincida una. |
| `limit` | int | `12` | Tope recomendado: 100. Si se omite, mantener el comportamiento actual (todo) por compatibilidad, pero el plugin siempre lo manda. |
| `offset` | int | `0` | Desplazamiento para paginar. |

No pedimos `vigente`, `anio` ni `tipo_documento` como filtros: se usan solo para **mostrar** metadata en las cards y el detalle.

**Opcionales (no bloqueantes):**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `destacado` | bool | Solo para el shortcode `[dges_documentos_destacados]`. Si no existe, el plugin lo resuelve con su índice. |
| `orden` | string | `anio_desc` (default), `nombre_asc`. Si no existe, el plugin ordena lo que recibe (solo la página), así que es deseable. |

**Respuesta propuesta:**

```json
{
  "documentos": [
    {
      "id": "116",
      "nombre": "Resolución Ministerial 116-2026",
      "observacion": "Referido a la aprobación de propuestas de postítulos.",
      "numero": "116",
      "anio": "2026",
      "vigente": "Si",
      "destacado": "No",
      "categorias": [
        { "idcategoria": "1", "nombre": "Normativas DGES" }
      ],
      "archivos_count": 1,
      "url": "https://…/archivo.pdf"
    }
  ],
  "total": 412,
  "limit": 12,
  "offset": 0
}
```

- `total` es **imprescindible** para saber si hay más páginas.
- `archivos` no hace falta en el listado; con `archivos_count` y el detalle alcanza.
- `url` (opcional) es la del archivo principal, para que la card pueda ofrecer descarga directa sin pedir el detalle. Si no se agrega, la card linkea al detalle.
- `anio`, `vigente`, `destacado` y `numero` se usan para **mostrar** metadata en la card, no para filtrar.

**Casos de prueba que pedimos validar:**

| Consulta | Resultado esperado |
|---|---|
| `txtbusqueda=resolucion` | Incluye “Resolución Ministerial 116-2026” (sin acento) |
| `txtbusqueda=postitulos` | Incluye documentos con “postítulos” |
| `txtbusqueda=116` | Incluye el documento número 116 |
| `txtbusqueda=resolucion 116` | Intersección (AND), no la frase exacta |
| `categoria=1` | Solo documentos de esa categoría |
| `categoria=1&txtbusqueda=postitulos` | Intersección de categoría y texto |
| `limit=2&offset=2` | Devuelve los documentos 3 y 4, no los 2 primeros |
| `limit=2&offset=2` con `total` | `total` = cantidad filtrada total, no la página |
| Sin resultados | `{"documentos":[],"total":0}` con `200` |
| `limit=9999` | Se capea a 100 (o al máximo acordado) |
| `txtbusqueda=` y `categoria=` | Devuelve todos (paginados) |

---

### 3.2 `GET list_documentos.php?iddocumento={id}` — detalle

**Hoy devuelve:** objeto plano con `archivos` y `documentos_relacionados`. **Propuesto:** mantenerlo, agregar `url` por archivo y corregir el 404.

```json
{
  "id": "116",
  "nombre": "Resolución Ministerial 116-2026",
  "observacion": "Referido a la aprobación de propuestas de postítulos.",
  "numero": "116",
  "anio": "2026",
  "vigente": "Si",
  "destacado": "No",
  "categorias": [
    { "idcategoria": "1", "nombre": "Normativas DGES" }
  ],
  "archivos": [
    {
      "idarchivo": "45",
      "nombre": "RM 116-2026 POSTITULOS",
      "descripcion": "Propuestas de postítulos",
      "tipo": "PDF",
      "carpeta": "archivos/2026/10/23",
      "nombre_archivo": "2026_10_23_11_12_02_660.pdf",
      "tamanio": "19801962",
      "url": "https://…/2026_10_23_11_12_02_660.pdf"
    }
  ],
  "documentos_relacionados": [
    {
      "id": "4",
      "tipo": "Complementa",
      "nombre": "Resolución Ministerial 337-2025",
      "observacion": "Referido a la aprobación de propuestas de postítulos.",
      "numero": "337",
      "anio": "2025",
      "vigente": "Si",
      "destacado": "No",
      "categorias": []
    }
  ]
}
```

**Cambios pedidos:**

1. **`archivos[].url` absoluta** (o base documentada). Hoy `carpeta` + `nombre_archivo` no resuelve en ninguna base probada.
2. **404 real** para `iddocumento` inexistente, con JSON:
   ```json
   { "error": "Documento no encontrado", "codigo": "documento_no_encontrado" }
   ```
   No devolver texto plano con `200`.
3. **`documentos_relacionados` consistente:** `id` (no `iddocumento`), `destacado` (no `destacada`), y `categorias` si se puede. Si se mantiene `iddocumento`, documentarlo y el plugin lo normaliza.
4. **`tipo` de la relación:** confirmar el enum (`Complementa`, `Modifica`, `Deroga`, `Sustituye`, `Corrige`, `Relacionado`, etc.) y si es direccional o simétrica.
5. **Anexos vs relacionados:** definir si un “Anexo I” o una “Fe de Erratas” del mismo acto va en `archivos[]` (recomendado) o en `documentos_relacionados` (si tiene número propio).

**Modelo acordado (preliminar):**

- `archivos[]`: todos los archivos del **mismo acto** (principal, anexos, planillas, instructivos, fe de erratas sin número propio). `nombre` y `descripcion` son las etiquetas visibles de cada archivo.
- `documentos_relacionados[]`: **otros actos** con número/año propio que complementan, modifican, derogan o sustituyen.
- El plugin muestra los archivos como “principal + anexos” y los relacionados como cards aparte.

---

### 3.3 `GET list_categorias.php` — filtro de categorías

**Propuesto:**

```json
{
  "categorias": [
    { "idcategoria": "1", "nombre": "Normativas DGES", "cantidad": 42 },
    { "idcategoria": "2", "nombre": "Curriculares", "cantidad": 18 }
  ]
}
```

- `cantidad` es opcional; el select puede mostrar solo el nombre.
- No hace falta que la cantidad respete los filtros activos: es un listado para poblar el select.
- Si el back prefiere, puede devolver `categorias` dentro del listado; pero el endpoint dedicado es más simple y se cachea aparte.

**No pedimos** `list_filtros.php`, años ni tipos: no son filtros en esta etapa.

---

### 3.4 Archivos — URL pública

**Opción A (recomendada):** cada `archivo` trae `url` absoluta y estable.

**Opción B:** documentar la base pública y el significado de `carpeta`:

- Si `carpeta` = `archivos/2026/10/23` y la base es `https://bd.dges-cba.edu.ar/bd_dges/`, la URL sería `…/bd_dges/archivos/2026/10/23/{nombre_archivo}`. Probamos esa y variantes: todas `404`.
- Si los archivos están en otro host, carpeta o requieren un script de descarga (`?idarchivo=45`), necesitamos la URL exacta.

**Opción C:** endpoint de descarga `GET /archivos/{idarchivo}` que redirija al archivo. El plugin usaría ese endpoint y no necesitaría conocer la base.

---

## 4. Prioridades

### P0 — bloqueantes para el plugin

1. `archivos[].url` o base documentada.
2. `offset` + `total` en el listado.
3. `txtbusqueda` y `categoria` funcionando.
4. 404 JSON para `iddocumento` inexistente.

### P1 — necesario para una buena experiencia

5. `list_categorias.php` con `idcategoria` y `nombre` (y `cantidad` si es barato).
6. Consistencia de claves (`id`, `destacado`) y booleanos.
7. Orden estable (`anio_desc` por defecto), si es posible.

### P2 — mejoras futuras

8. `destacado` como parámetro del listado (para el shortcode de destacados).
9. `tipo_documento` como campo (o confirmar que lo inferimos del título).
10. `fecha` para ordenar por fecha real.
11. `documentos_relacionados` con `categorias` y, si es barato, `archivos`.
12. Endpoint batch `?iddocumentos=2,3,4` para cards múltiples.
13. `ETag`/`Last-Modified` para cachear sin bajar todo.

---

## 5. Preguntas abiertas

1. ¿La URL de producción es la misma que la de prueba? ¿Requiere auth, token o rate limit?
2. ¿El volumen real es 300–500? ¿Cuánto estiman en 3 años?
3. ¿Todos los documentos de la vieja web (`dges-cba.infd.edu.ar/sitio/documentos/`) se migran a esta API? ¿Qué pasa con los que son páginas y no archivos?
4. ¿Una categoría por documento o varias? ¿Los IDs de categoría son estables?
5. ¿`numero` es único por año? ¿Formato esperado (“116”, “116/2026”, “116-2026”)?
6. ¿`vigente` y `destacado` van a ser booleanos o seguimos con “Si”/“No”?
7. ¿`observacion` puede traer HTML o entidades (`&amp;`, `&nbsp;`)?
8. ¿Los archivos son públicos o requieren sesión?
9. ¿Hay documentos sin archivo? ¿Cómo se muestran?
10. ¿La búsqueda por `txtbusqueda` va a ser accent-insensitive y multi-palabra? Es lo que necesita la barra.
11. ¿La relación entre documentos es manual o automática? ¿Simétrica?
12. ¿Confirmás que los anexos van en `archivos[]` y que `documentos_relacionados[]` queda solo para actos independientes? Es la decisión preliminar del plugin.

---

## 6. Compatibilidad

- El plugin actual ya consume `{"documentos":[...]}` y el detalle plano; se puede mantener.
- Agregar campos no rompe; cambiar `id`/`destacado` en relacionados sí, pero el plugin lo normaliza mientras tanto.
- Si se agrega un prefijo de versión (`/v1/`), avisar para actualizar la opción de URL del plugin.
- Cualquier cambio de esquema debe reflejarse en este documento y en el fixture `assets/documentos.json`.
