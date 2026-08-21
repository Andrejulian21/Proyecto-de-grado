# Proposal: Gestión de documentos, versiones y observaciones de entregas

## Intent

Una entrega es una **actividad o requisito**. Cada entrega solicita **uno o varios documentos** identificados por título. Cada documento tiene su propio historial de versiones, fechas de entrega y observaciones del director. Solo **uno** de esos documentos puede estar configurado para análisis mediante IA.

## Scope

### In Scope

- Permitir N documentos solicitados por entrega, cada uno con identificador, título e indicador de IA.
- Unicidad: a lo sumo un documento `analizable_ia` por entrega (validación de UI y backend).
- Historial de versiones **por documento** (no global a la entrega), con fecha/hora y observación del director por versión.
- Área del estudiante con una sección independiente por documento (subir versión, ver historial y observación de esa versión).
- Revisión del director: ver documentos, navegar versiones, registrar observación de la versión seleccionada sin ambigüedad.
- Integrar el flujo de análisis preliminar IA solo en el documento marcado como analizable.
- Migración no destructiva: entregas y versiones existentes siguen consultables.
- Tests Pest de documentos múltiples, versiones, observaciones, IA única y autorización.

### Out of Scope

- Modificar specs existentes en `openspec/specs/` u otros changes.
- Nueva tabla paralela de documentos si el JSON `archivos_requeridos` ya cumple el contrato (se evoluciona, no se duplica).
- Nuevo proveedor IA o cambio de `AiGateway` / `AiProvider`.
- Nota académica del director, rúbrica del evaluador, pesos `grade_percentage`.
- Añadir `uploaded_by` (la estructura actual de `versiones_documento` no lo contempla).
- Mocks o datos estáticos en lugar de API/BD.
- Eliminar la columna JSON `archivos_requeridos` ni filas históricas.

## Capabilities

### New Capabilities

- `documentos-solicitados-entrega`: Una entrega declara N documentos solicitados (título + identidad + flag IA).
- `versiones-por-documento`: Cada versión pertenece a un documento solicitado (y al proyecto vía pivote), con fecha y observación propias.
- `observaciones-por-version`: El director registra/consulta una observación por versión; el estudiante ve la de esa versión (o estado vacío).
- `documento-unico-analizable-ia`: Como máximo un documento de la entrega es analizable con IA; el resto no expone ni ejecuta el flujo IA.

### Modified Capabilities

- `creacion-entregas`: deja de exigir el slug fijo `documento-proyecto` y deja de restringir IA a ese slug.
- `evaluador-inteligente-estudiante` / `analisis-ia-director`: el análisis solo aplica al documento marcado `analizable_ia`.

## Approach

Evolucionar el metamodelo ya persistido: `entregas.archivos_requeridos` (JSON de documentos) + `versiones_documento.archivo_requerido_id`. No crear una segunda arquitectura. Corregir la unicidad de versión (hoy `unique(entrega_id, version_number)` colisiona entre documentos). Relajar RF-ENT-01/02 y RF-SUP-01/02 en código (el spec histórico no se modifica). Reutilizar `DocumentEvaluationService`.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `database/migrations/` | New | Unique por documento+proyecto; backfill de documentos vacíos |
| `app/Models/Entrega.php` | Modified | Helpers de documentos / documento IA |
| `app/Http/Requests/StoreEntregaRequest.php` | Modified | N documentos; ≤1 IA; sin slug obligatorio |
| `app/Http/Requests/UpdateEntregaRequest.php` | Modified | Igual |
| `app/Actions/Entrega/ReviewEntregaAction.php` | Modified | Observación en cualquier versión del documento |
| `app/Services/Evaluation/DocumentEvaluationService.php` | Modified | Solo el documento `analizable_ia` |
| `app/Http/Controllers/Api/EntregaEstudianteController.php` | Modified | Límite de versiones por documento (ya existe) |
| `app/Http/Controllers/Api/EstudianteController.php` | Modified | Exponer documentos y documento IA |
| `resources/js/components/entregas/ArchivosRequeridosBuilder.tsx` | Modified | Títulos libres; un solo checkbox IA |
| Páginas estudiante/director/coordinador | Modified | Secciones por documento; observaciones; IA |
| `tests/` | New/Modified | Cobertura de los 13 criterios |

## Assumptions

1. “Documento solicitado” = ítem de `archivos_requeridos` (`slug`/`id` + `nombre` + `analizable_ia` + `versionamiento`).
2. “Versión” = fila de `versiones_documento` con `archivo_requerido_id` = identidad del documento.
3. La entrega sigue siendo plantilla por semestre; las versiones se acotan al proyecto vía `entrega_proyecto_id`.
4. `MAX_VERSIONS = 4` y `max:6` documentos son límites operativos vigentes (no el límite “uno” que se elimina).
5. Observación canónica = `versiones_documento.director_notes` (no un texto global de la entrega).

## Non-Goals

- No migraciones contra la BD local real (`php artisan migrate` prohibido fuera de tests).
- No modificar `openspec/specs/`.
- No duplicar lógica de versiones ni de análisis IA.
- No borrar `evaluation_metrics` ni `ai_document_evaluations`.

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Unique antiguo `(entrega_id, version_number)` rompe subidas multi-documento | High | Migración que lo reemplaza por unique por documento+pivote |
| Entregas sin `archivos_requeridos` dejan de listarse | Med | Backfill a un documento a partir del título |
| UI/tests siguen exigiendo `documento-proyecto` | High | Actualizar builder, requests y tests del change |
| Análisis IA de una versión de anexo | Med | Guardia en `DocumentEvaluationService` |

## Rollback Plan

Revertir el commit. La columna JSON no se elimina; las versiones conservan `archivo_requerido_id`. Restaurar unique anterior solo si hace falta compatibilidad estricta con el test unitario viejo.

## Success Criteria

- [ ] Una entrega se crea con varios documentos titulados.
- [ ] Cada documento tiene versiones independientes con fecha.
- [ ] Cada versión tiene (o no) su observación; no se reutiliza la de otra.
- [ ] Un segundo documento IA se rechaza con mensaje claro.
- [ ] Solo el documento IA muestra/ejecuta análisis; el resto no.
- [ ] Entregas existentes siguen accesibles tras la migración (tests `:memory:`).
- [ ] Suite Pest del change en verde; no se tocan specs existentes.
