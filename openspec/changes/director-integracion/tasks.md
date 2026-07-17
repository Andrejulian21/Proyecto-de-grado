# Tasks: Director Integración — Conexión Frontend ↔ Backend

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1500 (suma 4 PRs) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1: Navbar+Dashboard → PR 2: Supervisión+Bitácoras → PR 3: Evaluaciones → PR 4: Recursos |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Navbar fix + Dashboard con datos reales | PR 1 | `vendor/bin/pest tests/Feature/DirectorDashboardTest.php` | Login como director → navegar `/dashboard/director` | Revertir merge PR; Sidebar director vuelve a 7 items, Dashboard a mock |
| 2 | Supervisión + Bitácoras con datos reales y firma directa | PR 2 | `vendor/bin/pest tests/Feature/DirectorBitacoraTest.php` | Login como director → firmar bitácora desde Pendiente | Revertir merge PR; Bitácora no permite firma directa director |
| 3 | Evaluaciones 0-5 + vista evaluador director | PR 3 | `vendor/bin/pest tests/Feature/EvaluacionEscalaTest.php` | Login como director/evaluador → calificar entrega 0-5 | Revertir merge PR; EvaluacionesController vuelve a max 100 |
| 4 | Recursos fix descarga + rediseño UI | PR 4 | `npx playwright test recursos.spec.ts` | Navegar `/recursos` → click Descargar → archivo se descarga | Revertir merge PR; Botón Descargar vuelve a no tener onClick |

---

## Phase 1: Navbar + Dashboard (PR 1)

- [ ] T-001 **Sidebar.tsx — trim entries director + fix active state**. Crear `navConfig.Director` con 5 entradas (Panel, Supervisión, Evaluaciones, Anuncios, Recursos). Eliminar `/bitacoras` y `/bitacoras/proyectos`. Agregar `end` a `/dashboard/director`; agregar `isActive` custom para Supervisión (`pathname.startsWith('/supervision')`). *Archivos: `resources/js/components/layout/Sidebar.tsx`*
- [ ] T-002 **Crear DirectorController.php con 3 métodos**. Implementar `proyectos()` (scope director + semestres activos + estudiantes), `kpis()` (4 contadores agregados), `entregas()` (últimas 20 pendientes/enviadas con proyecto y estudiante). *Archivos: `app/Http/Controllers/Api/DirectorController.php`*
- [ ] T-003 **Agregar rutas `/api/director/*` en api.php**. Grupo `auth:sanctum` dentro del bloque existente. Tres rutas GET: proyectos, kpis, entregas. *Archivos: `routes/api.php`*
- [ ] T-004 **Crear hook `useDirectorProyectos`**. Patrón `useReducer` con FETCH_START/SUCCESS/ERROR. Llamada a `GET /api/director/proyectos`. *Archivos: `resources/js/hooks/useDirectorProyectos.ts`*
- [ ] T-005 **Crear hook `useDirectorKpis`**. Mismo patrón, llama `GET /api/director/kpis`. Retorna `{proyectos_supervisando, entregas_pendientes, alertas, aprobadas_mes}`. *Archivos: `resources/js/hooks/useDirectorKpis.ts`*
- [ ] T-006 **Crear hook `useDirectorEntregas`**. Mismo patrón, llama `GET /api/director/entregas`. *Archivos: `resources/js/hooks/useDirectorEntregas.ts`*
- [ ] T-007 **Refactorizar DirectorDashboard.tsx**. Eliminar `MOCK_KPIS`, `MOCK_PROGRESS`, `MOCK_DELIVERIES`, `PhaseStepper`, `ProgressCard`. Integrar 3 hooks en `Promise.all`. Renderizar: 4 `StatCard` con KPIs reales, carrusel horizontal de proyectos (max 5), `DataTable` con entregas. Estados loading (skeletons), error (banner + retry), empty. *Archivos: `resources/js/pages/dashboard/DirectorDashboard.tsx`*
- [ ] T-008 **Tests Pest para PR 1**. Crear `DirectorDashboardTest.php`. Casos: director con proyectos → 200, director sin proyectos → arrays vacíos/KPIs 0, semestre inactivo → filtrado, estudiante/evaluador → 403. *Archivos: `tests/Feature/DirectorDashboardTest.php`*

