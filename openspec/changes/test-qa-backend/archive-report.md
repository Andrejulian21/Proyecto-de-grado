# Archive Report: test-qa-backend

**Change**: test-qa-backend
**Tipo**: Testing-only (Sprint 3)
**Estado**: ✅ ARCHIVADO
**Fecha**: 2026-07-09

## Resumen

Sprint 3 completado — cobertura integral de tests backend para el Sistema Centralizado de Proyectos de Grado UNAB.

## Stats

| Métrica | Valor |
|---------|-------|
| Tests totales | **439** (antes: 373) |
| Nuevas assertions | +131 (1159 total) |
| Nuevas factories | 7 (+ EvaluadorProyecto = 8) |
| Nuevos test files | 10 |
| Archivos spliteados | 3 (EvaluacionTest → EvaluadorProyecto + EvaluacionCrud + ReporteConsolidado) |
| Fixes a modelos | HasFactory en 9 modelos, FK en Semestre.proyectos() |
| Fakes agregados | Event::fake (AuditEvent), Mail::fake, Storage::fake |
| Cobertura config | phpunit.xml con text+html reports (requiere PCOV/Xdebug) |
| Estado suite | ✅ 439 passed, 0 failures |

## Lo completado

- [x] 7 factories (Proyecto, Entrega, Bitacora, Anuncio, RecursoInformativo, Notificacion, Evaluacion)
- [x] 3 enum unit tests (EstadoProyecto, FaseProyecto, EstadoInvitacionEvaluador)
- [x] 4 model unit tests (Proyecto, Semestre, Evaluacion, EvaluadorProyecto)
- [x] 3 feature tests con fakes (Event, Mail, Storage)
- [x] Split EvaluacionTest monolítico en 3 archivos
- [x] phpunit.xml con configuración de cobertura
- [x] Suite completa verde

## Riesgos abiertos

- Coverage threshold del 60% configurado pero no ejecutable sin PCOV/Xdebug
- EvaluacionTest original eliminado (split files pasan independientemente)

## Próximo paso recomendado

Sprint 4: **Frontend completo** — portear los 32 wireframes de Open Design a React
