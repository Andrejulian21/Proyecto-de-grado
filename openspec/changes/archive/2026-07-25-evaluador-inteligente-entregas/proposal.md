# Proposal: Evaluador Inteligente de Entregas

## Intent

Implementar el **primer consumidor real** de la infraestructura de IA: retroalimentación académica automática para el estudiante **antes** de la entrega oficial. No reemplaza al Director. No integra proveedores reales; el flujo completo llega al `AiGateway` y, sin proveedor configurado, responde con error controlado amigable.

## Problem

1. La página `AnalisisAutomaticoEntregas` sigue con mocks.
2. Ya existen DOCX→Markdown + `AiGateway`, pero ningún módulo de dominio los consume.
3. Las métricas del Coordinador (`evaluation_metrics`) no se usan aún para análisis.

## Scope

### In Scope
- Orquestación reutilizable de evaluación documental (estrategia + servicio).
- Estrategia `pre_submission` (Evaluador Inteligente de Entregas).
- Endpoint estudiante para disparar el análisis de una versión DOCX.
- Persistencia genérica de evaluaciones IA (reutilizable ABET/Director).
- Integración UI existente (sin rediseño; sin mocks).
- Extender DTO estudiante con `id` de versión y métricas.
- Tests Pest del flujo (stub provider + null provider).

### Out of Scope
- Proveedores reales (OpenAI/Azure/Gemini/…).
- Respuestas mock / análisis simulados.
- Evaluación ABET / Director (solo dejar el punto de extensión).
- UI nueva o rediseño visual.

## Approach

`DocumentEvaluationService` orquesta: validación → conversión DOCX → contexto → estrategia de prompt → `AiGateway` → parseo estructurado → persistencia. El Evaluador Inteligente solo aporta la estrategia `PreSubmissionDeliveryStrategy`. Sin proveedor: captura `ProviderNotConfigured` → HTTP 503 + mensaje amigable.

## Success Criteria

- [x] Flujo DOCX → MD → contexto → prompt → AiGateway → respuesta/error controlado.
- [x] Sin dependencia directa a proveedores.
- [x] Sin mocks de análisis.
- [x] UI muestra resultado o indisponibilidad amigable.
- [x] Persistencia genérica lista para otros tipos de evaluación.
- [x] OpenSpec + tests + archive.
