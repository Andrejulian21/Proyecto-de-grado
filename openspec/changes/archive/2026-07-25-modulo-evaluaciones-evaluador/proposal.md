# Proposal: Módulo Evaluaciones Evaluador Externo

## Intent

Separar **Panel de Control** (dashboard resumen) de **Evaluaciones** (pantalla de gestión) para el rol EvaluadorExterno. Hoy ambas entradas del sidebar apuntan a la misma ruta.

## Scope

### In Scope
- Nueva ruta `/evaluador/evaluaciones` + página de lista/gestión.
- Sidebar: Panel → dashboard; Evaluaciones → nueva página.
- Lista real vía `useEvaluadorEvaluaciones` / `GET /api/evaluador/evaluaciones`.
- UX: búsqueda, filtro estado, contadores, orden por fecha, badges, acciones Evaluar/Ver, paginación cliente, empty state, contador de resultados.
- CTA discreto en dashboard hacia Evaluaciones.

### Out of Scope
- Cambios backend / migraciones.
- Rediseño del dashboard (solo enlace).
- Módulos Director/Coordinador/Estudiante.

## Approach

Reutilizar hook, `DataTable`, `StatusBadge`, `StatCard`, `PageHeader`, `datoNoEncontrado`. Filtrado y paginación en cliente (mismo patrón BitácorasDirector). Acciones navegan a rutas existentes `/evaluaciones/:id` y `/evaluaciones/:id/calificar`.

## Success Criteria

- [ ] Sidebar sin redundancia.
- [ ] Evaluaciones lista solo asignaciones del autenticado.
- [ ] Filtros/búsqueda funcionan.
- [ ] Acciones según estado.
- [ ] Build TS OK.
