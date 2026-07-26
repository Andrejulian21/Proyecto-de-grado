# Proposal: Conversor de documentos DOCX → Markdown

## Intent

Incorporar una **infraestructura reutilizable** para convertir documentos DOCX a Markdown de forma local, sin IA y sin acoplarla a ningún módulo funcional. Este change **no implementa funcionalidad de IA** ni UI; solo prepara el bloque de conversión documental que consumirán futuros módulos.

## Problem

1. Los módulos de IA previstos (Evaluador Inteligente, métricas ABET, Chat Académico) necesitan texto estructurado a partir de DOCX.
2. No existe hoy ningún servicio de conversión documental en el backend.
3. Colocar la conversión dentro de controladores, modelos o módulos de rol acoplaría prematuramente una capacidad genérica a un caso de uso.

## Scope

### In Scope
- Servicio Laravel reutilizable de conversión DOCX → Markdown (local, sin IA).
- Ubicación arquitectónica independiente (`app/Services/…`), desacoplada de roles y dominio académico.
- Estrategia de errores tipada y reutilizable por consumidores futuros.
- Pruebas Pest unitarias del servicio (sin proveedor IA, sin HTTP, sin UI).
- Dependencia(s) Composer mínimas y justificadas para la conversión.
- Artefactos OpenSpec de este change.

### Out of Scope
- Pantallas, componentes React, hooks, endpoints o controladores de prueba.
- Modelos, migraciones o tablas.
- Lógica IA, prompts, FastAPI, Azure OpenAI.
- Integración con entregas, proyectos, estudiantes, evaluadores o chat.
- Conversión PDF u otros formatos distintos de DOCX.

## Approach

Introducir la primera capa `app/Services/` del proyecto (ya anticipada en `docs/Architecture.md` como “service class futuro” para clientes no-ML). Un convertidor genérico recibe una ruta de archivo DOCX, valida, convierte localmente y devuelve Markdown. Los consumidores futuros solo dependen de la API pública del servicio.

## Affected Areas

| Area | Impact |
|------|--------|
| `app/Services/Documents/` | Nuevo |
| Excepciones tipadas de conversión | Nuevo |
| `composer.json` / lock | Dependencias de conversión |
| `tests/Unit/Services/Documents/` | Nuevo |
| OpenSpec change | Nuevo |

## Success Criteria

- [x] Existe un servicio reutilizable DOCX → Markdown.
- [x] Ubicación coherente con la arquitectura (fuera de Controllers/Models/módulos de rol/IA).
- [x] Tecnología de conversión justificada técnicamente.
- [x] Manejo robusto de errores reutilizable.
- [x] Pruebas automáticas del servicio (casos felices y errores).
- [x] Sin UI, sin endpoints, sin IA, sin regresiones.
