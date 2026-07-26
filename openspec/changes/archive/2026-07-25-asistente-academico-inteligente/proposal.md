# Proposal: Asistente Académico Inteligente

## Intent

Implementar el **segundo consumidor real** de la infraestructura común de IA: un asistente especializado en Proyectos de Grado de Ingeniería de Sistemas que orienta al estudiante en la definición de su proyecto y recomienda Directores con información objetiva de la base de datos. No es un chatbot genérico. No integra proveedores reales; el flujo completo llega al `AiGateway` y, sin proveedor configurado, responde con error controlado amigable.

## Problem

1. La página `AsistenteOrientacion` sigue con conversación mock (`setTimeout`, mensajes fijos).
2. Ya existen `AiGateway`, `AiPromptComposer` y el patrón Strategy del Evaluador Inteligente, pero ningún módulo de orientación los consume.
3. El perfil académico de Directores es insuficiente: solo `users.areas` (texto libre) y `max_capacity`; faltan líneas, tecnologías y metodologías estructuradas.
4. No existe historial persistente de conversaciones del asistente.

## Scope

### In Scope
- Orquestación reutilizable del asistente académico (estrategia + servicio).
- Estrategia `student_orientation` (Asistente Académico para Estudiante).
- Catálogo reutilizable de Directores con perfil académico extensible.
- Endpoint(s) estudiante para historial y envío de mensajes.
- Persistencia genérica de conversaciones/mensajes IA (reutilizable para otros roles).
- Entidad relacionada de perfil académico del Director (sin acoplar al núcleo del asistente).
- Respuesta estructurada (resumen, idea, líneas, tecnologías, metodologías, directores, riesgos, próximos pasos).
- Integración UI existente (sin rediseño; sin mocks).
- Tests Pest del flujo (stub provider + null provider + RBAC).

### Out of Scope
- Proveedores reales (OpenAI/Azure/Gemini/FastAPI).
- Respuestas mock / conversaciones simuladas.
- Asistentes para Director/Coordinador (solo dejar extensión).
- Procesamiento documental / conversión DOCX.
- UI CRUD de perfil académico para Coordinador (seed/migración + modelo suficientes para el flujo).
- Rediseño visual de la pantalla.

## Approach

`AcademicAssistantService` orquesta: historial → contexto académico → catálogo de Directores → estrategia de prompt → `AiPromptComposer` + `AiGateway` → parseo estructurado → persistencia. El Asistente Académico Estudiante solo aporta `StudentOrientationStrategy`. El perfil académico vive en `director_academic_profiles` (1:1 con User Director). Las recomendaciones se fundamentan en el catálogo real; IDs inexistentes se descartan. Sin proveedor: captura `ProviderNotConfigured` → HTTP 503 + mensaje amigable.

## Success Criteria

- [x] Flujo mensaje → contexto → prompt → AiGateway → respuesta/error controlado.
- [x] Sin dependencia directa a proveedores.
- [x] Sin mocks de conversación.
- [x] Contexto construido con datos reales de BD.
- [x] Mecanismo reutilizable de recomendación de Directores.
- [x] Respuesta estructurada clara.
- [x] UI muestra resultado o indisponibilidad amigable.
- [x] Arquitectura extensible a otros roles/asistentes.
- [x] OpenSpec + tests.
