# Tareas: Seguimiento y Firma

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~550–700 (total 4 PRs) |
| 400-line budget risk | Low (cada PR individual está bajo 400) |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 4 → PR 2 → PR 3 |
| Delivery strategy | auto-chain |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Firma por clave dinámica | PR 1 | `vendor/bin/pest tests/Feature/BitacoraFirmaTest.php` | Crear bitácora → firmar con código | Revertir migración + eliminar endpoints |
| 2 | Backend de seguimiento | PR 2 | `vendor/bin/pest tests/Feature/SeguimientoTest.php` | GET /seguimiento/semestre/{id} | Eliminar tabla + controlador |
| 3 | Frontend de seguimiento | PR 3 | N/A — frontend puro | Abrir GestionAlertas → tab Seguimiento | Eliminar componente + revertir GestionAlertas |
| 4 | Semana, edición 15 min, rename | PR 4 | `vendor/bin/pest tests/Feature/BitacoraSemanaTest.php` | Crear bitácora con semana, editar tras 15 min | Revertir migración + eliminar guardia |

---

## PR 1 — Firma por Clave Dinámica (8 tareas)

- [ ] T-1.1: Migración — agregar `signature_code`, `signature_code_expires_at`, `signature_retries` a `bitacoras`. **Skills:** `skill(laravel-patterns)`. Archivos: `database/migrations/*_add_signature_to_bitacoras.php`. RF-SIG-04.
- [ ] T-1.2: Agregar caso `NoFirmada` al enum `EstadoFirma`. **Skills:** `skill(laravel-patterns)`. Archivos: `app/Enums/EstadoFirma.php`. RF-SIG-05.
- [ ] T-1.3: Métodos `hasValidSignature()`, `generateSignatureCode()`, `canResendCode()` en `Bitacora`. **Skills:** `skill(laravel-patterns)`. Archivos: `app/Models/Bitacora.php`. Depende de T-1.1, T-1.2.
- [ ] T-1.4: Endpoint `POST /api/bitacoras/{id}/firmar` — validar código con `Hash::check()`, 5 intentos vía `RateLimiter`, expiración 2 min, transicionar a `FirmadaDirector` o `NoFirmada`. **Skills:** `skill(laravel-patterns)`, `skill(strict-tdd)`. Archivos: `app/Http/Controllers/Api/BitacoraController.php`, `routes/api.php`. RF-SIG-01, RF-SIG-02. Depende de T-1.3.
- [ ] T-1.5: Endpoint `POST /api/bitacoras/{id}/re-solicitar-codigo` — regenerar código solo si `NoFirmada` y `retries < 1`, incrementar retries. **Skills:** `skill(laravel-patterns)`, `skill(strict-tdd)`. Archivos: `BitacoraController.php`, `routes/api.php`. RF-SIG-03. Depende de T-1.4.
- [ ] T-1.6: Componente `SignatureCodeDisplay` — modal con código de 6 dígitos y countdown de 2 min al crear bitácora. **Skills:** `skill(react-patterns)`. Archivos: `resources/js/components/SignatureCodeDisplay.tsx`. Depende de T-1.4.
- [ ] T-1.7: Componente `SignatureCodeInput` — input numérico para que el director firme, muestra intentos restantes. **Skills:** `skill(react-patterns)`. Archivos: `resources/js/components/SignatureCodeInput.tsx`.
- [ ] T-1.8: Botón "Solicitar nuevo código" en `SignatureCodeDisplay` si el código expiró; oculto si `retries ≥ 1`. **Skills:** `skill(react-patterns)`. Archivos: `SignatureCodeDisplay.tsx`. Depende de T-1.5, T-1.6.
- [ ] T-1.9: Tests — firma exitosa, 5 intentos fallidos, expiración, re-solicitud única, código devuelto al crear. **Skills:** `skill(strict-tdd)`. Archivos: `tests/Feature/BitacoraFirmaTest.php`. RF-SIG-01 a RF-SIG-05. Depende de T-1.4, T-1.5.

## PR 2 — Backend de Seguimiento (5 tareas)

- [ ] T-2.1: Migración — crear tabla `seguimiento_observaciones` con `UNIQUE(proyecto_id, semestre_id, fase)`. **Skills:** `skill(database-migrations)`, `skill(laravel-patterns)`. Archivos: `database/migrations/*_create_seguimiento_observaciones.php`. RF-TRK-01.
- [ ] T-2.2: Modelo `SeguimientoObservacion` con relaciones a Proyecto y Semestre. **Skills:** `skill(laravel-patterns)`. Archivos: `app/Models/SeguimientoObservacion.php`. Depende de T-2.1.
- [ ] T-2.3: Servicio `SeguimientoService` — método `calcularEstadoEntrega()` (entregada/pendiente/no_entrego) y `contarBitacorasPorGrupo()` (semana 1-16 grupo_a, 17-32 grupo_b). **Skills:** `skill(laravel-patterns)`. Archivos: `app/Services/SeguimientoService.php`. RF-TRK-03, RF-TRK-04. Depende de T-4.1 (PR 4, campo semana).
- [ ] T-2.4: Endpoint `GET /api/admin/seguimiento/semestre/{semestre_id}` — devolver proyectos con entregas, bitácoras por grupo y observaciones. **Skills:** `skill(laravel-patterns)`, `skill(strict-tdd)`. Archivos: `app/Http/Controllers/Admin/SeguimientoController.php`, `routes/api.php`. RF-TRK-02. Depende de T-2.2, T-2.3.
- [ ] T-2.5: Endpoint `PUT /api/admin/seguimiento/observaciones` — upsert de observación por `proyecto_id + semestre_id + fase`. **Skills:** `skill(laravel-patterns)`, `skill(strict-tdd)`. Archivos: `SeguimientoController.php`, `routes/api.php`. RF-TRK-05. Depende de T-2.4.

