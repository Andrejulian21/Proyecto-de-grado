# Sistema Centralizado de Proyectos de Grado

Plataforma web para gestionar proyectos de grado de Ingeniería de Sistemas en la **UNAB** (Universidad Autónoma de Bucaramanga).

Permite a coordinadores, directores, estudiantes y evaluadores externos gestionar el ciclo de vida completo: inscripción de proyectos, entregas con versionado, bitácoras firmadas, evaluación y generación de reportes.

## Stack

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel 11 + PHP 8.4 |
| Frontend | React 19 + Vite 8 + TypeScript |
| UI | Tailwind CSS v4 + shadcn/ui |
| Base de datos | PostgreSQL 16 / SQLite |
| Auth | Sanctum cookie SPA + Google OAuth |
| Testing | Pest (PHP) + Playwright (E2E) |

## Funcionalidades

### Coordinador
- Gestión de proyectos, usuarios y whitelist
- Asignación de evaluadores externos
- Configuración de entregas y porcentajes de nota
- Consulta de notas ponderadas por fase (Proyecto de Grado 1 y 2)
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
- Asistente de orientación con IA

### Evaluador Externo
- Evaluación de presentaciones (anteproyecto / final)
- Calificación con rúbrica

## Arquitectura

```
app/
├── Actions/          # Casos de uso
├── Enums/            # Enums PHP (UserRole, FaseProyecto, EstadoEntrega)
├── Http/
│   ├── Controllers/  # Controladores API
│   ├── Middleware/    # SingleSession, Activity, Role
│   └── Requests/     # Validación de formularios
├── Models/           # Modelos Eloquent
├── Policies/         # Políticas de autorización
└── Services/         # Lógica de negocio (AI, Evaluación, Seguimiento)

resources/js/
├── components/       # Componentes React compartidos
├── hooks/            # Hooks personalizados
├── pages/            # Páginas por rol
│   ├── coordinador/
│   ├── director/
│   ├── estudiante/
│   ├── evaluador/
│   └── shared/
└── types/            # Definiciones TypeScript
```

## Proyecto Académico

Desarrollado como proyecto de grado del Programa de Ingeniería de Sistemas — UNAB.
