# Tasks: Cartas de Aval y Export de Alertas de Seguimiento

> Change: `cartas-avales-y-export-alertas` | Sprint 5 | 2 capacidades: `director-cartas-aval` + `coordinator-export-seguimiento`
> Modo: hybrid | Strict TDD (`strict_tdd: true`) | Runner: `vendor/bin/pest`
> Todas las tareas inician sin checkear. Threat matrix: N/A (design.md) — sin RED tests de matriz.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~840 (design): PR1 ~350 · PR2 ~250 · PR3 ~180 · PR4 ~60 |
| 400-line budget risk | High (total) — Low por PR individual |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 → PR 4 (feature-branch-chain) |
| Delivery strategy | auto-chain (usuario fijó force-chained) |
| Chain strategy | feature-branch-chain |
| Tracker | `feature/cartas-avales-y-export-alertas` (draft, no merge hasta integrar PR4) |

```text
Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High
```

### Suggested Work Units (bases feature-branch-chain)

| Unit | Goal | PR / base | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Backend cartas: service, controller, rutas, tests, templates DOCX | PR 1 / base = tracker `feature/cartas-avales-y-export-alertas` | `vendor/bin/pest tests/Unit/Services/CartaAvalServiceTest.php tests/Feature/CartaAvalTest.php` | `php artisan route:list --path=api/director/cartas` (rutas registradas) | revert commit PR1 + borrar templates + `composer remove phpoffice/phpword` |
| 2 | Frontend cartas: `CartasAval.tsx`, hook, ruta | PR 2 / base = rama PR 1 | `npm run build` (0 errores) | Navegar `/dashboard/director/cartas` con rol DIRECTOR (UI español) | revert commit PR2 + quitar ruta/componente |
| 3 | Backend export: `exportar()` + ruta + tests | PR 3 / base = rama PR 2 | `vendor/bin/pest tests/Feature/ExportSeguimientoTest.php` | `php artisan route:list --path=api/admin/seguimiento` | revert commit PR3 + `composer remove phpoffice/phpspreadsheet` |
| 4 | Frontend export: botón en `SeguimientoSemestre.tsx` | PR 4 / base = rama PR 3 | `npm run build` (0 errores) | Tab Seguimiento rol COORDINADOR → click "Exportar" → descarga .xlsx | revert commit PR4 + quitar botón |

> Si el diff de un PR hijo muestra cambios de PRs previos, retarget/rebase a la rama padre antes de review (regla chained-pr).

## PR 1 — Backend cartas (base: tracker)

| ID | Tarea | Deps | Criterio de aceptación | Skills |
|----|-------|------|-------------------------|--------|
| T-001 | ✅ Documentar prerequisito `phpoffice/phpword` (composer require + regenerar lock) en `README.md`/tasks — la instalación real la ejecuta apply | — | Nota visible con comando exacto; no instalar ni modificar `composer.json` en esta fase | `C:\Users\Owner\.claude\skills\laravel-patterns\SKILL.md` |
| T-002 | ✅ Copiar templates `aval-sustentacion.docx` y `carta-jurados.docx` a `storage/app/templates/` con placeholders `${nombre_estudiante}`, `${codigo_estudiante}`, `${titulo_proyecto}`, `${jurado_1_nombre}`, `${jurado_2_nombre}`, `${jurado_3_nombre}`, `${nombre_director}`, `[Número de documento]` (D1/D4) | T-001 | Ambos archivos existen; placeholders verificables al abrir el DOCX | `C:\Users\Owner\.claude\skills\laravel-patterns\SKILL.md` |
| T-003 | ✅ RED: `tests/Unit/Services/CartaAvalServiceTest.php` — `calcularHabilitacion()`: due_date ayer + hora_maxima '18:00' → habilitado; hora_maxima null → 23:59:59 (D3); semestre sin entregas `desarrollo` → deshabilitado; `resolverPlaceholders()`: jurados faltantes → tabla vacía + warning (D2), cédula literal (D1) | T-001 | Tests fallan (rojo) | `C:\Users\Owner\.claude\skills\laravel-tdd\SKILL.md`, `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md` |
| T-004 | ✅ RED: `tests/Feature/CartaAvalTest.php` — GET `/api/director/cartas/proyectos` (factory proyecto + entrega desarrollo cerrada → `cartas_habilitadas: true`; sin entregas → false; proyecto sin estudiantes → mensaje RF-CA-01); GET `.../aval-sustentacion` y `.../carta-jurados` (StreamedResponse + Content-Disposition D4, template fake en storage); template faltante → 500 "La plantilla de carta no está disponible. Contacte al administrador." (RF-CA-02/03) | T-001 | Tests fallan (rojo) | `C:\Users\Owner\.claude\skills\laravel-tdd\SKILL.md`, `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md` |
| T-005 | ✅ GREEN: `app/Services/CartaAvalService.php` — `calcularHabilitacion()` (D3: `now() >= max(due_date + (hora_maxima ?? '23:59:59'))`), `resolverPlaceholders()`, `listarProyectosConHabilitacion()` (jurados de `evaluador_proyecto.fase='presentacion_final'`, `warnings[]` D4), `generarAvalSustentacion()`, `generarCartaJurados()` | T-003, T-004 | Tests T-003/T-004 verdes | `C:\Users\Owner\.claude\skills\laravel-patterns\SKILL.md`, `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md` |
| T-006 | ✅ GREEN: `app/Actions/GenerateCartAction.php` — TemplateProcessor PHPWord: setValue placeholders, tabla Jurado 1/2/3 + Director, error 500 si template falta | T-005 | Tests de descarga verdes; stream DOCX mime correcto | `C:\Users\Owner\.claude\skills\laravel-patterns\SKILL.md` |
| T-007 | ✅ GREEN: `app/Http/Controllers/Api/DirectorController.php` +3 métodos (`cartasProyectos`, `generarAvalSustentacion`, `generarCartaJurados`) y 3 rutas en `routes/api.php` bajo `director` prefix (middleware director) | T-006 | Tests feature verdes; rutas registradas (route:list) | `C:\Users\Owner\.claude\skills\laravel-patterns\SKILL.md` |
| T-008 | ✅ Commit unitario PR1 (conventional `feat:`, tests con código) | T-007 | Commit único, `git diff --stat` < 400 líneas, tests verdes registrados | `C:\Users\Owner\.config\opencode\skills\work-unit-commits\SKILL.md` |