## PR 3 — Frontend de Seguimiento (5 tareas)

- [ ] T-3.1: Componente `SemestreSelector` — `<select>` con semestres activos primero, inactivos después. **Skills:** `skill(react-patterns)`, `skill(frontend-patterns)`. Archivos: `resources/js/components/SemestreSelector.tsx`.
- [ ] T-3.2: Componente `ColumnaEntrega` — badge con icono según estado: ✅ `CheckCircle2` verde, ⏳ `Clock` amarillo, ❌ `XCircle` rojo. **Skills:** `skill(react-patterns)`, `skill(tailwind-patterns)`. Archivos: `resources/js/components/ColumnaEntrega.tsx`.
- [ ] T-3.3: Componente `ObservacionField` — textarea inline con `onBlur` → debounce 800 ms → `PUT /observaciones`. **Skills:** `skill(react-patterns)`, `skill(frontend-patterns)`. Archivos: `resources/js/components/ObservacionField.tsx`. RF-TRK-06 (persistencia). Depende de T-2.5.
- [ ] T-3.4: Página `SeguimientoSemestre` — tabla dinámica con filas por proyecto, columnas desde entregas, grupos colapsables con `<details>`. **Skills:** `skill(react-patterns)`, `skill(frontend-patterns)`, `skill(tailwind-patterns)`. Archivos: `resources/js/pages/coordinador/SeguimientoSemestre.tsx`. Depende de T-3.1, T-3.2, T-3.3.
- [ ] T-3.5: Integrar `SeguimientoSemestre` en `GestionAlertas.tsx` con tabs: `'seguimiento'` (activo por defecto) y `'alertas'`. **Skills:** `skill(react-patterns)`. Archivos: `resources/js/pages/coordinador/GestionAlertas.tsx`. Depende de T-3.4.

## PR 4 — Semana, Edición 15 min, Renombre (7 tareas)

- [x] T-4.1: Migración — agregar `semana` (unsignedTinyInteger) a `bitacoras`, `UNIQUE(proyecto_id, semana)`, backfill con `ROW_NUMBER() OVER (PARTITION BY proyecto_id ORDER BY created_at)`. **Skills:** `skill(database-migrations)`, `skill(laravel-patterns)`. Archivos: `database/migrations/*_add_semana_to_bitacoras.php`. RF-WK-01, RF-WK-02.
- [x] T-4.2: Modificar `BitacoraController@store` — validar `semana` requerido, `between:1,32`, único por proyecto. **Skills:** `skill(laravel-patterns)`, `skill(strict-tdd)`. Archivos: `app/Http/Controllers/Api/BitacoraController.php`. RF-WK-03. Depende de T-4.1.
- [x] T-4.3: Modificar `BitacoraController@update` — rechazar PUT si `created_at + 15 min < now()` con 422. **Skills:** `skill(laravel-patterns)`, `skill(strict-tdd)`. Archivos: `BitacoraController.php`. RF-WK-04.
- [x] T-4.4: Frontend — añadir `<select>` de semana (1-32) en formulario de creación de bitácora (`NuevaBitacora.tsx`). **Skills:** `skill(react-patterns)`. Archivos: `resources/js/pages/estudiante/NuevaBitacora.tsx`. Depende de T-4.2.
- [ ] T-4.5: Frontend — ocultar controles de edición si `created_at + 15 min < now()` y mostrar tiempo restante dentro de la ventana. **Skills:** `skill(react-patterns)`. Archivos: páginas de detalle/edición de bitácora. RF-WK-04.
- [ ] T-4.6: Renombrar etiqueta "descripción detallada" → "contenido" en todas las vistas de bitácora. El campo BD `notes` no cambia. **Skills:** `skill(react-patterns)`. Archivos: `NuevaBitacora.tsx`, detalle, edición, listado. RF-WK-05.
- [ ] T-4.7: Tests — validación de semana (rango, duplicado), ventana de edición 15 min (dentro y fuera). **Skills:** `skill(strict-tdd)`. Archivos: `tests/Feature/BitacoraSemanaTest.php`. RF-WK-01 a RF-WK-04. Depende de T-4.2, T-4.3.

---

### Orden de Implementación

**PR 1 → PR 4 → PR 2 → PR 3**

PR 1 (firma) es base. PR 4 (semana) se apoya en T-1.3 y añade el campo `semana` que necesita PR 2 para contar bitácoras por grupo. PR 3 es frontend puro que consume PR 2. Cada PR mergea al tracker branch; solo el tracker mergea a main.

### Notas

- PR 1 y PR 4 modifican los mismos archivos (`Bitacora.php`, `BitacoraController.php`). Resolver conflictos en PR 4 contra el branch de PR 1.
- `calcularEstadoEntrega` (T-2.3) necesita verificar qué relación existe en el modelo Entrega (`versiones` vs `VersionDocumento`). Ver diseño, sección Preguntas Abiertas.
