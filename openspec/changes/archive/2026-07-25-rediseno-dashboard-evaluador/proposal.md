# Proposal: Rediseño Dashboard Evaluador

## Intent

Convertir `EvaluadorDashboard` en un panel de inicio (perfil + resumen de actividad + accesos rápidos), dejando la gestión detallada en `/evaluador/evaluaciones`.

## Problem

Tras crear la pantalla Evaluaciones, el dashboard sigue listando proyectos/cards y duplica esa responsabilidad.

## Scope

### In Scope
- Reescribir `EvaluadorDashboard.tsx` (sin lista de evaluaciones).
- Bienvenida personalizada, datos del evaluador, KPIs reales, % avance, empty state, quick links.
- Exponer `created_at` en `GET /api/auth/user` como fecha de ingreso.
- Extender tipo `User` en `useAuth` con `created_at` opcional.

### Out of Scope
- Cambios a EvaluacionesEvaluador.
- Nuevos endpoints de KPIs (reutilizar existentes).
- Otros roles.

## Success Criteria

- [ ] Dashboard no muestra lista de proyectos.
- [ ] Datos personales + KPIs desde API/auth.
- [ ] Accesos a Evaluaciones / Recursos / Anuncios.
- [ ] Build TS OK.
