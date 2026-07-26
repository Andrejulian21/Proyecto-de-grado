# Proposal: Integración flujo Evaluaciones IA (Estudiante / Director)

## Intent

Integrar el Evaluador Inteligente en el flujo real de entregas con dos comportamientos claros:

1. **Estudiante** — análisis preventivo con archivo DOCX **temporal** (no versión oficial).
2. **Director** — evaluación ABET sobre el documento **oficial** ya entregado.

## Problem

1. `/analisis-entregas` abre directo el análisis y mezcla selección de entrega/versión oficial.
2. El estudiante no tiene una pantalla previa estilo dashboard para elegir entrega.
3. El flujo actual exige `VersionDocumento` → el análisis toca el historial oficial.
4. El prompt pide Inertia; la arquitectura del proyecto es **SPA + apiFetch** (sin Inertia).

## Scope

### In Scope
- Pantalla previa de selección (fases/entregas) reutilizando componentes del dashboard.
- Navegación React Router hacia análisis con contexto de la entrega.
- Upload temporal DOCX → evaluación `pre_submission` sin crear `VersionDocumento`.
- Persistencia con `version_documento_id` nullable para corridas temporales.
- Confirmar/ajustar UI Director ABET solo con versión oficial.
- Tests + OpenSpec.

### Out of Scope
- Nuevos providers / converters / gateway.
- Métricas ABET definitivas.
- Rediseño visual.

## Approach

Selector en `/analisis-entregas` → navega a `/analisis-entregas/:entregaId` con `location.state`. Análisis acepta multipart `file`. Orquestador reutilizado; solo cambia origen del archivo + estrategia (ya existente). Director sin cambios de carga manual.

## Success Criteria

- [x] Sidebar abre selector estilo dashboard.
- [x] Selección navega al análisis con datos de entrega.
- [x] Archivo temporal no crea versión oficial.
- [x] Director usa solo documento oficial (ABET).
- [x] Mismo pipeline IA; tests + archive.
