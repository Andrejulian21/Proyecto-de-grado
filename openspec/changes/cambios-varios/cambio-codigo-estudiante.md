# Cambio: código de estudiante

**Change**: cambios-varios
**Tipo**: Feature (base de datos + backend + frontend)
**Estado**: ✅ IMPLEMENTADO
**Fecha**: 2026-07-22

## Descripción

Agregar un campo opcional `codigo_estudiante` a la tabla `users` para almacenar el código
institucional que la universidad asigna a cada estudiante (formato `U00167215`).

El coordinador puede:
1. **Asignarlo** al agregar un estudiante desde la tarjeta "Estudiantes" en Gestión de Usuarios.
2. **Editarlo** desde la tabla "Usuarios y Accesos" → botón editar → campo visible solo cuando el rol es Estudiante.

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `database/migrations/2026_07_22_000001_add_codigo_estudiante_to_users.php` | 🔵 Nueva migración |
| `app/Models/User.php` | 🔧 `$fillable` + `$casts` + docblock |
| `app/Http/Requests/StoreWhitelistRequest.php` | 🔧 Regla `codigo_estudiante: nullable\|string\|max:20` |
| `app/Http/Requests/UpdateUserRequest.php` | 🔧 Regla `codigo_estudiante: nullable\|string\|max:20` |
| `app/Http/Controllers/Admin/UserController.php` | 🔧 `store()` envía código al crear User; `updateUsuario()` lo actualiza |
| `resources/js/pages/coordinador/GestionUsuarios.tsx` | 🔧 Campo en tarjeta + campo condicional en modal editar |

## Detalle técnico

### Base de datos

```sql
ALTER TABLE users ADD COLUMN codigo_estudiante VARCHAR(20) NULL;
CREATE INDEX users_codigo_estudiante_index ON users (codigo_estudiante);
```

- Solo en `users`, no en `authorized_emails` (el User se crea en el mismo POST, sería redundante).
- `NULL` por defecto, opcional.

### Frontend — Tarjeta de Estudiantes

Nuevo campo **"ID Estudiante"** entre "Nombre completo" y el botón "Agregar Estudiante":
- Input de texto, placeholder `Ej: U00167215`
- Texto de ayuda: "Código asignado por la institución (opcional)"
- Se limpia al agregar el estudiante exitosamente

### Frontend — Modal de Editar

Cuando el coordinador edita un usuario **con rol Estudiante**, aparece el campo
"Código de Estudiante" adicional en el modal. Precargado con el valor actual.

### API

| Endpoint | Cambio |
|----------|--------|
| `POST /api/admin/whitelist` | Acepta `codigo_estudiante` opcional |
| `PUT /api/admin/usuarios/{id}` | Acepta y actualiza `codigo_estudiante` |

## Pruebas

- Migración ejecutada correctamente ✅
- Columna `codigo_estudiante` existe en `users` ✅
- Tests de whitelist y usuarios pasan (los 2 failures existentes en el suite no están relacionados) ✅

## Estado actual de datos

De 6 estudiantes registrados, 2 tienen código asignado:
- `juliarteaga938@unab.edu.co` → `U00124578`
- `estudiante1@gmail.com` → `U00154714`
