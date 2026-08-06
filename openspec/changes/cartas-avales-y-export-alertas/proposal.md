# Proposal: Cartas de Aval y Export de Alertas de Seguimiento

## Intent

Eliminar el trabajo manual de los directores al redactar cartas de aval para sustentación y entrega a jurados, y dar al coordinador una herramienta de exportación de la tabla de seguimiento semestral para análisis y archivo institucional.

## Scope

### In Scope
- Página de cartas de aval (rol DIRECTOR): selección de proyecto propio, generación de dos cartas DOCX por estudiante, notificaciones de datos faltantes, habilitación post-cierre de fase `desarrollo`.
- Export .xlsx de seguimiento (rol COORDINADOR): botón en `SeguimientoSemestre.tsx`, endpoint dedicado, archivo con timestamp.
- Instalación documentada de `phpoffice/phpword` y `phpoffice/phpspreadsheet`.

### Out of Scope
- Agregar campo cédula a usuarios (se mantiene placeholder literal).
- Crear entidad "agenda de sustentación" (jurados se derivan de `evaluador_proyecto`).
- Envío automático por correo.

## Capabilities

### New Capabilities
- `director-cartas-aval`: Generación y descarga de cartas DOCX por estudiante, con validación de habilitación temporal y notificaciones de campos incompletos.
- `coordinator-export-seguimiento`: Exportación .xlsx completa de la tabla de seguimiento semestral desde el dashboard del coordinador.

### Modified Capabilities
- None (pure additions; existing tracking table endpoint remains unchanged; export is a new read-only operation).

## Approach

Backend: TemplateProcessor de PHPWord para DOCX y PhpSpreadsheet para XLSX. Templates Word copiados a `storage/app/templates/` con placeholders `${campo}`. Lógica de habilitación consulta la última entrega de fase `desarrollo` del semestre del proyecto (`due_date + hora_maxima`). Nombres de jurados extraídos de `evaluador_proyecto` con `fase='presentacion_final'`.

Frontend: Nueva ruta bajo `/dashboard/director`, tabla de proyectos del director con botón "Generar cartas", modal de notificaciones por estudiante. Botón de export en la tab "Seguimiento" de `GestionAlertas.tsx`.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Http/Controllers/Api/DirectorController.php` | Modified | Nuevos métodos para listar proyectos propios y generar cartas DOCX. |
| `app/Http/Controllers/Admin/SeguimientoController.php` | Modified | Nuevo método `exportar()` para .xlsx. |
| `resources/js/Pages/Dashboard/Director/` | New | Página de cartas de aval y componentes auxiliares. |
| `resources/js/Pages/Alertas/SeguimientoSemestre.tsx` | Modified | Botón de export .xlsx. |
| `routes/api.php` | Modified | Endpoints `GET /api/director/proyectos/{id}/cartas` y `GET /api/admin/seguimiento/semestre/{id}/export`. |
| `storage/app/templates/` | New | Templates DOCX de las dos cartas. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Templates DOCX no copiados al deploy | Med | Documentar en tasks.md y README; validar existencia en runtime con 500 claro. |
| Faltan jurados al generar Carta 1 | High (normal) | Generar carta igual con tabla vacía + notificación inline (no bloquear). |
| Librerías no instaladas en apply | Med | Dejar claro en tasks.md que `composer require` es requisito previo al código. |

## Rollback Plan

1. Revertir commits del change.
2. Eliminar templates de `storage/app/templates/`.
3. Si se instaló `phpoffice/phpword` o `phpspreadsheet`: `composer remove` y regenerar lock.
4. Frontend: eliminar ruta y componente; quitar botón export.

## Dependencies

- `phpoffice/phpword` (Carta 1 y 2).
- `phpoffice/phpspreadsheet` (Export seguimiento).
- Templates Word en `storage/app/templates/` (copia manual o script en apply).

## Success Criteria

- [ ] Director puede generar ambas cartas DOCX descargables por estudiante, con nombres de archivo correctos, incluso con datos incompletos.
- [ ] Coordinador exporta .xlsx con toda la tabla de seguimiento actual con nombre incluyendo grupo y timestamp.
- [ ] Tests unitarios/feature cubren habilitación de cartas, placeholders resueltos, y estructura del .xlsx.
- [ ] `composer.json` no se modifica en esta fase; solo se documenta la necesidad.

## Key Product Context (Exploration Findings)

- **Users**: Directores (aval) y Coordinadores (export).
- **Business rules**: Cartas solo post-cierre de última entrega `desarrollo`; jurados derivados de `evaluador_proyecto.fase='presentacion_final'`; cédula no existe (placeholder literal en template).
- **Edge cases**: Proyecto sin estudiantes (sin cartas); sin jurados (carta con tabla vacía + aviso); semestre sin entregas `desarrollo` (botón deshabilitado con tooltip).
- **Non-goals**: No agregar cédula a users, no crear entidad agenda, no enviar correo.
- **Tradeoff**: Placeholder cédula literal vs migración de schema (se eligió placeholder para no tocar users ni DB real).
