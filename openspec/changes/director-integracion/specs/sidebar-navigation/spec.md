# Delta for Sidebar Navigation (Modified)

## MODIFIED Requirements

### Requirement: Sidebar — Director Nav Trim + Active State Fix

(Previously: Director sidebar tenía 7 entradas incluyendo "Bitácoras" y "Bitácoras Proyectos" como rutas separadas. El active state no funcionaba correctamente porque `to: '/'` coincidía con todas las rutas.)

El sidebar del rol Director DEBE:
1. Eliminar las entradas "Bitácoras" (`/bitacoras`) y "Bitácoras Proyectos" (`/bitacoras/proyectos`)
2. Corregir el active state para que `to: '/'` solo se active en la ruta exacta del dashboard
3. La entrada "Supervisión" (`/supervision/:proyectoId`) DEBE activarse para cualquier ruta que comience con `/supervision`

#### Scenario: Sidebar Director muestra solo 5 entradas

- GIVEN un usuario con rol Director
- WHEN se renderiza el sidebar
- THEN solo muestra: Panel, Supervisión, Evaluaciones, Anuncios, Recursos
- AND "Bitácoras" y "Bitácoras Proyectos" NO aparecen

#### Scenario: Active state correcto en Panel

- GIVEN el director está en `/dashboard/director`
- WHEN se renderiza el sidebar
- THEN "Panel" está activo (highlighted)
- AND al navegar a `/supervision/1`, "Panel" deja de estar activo

#### Scenario: Active state en Supervisión

- GIVEN el director está en `/supervision/1`
- WHEN se renderiza el sidebar
- THEN "Supervisión" está activo
- AND también está activo en `/supervision/1/entrega/5`

### Implementation

**Archivo**: `resources/js/components/layout/Sidebar.tsx`

```tsx
// Config Director modificada:
Director: [
    { to: '/dashboard/director', label: 'Panel', icon: LayoutDashboard },
    { to: '/supervision',      label: 'Supervisión', icon: FolderKanban },
    { to: '/evaluaciones',     label: 'Evaluaciones', icon: ClipboardCheck },
    { to: '/anuncios',         label: 'Anuncios', icon: Megaphone },
    { to: '/recursos',         label: 'Recursos', icon: FolderKanban },
],

// Active state para rutas con prefijo:
// Añadir '/supervision' a la lista de rutas con `end` para que use coincidencia exacta
// O usar una función de matching custom:
//   isActive: (match, location) => location.pathname.startsWith('/supervision')
```

> **Nota técnica**: Para que "Supervisión" se active en subrutas, se debe usar `isActive` en lugar de `end`. `NavLink` de React Router acepta una función `isActive`.

#### Scenario: Testing

- **Unit (Vitest)**: Renderizar sidebar con `MemoryRouter`, verificar que las entradas eliminadas no existen, verificar active states con diferentes ubicaciones
- **E2E (Playwright)**: Navegar entre páginas del director, verificar visualmente que el item correcto está resaltado