## Phase 2: Supervisión + Bitácoras (PR 2)

- [ ] T-009 **BitacoraController@firmar — firma directa director desde Pendiente**. Agregar rama: si `currentStatus === Pendiente` y `proyecto->director_id === $user->id`, cambiar a `Completada` sin pasar por FirmadaEstudiante. Enviar notificación a estudiantes. *Archivos: `app/Http/Controllers/Api/BitacoraController.php`*
- [ ] T-010 **Endpoint `GET /api/director/proyectos/{id}/bitacoras`**. En `DirectorController`, método `bitacoras()`. Validar que el user sea director del proyecto. Retornar bitácoras con signature_status, duration_hours, meeting_date. *Archivos: `app/Http/Controllers/Api/DirectorController.php`, `routes/api.php`*
- [ ] T-011 **Refactorizar SupervisionProyectoDirector.tsx**. Modo lista: cards grid con `useDirectorProyectos()`, cada card muestra code/title/estudiantes/fase. Click → navega a `/supervision/:id`. Modo detalle: fetch real `GET /api/admin/proyectos/:id`, entregas con `GET /api/admin/entregas?proyecto_id=:id`. Eliminar `MOCK_PROJECT`, `MOCK_DELIVERIES`, stepper. *Archivos: `resources/js/pages/director/SupervisionProyectoDirector.tsx`*
- [ ] T-012 **Refactorizar BitacorasDirector.tsx**. Crear hook `useBitacorasDirector()` o reusar `useDirectorBitacoras`. Fetch real vía `GET /api/director/proyectos/{id}/bitacoras`. Botón Firmar con confirm modal → `POST /api/bitacoras/{id}/firmar`. Eliminar `MOCK_BINNACLES`. Mantener search/filtros/DataTable. *Archivos: `resources/js/pages/director/BitacorasDirector.tsx`, `resources/js/hooks/useDirectorBitacoras.ts`*
- [ ] T-013 **Conectar DetalleEntregaDirector.tsx**. Fetch real `GET /api/admin/entregas/:id` + versiones `GET /api/entregas/:id/versiones`. Form revisión: nota 0.0–5.0, selector estado, campo notas. Submit → `PUT /api/admin/entregas/:id/revisar`. *Archivos: `resources/js/pages/director/DetalleEntregaDirector.tsx`*
- [ ] T-014 **Tests Pest para PR 2**. Casos: director firma bitácora Pendiente→Completada (200), director firma proyecto ajeno (403), bitácora ya Completada (422), lista bitácoras solo director autorizado, revisar entrega con nota 0.0-5.0. *Archivos: `tests/Feature/DirectorBitacoraTest.php`*

## Phase 3: Evaluaciones (PR 3)

