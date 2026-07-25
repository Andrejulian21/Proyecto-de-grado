# Proposal: Integración módulo Evaluador

## Intent

Conectar el módulo del Evaluador Externo (dashboard + pantallas de evaluación/calificación) a la base de datos real, eliminando mocks hardcodeados y reutilizando la arquitectura existente (asignaciones `evaluador_proyecto`, API de calificaciones, patrón Director).

## Problem

1. `EvaluadorDashboard` muestra KPIs y cards desde `MOCK_EVALUATIONS`.
2. `EvaluarProyecto` y `EvaluadorCalificar` ignoran `:id` de la ruta, usan subtítulos/documentos mock y simulan submit con `setTimeout`.
3. El botón "Evaluar proyecto" no navega.
4. El sidebar de `EvaluadorExterno` apunta a `/evaluaciones` (solo `Director`) → acceso bloqueado.
5. No existe un endpoint `/api/evaluador/*` orientado al dashboard del evaluador externo (el listado del Director excluye proyectos propios y no expone director/estado de evaluación).

## Scope

### In Scope
- API `GET /api/evaluador/evaluaciones` y `GET /api/evaluador/kpis` (solo proyectos asignados al autenticado).
- Reutilizar `entrega-fase` (misma lógica de asignación que Director) vía ruta bajo `/api/evaluador/`.
- Hook `useEvaluadorEvaluaciones` + cableado de las 3 páginas del módulo.
- Navegación Dashboard → `/evaluaciones/:id` / `/evaluaciones/:id/calificar` con datos reales.
- Mensajes informativos cuando falte un dato: `"El/La <dato> no se ha podido encontrar."`
- Fix sidebar EvaluadorExterno (rutas del módulo).
- Seed de asignación de prueba para el evaluador Angel (dev).
- Tests Pest del API evaluador.

### Out of Scope
- Rediseño visual / nuevos wireframes.
- Módulos Coordinador, Director, Estudiante.
- Nueva tabla/migración de modalidad (campo inexistente → mensaje informativo).
- TOTP, IA, despliegue, exportación PDF.

## Capabilities

### New Capabilities
- `evaluador-dashboard-api`: listado de asignaciones + KPIs para el evaluador autenticado.
- `evaluador-ui`: dashboard y pantallas de evaluación/calificación con datos reales y missing-data messages.

### Modified Capabilities
- Sidebar navigation para rol `EvaluadorExterno` (rutas del módulo).

## Approach

1. Espejar el patrón de `DirectorController@evaluaciones` / `entregaFase` en un `EvaluadorController` delgado (sin filtro `director_id !=`), enriqueciendo con director, `assigned_at` y estado de evaluación.
2. Reutilizar `POST /api/evaluaciones` existente para enviar rúbrica/criterios.
3. Frontend: reemplazar mocks por `apiFetch` + hook estilo `useDirectorEvaluaciones`; conservar layout y copy en español.
4. Helper `datoNoEncontrado()` para campos ausentes (sin strings vacíos ni mocks).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Http/Controllers/Api/EvaluadorController.php` | New | evaluaciones, kpis, entregaFase |
| `routes/api.php` | Modified | prefijo `/api/evaluador` |
| `resources/js/hooks/useEvaluadorEvaluaciones.ts` | New | fetch listado |
| `EvaluadorDashboard.tsx` | Modified | datos reales + navegación |
| `EvaluarProyecto.tsx` | Modified | `:id`, API, missing messages |
| `EvaluadorCalificar.tsx` | Modified | `:id`, API, missing messages |
| `Sidebar.tsx` | Modified | nav EvaluadorExterno |
| `TestUsersSeeder.php` | Modified | asignación Angel → proyecto demo |
| `tests/Feature/Api/EvaluadorDashboardTest.php` | New | casos API |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Evaluador sin asignación ve vacío | Low | Empty state + seed de prueba |
| Entrega no aprobada para la fase | Med | Mensaje "El documento…" / error API 404 mapeado |
| Confusión EvaluarProyecto vs Calificar | Low | Ambas consumen misma API; botones del dashboard distinguen pendiente vs evaluado |

## Success Criteria

- [ ] Dashboard lista solo proyectos asignados al evaluador autenticado.
- [ ] Sin mocks en las 3 páginas del módulo.
- [ ] Navegación "Evaluar proyecto" → detalle con `:id` real.
- [ ] Datos faltantes muestran mensaje informativo (no vacío, no mock).
- [ ] Pest + `npm run build` OK.
- [ ] Change archivado tras verify.
