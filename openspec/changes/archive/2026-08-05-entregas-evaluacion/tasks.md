# Tasks: Entregas y Evaluación de Evaluadores

> Change: `entregas-evaluacion` | Sprint 5 | Modo: hybrid | 14 RFs | 3 capacidades | 5 PRs encadenados
> Convención: strict TDD (RED → GREEN → REFACTOR), UI español / código inglés, commits convencionales, archivos < 500 líneas.

## Review Workload Forecast

| Campo | Valor |
|-------|-------|
| Líneas cambiadas estimadas (additions + deletions) | ~1,050 (120 + 350 + 180 + 80 + 320) |
| Riesgo presupuesto 400 líneas | High (total) — PR2 y PR5 son los slices más grandes |
| Chained PRs recommended | Yes |
| Suggested split | PR1 → PR2 → PR3 → PR4 → PR5 |
| Delivery strategy | force-chained (equivalente auto-chain; ver risks del envelope) |
| Chain strategy | feature-branch-chain |

```
Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High
```

**Feature Branch Chain**: tracker `feature/entregas-evaluacion` (draft, no-merge). PR1 base = tracker; PR2 base = branch PR1; PR3 base = branch PR2; PR4 base = branch PR3; PR5 base = branch PR4. Solo el tracker mergea a main. Si un child PR muestra cambios del PR anterior en su diff, retarget/rebase antes de review.

### Work Units Sugeridos

| Unidad | Meta | PR | Comando de test enfocado | Runtime harness | Límite de rollback |
|--------|------|----|--------------------------|-----------------|--------------------|
| Schema | Migraciones + modelos | PR1 | `vendor/bin/pest tests/Feature/database/EntregasEvaluacionSchemaTest.php` | `php artisan migrate` + `migrate:rollback --step=3` | 3 migraciones + 3 modelos (columnas nullable, sin pérdida) |
| Entregas backend | Actions + pesos + validaciones | PR2 | `vendor/bin/pest tests/Unit/Services/EntregaPesoServiceTest.php tests/Feature/Admin/EntregaPesoTest.php` | `POST /api/admin/entregas` con payload del contract (design) | Revert `app/Actions/Entrega/*`, `EntregaPesoService`, Requests, controller — no toca schema |
| Evaluador backend | 3 endpoints | PR3 | `vendor/bin/pest tests/Feature/Evaluador/EvaluadorAsignacionesTest.php` | Flujo evaluador con Sanctum en dev (`/evaluador/mis-asignaciones`) | Revert controller + rutas + `EvaluacionEvaluador` (tabla inofensiva) |
| Nota director | `director_grade` en revisar | PR4 | `vendor/bin/pest tests/Feature/Admin/DirectorGradeTest.php` | `PUT /api/admin/entregas/{id}/revisar` con `director_grade` | Revert cambios a `ReviewEntregaAction` + `revisar` |
| Frontend | Builder, form %, cards evaluador | PR5 | `npm run build` + `npx playwright test e2e/entregas-evaluacion.spec.ts` | `npm run dev` + flujo browser evaluador (login → evaluar → solo lectura) | Revert 7 archivos frontend |

---

## PR1 — Schema Foundation (tracker: feature/entregas-evaluacion)

Commits: `feat(entregas): add grade_percentage and director_grade columns`, `feat(evaluador): add evaluado flag`, `feat(evaluador): add evaluaciones_evaluador table`, tests en el mismo commit.

- [x] T-001 — RED: crear `tests/Feature/database/EntregasEvaluacionSchemaTest.php` (Schema::hasColumn grade_percentage/director_grade/evaluado; tabla evaluaciones_evaluador; FK UNIQUE rechaza duplicado RF-EVA-05; modelo `EvaluacionEvaluador`). Aceptación: falla antes de migrar.
- [x] T-002 — GREEN: crear migración `2026_08_04_000001_add_grade_and_director_grade_to_entregas.php` (decimal 5,2 y 4,2, nullable). Aceptación: `php artisan migrate` OK.
- [x] T-003 — GREEN: crear migración `2026_08_04_000002_add_evaluado_to_evaluador_proyecto.php` (boolean default false). Aceptación: columna default false en schema.
- [x] T-004 — GREEN: crear migración `2026_08_04_000003_create_evaluaciones_evaluador_table.php` (id, evaluador_proyecto_id FK UNIQUE, nota decimal 4,2, observaciones text, timestamps). Aceptación: UNIQUE a nivel DB.
- [x] T-005 — GREEN: modificar `app/Models/Entrega.php` — add `grade_percentage`, `director_grade` a fillable y casts. Aceptación: schema test verde.
- [x] T-006 — GREEN: modificar `app/Models/EvaluadorProyecto.php` — add `evaluado` a fillable y casts. Aceptación: schema test verde.
- [x] T-007 — GREEN: crear `app/Models/EvaluacionEvaluador.php` (fillable, `belongsTo` EvaluadorProyecto). Aceptación: T-001 verde. (deps: T-002..T-006)

