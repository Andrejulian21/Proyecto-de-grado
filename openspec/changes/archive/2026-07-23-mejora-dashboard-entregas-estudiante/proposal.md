# Proposal: Mejora Dashboard entregas estudiante

## Intent

Enriquecer las cards de entregas del Dashboard del Estudiante para mostrar el **título real** de cada entrega y un **historial resumido de versiones** (archivo, fecha/hora, estado, preview de observación) al expandir el accordion — sin cambiar la navegación al detalle.

## Problem

1. **Título**: el frontend prioriza un mapa hardcodeado `LABELS[fase]` sobre `titulo` del API, por lo que todas las entregas de una fase se ven con el mismo nombre genérico.
2. **Versiones**: `GET /api/estudiante/entregas` hace eager-load de `versiones` pero el map JSON solo expone `total_versiones` / `ultima_version`. El Dashboard mapea `e.versiones` → siempre vacío → accordion sin historial útil. Tampoco se muestran observaciones.

## Scope

### In Scope
- Incluir array `versiones` en la respuesta de `/api/estudiante/entregas` (campos existentes, eager-load ya presente).
- Usar `titulo` real de BD en la card.
- Mostrar historial en `DeliveryAccordion` con preview truncado de `director_notes`.
- Extender tipos `EntregaData` / `VersionData` si hace falta.

### Out of Scope
- Página de detalle, subida de versiones, mocks.
- Nuevos componentes/hooks/modelos/migraciones.
- Cambiar PhaseStepper o flujo de navegación.

## Approach

Reutilizar `DeliveryAccordion` + `StatusBadge`. Ampliar el map del endpoint existente (sin N+1: `with('versiones')` ya existe). Truncar observaciones en UI (~80 caracteres, patrón ya documentado en el proyecto).

## Affected Areas

| Area | Impact |
|------|--------|
| `EstudianteController@entregas` | Incluir `versiones[]` |
| `EstudianteDashboard.tsx` | Título real + map de versiones enriquecido |
| `DeliveryAccordion.tsx` | Columna/preview de observación |
| `types/estudiante.ts` | Campo opcional de observación |

## Success Criteria

- [ ] Card muestra título de BD.
- [ ] Expandir muestra todas las versiones con archivo, fecha/hora, estado y preview.
- [ ] Sin versiones: mensaje vacío sin error.
- [ ] Observaciones largas truncadas.
- [ ] "Ver detalle" intacto.
- [ ] Build TS OK + test API si aplica.