## PR 2 — Frontend cartas (base: rama PR 1)

| ID | Tarea | Deps | Criterio de aceptación | Skills |
|----|-------|------|-------------------------|--------|
| T-009 | ✅ Crear `resources/js/hooks/useDirectorCartas.ts` — fetch `GET /api/director/cartas/proyectos`, tipos TS del contrato JSON (proyectos, `cartas_habilitadas`, `cierre_efectivo`, estudiantes, `warnings[]`) | T-007 | `npm run build` sin errores | `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`, `C:\Users\Owner\.claude\skills\typescript-pro\SKILL.md` |
| T-010 | ✅ Crear `resources/js/pages/director/CartasAval.tsx` — tabla proyectos, botón "Generar cartas" habilitado/deshabilitado + tooltips (RF-CA-01 textos en español), expandir estudiantes, banners de `warnings[]` (D4), botones "Descargar Carta 1"/"Carta 2" (descarga blob), EmptyState sin estudiantes | T-009 | `npm run build` sin errores; flujo manual con rol DIRECTOR | `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`, `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`, `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md` |
| T-011 | ✅ Registrar ruta `/dashboard/director/cartas` en `resources/js/app.tsx` + enlace en `Sidebar.tsx` | T-010 | `npm run build` sin errores; navegación desde dashboard director | `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md` |
| T-012 | ✅ Commit unitario PR2 | T-011 | Commit único < 400 líneas, build OK | `C:\Users\Owner\.config\opencode\skills\work-unit-commits\SKILL.md` |

## PR 3 — Backend export (base: rama PR 2)

| ID | Tarea | Deps | Criterio de aceptación | Skills |
|----|-------|------|-------------------------|--------|
| T-013 | ✅ Documentar prerequisito `phpoffice/phpspreadsheet` (apply instala) | — | Nota visible con comando exacto | `C:\Users\Owner\.claude\skills\laravel-patterns\SKILL.md` |
| T-014 | ✅ RED: `tests/Feature/ExportSeguimientoTest.php` — GET `/api/admin/seguimiento/semestre/{id}/export` con 5 proyectos → headers + 5 filas (RF-EX-01); semestre sin proyectos → headers + 0 filas; nombre `Seguimiento del [Grupo] [YYYY-MM-DD HHmm].xlsx` (D5); lib ausente → 500 "Error al generar el archivo Excel. Verifique que la librería esté instalada." | T-013 | Tests fallan (rojo) | `C:\Users\Owner\.claude\skills\laravel-tdd\SKILL.md`, `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md` |
| T-015 | ✅ GREEN: `app/Http/Controllers/Admin/SeguimientoController.php::exportar()` — reutiliza `SeguimientoService::obtenerSeguimiento()`, PhpSpreadsheet → Worksheet con headers (estudiante, proyecto, director, estado por fase/entrega, bitácoras, observaciones), StreamedResponse mime xlsx + Content-Disposition D5; ruta en `routes/api.php` bajo `admin/seguimiento` | T-014 | Tests T-014 verdes; ruta registrada | `C:\Users\Owner\.claude\skills\laravel-patterns\SKILL.md`, `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md` |
| T-016 | ✅ Commit unitario PR3 | T-015 | Commit único < 400 líneas, tests verdes | `C:\Users\Owner\.config\opencode\skills\work-unit-commits\SKILL.md` |

## PR 4 — Frontend export (base: rama PR 3)