## PR2 — Entregas Backend (base: branch PR1)

Commits: `feat(entregas): add EntregaPesoService with pair-sum validation`, `feat(entregas): extract store/update/review actions`, `feat(entregas): validate documento-proyecto slug and analizable_ia`.

- [x] T-008 — RED: crear `tests/Unit/Services/EntregaPesoServiceTest.php` — suma <100, =100, >100, NULL no participa, par incompleto, completitud exacta (RF-ENT-04). Aceptación: falla (clase no existe).
- [x] T-009 — GREEN: crear `app/Services/EntregaPesoService.php` — `validarSumaPar()`, `obtenerSumaPar()`, `fasesDelPar()` (throws ValidationException con mensajes de la spec). Aceptación: T-008 verde.
- [x] T-010 — RED: crear `tests/Feature/Admin/EntregaPesoTest.php` (store) — slug obligatorio 422 (RF-ENT-01), analizable_ia en secundario 422 (RF-ENT-02), % fuera de rango 422 (RF-ENT-03), suma >100 422 + completitud exacta (RF-ENT-04), % NULL no bloquea. Aceptación: falla.
- [x] T-011 — RED: mismo archivo, casos update — cambio a NULL no bloquea, suma >100 bloquea (D4: misma lógica Store/Update). Aceptación: falla.
- [x] T-012 — GREEN: crear `app/Actions/Entrega/StoreEntregaAction.php` y `UpdateEntregaAction.php` (lógica pura extraída). Aceptación: T-010/T-011 verdes.
- [x] T-013 — GREEN: crear `app/Actions/Entrega/ReviewEntregaAction.php`, `SolicitarEntregaAction.php`, `HabilitarEntregaAction.php`. Aceptación: suite existente verde.
- [x] T-014 — GREEN: modificar `app/Http/Requests/StoreEntregaRequest.php` — validar slug `documento-proyecto`, `analizable_ia` solo principal, `grade_percentage` 0-100, invocar `EntregaPesoService`. Aceptación: T-010 verde. (deps: T-009, T-012)
- [x] T-015 — GREEN: crear `app/Http/Requests/UpdateEntregaRequest.php` — mismas reglas que Store (D4). Aceptación: T-011 verde. **Nota: archivo no existe hoy; crear.**
- [x] T-016 — GREEN: refactor `app/Http/Controllers/Admin/EntregaController.php` → delega a Actions, queda < 400 líneas. Aceptación: `vendor/bin/pest tests/Feature/Admin/EntregaCrudTest.php tests/Feature/Admin/StoreEntregaTest.php` verde + archivo < 500 líneas. (deps: T-012..T-015)

## PR3 — Evaluador Backend (base: branch PR2)

Commits: `feat(evaluador): add mis-asignaciones, detalle and evaluar endpoints`.

- [x] T-017 — RED: crear `tests/Feature/Evaluador/EvaluadorAsignacionesTest.php` — aislamiento por evaluador (RF-EVA-01), detalle 200 con contexto completo + 403 ajeno (RF-EVA-02), evaluar 201 + 422 rango + 409 re-envío (RF-EVA-03), `director_grade` presente/null (RF-NOT-04). Aceptación: falla (404).
- [x] T-018 — GREEN: crear `app/Http/Controllers/Api/EvaluadorAsignacionesController.php` — index (eager-load proyecto.estudiantes/director), show (eager-load versiones latest + mapeo fase `Anteproyecto→anteproyecto`, `Final→presentacion_final`), store (chequeo previo → 409, create + `evaluado=true`). Aceptación: T-017 verde. (deps: T-005, T-007)
- [x] T-019 — GREEN: modificar `routes/api.php` — grupo `auth:sanctum` prefix `evaluador` con las 3 rutas. Aceptación: rutas registradas, T-017 verde. (deps: T-018)

## PR4 — Nota Director (base: branch PR3)

Commits: `feat(entregas): persist director_grade on review approval`.

- [x] T-020 — RED: crear `tests/Feature/Admin/DirectorGradeTest.php` — edición permitida antes del cierre 200 (RF-NOT-03), rechazada si status terminal o due_date vencido 422, rango 422, campo visible/oculto según aprobación (RF-NOT-02). Aceptación: falla.
- [x] T-021 — GREEN: modificar `app/Actions/Entrega/ReviewEntregaAction.php` — acepta y persiste `director_grade` en Entrega si aprobada; valida editable (status ≠ terminal y due_date ≥ now). Aceptación: T-020 verde. (deps: T-013)
- [x] T-022 — GREEN: modificar `EntregaController@revisar` — pasar `director_grade` + responder 422 con mensaje de la spec si cerrada. Aceptación: T-020 verde, archivo sigue < 500 líneas. (deps: T-021)

