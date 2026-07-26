# Design: Asistente Académico Inteligente

## Phase 1 — Architecture findings

| Area | Finding |
|------|---------|
| Architecture | API JSON + SPA; AI via Laravel services → future FastAPI provider (ADR-007). Controllers thin; domain in services. |
| Infra IA | `AiGateway`, `AiPromptComposer`, `AiProviderRegistry`, `NullAiProvider`, DTOs `AiRequest`/`AiResponse`/`AiMessage` |
| Evaluador | Pattern to mirror: Strategy + orchestrator + parser + generic persistence + HTTP 503 on `ProviderNotConfigured` |
| Conversor DOCX | Exists; **not used** by this module (no document processing) |
| UI | `AsistenteOrientacion.tsx` fully mocked; keep layout, wire real API |
| Docs | `Architecture.md` §10 still marks both AI pages as mock (stale for Análisis; Asistente was still mock) |

## Phase 2 — Academic domain (actual DB)

| Source | Available | Use in context |
|--------|-----------|----------------|
| Student `User` | `name`, `email`, `codigo_estudiante` | Identity |
| `Proyecto` (if any) | `code`, `title`, `current_phase`, `status`, `director_id` | Weak signal of idea / stage |
| Conversation history | **None today** → new tables | Prior messages |
| Idea inicial | **No column** | Derived from conversation + structured result (`idea_refinada`) |
| Líneas / tecnologías / metodologías (student) | **None** | Suggested by AI in structured response |

**Decision:** do not invent `idea_inicial` on `proyectos`. Capture and refine the idea inside the conversation + `result_json`.

## Phase 3 — Director academic profile

| Need | Exists today | Gap |
|------|--------------|-----|
| Expertise | `users.areas` (free text) | Unstructured |
| Technologies / methodologies / research lines | — | Missing |
| Capacity | `max_capacity` + `proyectosDirigidos` count | Usable |
| Contact | `name`, `email` | Usable |

### Alternatives evaluated

| Option | Pros | Cons | Verdict |
|--------|------|------|---------|
| A. Stuff JSON on `users` | Simple | Couples profile growth to User; mixes auth identity with academic catalog | Rejected |
| B. Reuse only `areas` | Zero migration | Insufficient quality for founded recommendations | Rejected as sole source |
| C. Related entity `director_academic_profiles` | Open/Closed; assistant reads via catalog builder; profile grows without touching assistant core | One migration + model | **Adopted** |

### Adopted profile shape

```text
director_academic_profiles
  user_id (unique FK → users)
  research_lines (json[])
  technologies (json[])
  methodologies (json[])
  academic_experience (text, nullable)
  timestamps
```

Backfill: if `users.areas` has newline-separated values and no profile exists, seed `research_lines` from `areas` (seeder + optional migration helper). `users.areas` remains for admin cupos UI; catalog merges both (profile first, then `areas` fallback).

## Phase 4 — Assistant architecture

```text
Estudiante UI (AsistenteOrientacion)
    → GET  /api/estudiante/asistente/conversacion
    → POST /api/estudiante/asistente/mensajes
        → AsistenteAcademicoController
            → AcademicAssistantService
                 ├─ resolve/create conversation (type=student_orientation)
                 ├─ load history (messages)
                 ├─ build academic context (student + optional proyecto)
                 ├─ DirectorCatalogBuilder (+ enrichers)
                 ├─ AssistantPromptStrategy (StudentOrientation…)
                 ├─ AiPromptComposer + AiGateway
                 ├─ AssistantResultParser (validate director IDs)
                 └─ persist messages + result_json
```

### Contracts (Open/Closed)

```php
interface AssistantPromptStrategy {
    public function type(): AiAssistantType;
    public function promptVersion(): string;
    public function systemInstructions(): string;
    public function contextSections(AssistantContext $ctx): array;
}

interface DirectorCatalogEnricher {
    /** @param list<array<string,mixed>> $directors */
    public function enrich(array $directors): array;
}
```

Future Director/Coordinador assistants = new strategy + same service. New recommendation criteria = new enricher registered in the catalog builder. **No provider knowledge** in strategies/service.

### Reused (never duplicated)

