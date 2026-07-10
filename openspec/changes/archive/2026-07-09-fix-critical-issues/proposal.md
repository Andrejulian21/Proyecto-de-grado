# Proposal: Fix Critical Issues

## Intent

Resolver los 5 issues críticos del repositorio GitHub que afectan la seguridad, integridad de datos y confiabilidad del sistema. Todos tienen fix propuesto y criterios de aceptación definidos en los propios issues.

## Issues

| # | Título | Área | Depende de |
|---|--------|------|------------|
| #12 | Inmutabilidad de audit_logs a nivel de BD solo existe en documentación | DB/Security | — |
| #11 | Subsistema de retención de auditoría (AuditArchive) no funcional | Backend | #12 |
| #10 | Single-session no cubre el login institucional por cookie | Security | — |
| #13 | El lockout por ventana de 10 minutos no se aplica | Backend | — |
| #9 | El módulo auth nunca se mergeó a master y no hay CI | DevOps | — |

## Orden de ejecución

1. **#12** — Trigger PostgreSQL `BEFORE UPDATE OR DELETE` en `audit_logs`
2. **#11** — Migración `audit_logs_archive` + fix AuditArchive command + schedule
3. **#10** — Fix SingleSessionMiddleware para cubrir login con cookie
4. **#13** — Fix LoginAttemptPolicy con ventana temporal de 10 min
5. **#9** — CI pipeline (GitHub Actions) + verificar merge de auth en master

## Estrategia

Commits directo a master con mensaje `fix(#N): descripción`. Cada fix con su test que valida los acceptance criteria del issue.

## Stack

Laravel 11 + PostgreSQL 16 + Pest (tests) + GitHub Actions (CI)

## Success Criteria

- [ ] #12: Raw `UPDATE`/`DELETE` sobre `audit_logs` falla a nivel PostgreSQL
- [ ] #11: AuditArchive migra filas >5 años sin excepción; schedule registrado
- [ ] #10: Segundo login invalida sesión cookie del primer dispositivo (401)
- [ ] #13: 3 fallos fuera de ventana NO bloquean; 3 dentro SÍ
- [ ] #9: CI corre en cada push; tests + lint pasan; master protegido
