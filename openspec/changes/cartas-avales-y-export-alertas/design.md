# Design: Cartas de Aval y Export de Alertas de Seguimiento

## Enfoque técnico

Dos capacidades independientes que comparten patrón: **leer datos existentes → renderizar artefacto (DOCX/XLSX) → descargar**. Se agregan endpoints de solo lectura al `DirectorController` y `SeguimientoController`, services de dominio (`CartaAvalService`, método en `SeguimientoService`), y páginas React bajo `resources/js/Pages/director/CartasAval.tsx` y un botón en `SeguimientoSemestre.tsx`. No se modifica API pública existente.

## Decisiones de arquitectura

### D1 — Contrato de listado de proyectos para cartas

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Extender `GET /api/director/proyectos` con flag `?cartas=1` | Reutiliza endpoint, pero mezcla dominio y rompe SRP | ❌ Rechazada |
| **Nuevo endpoint `GET /api/director/cartas/proyectos`** | Endpoint dedicado, respuesta enriquecida con `cartas_habilitadas`, `cierre_efectivo`, `estudiantes[]` | ✅ Elegida |

**Racional**: el endpoint de cartas tiene lógica propia (calcular habilitación temporal por semestre, listar estudiantes con código, contar jurados). Mezclarlo con el listado general contamina la respuesta del dashboard.

### D2 — Descarga individual vs ZIP

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| ZIP con todas las cartas del proyecto | Menos clicks, pero bloquea hasta generar todo, difícil manejo de errores parciales | ❌ Rechazada |
| **Descarga individual por estudiante/carta** (`GET /api/director/cartas/{proyecto}/estudiante/{user}/aval-sustentacion` y `.../carta-jurados`) | Simple, streaming directo, errores aislados, el director elige qué generar | ✅ Elegida |

**Racional**: spec RF-CA-04 exige descarga individual. ZIP agregaría complejidad (ZipArchive, cleanup de temporales) sin beneficio claro para 2-4 archivos.

### D3 — Capa de generación de cartas

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Lógica inline en controller | Rápido, pero controller >200 líneas y no testeable | ❌ Rechazada |
| **`CartaAvalService` + `GenerateCartAction`** | Service calcula datos (jurados, habilitación), Action ejecuta TemplateProcessor. Testeable por separado | ✅ Elegida |

**Racional**: el proyecto usa patrón Controller → Service (ver `SeguimientoService`). `CartaAvalService` resuelve placeholders y jurados; la Action ejecuta PHPWord. Ambos inyectables.

### D4 — Notificaciones de faltantes

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Crear registros en tabla `notificaciones` | Persistente, pero spam si el director abre la página varias veces | ❌ Rechazada |
| **Avisos inline en UI + campo `warnings[]` en la respuesta JSON** | Sin estado, el director ve faltantes al abrir, no se acumulan | ✅ Elegida |

**Racional**: los faltantes (jurados sin asignar, cédula placeholder) son transitorios y de consulta. El endpoint devuelve `warnings: string[]` por estudiante; el frontend los renderiza como banners.

### D5 — Endpoint de export .xlsx

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Nuevo `ExportSeguimientoController` | Overkill para un solo método | ❌ Rechazada |
| **Método `exportar()` en `SeguimientoController`** | Reutiliza DI de `SeguimientoService`, coherente con `porSemestre()` | ✅ Elegida |

**Racional**: `SeguimientoController` ya tiene el service inyectado. `exportar()` llama a `obtenerSeguimiento()` y transforma el array a XLSX.

### D6 — Frontend: ruta de cartas

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Tab dentro de `DirectorDashboard` | Dashboard ya tiene 4 secciones; agregar cartas lo sobrecarga | ❌ Rechazada |
| **Página dedicada `/dashboard/director/cartas`** con navegación desde sidebar | Aislada, flujo claro, respeta el router existente | ✅ Elegida |

## Data Flow

```
CARTAS:
  Frontend (CartasAval.tsx)
    → GET /api/director/cartas/proyectos
    → CartaAvalService::listarProyectosConHabilitacion()
        → Entrega::where(phase='desarrollo') + due_date/hora_maxima
        → EvaluadorProyecto::where(fase='presentacion_final')
    ← JSON { proyectos[], cartas_habilitadas, warnings[] }

  Frontend (botón "Descargar Carta 1")
    → GET /api/director/cartas/{proyecto}/estudiante/{user}/aval-sustentacion
    → CartaAvalService::generarAvalSustentacion()
        → TemplateProcessor(storage/app/templates/aval-sustentacion.docx)
        → setValue(${nombre_estudiante}, ${codigo_estudiante}, ...)
    ← StreamedResponse (application/vnd.openxmlformats-officedocument.wordprocessingml.document)

EXPORT:
  Frontend (SeguimientoSemestre.tsx → botón "Exportar")
    → GET /api/admin/seguimiento/semestre/{id}/export
    → SeguimientoController::exportar()
        → SeguimientoService::obtenerSeguimiento($id)
        → PhpSpreadsheet: Spreadsheet → Worksheet → rows
    ← StreamedResponse (application/vnd.openxmlformats-officedocument.spreadsheetml.sheet)
       Content-Disposition: Seguimiento del [Grupo] [YYYY-MM-DD HHmm].xlsx
```

## Cambios de archivos

| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `app/Services/CartaAvalService.php` | Create | Lista proyectos con habilitación, genera cartas con PHPWord |
| `app/Http/Controllers/Api/DirectorController.php` | Modify | +3 métodos: `cartasProyectos()`, `generarAvalSustentacion()`, `generarCartaJurados()` |
| `app/Http/Controllers/Admin/SeguimientoController.php` | Modify | +1 método: `exportar()` |
| `routes/api.php` | Modify | +3 rutas bajo `director` prefix, +1 ruta bajo `admin/seguimiento` |
| `storage/app/templates/aval-sustentacion.docx` | Create | Template Word con placeholders `${...}` |
| `storage/app/templates/carta-jurados.docx` | Create | Template Word con placeholders `${...}` |
| `resources/js/Pages/director/CartasAval.tsx` | Create | Página de cartas: tabla proyectos → expandir estudiantes → botones |
| `resources/js/Pages/coordinador/SeguimientoSemestre.tsx` | Modify | +botón "Exportar" en PageHeader actions |
| `resources/js/hooks/useDirectorCartas.ts` | Create | Hook para fetch de proyectos con habilitación |
| `tests/Feature/CartaAvalTest.php` | Create | Tests de habilitación, placeholders, template faltante |
| `tests/Feature/ExportSeguimientoTest.php` | Create | Tests de estructura XLSX, nombre archivo, semestre vacío |

## Interfaces / Contratos

### Respuesta `GET /api/director/cartas/proyectos`

```json
{
  "data": [
    {
      "id": 1,
      "code": "PGI-63754",
      "title": "Sistema centralizado...",
      "cartas_habilitadas": true,
      "cierre_efectivo": "2026-08-01T18:00:00-05:00",
      "estudiantes": [
        {
          "id": 5,
          "name": "Juan Pérez",
          "codigo_estudiante": "12345",
          "warnings": ["Faltan asignaciones de jurados para presentación final"]
        }
      ]
    }
  ]
}
```

### Placeholders en templates DOCX

**aval-sustentacion.docx**: `${nombre_estudiante}`, `${codigo_estudiante}`, `${titulo_proyecto}`, `${jurado_1_nombre}`, `${jurado_2_nombre}`, `${jurado_3_nombre}`, `${nombre_director}`

**carta-jurados.docx**: `${nombre_estudiante}`, `[Número de documento]` (literal), `${codigo_estudiante}`, `${titulo_proyecto}`, `${nombre_director}`

### Nombre de archivos descargados

- `Aval Sustentacion Publica [Nombre Apellido].docx`
- `Carta de Aval Entrega a Jurados [Nombre Apellido].docx`
- `Seguimiento del [Grupo] [YYYY-MM-DD HHmm].xlsx`

## Estrategia de testing

| Capa | Qué | Cómo |
|------|-----|------|
| Unit | `CartaAvalService::calcularHabilitacion()` | Test con due_date + hora_maxima null/no-null, comparar contra `now()` mockeado |
| Unit | `CartaAvalService::resolverPlaceholders()` | Verificar que jurados faltantes → tabla vacía + warning |
| Feature | `GET /api/director/cartas/proyectos` | Factory de proyecto + entrega desarrollo cerrada → `cartas_habilitadas: true` |
| Feature | `GET .../aval-sustentacion` | Template fake en storage, verificar StreamedResponse con Content-Disposition correcto |
| Feature | `GET .../export` | Factory de semestre + proyectos → verificar XLSX tiene headers y filas correctas |
| Feature | Template faltante → 500 con mensaje en español | Eliminar template fake, verificar error message |

## Threat Matrix

N/A — no hay routing externo, shell commands, subprocesses, VCS/PR automation, executable-file classification, ni process-integration boundary. Los endpoints son lecturas con descarga de archivos; el único I/O es filesystem (templates en `storage/app/templates/`).

## Migración / Rollout

No migration required. Los templates DOCX son archivos estáticos que se copian a `storage/app/templates/` (fuera de versionamiento git). Las librerías `phpoffice/phpword` y `phpoffice/phpspreadsheet` se instalan con `composer require` en la fase apply.

## División en PRs encadenados (forecast)

| PR | Scope | Líneas estimadas |
|----|-------|------------------|
| **PR 1**: Backend cartas | `CartaAvalService`, métodos en `DirectorController`, rutas, tests feature, templates DOCX | ~350 |
| **PR 2**: Frontend cartas | `CartasAval.tsx`, hook, integración con API, navegación | ~250 |
| **PR 3**: Backend export | Método `exportar()` en `SeguimientoController`, ruta, tests | ~180 |
| **PR 4**: Frontend export | Botón en `SeguimientoSemestre.tsx`, descarga blob | ~60 |

**Total**: ~840 líneas (dentro del budget 400 por PR con holgura).

## Dependencias a instalar (documentar en apply)

```bash
composer require phpoffice/phpword
composer require phpoffice/phpspreadsheet
```

## Open Questions

- [ ] ¿Los templates DOCX los provee el usuario o se generan placeholders genéricos en apply?
- [ ] ¿El campo `codigo_estudiante` existe en `users` o se deriva de otro campo? (spec asume que existe)
- [ ] ¿Sanitizar caracteres inválidos en nombres de archivo (ej. `/`, `\`, `:`) con regex o librería?