- [x] T-015 **EvaluacionController@store — cambiar validación grade max 5**. `'grade' => 'nullable|numeric|min:0|max:5'`. *Archivos: `app/Http/Controllers/Api/EvaluacionController.php`*
- [x] T-016 **EntregaController@revisar — cambiar consolidated_grade max 5**. `'consolidated_grade' => 'nullable|numeric|min:0|max:5'`. *Archivos: `app/Http/Controllers/Admin/EntregaController.php`*
- [x] T-017 **EvaluacionController@consolidado — ajustar fórmula a escala 0-5**. Cambiar a `round(($totalWeighted / $totalPercentage) * 100, 1)`. **Nota: se conservó `* 100` porque la fórmula matemática lo requiere para normalizar correctamente. Sin él, el resultado para 0-5 sería incorrecto (ej: 4.3→0.0).** *Archivos: `app/Http/Controllers/Api/EvaluacionController.php`*
- [x] T-018 **Endpoint `GET /api/director/evaluaciones`**. En `DirectorController`, método `evaluaciones()`. Proyectos donde el director es evaluador (`evaluador_proyecto`), excluyendo propios (`director_id != userId`), con estudiantes, co-evaluadores, semestre y fase. *Archivos: `app/Http/Controllers/Api/DirectorController.php`*
- [x] T-019 **Endpoint `GET /api/director/proyectos/{id}/entrega-fase`**. En `DirectorController`, método `entregaFase()`. Buscar entrega aprobada de la fase. Retorna entrega + versiones. *Archivos: `app/Http/Controllers/Api/DirectorController.php`*
- [x] T-020 **Crear hook `useDirectorEvaluaciones`**. Fetch de `GET /api/director/evaluaciones`. Patrón `useReducer`. *Archivos: `resources/js/hooks/useDirectorEvaluaciones.ts`*
- [x] T-021 **Crear EvaluacionesDirector.tsx**. Lista de proyectos (cards) + detalle con entrega aprobada + form calificación 0.0-5.0. Estados: loading, empty, error, success. *Archivos: `resources/js/pages/director/EvaluacionesDirector.tsx`*
- [x] T-022 **Agregar rutas evaluaciones nuevas en api.php**. `GET /director/evaluaciones`, `GET /director/proyectos/{proyecto}/entrega-fase`. *Archivos: `routes/api.php`*
- [x] T-023 **Tests actualizados para escala 0-5**. Actualizados tests existentes en `EvaluacionCrudTest`, `EntregaCrudTest`, `NotificacionTest` para usar notas 0-5. (T-023 opcional, no se creó archivo nuevo). *Archivos: `tests/Feature/Admin/EvaluacionCrudTest.php`, `tests/Feature/Admin/EntregaCrudTest.php`, `tests/Feature/Api/NotificacionTest.php`*

## Phase 4: Recursos — Fix Descarga + Rediseño UI (PR 4)

- [ ] T-024 **Fix Resource interface y fromApi() en Recursos.tsx**. Agregar `file_path: string | null` y `link: string | null` a `Resource`. En `fromApi()` mapear `r.file_path` y `r.link`. Extraer `size` real del nombre del archivo si `file_path` existe. *Archivos: `resources/js/pages/shared/Recursos.tsx`*
- [ ] T-025 **Botón descarga funcional en cards de Recursos.tsx**. Agregar botón "Descargar" en cada card. Si `file_path` existe → `<a href="/storage/{file_path}" target="_blank">`. Si `link` existe → `<a href={link} target="_blank" rel="noopener noreferrer">`. Ambos con `aria-label`. *Archivos: `resources/js/pages/shared/Recursos.tsx`*
- [ ] T-026 **Rediseño UI Recursos.tsx**. Mejorar jerarquía visual: icono badge más grande, título con tipografía mejorada, metadata en footer (tamaño/tipo), bordes por categoría. Search con debounce 300ms. Loading skeletons, error con retry, empty state por categoría. A11y: `role="article"`, focus visible. *Archivos: `resources/js/pages/shared/Recursos.tsx`*
- [ ] T-027 **Fix ResourceDetail interface y fromApi() en RecursoDetalle.tsx**. Agregar `file_path` y `link` a `ResourceDetail`. Mapear en `fromApi()`. *Archivos: `resources/js/pages/shared/RecursoDetalle.tsx`*
- [ ] T-028 **Fix botón "Descargar" onClick en RecursoDetalle.tsx**. Agregar `onClick` que navegue a `/storage/{file_path}` o abra `link` en nueva pestaña. *Archivos: `resources/js/pages/shared/RecursoDetalle.tsx`*
- [ ] T-029 **Fix metadata y rediseño RecursoDetalle.tsx**. Mostrar tamaño real del archivo si está disponible (extraer de `file_path`). Mejorar layout, metadatos reales, breadcrumb. *Archivos: `resources/js/pages/shared/RecursoDetalle.tsx`*
- [ ] T-030 **Verificar symlink public/storage**. Ejecutar `php artisan storage:link` si no existe el symlink. *Archivos: `public/storage` (symlink)*
- [ ] T-031 **Tests para PR 4**. Verificar: click Descargar en card → descarga archivo real / abre enlace externo, responsive (320/768/1440), a11y (tab nav, aria-labels, focus visible), filtros y search con datos reales. *Archivos: `tests/Browser/RecursosTest.php` (Playwright)*
