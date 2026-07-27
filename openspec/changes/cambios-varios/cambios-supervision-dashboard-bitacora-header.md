# Cambios: supervisión, dashboard estudiante, bitácora y header

**Change**: cambios-varios
**Tipo**: Features + fixes (backend + frontend)
**Estado**: ✅ IMPLEMENTADO
**Fecha**: 2026-07-27

## 1. Toggle proyectos inactivos — Supervisión director + Directores coordinador

### Problema

La vista de proyectos del director y la página de directores del coordinador solo
mostraban proyectos de semestres activos. No había forma de ver proyectos de semestres
cerrados.

### Solución

**Backend:** Se agregó el query param `?todas=1` a los endpoints:

| Endpoint | Cambio |
|----------|--------|
| `GET /api/director/proyectos` | Si no se envía `todas`, filtra por `enSemestresActivos()` |
| `GET /api/admin/directores/{id}/proyectos` | Mismo comportamiento + incluye `semestre` en la respuesta |

**Frontend:** Botón toggle "Mostrar inactivos" en ambas vistas. Al activarlo:
- Se muestran todos los proyectos (incluso de semestres cerrados)
- Los proyectos de semestres inactivos muestran badge **"Inactivo"** + "Semestre inactivo"

### Archivos
- `app/Http/Controllers/Api/DirectorController.php`
- `app/Http/Controllers/Admin/DirectorCupoController.php`
- `resources/js/hooks/useDirectorProyectos.ts`
- `resources/js/pages/director/SupervisionProyectoDirector.tsx`
- `resources/js/hooks/useDirectores.ts`
- `resources/js/pages/coordinador/DirectoresPage.tsx`

---

## 2. Editar título del proyecto — Dashboard estudiante

### Problema

El estudiante no podía modificar el nombre de su proyecto desde el dashboard.

### Solución

Se agregó un ícono ✏️ al lado del título del proyecto en el dashboard del estudiante.
Al hacer clic, el título se vuelve un input editable con botones Guardar/Cancelar.

**Backend:** Nuevo endpoint:

```
PUT /api/estudiante/proyecto
Body: { "title": "Nuevo título" }
```

### Archivos
- `routes/api.php`
- `app/Http/Controllers/Api/EstudianteController.php`
- `resources/js/pages/dashboard/EstudianteDashboard.tsx`

---

## 3. Eliminar "Resumen Semanal" y mensaje de bloqueo — Detalle bitácora

### Problema

El detalle de bitácora mostraba un campo "Resumen Semanal" que no se utiliza, y un
mensaje "El contenido está bloqueado porque el director ya firmó esta bitácora"
innecesario.

### Solución

Se eliminó:
- Campo `weeklySummary` de la interfaz `BitacoraDetail`
- Textarea de "Resumen semanal" en modo edición
- Visualización de "Resumen semanal" en modo vista
- Mensaje de bloqueo por firma del director
- Parámetro `weeklySummary` en `onSaveContent`

Aplicado en todos los roles: estudiante, director y coordinador.

### Archivos
- `resources/js/components/bitacoras/RevisionBitacoraView.tsx`
- `resources/js/pages/estudiante/RevisionBitacora.tsx`
- `resources/js/pages/director/RevisionBitacora.tsx`
- `resources/js/pages/coordinador/RevisionBitacoraCoordinador.tsx`

---

## 4. Header — Nombre y rol visibles para todos los roles

### Problema

El nombre y el rol del usuario no se mostraban correctamente en pantallas pequeñas
(estaban ocultos con `hidden sm:block`). Además, no había un label claro para roles
como "Evaluador Externo".

### Solución

- Se agregó un mapa `roleLabels` con nombres legibles para todos los roles
- Se quitó `hidden` para que el nombre y rol sean visibles en todos los tamaños
- Se agregó fallback `'Usuario'` si el nombre está vacío

| Rol | Display |
|-----|---------|
| `Coordinador` | Coordinador |
| `Director` | Director |
| `Estudiante` | Estudiante |
| `EvaluadorExterno` | Evaluador Externo |

### Archivos
- `resources/js/components/layout/Header.tsx`

---

## Archivos modificados (resumen)

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Api/DirectorController.php` | Query param `todas` |
| `app/Http/Controllers/Admin/DirectorCupoController.php` | Query param `todas` + semestre en respuesta |
| `routes/api.php` | Nueva ruta PUT /api/estudiante/proyecto |
| `app/Http/Controllers/Api/EstudianteController.php` | Método `actualizarProyecto()` |
| `resources/js/hooks/useDirectorProyectos.ts` | Parámetro `todas` |
| `resources/js/hooks/useDirectores.ts` | Parámetro `todas` + tipo semestre |
| `resources/js/components/bitacoras/RevisionBitacoraView.tsx` | Eliminar weeklySummary y bloqueo |
| `resources/js/components/layout/Header.tsx` | roleLabels + quitar hidden |
| `resources/js/components/forms/StudentAutocomplete.tsx` | Prop `sinProyecto` |
| `resources/js/pages/director/SupervisionProyectoDirector.tsx` | Toggle inactivos + badge |
| `resources/js/pages/coordinador/DirectoresPage.tsx` | Toggle inactivos |
| `resources/js/pages/coordinador/GestionProyectos.tsx` | `sinProyecto={true}` en buscador |
| `resources/js/pages/dashboard/EstudianteDashboard.tsx` | Editar título inline |
| `resources/js/pages/estudiante/RevisionBitacora.tsx` | Eliminar weeklySummary |
| `resources/js/pages/director/RevisionBitacora.tsx` | Eliminar weeklySummary |
| `resources/js/pages/coordinador/RevisionBitacoraCoordinador.tsx` | Eliminar weeklySummary |
| `resources/js/hooks/useStudentSearch.ts` | Parámetro `sinProyecto` |