| ID | Tarea | Deps | Criterio de aceptación | Skills |
|----|-------|------|-------------------------|--------|
| T-017 | ✅ Botón "Exportar" en `resources/js/pages/coordinador/SeguimientoSemestre.tsx` — junto al selector de semestre, fetch blob a `GET /api/admin/seguimiento/semestre/{id}/export`, descarga vía `URL.createObjectURL`, mantiene nombre del servidor (D5), loading + disabled sin semestre | T-015 | `npm run build` sin errores; descarga manual .xlsx con rol COORDINADOR | `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`, `C:\Users\Owner\.claude\skills\typescript-pro\SKILL.md` |
| T-018 | ✅ Commit unitario PR4 | T-017 | Commit único < 400 líneas, build OK | `C:\Users\Owner\.config\opencode\skills\work-unit-commits\SKILL.md` |

## Notas de ejecución (apply)

- **Prohibido**: `git clean`, `Remove-Item`, tocar `database/database.sqlite`, `migrate*` contra DB real (tests solo `:memory:`). Sin push/PR/review — decide el orquestador.
- **Orden de PRs**: 1 → 2 → 3 → 4. PR3 es independiente de PR1/2 en código pero se encadena por la estrategia elegida; si su diff muestra PR2, retarget a rama PR2.
- **Template faltante** (risk proposal): validar existencia en runtime → 500 claro (cubierto en T-004/T-006).
- **Open questions del design** (resolver antes de apply): origen de `codigo_estudiante` en `users`; sanitización de nombres de archivo (`/`, `\`, `:`) con regex.

## Follow-up — Ciudad y Fecha en cartas (post-PR4, batch en master)

> Agrega `${ciudad}` (Bucaramanga) y `${fecha}` (fecha de generación en español, ej. "5 de agosto de 2026") a ambas cartas y versiona los templates en storage.

| ID | Tarea | Deps | Criterio de aceptación | Estado |
|----|-------|------|-------------------------|--------|
| T-101 | ✅ Inyectar `${ciudad}` y `${fecha}` en la línea "Ciudad, Fecha:" de `aval-sustentacion.docx` y `carta-jurados.docx` (runs hermanos, sin anidar, `xml:space="preserve"`) | — | Tests de template verifican los 2 placeholders nuevos; sin runs anidados; XML bien formado | ✅ |
| T-102 | ✅ `CartaAvalService::resolverPlaceholders()`: `ciudad`='Bucaramanga' y `fecha` (Carbon locale `es`, formato `D de MMMM de YYYY`) en la base de ambas cartas | T-101 | `generarAvalSustentacion` y `generarCartaJurados` incluyen ambos placeholders | ✅ |
| T-103 | ✅ Versionar templates en storage: excepciones `!templates/` y `!templates/*.docx` en `storage/app/.gitignore` + commit | T-101 | `git status` muestra los docx como nuevos (trackeados) | ✅ |
| T-104 | ✅ Tests: `resolverPlaceholders` incluye ciudad/fecha; generación sin `${...}` sobrantes con valores reales; `CartaTemplateXmlTest` valida los 2 placeholders nuevos | T-102 | pest enfocado 26/0 + suite completa 686/0; pint limpio en archivos tocados | ✅ |

## Follow-up — Formato visual del export xlsx (post-PR4, batch en master)

> El usuario reporta que el export xlsx "funciona pero se ve muy feo". Se aplica formato profesional a la hoja generada por `SeguimientoController::exportar()` sin cambiar el contrato (mismo endpoint, columnas y nombre de archivo).

| ID | Tarea | Deps | Criterio de aceptación | Estado |
|----|-------|------|-------------------------|--------|
| T-201 | ✅ Fila de título "Seguimiento del Semestre [Nombre]" mergeada en todas las columnas, bold, tamaño mayor, relleno naranja | — | A1 contiene el título; merge A1:{última col}; estilo bold + fondo `C2410C` | ✅ |
| T-202 | ✅ Fila de encabezados: bold, fondo índigo `4F46E5`, texto blanco, bordes, altura de fila mayor | T-201 | Header en fila 2 con fondo índigo + bold (verificado por test) | ✅ |
| T-203 | ✅ autoSize de columnas para que no se vean apretadas | T-202 | `setAutoSize(true)` sobre cada columna | ✅ |
| T-204 | ✅ Estados Entregado/Pendiente/No entregó con relleno verde/ámbar/rojo claro | T-202 | Fill por valor de estado aplicado en celdas de entrega | ✅ |
| T-205 | ✅ Bordes finos en toda la tabla + zebra en filas de datos | T-202 | allBorders thin + fondo `F9FAFB` alternado | ✅ |
| T-206 | ✅ Fila de totales: % de entregas "Entregado" por columna + suma de bitácoras | T-203 | Fila final bold con relleno índigo claro; % calculado sobre proyectos con dato | ✅ |
| T-207 | ✅ `ExportSeguimientoTest` ajustado a la nueva estructura (título+header+datos+totales) + asserts de formato (título mergeado, header bold/fondo) | T-204 | pest enfocado 9/0 (39 assertions); suite completa 0 failed; pint limpio | ✅ |
| T-208 | ✅ Commit unitario del follow-up (work unit único, sin push/PR) | T-207 | Commit único, diff < 400 líneas, tests verdes | ✅ |
