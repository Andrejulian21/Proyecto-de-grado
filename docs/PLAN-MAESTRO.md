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

## 3. Lo que falta — 7 sprints restantes

| Sprint | Área | Días | Prioridad |
|--------|------|------|-----------|
| 2 | Proyectos + Dashboard coordinador + Semestres | 1 | **Crítica** |
| 3 | Entregas + Versiones + Revisiones + Calificación | 1 | **Crítica** |
| 4 | Bitácoras + TOTP + Firmas | 1 | **Crítica** |
| 5 | Directores + Evaluadores + Evaluaciones + Reportes | 1 | **Alta** |
| 6 | Anuncios + Recursos + Alertas + Notificaciones | 1 | Media |
| 7 | IA (Asistente + Análisis) + QA + Despliegue | 2 | Media |

Detalle diario en `docs/ROADMAP.md`.

---

## 4. Orden de sacrificio (no-negociable)

Si el tiempo no alcanza, se sacrifica en este orden:

1. ❌ **Chat interno** (Reverb WebSocket) → comunicación por email
2. ❌ **Notificaciones push/correo** → solo notificaciones in-app
3. ❌ **TOTP** → firma manual con checkbox + timestamp + auditoría
4. ❌ **Agenda sustentaciones PDF/Excel** → agenda manual
5. ❌ **Análisis automático IA de entregas** → solo asistente de orientación
6. ❌ **Asistente IA (chatbot)** → se entrega sin módulo IA
7. ❌ **Exportación PDF/Excel** → solo vista en pantalla

**NUNCA se sacrifica:** Proyectos · Entregas · Bitácoras · Evaluación · Reportes · Auditoría

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
