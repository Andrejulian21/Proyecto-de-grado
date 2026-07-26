# Proposal: Evaluación Inteligente ABET

## Intent

Implementar un **nuevo tipo de evaluación documental** orientado a Directores, basado en un **perfil de métricas ABET placeholder** (no definitivas). Reutiliza completamente el pipeline DOCX→Markdown→contexto→prompt→`AiGateway`→persistencia. El objetivo es dejar una arquitectura extensible donde incorporar nuevos conjuntos de métricas no modifique el núcleo de IA.

## Problem

1. `DocumentEvaluationService` ya orquesta evaluaciones, pero el acceso está acoplado al estudiante y el parser al esquema `pre_submission`.
2. `AiEvaluationType::Abet` existe pero no hay estrategia ni API para Directores.
3. No hay un punto de extensión claro para **conjuntos de métricas** independientes del tipo de evaluación.

## Scope

### In Scope
- Refactor mínimo del orquestador: acceso pluggable + intérprete de resultado pluggable.
- Contrato `EvaluationMetricsDefinition` para conjuntos de métricas intercambiables.
- Estrategia + métricas placeholder ABET + parser de resultado ABET.
- Endpoint Director para disparar evaluación ABET de una versión DOCX.
- Reutilizar persistencia `ai_document_evaluations` (`type=abet`).
- Panel UI en revisión de entrega del Director (sin rediseño).
- Tests Pest (stub + null provider + RBAC director).

### Out of Scope
- Métricas ABET definitivas / oficiales.
- Proveedores reales de IA.
- Respuestas mock.
- Nueva infraestructura común de IA / nuevo conversor.
- Nuevas tablas de perfiles (salvo que el análisis lo exija — no aplica: reutilizar `ai_document_evaluations` + definición en código).

## Approach

**Evaluation Definition (Strategy + Metrics + Access + Interpreter):**
- El núcleo (`AiGateway`, composer, converter) no conoce el tipo.
- Cada evaluación aporta: estrategia de prompt, definición de métricas, resolver de acceso, intérprete de resultado.
- ABET Director = nueva definición; PreSubmission Estudiante = misma definición con piezas actuales (sin regresión).

## Success Criteria

- [x] Pipeline existente reutilizado sin duplicación.
- [x] Sin dependencia directa a proveedores.
- [x] Sin mocks.
- [x] Nuevos tipos/métricas sin tocar núcleo IA.
- [x] Persistencia genérica `type=abet`.
- [x] UI Director con 503 amigable.
- [x] OpenSpec + tests + archive.
