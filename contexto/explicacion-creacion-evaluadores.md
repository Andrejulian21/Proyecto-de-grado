# Creación de Evaluadores Externos — Guía del Coordinador

## ¿Qué es un Evaluador Externo?

Son personas externas a la universidad (profesionales, otros docentes, expertos) que evalúan proyectos de grado. A diferencia de estudiantes y directores, **no tienen correo institucional UNAB** ni usan Google OAuth.

## Flujo de creación

```
Coordinador llena el formulario
       ↓
POST /api/admin/evaluadores
       ↓
Se crea el User directamente en la BD

     ↓
Se agrega también a la whitelist (authorized_emails)
para que aparezca en la tabla unificada de usuarios
y quede trazabilidad de quién lo creó
       ↓
Se genera contraseña temporal
       ↓
Se guarda audit log
       ↓
El coordinador comparte la contraseña con el evaluador
       ↓
El evaluador hace login externo y cambia su contraseña
```

## Diferencia clave con estudiantes/directores

| | Estudiantes / Directores | Evaluadores Externos |
|---|---|---|
| **Método de autenticación** | Google OAuth (correo institucional) | Credenciales (correo + contraseña) |
| **¿Pasa por whitelist?** | ✅ Sí, se agrega a `authorized_emails` primero | ✅ Sí, también se agrega a `authorized_emails` |
| **Columna `es_externo`** | `false` | `true` |
| **Contraseña** | `null` (usa OAuth) | Generada automáticamente, obliga a cambiar en primer login |
| **Restricción de correo** | Debe terminar en `@unab.edu.co` | Cualquier correo válido |

## El formulario

Sección **"Evaluadores Externos — Crear Cuentas"** en Gestión de Usuarios.

### Campos

| Campo | Obligatorio | Descripción |
|-------|:-----------:|-------------|
| Nombre completo | ✅ | Nombre del evaluador |
| Correo electrónico | ✅ | Cualquier dominio, sin restricción |
| Contraseña | ✅ | Generada automáticamente (14 caracteres + `!`). Se puede regenerar con el botón "Generar" o editar manualmente. |
| Confirmar contraseña | ✅ | Debe coincidir con la contraseña |

### Botones

- **Generar** — regenera ambas contraseñas con caracteres aleatorios
- **Crear cuenta de evaluador** — envía el formulario

## Backend — `storeExternal()`

**Endpoint:** `POST /api/admin/evaluadores`

**Controlador:** `UserController@storeExternal`

```php
public function storeExternal(CreateEvaluadorRequest $request): JsonResponse
{
    $temporaryPassword = Str::password(length: 16, symbols: true);

    $user = User::create([
        'name'                  => $payload['name'],
        'email'                 => $payload['email'],
        'password'              => Hash::make($temporaryPassword),
        'role'                  => UserRole::EvaluadorExterno->value,
        'es_externo'            => true,
        'password_changed_at'   => null, // fuerza cambio de contraseña en primer login
    ]);

    // Audit log
    AuditEvent::dispatch($request->user(), 'user.created_external', ...);

    // Devuelve el usuario + la contraseña en texto plano
    return response()->json([
        'user' => $user,
        'temporary_password' => $temporaryPassword,
    ], 201);
}
```

## Login del Evaluador

1. Va a la pantalla de login y selecciona **"Inicio de sesión externo"**
2. Ingresa su correo y la contraseña temporal que el coordinador le compartió
3. El sistema detecta `password_changed_at === null` y lo redirige a **cambiar la contraseña**
4. A partir de ahí, usa su nueva contraseña para acceder

## Tabla de Evaluadores Creados

Debajo del formulario se muestra una tabla con todos los evaluadores externos registrados:

- Nombre, correo, usuario (parte antes del `@`)
- Fecha de creación, último acceso, estado (Activo/Inactivo)
- Botones de acción: **Editar** y **Restablecer contraseña** (pendientes de implementar)

## Consideraciones de seguridad

- La contraseña temporal se devuelve **una sola vez** en la respuesta del endpoint
- El coordinador debe compartirla por un canal seguro (no queda almacenada en texto plano en la BD, solo el hash)
- El evaluador **debe** cambiar la contraseña en su primer inicio de sesión
- Todos los eventos quedan registrados en el **audit log** del sistema
