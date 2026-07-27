# Cambios: buscador de proyectos en asignación de evaluadores + filtro estudiantes sin proyecto

**Change**: cambios-varios
**Tipo**: Feature (backend + frontend)
**Estado**: ✅ IMPLEMENTADO
**Fecha**: 2026-07-27

## 1. Buscador de proyectos (Asignación de Evaluadores)

### Problema

El formulario de registro de asignación de evaluadores usaba un `<select>` con todos los
proyectos del semestre. Con muchos proyectos, era incómodo de usar.

### Solución

Se creó el componente `ProjectAutocomplete` (similar a `StudentAutocomplete`) que permite
buscar proyectos por código o nombre con autocomplete.

```
┌──────────────────────────────────────────────────┐
│ 🔍 Buscar proyecto por código o nombre...        │
├──────────────────────────────────────────────────┤
│ PG-2026-001 - Proyecto juego de ciberseguridad   │
│   Dir: Juan Pérez | Est: Carlos, María           │
│ PG-2026-003 - Sistema de gestión                 │
│   Dir: Ana Gómez | Est: Pedro                    │
└──────────────────────────────────────────────────┘
```

- Debounce de 300ms
- Cache en sessionStorage (5 minutos)
- Solo muestra proyectos de semestres activos
- Al seleccionar, muestra el proyecto elegido con su información

### Archivos

| Archivo | Acción |
|---------|--------|
| `resources/js/components/forms/ProjectAutocomplete.tsx` | 🔵 Nuevo |
| `resources/js/pages/coordinador/AsignacionEvaluadores.tsx` | 🔧 Modificado |

---

## 2. Filtro: estudiantes sin proyecto asignado

### Problema

Al crear un proyecto, el buscador de estudiantes mostraba TODOS los estudiantes,
incluyendo los que ya tenían un proyecto asignado. Esto permitía asignar un estudiante
a dos proyectos diferentes.

### Solución

**Backend:** Se agregó el filtro `sin_proyecto` al endpoint `GET /api/admin/usuarios`.
Cuando está presente, excluye a los estudiantes que ya están vinculados a un proyecto
via la tabla pivote `proyecto_estudiante`.

```php
if ($request->boolean('sin_proyecto')) {
    $query->whereDoesntHave('proyectosComoEstudiante');
}
```

**Frontend:** Se agregó la prop `sinProyecto` al componente `StudentAutocomplete` y al
hook `useStudentSearch`. Cuando es `true`, agrega `&sin_proyecto=1` a la query.

Se activó el filtro en los dos lugares donde se usa:

| Ubicación | Formulario |
|-----------|-----------|
| `GestionProyectos.tsx` — crear proyecto | 🔧 `sinProyecto={true}` |
| `GestionProyectos.tsx` — editar proyecto | 🔧 `sinProyecto={true}` |

### Archivos

| Archivo | Acción |
|---------|--------|
| `app/Http/Controllers/Admin/UserController.php` | 🔧 Filtro sin_proyecto |
| `app/Models/User.php` | 🔧 Relación `proyectosComoEstudiante()` |
| `resources/js/hooks/useStudentSearch.ts` | 🔧 Parámetro `sinProyecto` |
| `resources/js/components/forms/StudentAutocomplete.tsx` | 🔧 Prop `sinProyecto` |
| `resources/js/pages/coordinador/GestionProyectos.tsx` | 🔧 Activado en crear y editar |

---

## Pruebas

- Tests de usuario pasan (45 passed, 1 pre-existing failure) ✅
- Build frontend: 0 errores TypeScript ✅
