# Principios de diseño — Sistema de Proyectos de Grado UNAB

> Las leyes de producto. Definen el CARÁCTER del sistema.
> Resumen: **funcionalidad primero, seguridad siempre, UI intuitiva.**

## 1. Funcionalidad > estética

El sistema resuelve un problema real de gestión académica. Cada pantalla, botón y flujo existe
porque un usuario lo necesita para hacer su trabajo. No hay decoración sin propósito.

- Cada feature responde a una HU y RF documentados.
- Los wireframes de Open Design son la fuente de verdad visual, pero si hay tradeoff entre
  funcionalidad y pixel-perfect, gana la funcionalidad.

## 2. Seguridad por capas (defense in depth)

El sistema maneja datos académicos sensibles y firmas digitales. La seguridad no es negociable.

- **Auth:** triple validación en OAuth (hd claim + email suffix + whitelist).
- **RBAC:** 4 roles con validación server-side. El frontend NO es confiable para autorización.
- **Auditoría:** log inmutable, append-only, 5 años de retención.
- **Firmas:** TOTP (RFC 6238) para integridad de bitácoras.
- **Sanitización:** todo input se valida en los bordes del sistema.

## 3. SDD nativo (Spec-Driven Development)

El código sigue a la especificación, no al revés. No se implementa nada que no esté spec'teado.

- Cada change pasa por: `proposal → spec → design → tasks → apply → verify → archive`.
- Las specs usan formato EARS para criterios de aceptación verificables.
- Gates duros: no implementar sin tasks aprobado, no archivar sin verify pasado.

## 4. TDD estricto

- Test que falla → código mínimo para que pase → refactor.
- 151 tests baseline. No se baja cobertura.
- `vendor/bin/pest` verde antes de cada commit.

## 5. Mobile-first responsive

El coordinador, director y estudiante acceden desde cualquier dispositivo.

- Layout adaptable: 375px (móvil) → 768px (tablet) → 1280px+ (desktop).
- Sidebar colapsable, tablas con scroll horizontal en móvil.
- Skip-link, aria-labels, focus-visible, contraste WCAG AA.

## 6. UI en español, código en inglés

- El usuario final ve todo en español: labels, mensajes, errores, notificaciones.
- El código fuente está en inglés: variables, funciones, clases, comentarios técnicos.
- Las rutas de API usan kebab-case en español (`/api/admin/usuarios`, `/api/admin/evaluadores`).

## 7. Automatización de calidad

- Hooks de lint + tests antes de commit.
- Build frontend verificado con `npm run build`.
- Auditoría automática de eventos del sistema.

## 8. El cronograma es realista, no optimista

El plan original de 12 sprints (60 días) se comprimió a 7 días con 1 desarrollador.
Esto implica sacrificios explícitos documentados en el ROADMAP.

| Prioridad | Se sacrifica primero |
|-----------|---------------------|
| 1 | Chat interno (Reverb WebSocket) |
| 2 | Módulos IA si Azure OpenAI no está listo |
| 3 | TOTP (reemplazo por firma manual) |
| **Nunca** | Auth, Proyectos, Entregas, Bitácoras, Evaluación, Reportes |