## PR5 — Frontend (base: branch PR4)

Commits: `feat(entregas): add analizable_ia toggle and pair-sum indicator`, `feat(evaluador): wire MisAsignaciones and EvaluadorCalificar to API`.

- [x] T-023 — modificar `resources/js/types/entregas.ts` — + `analizable_ia`, `grade_percentage`, `director_grade`, tipos `AsignacionEvaluador`/`Evaluacion`. Aceptación: `npx tsc --noEmit` limpio.
- [x] T-024 — modificar `resources/js/components/entregas/ArchivosRequeridosBuilder.tsx` — toggle "Analizable con IA" solo habilitado para slug `documento-proyecto` (RF-ENT-02). Aceptación: build OK, toggle deshabilitado en secundarios. (deps: T-023)
- [x] T-025 — modificar `resources/js/pages/coordinador/CoordinadorEntregas.tsx` — campo % nota + indicador suma del par: verde ≤100, rojo >100, advertencia "Par incompleto: falta asignar % en la(s) fase(s) X" (RF-ENT-05). Aceptación: build OK + 3 escenarios RF-ENT-05 verificados en browser. (deps: T-023)
- [x] T-026 — crear `resources/js/hooks/useEvaluadorAsignaciones.ts` — fetch `mis-asignaciones` + `detalle` con apiFetch. Aceptación: `npx tsc --noEmit` limpio. (deps: T-023)
- [x] T-027 — crear `resources/js/pages/evaluador/MisAsignaciones.tsx` — cards + toggle "Ver ya evaluados" que filtra `evaluado=true` y muestra botón "Ver" (RF-EVA-04). Aceptación: build OK. (deps: T-026)
- [x] T-028 — modificar `resources/js/pages/evaluador/EvaluadorCalificar.tsx` — conectar a API real: detalle con archivos + `director_grade`, envío de nota (201/409/422), modo solo lectura post-evaluación con inputs deshabilitados (RF-EVA-04). Aceptación: build OK. (deps: T-026, T-027)
- [x] T-029 — crear `e2e/entregas-evaluacion.spec.ts` — Playwright: login evaluador → ver cards → evaluar → detalle solo lectura. Aceptación: `npx playwright test e2e/entregas-evaluacion.spec.ts` verde. (deps: T-027, T-028)

---

---

## Bug-fix batch PR5 (follow-up apply — 2026-08-05, branch feature-entregas-evaluacion-pr5)

Commits: `2a2843b` (backend contrato canónico + alias slug), `528eccd` (frontend identidad id/slug + payload español).

- [x] B-001 — RED/GREEN backend: `tests/Feature/Admin/UpdateEntregaContratoTest.php` — update persiste campos canónicos en español (descripcion/fecha_limite/fecha_inicio/hora_inicio/criterios); `UpdateEntregaAction` lee claves españolas y mapea a columnas. Aceptación: 5 passed; suite 617 passed/8 skipped/0 failed.
- [x] B-002 — Backend alias: `Store/UpdateEntregaRequest::prepareForValidation()` normaliza `archivos_requeridos.*.slug` → `*.id` (persisted JSON shape como alias, RF-ENT-01). Aceptación: update/store con slug 200/201; RF-ENT-02 sigue rechazando secundario.
- [x] B-003 — Frontend: `ArchivosRequeridosBuilder` normaliza identidad `id ?? slug` (esPrincipal/eliminar/actualizar/label "ID:"); "Analizable con IA" alineado con "Versiones" en el mismo row. Aceptación: tsc 0 errores en archivos tocados + `npm run build` EXIT=0.
- [x] B-004 — Frontend: `CoordinadorEntregas` mapea persistido→builder al editar (payload con `id`) y manda claves españolas canónicas en el update (UpdateEntregaPayload alineado). Aceptación: Bug 4 resuelto (id presente) + Bug 3 edit persiste.

### Segunda ronda (revisión del usuario — crear/editar entregas, 2026-08-05)

Commits: `92c38ee` (grupo_id canónico), `68b36e7` (tests store ventana + cortes de subida), `5966e4b` (bloqueo subida post-fecha límite).

