# Tasks: Asistente Académico Inteligente

- [x] T-001 **OpenSpec** — proposal / design / spec / tasks.
- [x] T-002 **Perfil Director** — migración + modelo `DirectorAcademicProfile` + relación User; seed/backfill desde `areas` cuando aplique.
- [x] T-003 **Persistencia asistente** — migraciones + modelos `AiAssistantConversation` / `AiAssistantMessage` + enums type/status.
- [x] T-004 **Dominio asistente** — contracts, DTOs, parser, `DirectorCatalogBuilder` + enricher, `StudentOrientationStrategy`, `AcademicAssistantService`.
- [x] T-005 **API** — `AsistenteAcademicoController` + rutas GET conversación / POST mensajes.
- [x] T-006 **Frontend** — integrar `AsistenteOrientacion` (sin mocks de conversación).
- [x] T-007 **Tests Pest** — éxito con stub; 503 sin proveedor; filtrado IDs director; RBAC; historial.
- [x] T-008 **Verify** — suite relevante + checklist de no-duplicación; archivar solo cuando todo esté ✅.
