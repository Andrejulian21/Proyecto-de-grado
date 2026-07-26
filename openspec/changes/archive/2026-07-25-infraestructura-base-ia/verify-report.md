# Verify Report: Infraestructura base de IA

**Date:** 2026-07-25  
**Change:** `2026-07-25-infraestructura-base-ia`

## Checks

| Check | Result |
|-------|--------|
| No AI infra duplicated (greenfield under Contracts/Ai + Services/Ai) | ✅ |
| No provider SDK / HTTP / credentials | ✅ |
| No Evaluador / Chat / ABET / domain imports | ✅ |
| No coupling to `DocxToMarkdownConverter` in AI gateway | ✅ |
| No UI / endpoints / models / migrations | ✅ |
| Open/Closed via `AiProvider` + registry + `config/ai.php` | ✅ |
| Pest AI + Documents suites | ✅ 19 passed |

## Test commands

```bash
php vendor/bin/pest tests/Unit/Services/Ai/AiInfrastructureTest.php
php vendor/bin/pest tests/Unit/Services/Documents/DocxToMarkdownConverterTest.php tests/Unit/Services/Ai/AiInfrastructureTest.php
```

**Result:** 9 + 10 = 19 passed (40 assertions).

## Spec coverage

| Spec | Verified |
|------|----------|
| IAI-001 Gateway | ✅ |
| IAI-002 AiProvider contract | ✅ |
| IAI-003 Registry selection | ✅ |
| IAI-004 Unknown provider | ✅ |
| IAI-005 Null provider | ✅ |
| IAI-006 DTOs | ✅ |
| IAI-007 Prompt composer | ✅ |
| IAI-008 Typed errors | ✅ |
| IAI-009–011 Scope guards | ✅ |
| IAI-012 Tests | ✅ |

## Ready for consumers

```php
app(AiGateway::class)->complete(new AiRequest([
    AiMessage::system('…'),
    AiMessage::user(app(AiPromptComposer::class)->compose([
        ['title' => 'Documento', 'body' => $markdownFromDocxConverter],
    ])),
]));
```

Future providers (FastAPI HMAC, OpenAI, Gemini, …) only need `AiProvider` + config entry.
