# Cambios: gestión de usuarios externos, contraseñas y roles

**Change**: cambios-varios
**Tipo**: Features + fixes (backend + frontend)
**Estado**: ✅ IMPLEMENTADO
**Fecha**: 2026-07-24

## 1. Usuarios externos ahora se agregan a la whitelist

### Problema

Al crear un evaluador externo mediante `storeExternal()`, solo se creaba un registro
en la tabla `users`. No aparecía en `authorized_emails` (whitelist), por lo que no
tenía trazabilidad de quién lo creó ni aparecía en la tabla unificada de usuarios.

### Solución

`UserController@storeExternal()` ahora también crea un registro en `authorized_emails`:

```php
AuthorizedEmail::create([
    'email' => $user->email,
    'name' => $payload['name'],
    'role' => UserRole::EvaluadorExterno->value,
    'created_by' => $request->user()?->id,
]);
```

### Archivos modificados
- `app/Http/Controllers/Admin/UserController.php`

---

## 2. Sincronización de usuarios existentes a la whitelist

Se ejecutó un script que agregó 7 usuarios existentes en `users` que no estaban
en `authorized_emails`, incluyendo estudiantes, directores y evaluadores externos.

---

## 3. Al cambiar rol de EvaluadorExterno se limpian campos bloqueantes

### Problema

Al cambiar el rol de un usuario de EvaluadorExterno a Estudiante/Director a través
del modal de editar, el middleware `EnsurePasswordChanged` bloqueaba todas las
requests porque detectaba `es_externo = true` y `password_changed_at = null`.

### Solución

`UserController@updateUsuario()` ahora marca `password_changed_at = now()` cuando
el nuevo rol no es EvaluadorExterno, pero **mantiene `es_externo = true`** para que
el usuario pueda seguir iniciando sesión con su contraseña.

También sincroniza el cambio de rol en la whitelist (`authorized_emails`).

```php
if ($user->role->value !== UserRole::EvaluadorExterno->value) {
    if ($user->es_externo) {
        $user->password_changed_at = now();
    }
}

AuthorizedEmail::where('email', $user->email)
    ->update(['role' => $user->role->value]);
```

### Archivos modificados
- `app/Http/Controllers/Admin/UserController.php`

---

## 4. Columna `last_temp_password` para visualizar contraseñas

### Problema

La contraseña temporal se generaba y devolvía una sola vez al crear el usuario.
Si el coordinador no la copiaba en ese momento, no había forma de recuperarla.

### Solución

Se agregó la columna `last_temp_password` (text, nullable) a la tabla `users`.
Al crear un usuario externo, se guarda la contraseña en texto plano en este campo.
El modal de editar permite visualizarla y regenerarla.

### Archivos modificados
- `database/migrations/2026_07_24_113939_add_last_temp_password_to_users.php`
- `app/Models/User.php`

---

## 5. Endpoint para regenerar contraseña de usuarios externos

### Problema

No existía una forma de generar una nueva contraseña para un usuario externo sin
borrarlo y crearlo de nuevo.

### Solución

Nuevo endpoint:

```
PUT /api/admin/usuarios/{id}/reset-password
```

Genera una nueva contraseña de 16 caracteres, la hashea, la guarda en `password`
y `last_temp_password`, resetea `password_changed_at = null`, y la devuelve en
texto plano en la respuesta.

### Archivos modificados
- `app/Http/Controllers/Admin/UserController.php` — nuevo método `resetPassword()`
- `routes/api.php` — nueva ruta

---

## 6. Al crear usuario externo se usa la contraseña del formulario

### Problema

El frontend solo enviaba `name` y `email` al crear un evaluador. El backend
ignoraba la contraseña que el coordinador escribía en los campos del formulario
y generaba una automáticamente.

### Solución

**Frontend:** ahora envía `password` y `password_confirmation` en el body.

**Backend:** `CreateEvaluadorRequest` ahora valida:
```php
'password' => ['required', 'string', 'min:8', 'confirmed'],
```

`storeExternal()` usa la contraseña enviada en vez de generar una aleatoria.

### Archivos modificados
- `resources/js/pages/coordinador/GestionUsuarios.tsx`
- `app/Http/Requests/CreateEvaluadorRequest.php`
- `app/Http/Controllers/Admin/UserController.php`
- `tests/Feature/Auth/CreateExternalEvaluatorTest.php`

---

## 7. Modal de editar: gestión de contraseña

### Problema

El modal de editar mostraba la contraseña actual desde `last_temp_password`, pero
este valor podía estar desactualizado (si se regeneró antes de tener el campo)
o simplemente no existir (usuarios previos a la migración).

### Solución

El modal ahora:
- Si existe `last_temp_password` → muestra "Contraseña actual: XXXX" (solo texto)
- Si no existe → muestra "El usuario ya tiene una contraseña asignada"
- Botón "Generar nueva contraseña" que regenera y muestra la nueva en un banner

### Archivos modificados
- `resources/js/pages/coordinador/GestionUsuarios.tsx`

---

## 8. Renombrado de UI: Evaluadores Externos → Usuarios Externos

### Cambios de etiqueta

| Ubicación | Antes | Después |
|-----------|-------|---------|
| Login institucional | Login para evaluadores externos | Login para usuarios externos |
| Sección de creación | Evaluadores Externos - Crear Cuentas | Usuarios Externos - Crear Cuentas |
| Botón crear | Crear cuenta de evaluador | Crear usuario externo |
| Tabla | Evaluadores Creados | Usuarios Creados |
| Texto vacío | No hay evaluadores externos registrados | No hay usuarios externos registrados |
| Descripción | cree cuentas para evaluadores externos | cree cuentas para usuarios externos |

### Archivos modificados
- `resources/js/pages/coordinador/GestionUsuarios.tsx`
- `resources/js/pages/auth/LoginInstitucional.tsx`

---

## 9. Corrección de `es_externo` en base de datos

### Problema

Usuarios creados como evaluadores externos que luego cambiaron de rol quedaron con
`es_externo = false` (por un fix anterior incorrecto), impidiendo el login externo.

### Solución

Se corrigieron manualmente los siguientes usuarios en la base de datos:

| Email | Rol | `es_externo` |
|-------|-----|:------------:|
| `estudiante1@gmail.com` | Estudiante | `true` |
| `juliartega938@gmail.com` | Estudiante | `true` |
| `julian21arteaga@gmail.com` | Director | `true` |

Y se revirtieron los usuarios institucionales con `password = null` a `es_externo = false`.

---

## Archivos modificados (resumen)

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Admin/UserController.php` | storeExternal + updateUsuario + resetPassword |
| `app/Http/Requests/CreateEvaluadorRequest.php` | Validación de password |
| `routes/api.php` | Ruta reset-password |
| `app/Models/User.php` | fillable + last_temp_password |
| `database/migrations/2026_07_24_113939_add_last_temp_password_to_users.php` | Migración nueva |
| `resources/js/pages/coordinador/GestionUsuarios.tsx` | Modal + UI labels |
| `resources/js/pages/auth/LoginInstitucional.tsx` | Label |
| `tests/Feature/Auth/CreateExternalEvaluatorTest.php` | Tests con password |

## Pruebas

- 60+ tests de usuarios, evaluadores y whitelist pasan (1 fallo preexistente en SingleSessionOnLogin) ✅
