# Sistema Centralizado de Proyectos de Grado

Plataforma web para gestionar proyectos de grado de Ingeniería de Sistemas en la **UNAB** (Universidad Nacional Abierta y a Distancia). Permite a coordinadores, directores, estudiantes y evaluadores externos gestionar el ciclo de vida completo: inscripción, entregas con versionado, bitácoras firmadas, evaluación y generación de reportes.

## Stack

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel 11 + PHP 8.4 |
| Frontend | React 19 + Vite 8 + TypeScript |
| UI | Tailwind CSS v4 + shadcn/ui |
| Base de datos | PostgreSQL 16 (producción) / SQLite (desarrollo) |
| Auth | Sanctum cookie SPA + Google OAuth |
| Testing | Pest (PHP) + Playwright (E2E) |
| Deploy | Docker + Coolify |

## Requisitos

- PHP 8.4+
- Node.js 18+ con npm/pnpm
- Composer
- SQLite (desarrollo) o PostgreSQL (producción)

## Instalación local

```bash
# Clonar
git clone https://github.com/Andrejulian21/Proyecto-de-grado.git
cd Proyecto-de-grado

# Dependencias
composer install
npm install

# Entorno
cp .env.example .env
php artisan key:generate

# Base de datos
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed

# Storage link
php artisan storage:link

# Servidores (en terminales separadas)
php artisan serve --port=8000
npm run dev
```

- App: http://localhost:8000
- Vite: http://localhost:5173

## Usuarios de prueba

| Correo | Contraseña | Rol |
|--------|-----------|-----|
| `julian21arteaga@gmail.com` | `Pruebas123!` | Director |
| `juliarteaga938@gmail.com` | `Pruebas123!` | Estudiante |
| `evaluador.externo@test.com` | `password` | Evaluador Externo |
| `jarteaga145@unab.edu.co` | — (Google OAuth) | Coordinador |

## Funcionalidades

### Coordinador
- Gestión de proyectos, usuarios y whitelist
- Asignación de evaluadores externos
- Configuración de entregas y porcentajes de nota
- Consulta de notas ponderadas por fase (PG1/PG2)
- Exportación de notas a Excel
- Seguimiento por semestre
- Anuncios y recursos informativos

### Director
- Supervisión de proyectos asignados
- Revisión y calificación de entregas
- Firma de bitácoras con código
- Consulta de notas de sus proyectos

### Estudiante
- Subida de documentos por versión (máx. 4)
- Bitácoras semanales con firma
- Consulta de notas de su proyecto
- Asistente de orientación (IA)

### Evaluador Externo
- Evaluación de presentaciones (anteproyecto / final)
- Calificación con rúbrica

## Estructura del proyecto

```
app/
├── Actions/          # Use cases (ReviewEntrega, SolicitarEntrega, etc.)
├── Console/          # Artisan commands
├── Enums/            # PHP enums (UserRole, FaseProyecto, EstadoEntrega, etc.)
├── Http/
│   ├── Controllers/  # API controllers
│   ├── Middleware/    # SingleSession, Activity, Role
│   └── Requests/     # Form request validation
├── Models/           # Eloquent models
├── Policies/         # Authorization policies
├── Services/         # Business logic (AI, Evaluation, Seguimiento, etc.)
└── Events/           # Audit events

resources/js/
├── components/       # Shared React components
├── hooks/            # Custom hooks (useAuth, useEntregas, etc.)
├── pages/            # Page components by role
│   ├── coordinador/
│   ├── director/
│   ├── estudiante/
│   ├── evaluador/
│   └── shared/
└── types/            # TypeScript type definitions

tests/
├── Feature/          # Feature tests (Pest)
└── Unit/             # Unit tests
```

## Testing

```bash
# Ejecutar todos los tests
vendor/bin/pest

# Tests específicos
vendor/bin/pest --filter="EntregaPeso"
vendor/bin/pest --filter="ConsultaNotas"
```

## Despliegue (Coolify)

El proyecto incluye `Dockerfile` multi-stage para producción. Coolify detecta automáticamente el Dockerfile y despliega con Nginx + PHP-FPM.

Variables de entorno necesarias en Coolify:
- `APP_KEY`, `APP_URL`, `APP_ENV=production`
- `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `SESSION_DRIVER=database`
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (opcional, para OAuth)

## Licencia

Proyecto académico — UNAB Ingeniería de Sistemas.
