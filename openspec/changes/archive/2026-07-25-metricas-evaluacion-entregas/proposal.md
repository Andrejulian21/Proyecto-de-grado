# Proposal: Métricas de evaluación en entregas

## Intent

Incorporar un campo de texto libre **«Métricas de evaluación»** en la creación y edición de entregas, persistido en BD, con guía contextual (tooltip + modal). Preparar el dato como contexto futuro para el módulo de IA — **sin integrar IA todavía**.

## Problem

1. Cada entrega ya tiene título, descripción, fechas y **criterios de aceptación** (`acceptance_criteria`), orientados a requisitos de aprobación visibles en el flujo académico.
2. No existe un campo dedicado donde quien define la entrega describa **métricas/aspectos a evaluar** (calidad, metodología, errores a evitar, etc.) pensados como contexto para análisis automático futuro.
3. Reutilizar `acceptance_criteria` mezclaría dos propósitos distintos y dificultaría la integración IA posterior.

## Scope

### In Scope
- Columna nullable `evaluation_metrics` en `entregas` (migración).
- Persistencia en `POST/PUT` de entregas (`EntregaController` store/update).
- Campo textarea en formulario de creación y edición (`CoordinadorEntregas`).
- Icono de ayuda «(?)», tooltip en hover y modal con guía de redacción.
- Tipos TypeScript / hook `useEntregas`.
- Tests Pest de create/update con el nuevo campo.
- Compatibilidad con entregas existentes (NULL permitido).

### Out of Scope
- Llamadas a FastAPI / Azure OpenAI / análisis automático.
- Exponer las métricas al estudiante o al evaluador en vistas de detalle (salvo lo que ya traiga el JSON del modelo).
- Cambiar RBAC (sigue creando/editando el **Coordinador**; el Director no crea entregas en la arquitectura actual).
- Inertia (el proyecto usa API JSON + SPA).

## Approach

Menor impacto: un campo `text` nullable en la misma tabla `entregas`, mapeado en modelo/API/UI. Extraer un pequeño componente de formulario para label + textarea + ayuda, reutilizando el patrón visual de modal ya usado en la página (overlay `fixed inset-0`). Tooltip vía atributo `title` (patrón existente; no hay componente Tooltip shadcn). `ConfirmDialog` no aplica (es confirmación destructiva/acción, no contenido informativo).

## Affected Areas

| Area | Impact |
|------|--------|
| Migración `entregas` | Nuevo |
| `Entrega` model / factory | Modified |
| `EntregaController` store/update | Modified |
| `useEntregas` types/payloads | Modified |
| `CoordinadorEntregas.tsx` | Modified |
| Componente campo + guía | Nuevo (extracción, evitar duplicar create/edit) |
| `EntregaCrudTest` | Modified |

## Success Criteria

- [ ] Crear entrega con métricas las persiste.
- [ ] Editar carga y actualiza métricas.
- [ ] Entregas sin métricas siguen válidas (NULL).
- [ ] Hover muestra tooltip; click abre guía; «Cerrar» cierra.
- [ ] Sin mocks ni integración IA.
- [ ] Pest + build TypeScript OK.
