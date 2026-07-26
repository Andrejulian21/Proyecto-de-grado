# Design: Infraestructura base de IA

## Phase 1 — Architecture findings

Sources: `docs/Architecture.md`, `docs/Backend.md`, `docs/Frontend.md`, `docs/ARQUITECTURA.md`, `docs/DECISIONES.md` (ADR-007), `constitution.md`, change archivado del conversor DOCX.

| Finding | Implication |
|---------|-------------|
| API JSON + SPA; controllers orchestrate | AI infra = services/contracts, **not** controllers/UI |
| ADR-007: ML in FastAPI + HMAC | Laravel owns **generic client contracts**; FastAPI will be *one* `AiProvider` later |
| Existing `app/Services/Documents/DocxToMarkdownConverter` | Keep AI infra **orthogonal**; modules compose converter + gateway |
| No AI code in `app/` today | Greenfield under `Contracts/Ai` + `Services/Ai` |
| Pattern: enums + typed exceptions (document conversion) | Mirror for AI errors |
| Frontend mocks for AI pages | Untouched in this change |

## Phase 2 — Shared responsibilities analysis

| Responsibility | Shared? | Decision |
|----------------|---------|----------|
| Request/response representation | Yes | `AiRequest`, `AiResponse`, `AiMessage` |
| Message roles | Yes | `AiMessageRole` enum |
| Provider communication | Yes (abstracted) | `AiProvider` contract |
| Provider selection | Yes | `AiProviderRegistry` + `config/ai.php` |
| Uniform errors | Yes | `AiException` + `AiErrorCode` |
| Response normalization | Yes (minimal) | `AiResponse` DTO only; no vendor JSON leakage |
| Public consumer API | Yes | `AiGateway::complete()` |
| Prompt construction (generic text assembly) | Yes (thin) | `AiPromptComposer` — sections title/body, **no** domain templates |
| Document context / DOCX | No (already exists) | Modules call `DocxToMarkdownConverter` then pass text into messages |
| Use-case strategies (eval/chat/ABET) | No | Out of scope |
| Persistence / conversations | No | Out of scope |
| Real HTTP / HMAC / SDK vendors | No | Out of scope; only `NullAiProvider` |

## Phase 3 — Architecture

```text
Future module (Evaluador / Chat / ABET / …)
        │
        │  AiRequest / AiMessage
        ▼
   AiGateway  ──────────────────────────┐
        │                               │
        ▼                               │
 AiProviderRegistry.resolve(name?)      │
        │                               │
        ▼                               │
   AiProvider::complete(AiRequest) ─────┘
        │
        ├── NullAiProvider          (now — support only)
        ├── FastApiAiProvider       (future — ADR-007)
        ├── OpenAiProvider          (future — optional)
        ├── GeminiProvider          (future)
        └── …
        │
        ▼
   AiResponse
```

### Location

```text
app/Contracts/Ai/AiProvider.php
app/Services/Ai/AiGateway.php
app/Services/Ai/AiProviderRegistry.php
app/Services/Ai/AiPromptComposer.php
app/Services/Ai/DTO/AiMessage.php
app/Services/Ai/DTO/AiRequest.php
app/Services/Ai/DTO/AiResponse.php
app/Services/Ai/Providers/NullAiProvider.php
app/Enums/AiMessageRole.php
app/Enums/AiErrorCode.php
app/Exceptions/AiException.php
app/Providers/AiServiceProvider.php
config/ai.php
tests/Unit/Services/Ai/*
```

### Justification

1. Matches Architecture.md “service class futuro” for Laravel↔AI boundary.
2. Independent of Controllers/Models/roles/domain entities.
3. Open/Closed: new provider = new class + config entry; gateway unchanged.
4. Docx converter stays a sibling service — no import cycle, composition at module level.
5. FastAPI remains the planned production transport (ADR-007) as a future `AiProvider`, not as a hardwired dependency today.

### Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Hardcode FastAPI client now | Violates “no HTTP / no real providers”; couples early |
| Put AI DTOs inside Controllers | Wrong layer; implies HTTP surface |
| Domain-specific PromptService (entregas/ABET) | Use-case leakage |
| Merge Docx conversion into AI gateway | Couples document I/O to AI; breaks reuse for non-AI needs |
| Frontend-only AI abstraction | Backend is source of truth for secrets/HMAC later |

## Phase 4 — Extensibility

### New provider (e.g. OpenAI / Gemini / Azure / Ollama / Anthropic / FastAPI)

1. Implement `AiProvider` (`name()`, `complete(AiRequest): AiResponse`).
2. Register class under `config/ai.php` → `providers`.
3. Optionally set `AI_PROVIDER` env to that name.

No changes to `AiGateway`, modules, or DTOs.

### New consumer module

1. Build `AiMessage` list (optionally via `AiPromptComposer`).
2. Optionally convert DOCX with existing `DocxToMarkdownConverter` and attach as user content.
3. Call `AiGateway::complete(new AiRequest(...))`.
4. Handle `AiException` by `error` code.

### Null provider

`NullAiProvider` supports wiring/tests without network. `complete()` throws `AiException` with `ProviderNotConfigured` so production never silently “succeeds” without a real provider. Unit tests inject a stub `AiProvider` into the registry/gateway.

## Public API (consumers)

```php
$gateway->complete(new AiRequest(
    messages: [
        AiMessage::system('…'),
        AiMessage::user($composer->compose([
            ['title' => 'Documento', 'body' => $markdown],
        ])),
    ],
    model: null,
    options: [],
));
```

## Risks

| Risk | Mitigation |
|------|------------|
| Overengineering adapters | Only Null + registry + gateway now |
| Accidental domain coupling | Code review gate: no Entrega/Proyecto/User imports |
| Silent fake AI in prod | Null throws; default provider `null` until configured |
