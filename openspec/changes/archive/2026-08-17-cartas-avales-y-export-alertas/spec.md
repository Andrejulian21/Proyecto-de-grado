# Especificación: Cartas de Aval y Export de Alertas

> Change: `cartas-avales-y-export-alertas` | Sprint 5 | 2 capacidades nuevas

## Decisiones

| # | Decisión | Resolución |
|---|----------|-----------|
| D1 | Cédula en Carta 2 | Placeholder literal `[Número de documento]`. No se agrega columna a usuarios. |
| D2 | Jurados faltantes en Carta 1 | Tabla de firmas sin nombres + notificación "Faltan asignaciones de jurados para presentación final". Carta se genera igual. |
| D3 | Habilitación botón cartas | `now() >= max(cierre_efectivo)` de entregas `desarrollo` del semestre. `cierre_efectivo = due_date + (hora_maxima ?? '23:59:59')`. |
| D4 | Nombres de archivo DOCX | `Aval Sustentacion Publica [Nombre Apellido].docx` y `Carta de Aval Entrega a Jurados [Nombre Apellido].docx` por estudiante. |
| D5 | Nombre del .xlsx | `Seguimiento del [Grupo] [YYYY-MM-DD HHmm].xlsx`. |

---

## Capacidad: director-cartas-aval

### RF-CA-01: Listar proyectos y habilitación

El sistema MUST listar proyectos del director en el semestre activo. El botón "Generar cartas" MUST permanecer deshabilitado hasta `now() >= max(cierre_efectivo)` de entregas `desarrollo` del semestre (D3). Mientras deshabilitado, MUST mostrar tooltip "Las cartas estarán disponibles cuando cierre la fase de desarrollo".

#### Escenario: Habilitación post-cierre
- GIVEN última entrega `desarrollo` con `due_date = ayer` y `hora_maxima = '18:00'`
- WHEN `now() > ayer 18:00`
- THEN botón habilitado

#### Escenario: Botón deshabilitado — semestre sin entregas desarrollo
- GIVEN semestre sin entregas en `desarrollo`
- WHEN director accede a la página
- THEN botón deshabilitado con tooltip "No hay entregas en la fase de desarrollo para este semestre"

#### Escenario: hora_maxima null usa fin del día
- GIVEN entrega con `due_date = ayer` y `hora_maxima = null`
- WHEN `now() > ayer 23:59:59`
- THEN botón habilitado

#### Escenario: Proyecto sin estudiantes
- GIVEN proyecto seleccionado sin estudiantes
- WHEN director solicita cartas
- THEN sistema muestra "Este proyecto no tiene estudiantes asignados"

### RF-CA-02: Carta 1 — Aval Sustentación Pública

Genera DOCX desde `storage/app/templates/aval-sustentacion.docx` con `${nombre_estudiante}`, `${codigo_estudiante}`, `${titulo_proyecto}`, tabla Jurado 1/2/3 + Director. Jurados de `evaluador_proyecto.fase = 'presentacion_final'`.

#### Escenario: Con jurados
- GIVEN 3 evaluadores en `presentacion_final`
- WHEN director genera Carta 1
- THEN DOCX contiene los 3 nombres en tabla de firmas

#### Escenario: Sin jurados
- GIVEN sin evaluadores en `presentacion_final`
- WHEN director genera Carta 1
- THEN DOCX con tabla vacía + notificación "Faltan asignaciones de jurados para presentación final"

#### Escenario: Template faltante
- GIVEN `aval-sustentacion.docx` no existe en storage
- WHEN se genera Carta 1
- THEN 500 con mensaje "La plantilla de carta no está disponible. Contacte al administrador."

### RF-CA-03: Carta 2 — Aval Entrega a Jurados

Genera DOCX desde `storage/app/templates/carta-jurados.docx` con `${nombre_estudiante}`, placeholder literal `[Número de documento]`, `${codigo_estudiante}` como "ID UNAB", `${titulo_proyecto}`, `${nombre_director}`.

#### Escenario: Placeholder cédula literal
- GIVEN estudiante código 12345
- WHEN director genera Carta 2
- THEN DOCX contiene `[Número de documento]` literal y `12345` como ID UNAB

#### Escenario: Template faltante
- GIVEN `carta-jurados.docx` no existe
- WHEN se genera Carta 2
- THEN 500 con mensaje claro en español

### RF-CA-04: Descarga por estudiante

El sistema MUST generar y descargar ambas cartas individualmente por estudiante con nombres de archivo según D4. Dos estudiantes producen 4 descargas.

#### Escenario: Dos estudiantes, 4 archivos
- GIVEN proyecto con 2 estudiantes
- WHEN director genera cartas
- THEN descargan 4 DOCX con nombres `Aval Sustentacion Publica [Nombre1 Apellido1].docx`, `Carta de Aval Entrega a Jurados [Nombre1 Apellido1].docx`, y análogos para estudiante 2

---

## Capacidad: coordinator-export-seguimiento

### RF-EX-01: Export .xlsx de tabla de seguimiento

`GET /api/admin/seguimiento/semestre/{id}/export` MUST devolver `.xlsx` con columnas: estudiante, proyecto, director, estado por fase/entrega (Entregado/Pendiente/No entregó), bitácoras, observaciones. Nombre según D5.

#### Escenario: Export con datos
- GIVEN semestre con 5 proyectos
- WHEN coordinador hace click en "Exportar"
- THEN descarga `Seguimiento del [Grupo] [timestamp].xlsx` con 5 filas y todas las columnas

#### Escenario: Semestre sin datos
- GIVEN semestre sin proyectos
- WHEN se solicita export
- THEN .xlsx con headers pero 0 filas

#### Escenario: Librería no instalada
- GIVEN PhpSpreadsheet ausente
- WHEN se solicita export
- THEN 500 "Error al generar el archivo Excel. Verifique que la librería esté instalada."