- [x] B-005 — Bug A (create no persiste fechas/horas): el form de crear YA está cableado (verificado en CoordinadorEntregas, payload manda `fecha_inicio`/`hora_inicio`/`hora_maxima`) y StoreEntregaAction los persiste — faltaba prueba. Test nuevo "persiste fecha de inicio, hora de inicio y hora máxima al crear" (201 + fila DB + response). Aceptación: passed.
- [x] B-006 — Bug E (grupo_id fantasma en la respuesta): accessor `getGrupoIdAttribute()` derivaba el grupo del primer proyecto pivot (`proyectos[].semester_id`), columna que `index()`/`update()` NO seleccionan → null con `semester_id=5`. Fix: `semester_id` canónico primero; proyectos solo fallback legacy si semester_id null. Test unitario con el shape exacto de index(). Aceptación: RED 1 failed → GREEN 10 passed; suite 624/8/0.
- [x] B-007 — Regla de negocio (corte de subida): `tests/Feature/Estudiante/VentanaEntregaTest.php` creado — bloqueo antes de fecha de inicio, mismo día antes de hora de inicio, tras fecha límite, el día límite tras hora máxima + control positivo; GET detalle sigue 200 tras la fecha límite (ver permitido). Aceptación: 5 passed.
- [x] B-008 — UX post-fecha límite: `DetalleEntregaEstudiante` deriva `isLocked`/`vencida` del payload parseado en local (sin el bug de zona horaria UTC que desbloqueaba horas antes) y deshabilita Subir/Reemplazar tras due_date+hora_maxima con aviso, manteniendo la vista. Aceptación: tsc 0 errores nuevos (6 pre-existentes intactos) + build EXIT=0.
- [x] B-009 — Verificación final: suite 624 passed/8 skipped/0 failed (1759 assertions); pint passed en 4 archivos backend tocados; build EXIT=0; tsc sin errores nuevos.

## Corrección de dominio D3-rev (2026-08-05, branch feature-entregas-evaluacion-pr5)

La nota del director es POR ENTREGA DEL ESTUDIANTE (por proyecto), NO de la entrega general (plantilla). `director_grade` se mueve a `entrega_proyecto`; `entregas.director_grade` queda legacy sin uso.

- [x] D-rev-01 — Enmendar spec: D3 + RF-NOT-01/02/03/04 → `director_grade` en `entrega_proyecto` (por proyecto), con nota de enmienda D3-rev. Aceptación: spec.md actualizado + topic Engram `sdd/entregas-evaluacion/spec`.
- [x] D-rev-02 — RED→GREEN schema: migración `2026_08_05_000001_add_director_grade_to_entrega_proyecto_table.php` (decimal 4,2 nullable tras `observaciones_director`, down dropColumn) + `EntregaProyecto` fillable + cast decimal:2 + 4 schema tests. Aceptación: RED 3 failed → GREEN.
- [x] D-rev-03 — RED→GREEN `ReviewEntregaAction`: al aprobar persiste `director_grade` en la `EntregaProyecto` de la versión revisada (`versiones_documento.entrega_proyecto_id`), `observaciones_director` en el pivot; deja de escribir `entregas.director_grade`. Mantiene RF-NOT-01 (0-5, 2 decimales), RF-NOT-03 (422 cerrada), director_notes en versión, consolidated_grade en entrega. Aceptación: DirectorGradeTest reescrito (10 it) + test independencia 2 proyectos.
- [x] D-rev-04 — RED→GREEN `EvaluadorAsignacionesController`: detalle y cards devuelven `director_grade` del `entrega_proyecto` del PROYECTO evaluado (helper `entregaProyectoDeAsignacion`). Aceptación: EvaluadorAsignacionesTest actualizado (17 it) + test "misma entrega general, notas distintas por proyecto".
- [x] D-rev-05 — Frontend: `RevisionEntregaDirector` muestra/edita la nota de la versión seleccionada (por proyecto); `MisAsignaciones` muestra `director_grade` del proyecto en cards evaluadas; `EvaluadorCalificar` etiqueta "(proyecto)"; `EntregaController@show` enriquece cada versión con `director_grade` de su pivot; tipos `AsignacionEvaluador.director_grade`. Aceptación: tsc 0 nuevos + build EXIT=0.
- [x] D-rev-06 — Verificación final: suite 632 passed/9 skipped/0 failed (1781 assertions, +8 tests vs baseline 624); pint passed en 8 archivos; build EXIT=0; tsc 65 pre-existentes sin nuevos.

## Criterios globales de aceptación (verificación final)
- [x] Total tests ≥ 520 (baseline 495 + ~25 nuevos) — `vendor/bin/pest` (632)
- [x] `vendor/bin/pint` limpio en archivos PHP nuevos/modificados
- [x] `npm run build` con 0 errores; `npx tsc --noEmit` sin errores NUEVOS (65 pre-existentes intactos)
- [ ] Migraciones rollback sin pérdida (`migrate:rollback --step=3`)
- [x] Ningún archivo nuevo/modificado supera 500 líneas
- [x] Mensajes de error en español (spec), código/commits en inglés
