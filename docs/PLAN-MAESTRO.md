# PLAN-MAESTRO — Sistema Centralizado de Proyectos de Grado UNAB

> **Documento de ejecución definitivo.** Cualquier IA/persona lo sigue de arriba a abajo para
> construir el proyecto. **Si dos documentos se contradicen, ESTE gana.**
> Última revisión: 2026-07-08 (Sprint 1 completado).

---

## 0. Cómo usar este documento

**Orden de lectura canónico para una IA fresca:**
`AGENTS.md` → **este `PLAN-MAESTRO.md`** → `constitution.md` → la `spec` activa en `openspec/`.

**Fuente de verdad por tema:**

| Tema | Doc |
|------|-----|
| Ejecución / qué hacer ahora | **este PLAN-MAESTRO** |
| Carácter del producto | `docs/PRINCIPIOS.md` |
| Diseño técnico | `docs/ARQUITECTURA.md` |
| Decisiones (por qué) | `docs/DECISIONES.md` (ADR-001..012) |
| Plan detallado 7 días | `docs/ROADMAP.md` |
| Documentos de contexto | `contexto/` (fase2, HU, Gantt, etc.) |
| Artefactos SDD | `openspec/changes/` |

---

## 1. Qué es y decisiones cerradas

Sistema web para gestionar proyectos de grado de Ingeniería de Sistemas — UNAB.
Cubre el ciclo completo: inscripción, entregas, bitácoras, evaluación, reportes.

| Decisión | Valor |
|----------|-------|
| Backend | **Laravel 11** + PHP 8.3+ — ADR-001 |
| Frontend | **React 18 + Vite + TypeScript + Tailwind v4 + shadcn/ui** — ADR-002 |
| Auth | **Sanctum cookie SPA + Google OAuth + credenciales** — ADR-003 |
| Roles | **PHP Enum** en `users.role` + Gates/Policies — ADR-004 |
| Sesión única | Middleware SingleSession + Activity + SESSION_LIFETIME — ADR-005 |
| Auditoría | Eventos + Listeners, tabla append-only — ADR-006 |
| IA | **FastAPI** separado + HMAC — ADR-007 |
| API Routes | Prefijo por rol (`/api/admin/`, `/api/auth/`) — ADR-008 |
| Sesiones | Redis — ADR-009 |
| CSRF | `apiFetch()` helper con X-XSRF-TOKEN — ADR-010 |
| Diseño | Design tokens Open Design (burnt orange + indigo) — ADR-011 |
| Entrega | **7 días, 1 dev, orden de sacrificio** — ADR-012 |

---

## 2. Estado actual — ✅ Sprint 1 completado

| Entregable | Estado |
|-----------|--------|
| Google OAuth con triple validación (hd + @unab.edu.co + whitelist) | ✅ |
| Login evaluador externo con bloqueo por 3 intentos + lockout | ✅ |
| RBAC: 4 roles, middleware `role:`, Gates/Policies | ✅ |
| Gestión de Usuarios (whitelist CRUD, crear evaluadores, cambiar roles) | ✅ |
| Auditoría inmutable con filtros, 5-year retention | ✅ |
| Layout: sidebar + header fijos, responsive, títulos por sección | ✅ |
| Wireframes porteados de Open Design (logins, sidebar, header, gestión usuarios) | ✅ |
| API endpoints: `/api/admin/usuarios`, `/api/admin/evaluadores` | ✅ |
| 151 tests backend pasando, build frontend OK | ✅ |
| Documentación: AGENTS.md, constitution.md, PRINCIPIOS, ADRs, ARQUITECTURA | ✅ |

---

## 3. Lo que falta — 6 sprints restantes

| Sprint | Área | Prioridad |
|--------|------|-----------|
| 2 | Backend completo (13 migraciones, modelos, API) | **Crítica** |
| 3 | Tests backend (200+ tests con Pest) | **Crítica** |
| 4 | Frontend completo (portear 32 wireframes) | **Crítica** |
| 5 | Integración frontend + backend | **Alta** |
| 6 | Docker + CI/CD | Media |
| 7 | Despliegue (Azure + Nginx TLS) | Media |

Detalle en `docs/ROADMAP.md`.

---

## 4. Orden de sacrificio (no-negociable)

Si el tiempo no alcanza, se sacrifica en este orden:

1. ❌ **Módulos IA** (asistente + análisis automático) → se entrega sin IA
2. ❌ **TOTP para firmas** → firma manual con timestamp + auditoría
3. ❌ **Despliegue Azure** → deploy local / Docker Compose sin TLS
4. ❌ **Exportación PDF/Excel** → solo vista en pantalla
5. ❌ **Notificaciones push** → solo notificaciones in-app

**NUNCA se sacrifica:** Auth · Proyectos · Entregas · Bitácoras · Evaluación · Reportes · Tests

---

## 5. Arquitecura (resumen)

Ver `docs/ARQUITECTURA.md` para detalle completo.

```
Browser (React SPA) ←→ Laravel 11 (API REST + Auth)
                            │
                    ┌───────┴───────┐
                    │               │
              PostgreSQL 16    Redis 7
              + pgvector      (sesiones + cache)
                    │
               FastAPI (IA)
          Sentence-Transformers
          + Azure OpenAI
```

---

## 6. Definición de "Done"

- [ ] ¿Los endpoints nuevos tienen tests pasando?
- [ ] ¿Las pantallas nuevas son responsive (375px → 1280px)?
- [ ] ¿Los mensajes de error están en español y son claros?
- [ ] ¿Se registraron las acciones en el log de auditoría?
- [ ] ¿El build frontend compila sin errores?
- [ ] ¿Todos los tests del sprint anterior siguen pasando?