- `AiGateway`, `AiPromptComposer`, `AiProviderRegistry`, `AiRequest`/`AiResponse`/`AiMessage`
- `AiException` / `AiErrorCode`
- HTTP 503 + `code: ai_unavailable` pattern from Evaluador
- Existing `AsistenteOrientacion` shell

### Not created

- New AI providers
- New gateway / prompt composer
- Document conversion path
- Mock chat responses

## Phase 5 — Functional flow

1. Student opens `/asistente` → GET conversation (create empty if none active).
2. Student sends message → POST.
3. Service loads history, academic context, director catalog.
4. Strategy builds system + context sections; composer merges sections; gateway completes.
5. Parser returns structured result; director recommendations filtered to catalog IDs.
6. Persist user + assistant messages; update conversation `result_json` / status / timing.
7. UI renders chat text + structured side panel.

Without provider: steps 1–4 run until gateway → `NullAiProvider` → `AiException` → persist failed turn metadata → 503 friendly.

## Phase 6 — Director recommendations

**Strategy:** send the full eligible catalog (profile + capacity) to the model; ask for ranked recommendations with justification grounded in catalog fields. PHP validates IDs against the catalog (anti-hallucination). No random pick.

Eligibility baseline enricher:
- role = Director
- remaining capacity = `max_capacity - active_projects_count` (include even if 0, but mark `disponible: false` so AI prefers available ones)

Extensibility: additional `DirectorCatalogEnricher` implementations can add historical topics, ABET tags, etc., without changing `AcademicAssistantService`.

## Phase 7 — Structured response

```json
{
  "mensaje": "string (texto conversacional para el chat)",
  "resumen_conversacion": "string",
  "idea_refinada": "string",
  "lineas_investigacion": ["string"],
  "tecnologias_recomendadas": ["string"],
  "metodologias_sugeridas": ["string"],
  "directores_recomendados": [
    {"id": 1, "nombre": "string", "justificacion": "string", "afinidad": 0.0}
  ],
  "riesgos": ["string"],
  "proximos_pasos": ["string"]
}
```

Prompt asks for JSON-only. `mensaje` feeds the chat bubble; the rest feeds the side panel. Extra fields rejected silently if unknown (forward-compatible).

## Phase 8 — Persistence decision

**Persist** conversations and messages in generic tables (mirror `ai_document_evaluations` philosophy).

| Store | Why |
|-------|-----|
| Conversations | Continuity, type discriminator for future role assistants, last structured result, provider/status/timing/errors |
| Messages | History for context window; audit of turns |
| result_json on conversation | Latest structured orientation without re-calling IA |
| Context snapshot | **No** full dump each turn (avoid bloat); catalog/context rebuilt live from BD |

Tables:

```text
ai_assistant_conversations
  id, user_id, type, status, provider, prompt_version,
  processing_ms, result_json, error_code, error_message, timestamps

ai_assistant_messages
  id, conversation_id, role (user|assistant|system), content,
  structured_json (nullable), timestamps
```

One active conversation per `(user_id, type)` for v1 (get-or-create).

## Phase 9 — Frontend

Reuse `AsistenteOrientacion.tsx`:
- Remove `INITIAL_MESSAGES` mock dialogue.
- Load conversation on mount; show welcome only if empty (static local welcome, not a fake IA reply).
- Wire send → POST; append user + assistant `mensaje`.
- Side panel: show structured blocks (idea, líneas, tecnologías, metodologías, directores, riesgos, próximos pasos) when available; keep “Temas recomendados” as quick-prompts that fill the input.
- 503 → clear banner (same copy as Análisis Automático).
- No stack traces / technical codes in UI body (code only for branching).

## API

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/estudiante/asistente/conversacion` | Active conversation + messages + last result |
| POST | `/api/estudiante/asistente/mensajes` | Body `{ "mensaje": "..." }` → process turn |

RBAC: middleware `role:Estudiante` (same group as other student routes).

## Superior alternative adopted

Generic `AcademicAssistantService` + strategies + catalog enrichers, instead of a hard-coded “solo estudiante” chatbot service: same orchestrator serves future Director/Coordinador assistants and new recommendation criteria without duplicating gateway/persist logic.
