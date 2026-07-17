# Delta for Director UI (Modified)

## MODIFIED Requirements

### Requirement: DirectorDashboard — Real Data Integration

(Previously: DirectorDashboard mostraba datos mock estáticos y un PhaseStepper)

El `DirectorDashboard.tsx` DEBE reemplazar todos los datos mock por llamadas a `apiFetch()` usando los nuevos endpoints `/api/director/*`. Se DEBE mantener las KPI cards (Proyectos supervisando, Entregas por revisar, Alertas, Aprobadas este mes). El PhaseStepper se ELIMINA.

#### Scenario: Dashboard carga datos reales exitosamente

- GIVEN el director está autenticado
- WHEN carga la ruta `/dashboard/director`
- THEN se disparan 3 llamadas concurrentes: `GET /api/director/kpis`, `GET /api/director/proyectos`, `GET /api/director/entregas`
- AND las KPI cards muestran valores reales del backend
- AND el carrusel horizontal muestra proyectos del director (máx 5, scroll horizontal)
- AND la tabla de entregas muestra las últimas 10 pendientes

#### Scenario: Dashboard en estado de carga

- GIVEN las llamadas API están en flight
- WHEN el dashboard se renderiza
- THEN las KPI cards muestran skeleton/placeholder animado
- AND el carrusel y tabla muestran skeletons

#### Scenario: Dashboard con error de red

- GIVEN una de las llamadas API falla
- WHEN el componente se renderiza
- THEN las secciones que fallaron muestran mensaje de error con botón "Reintentar"
- AND las secciones que sí cargaron se muestran normalmente

#### Scenario: Carrusel horizontal de proyectos

- GIVEN el director tiene 8 proyectos supervisados
- WHEN se renderiza el dashboard
- THEN se muestran máximo 5 proyectos en scroll horizontal (overflow-x-auto)
- AND cada card muestra: `code`, `title`, `estudiantes` (nombres concatenados), `status`

#### Component: DirectorDashboard.tsx

```tsx
// Hooks a usar
import { useState, useEffect } from 'react';
import { apiFetch } from '@/lib/utils';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DataTable } from '@/components/ui/DataTable';

// Datos fetcheados (3 llamadas concurrentes con Promise.all)
const [kpis, setKpis] = useState(null);
const [proyectos, setProyectos] = useState([]);
const [entregas, setEntregas] = useState([]);

// QUITAR: PhaseStepper, MOCK_KPIS, MOCK_PROGRESS, MOCK_DELIVERIES
```

#### Testing

- **Unit**: Verificar que el estado loading/error/data se maneja correctamente con MSW mocks
- **E2E (Playwright)**: Navegar a `/dashboard/director`, verificar que 4 KPI cards tienen valores no-cero, que el carrusel tiene items, que la tabla de entregas tiene filas

---

### Requirement: SupervisionProyectoDirector — Real Data

(Previously: Mostraba un solo proyecto mock con entregas mock y un stepper estático)

El componente DEBE mostrar una lista de cards con TODOS los proyectos supervisados (de `GET /api/director/proyectos`). Al hacer clic en un proyecto, se navega a la vista de detalle (`/supervision/{id}`). El stepper y datos mock se ELIMINAN.

#### Scenario: Lista de proyectos supervisados

- GIVEN el director tiene proyectos en semestre activo
- WHEN carga `/supervision`
- THEN ve una grid de cards (2-3 columnas responsive) con datos reales
- AND cada card muestra: código, título, estudiantes, fase actual, badge de estado

#### Scenario: Click en proyecto navega a detalle

- GIVEN una card de proyecto renderizada
- WHEN el director hace clic
- THEN navega a `/supervision/{proyecto.id}`

#### Scenario: Estado vacío

- GIVEN el director no tiene proyectos
- WHEN carga `/supervision`
- THEN ve ilustración y mensaje: "No tienes proyectos supervisados en el semestre activo"

---

### Requirement: BitacorasDirector — Real Data

(Previously: `BitacorasDirector.tsx` usaba `MOCK_BINNACLES` con datos estáticos)

El componente DEBE cargar bitácoras desde el endpoint real. La tabla, filtros y acciones se mantienen con la misma estructura visual pero con datos del backend.

