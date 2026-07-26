# Proposal: Infraestructura base de IA

## Intent

Incorporar la **infraestructura común** que compartirán todos los futuros módulos de Inteligencia Artificial del sistema. Este change **no implementa ningún caso de uso de IA** ni integra proveedores reales; solo deja contratos, DTOs, gateway, registro de proveedores y manejo de errores reutilizables.

## Problem

1. Ya existe conversión DOCX → Markdown (`DocxToMarkdownConverter`), pero no hay capa común para consumir IA.
2. Sin contratos compartidos, cada módulo (Evaluador, Chat, ABET) tendería a acoplarse a un proveedor o a duplicar cliente/errores/DTOs.
3. ADR-007 ubica ML en FastAPI; Laravel necesita un **cliente/service layer** genérico antes de cablear HMAC o proveedores concretos.

## Scope

### In Scope
- Contratos públicos (`AiProvider`) y gateway de consumo (`AiGateway`).
- DTOs genéricos de solicitud/respuesta/mensajes.
- Registro/resolución de proveedores (Open/Closed).
- Proveedor nulo de soporte (sin HTTP, sin API externa).
- Errores tipados reutilizables.
- Configuración mínima (`config/ai.php`) + binding en service provider.
- Composer genérico de texto de prompt (sin prompts de dominio).
- Tests Pest de la infraestructura (sin proveedores reales).
- Artefactos OpenSpec de este change.

### Out of Scope
- OpenAI / Gemini / Azure / Ollama / Anthropic / FastAPI HTTP real.
- Prompts específicos, evaluación, chat, ABET.
- UI, hooks, endpoints, controladores de caso de uso.
- Modelos, migraciones, persistencia de conversaciones/resultados.
- Cambios al convertidor DOCX (se reutiliza por composición en módulos futuros, no se acopla aquí).

## Approach

Seguir el patrón iniciado por `app/Services/Documents/`: capa `app/Services/Ai/` + contratos en `app/Contracts/Ai/`. Los módulos futuros dependen solo de `AiGateway` + DTOs; añadir un proveedor = implementar `AiProvider` + registrarlo en config/registry.

## Affected Areas

| Area | Impact |
|------|--------|
| `app/Contracts/Ai/` | Nuevo |
| `app/Services/Ai/` | Nuevo |
| `app/Enums/` (roles/errores IA) | Nuevo |
| `app/Exceptions/AiException.php` | Nuevo |
| `config/ai.php` | Nuevo |
| `app/Providers/AiServiceProvider.php` | Nuevo |
| `bootstrap/providers.php` | Modified |
| `tests/Unit/Services/Ai/` | Nuevo |
| OpenSpec change | Nuevo |

## Success Criteria

- [x] Existe infraestructura común reutilizable para IA.
- [x] Ningún componente depende de un proveedor específico real.
- [x] Nuevos proveedores se añaden implementando el contrato (OCP).
- [x] Sin lógica de Evaluador / Chat / ABET / dominio académico.
- [x] Sin UI, endpoints, migraciones ni HTTP a APIs de IA.
- [x] Tests Pest de gateway/registry/null provider.
- [x] Diseño documentado en OpenSpec.
