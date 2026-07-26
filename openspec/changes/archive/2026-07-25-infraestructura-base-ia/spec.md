# Spec: Infraestructura base de IA

## IAI-001 — Gateway público
**THE SYSTEM SHALL** expose a reusable `AiGateway` that accepts an `AiRequest` and returns an `AiResponse` without requiring consumers to know the concrete provider class.

## IAI-002 — Contrato de proveedor
**THE SYSTEM SHALL** define an `AiProvider` contract with at least `name(): string` and `complete(AiRequest): AiResponse` so new providers can be added without modifying the gateway.

## IAI-003 — Registro / selección
**WHEN** a consumer calls the gateway with an optional provider name (or omits it)  
**THE SYSTEM SHALL** resolve the provider via a registry using the explicit name or the configured default.

## IAI-004 — Proveedor desconocido
**WHEN** the requested provider name is not registered  
**THE SYSTEM SHALL** throw `AiException` with error `UnknownProvider`.

## IAI-005 — Proveedor nulo
**THE SYSTEM SHALL** ship a `NullAiProvider` that implements `AiProvider` and, when `complete()` is invoked, throws `AiException` with error `ProviderNotConfigured` (no external HTTP calls).

## IAI-006 — DTOs genéricos
**THE SYSTEM SHALL** provide provider-agnostic `AiMessage`, `AiRequest`, and `AiResponse` types with no fields tied to entregas, chat, ABET, or a vendor SDK.

## IAI-007 — Composer de prompt genérico
**THE SYSTEM SHALL** provide a generic `AiPromptComposer` that assembles labeled text sections into a single string and **SHALL NOT** embed domain-specific prompts.

## IAI-008 — Errores tipados
**THE SYSTEM SHALL** expose `AiException` carrying an `AiErrorCode` for uniform handling by future modules.

## IAI-009 — Sin casos de uso
**THE SYSTEM SHALL NOT** implement Evaluador Inteligente, Chat Académico, evaluación ABET, persistence of AI results, UI, or HTTP calls to external AI APIs as part of this change.

## IAI-010 — Sin acoplamiento documental
**THE SYSTEM SHALL NOT** depend on `DocxToMarkdownConverter` inside the AI gateway; document conversion remains a separate reusable service for future composition.

## IAI-011 — Sin dependencia de proveedor real
**THE SYSTEM SHALL NOT** include SDK clients or credentials wiring for OpenAI, Gemini, Azure OpenAI, Ollama, or Anthropic in this change.

## IAI-012 — Pruebas
**THE SYSTEM SHALL** include Pest unit tests covering gateway success with a stub provider, unknown provider, null provider behavior, registry resolution, and prompt composer — without external AI network calls.