#### Scenario: Tabla con datos reales

- GIVEN el director tiene bitácoras de sus proyectos
- WHEN carga `/bitacoras`
- THEN la tabla muestra datos de `GET /api/admin/proyectos/{id}/bitacoras` (o endpoint director equivalente)
- AND los filtros (búsqueda, estado) funcionan sobre los datos reales
- AND el botón "Firmar" llama a `POST /api/bitacoras/{id}/firmar`

#### Scenario: Firma exitosa

- GIVEN una bitácora con estado `pendiente`
- WHEN el director hace clic en "Firmar"
- THEN se llama `POST /api/bitacoras/{id}/firmar`
- AND la fila se actualiza a `signature_status: "Completada"` sin recargar la página

---

### Requirement: DetalleFirmaBitacora y RevisionBitacora — Real Data

(Previously: Usaban `getBitacoraDetail()` del mock)

Estos componentes DEBEN cargar datos de `GET /api/bitacoras/{id}` y permitir firma vía `POST /api/bitacoras/{id}/firmar`.

> **Cambio de flujo**: La firma ahora es inmediata (cambia `signature_status` a `Completada`). El TOTP se omite en este sprint. Se debe eliminar el `TOTPInput` y reemplazar con un botón "Firmar" directo.

#### Scenario: Firma sin TOTP

- GIVEN el director ve el detalle de una bitácora
- WHEN hace clic en "Firmar Bitácora"
- THEN se llama `POST /api/bitacoras/{id}/firmar` directamente
- AND la UI muestra confirmación de firma exitosa

---

### Requirement: RevisionEntregaDirector — Conectar a Endpoint Real

(Previously: Usaba mock para rúbrica, decisión, y envío con `setTimeout`)

El componente DEBE:
1. Cargar datos de la entrega desde `GET /api/admin/entregas/{id}`
2. Cargar versiones desde `GET /api/entregas/{id}/versiones`
3. Enviar revisión a `PUT /api/admin/entregas/{id}/revisar` con nota 0.0–5.0

#### Scenario: Revisar entrega con endpoint real

- GIVEN el director abre la revisión de una entrega `id=5`
- WHEN asigna nota (ej: 4.5), selecciona "Aprobar", escribe comentarios y envía
- THEN se llama `PUT /api/admin/entregas/5/revisar` con `{status: "aprobada", consolidated_grade: 4.5, director_notes: "..."}`
- AND la UI muestra el estado de éxito

#### Scenario: Descarga de documento

- GIVEN una versión de documento con `file_path`
- WHEN el director hace clic en "Descargar"
- THEN se abre la URL del archivo (`/storage/{file_path}`) en nueva pestaña o descarga directa

---

### Requirement: Recursos — Fix Descarga + Rediseño UI

(Previously: `Recursos.tsx` y `RecursoDetalle.tsx` no usaban `file_path`/`link` de la API, el botón "Descargar" no funcionaba)

Ambos componentes DEBEN:
1. Incluir `file_path` y `link` en sus interfaces y mapearlos desde la API
2. Mostrar botón de descarga funcional cuando `file_path` existe (href a `/storage/{file_path}`)
3. Mostrar enlace externo cuando `link` existe (abrir en nueva pestaña)
4. Mostrar metadata real del archivo (tamaño si está disponible, tipo)
5. Mejorar el diseño visual (cards con mejor jerarquía, bordes por categoría, estados vacíos mejorados)

#### Scenario: Descarga de archivo desde card

- GIVEN un recurso con `file_path: "recursos/guia.pdf"`
- WHEN el usuario hace clic en "Descargar" en la card
- THEN navega a `/storage/recursos/guia.pdf` y descarga el archivo

#### Scenario: Enlace externo desde card

- GIVEN un recurso con `link: "https://normasapa.com/"`
- WHEN el usuario hace clic en "Descargar"
- THEN se abre `https://normasapa.com/` en nueva pestaña

#### Scenario: Botón descargar en detalle del recurso

- GIVEN un recurso con `file_path` existente
- WHEN el usuario está en la página de detalle `/recursos/{id}`
- THEN el botón "Descargar" en la sidebar descarga el archivo
- AND muestra el tamaño real o "Documento" como metadata
