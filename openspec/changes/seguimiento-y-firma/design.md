# Diseño Técnico: Seguimiento y Firma

## Enfoque Técnico
Cuatro PRs encadenados que añaden firma TOTP-like a bitácoras, tablero de seguimiento semestral para coordinadores y bitácoras semanales con ventana de edición. Laravel 11 + React 18, persistencia PostgreSQL, eager-load en queries de seguimiento.

## Decisiones de Arquitectura

| Decisión | Alternativas | Rationale |
|---|---|---|
| `signature_retries` en BD (no memoria) | Contador en request/cache | Re-solicitud es stateful entre requests; Redis es overkill para 1 int |
| Upsert observaciones por `UNIQUE(proyecto, semestre, fase)` | Tabla histórica | Solo necesitamos 1 observación viva por fase; evita JOIN complejo |
| Backfill semana por `created_at` en migración | Job separado | Set pequeño; Laravel `DB::raw('row_number()')` con window function en PG resuelve en una query |
| Edición 15 min guardada en backend | Solo frontend | Previene bypass directo a la API |

## Flujo de Datos

```
Estudiante → POST /bitacoras → genera código 6 dígitos + hash + expiración
  → modal muestra código plain text
Director → POST /{id}/firmar → Hash::check() + intentos ≤ 5
  → éxito: FirmadaDirector | fallo: NoFirmada
Coordinador → GET /admin/seguimiento/semestre/{id}
  → SeguimientoService::calcularEstadoEntrega() + contarBitacorasPorGrupo()
  → TablaSeguimiento.tsx renderiza columnas dinámicas
```

---

## PR 1 — Firma por Clave Dinámica

### Migración

```php
Schema::table('bitacoras', function (Blueprint $table) {
    $table->string('signature_code', 255)->nullable()->after('signature_status');
    $table->timestamp('signature_code_expires_at')->nullable();
    $table->unsignedTinyInteger('signature_retries')->default(0);
});
```

### Modelo `Bitacora.php`

- `fillable`: añadir `signature_code`, `signature_code_expires_at`, `signature_retries`.
- `casts`: añadir `signature_code_expires_at => 'datetime'`.
- `hasValidSignature(): bool` → `signature_code_expires_at !== null && now() <= signature_code_expires_at`.
- `generateSignatureCode(): string` → `$plain = random_int(100000, 999999); $this->signature_code = Hash::make($plain); $this->signature_code_expires_at = now()->addMinutes(2); return $plain;`.
- `canResendCode(): bool` → `signature_status === EstadoFirma::NoFirmada && signature_retries < 1`.

### Controlador `BitacoraController.php`

- **`store`**: tras validar, llamar `$bitacora->generateSignatureCode()` y devolver `signature_code_plain` en la respuesta JSON (campo efímero, no persistido).
- **`firmar(Request, $id)`**:
  1. Buscar bitácora; verificar `signature_status === Pendiente`.
  2. Verificar `now() <= signature_code_expires_at`; si no, transicionar a `NoFirmada` y retornar 422.
  3. Verificar `Hash::check($request->code, $bitacora->signature_code)`.
  4. Si falla: contar intento implícito (por ahora sin columna de intentos de firma; el spec dice contador en memoria durante request con máximo 5). **Nota técnica:** usar `RateLimiter::hit("firmar:{$id}")` con 5 intentos por ventana de 2 min para no complicar el schema.
  5. Si éxito: `signature_status = FirmadaDirector`, `director_signed_at = now()`.
  6. Si se agotan intentos o expira: `signature_status = NoFirmada`.
- **`reSolicitarCodigo(Request, $id)`**:
  1. Verificar `canResendCode()`.
  2. Incrementar `signature_retries`.
  3. Regenerar código, nueva expiración (+2 min), devolver plain text.

### Rutas `api.php`

```php
Route::post('/bitacoras/{id}/firmar', [BitacoraController::class, 'firmar'])
    ->whereNumber('id');
Route::post('/bitacoras/{id}/re-solicitar-codigo', [BitacoraController::class, 'reSolicitarCodigo'])
    ->whereNumber('id');
```

### Frontend

| Componente | Props / Comportamiento |
|---|---|
| `SignatureCodeDisplay` | `{ code: string; expiresAt: Date }` — modal con countdown de 2 min, botón "Solicitar nuevo" si expiró (llama a re-solicitud). |
| `SignatureCodeInput` | `{ bitacoraId: number; onSuccess: () => void }` — 6 inputs o 1 input numérico, envía a `firmar`, muestra intentos restantes. |
| `NuevaBitacora.tsx` | Al recibir 201 del `store`, si `signature_code_plain` existe, abrir `<SignatureCodeDisplay>`. |
| Detalle director | Mostrar `<SignatureCodeInput>` cuando `signature_status === Pendiente`. |

---

## PR 2 — Backend de Seguimiento

### Migración

```php
Schema::create('seguimiento_observaciones', function (Blueprint $table) {
    $table->id();
    $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
    $table->foreignId('semestre_id')->constrained('semestres')->cascadeOnDelete();
    $table->string('fase', 50); // anteproyecto, presentacion_anteproyecto, desarrollo, presentacion_final
    $table->text('observacion')->nullable();
    $table->timestamps();
    $table->unique(['proyecto_id', 'semestre_id', 'fase']);
});
```

### Modelo `SeguimientoObservacion.php`

```php
class SeguimientoObservacion extends Model
{
    protected $fillable = ['proyecto_id', 'semestre_id', 'fase', 'observacion'];
    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }
    public function semestre(): BelongsTo { return $this->belongsTo(Semestre::class); }
}
```

### Controlador `SeguimientoController.php`

- **`porSemestre(Semestre $semestre)`**:
  ```php
  $proyectos = $semestre->proyectos()->with(['entregas', 'bitacoras'])->get();
  return response()->json([
      'data' => $proyectos->map(fn ($p) => [
          'proyecto' => $p->only(['id','code','title']),
          'estudiantes' => $p->estudiantes->pluck('name'),
          'director' => $p->director?->name,
          'entregas' => $p->entregas->map(fn ($e) => [
              'id' => $e->id,
              'title' => $e->title,
              'status' => app(SeguimientoService::class)->calcularEstadoEntrega($e, $p->id),
              'due_date' => $e->due_date,
          ]),
          'bitacoras' => app(SeguimientoService::class)->contarBitacorasPorGrupo($p->id),
          'observaciones' => SeguimientoObservacion::where('proyecto_id', $p->id)
              ->where('semestre_id', $semestre->id)
              ->get()
              ->keyBy('fase'),
      ])
  ]);
  ```
- **`guardarObservacion(Request $request)`**:
  ```php
  $data = $request->validate([
      'proyecto_id' => 'required|exists:proyectos,id',
      'semestre_id' => 'required|exists:semestres,id',
      'fase' => 'required|in:anteproyecto,presentacion_anteproyecto,desarrollo,presentacion_final',
      'observacion' => 'nullable|string',
  ]);
  $obs = SeguimientoObservacion::updateOrCreate(
      Arr::only($data, ['proyecto_id', 'semestre_id', 'fase']),
      ['observacion' => $data['observacion']]
  );
  return response()->json(['data' => $obs]);
  ```

### Servicio `SeguimientoService.php`

```php
class SeguimientoService
{
    public function calcularEstadoEntrega(Entrega $entrega, int $proyectoId): string
    {
        $tieneVersion = $entrega->versiones()->exists(); // o relación definida
        if ($tieneVersion) return 'entregada';
        if ($entrega->due_date < now()) return 'no_entrego';
        return 'pendiente';
    }

    public function contarBitacorasPorGrupo(int $proyectoId): array
    {
        $base = Bitacora::where('proyecto_id', $proyectoId);
        return [
            'grupo_a' => (clone $base)->whereBetween('semana', [1, 16])->count(),
            'grupo_b' => (clone $base)->whereBetween('semana', [17, 32])->count(),
        ];
    }
}
```

### Rutas `api.php`

```php
Route::middleware(['auth:sanctum', 'single_session', 'activity', 'role:Coordinador'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/seguimiento/semestre/{semestre}', [SeguimientoController::class, 'porSemestre'])
            ->whereNumber('semestre');
        Route::put('/seguimiento/observaciones', [SeguimientoController::class, 'guardarObservacion']);
    });
```

---

## PR 3 — Frontend de Seguimiento

### Componentes

| Componente | Responsabilidad |
|---|---|
| `SeguimientoSemestre.tsx` | Página principal. Carga semestres, renderiza selector + `TablaSeguimiento`. Usa `useState` para `semestreId` y `seguimientoData`. |
| `SemestreSelector.tsx` | `Select` con semestres ordenados: `activo === true` primero. OnChange emite `semestreId`. |
| `TablaSeguimiento.tsx` | Recibe `data[]` de PR2. Renderiza tabla con fila por proyecto. Columnas dinámicas desde `entregas`. |
| `ColumnaEntrega.tsx` | Recibe `status: 'entregada' \| 'pendiente' \| 'no_entrego'`. Renderiza badge con icono (✅ verde / ⏳ amarillo / ❌ rojo). |
| `ObservacionField.tsx` | Recibe `proyectoId`, `semestreId`, `fase`, `valor`. Textarea con `onBlur` → debounce 800 ms → `PUT /api/admin/seguimiento/observaciones`. |

### Integración en `GestionAlertas.tsx`

Reemplazar el estado de tabs actual:
```tsx
const [activeTab, setActiveTab] = useState<'seguimiento' | 'alertas'>('seguimiento');
```
Renderizar `<SeguimientoSemestre />` cuando `activeTab === 'seguimiento'`, manteniendo la lista de alertas en el otro tab.

### Estado visual

| Estado | Color Tailwind | Icono lucide-react |
|---|---|---|
| entregada | `bg-green-100 text-green-700` | `CheckCircle2` |
| pendiente | `bg-yellow-100 text-yellow-700` | `Clock` |
| no_entrego | `bg-red-100 text-red-700` | `XCircle` |

---

## PR 4 — Semana, Edición 15 min, Renombre

### Migración

```php
Schema::table('bitacoras', function (Blueprint $table) {
    $table->unsignedTinyInteger('semana')->nullable()->after('meeting_date');
    $table->unique(['proyecto_id', 'semana']);
});
```

**Backfill** (mismo archivo migración, método `up` posterior a la alteración):
```php
DB::statement(<<-'SQL'
    WITH numbered AS (
        SELECT id, ROW_NUMBER() OVER (PARTITION BY proyecto_id ORDER BY created_at) AS rn
        FROM bitacoras
        WHERE semana IS NULL
    )
    UPDATE bitacoras b
    SET semana = n.rn
    FROM numbered n
    WHERE b.id = n.id;
SQL);
```

### Modelo `Bitacora.php`

- Añadir `semana` a `fillable` y `casts` (`'integer'`).
- Validación en `boot` o FormRequest: `semana` entre 1 y 32.

### Controlador `BitacoraController.php`

- **`store`**: validar `semana` requerido, `integer`, `between:1,32`, y regla custom `unique:bitacoras,semama,NULL,id,proyecto_id,' . $request->proyecto_id`.
- **`update`**: al inicio del método, verificar:
  ```php
  if ($bitacora->created_at->addMinutes(15)->isPast()) {
      return response()->json(['error' => 'Ventana de edición cerrada (15 min).'], 422);
  }
  ```

### Frontend

| Cambio | Archivo / Patrón |
|---|---|
| Selector semana | `NuevaBitacora.tsx`: añadir `<select>` 1-32 después de duración, mapear a `semana`. |
| Contador edición | Páginas de detalle/lista: mostrar `createdAt + 15min - now()` si > 0; ocultar botón Editar si expiró. |
| Renombre UI | Buscar/reemplazar literal `"Descripcion detallada"` / `"descripcion detallada"` en todas las páginas de bitácora (`NuevaBitacora.tsx`, detalle, edición). El campo `notes` en API sigue igual. |

---

## Cambios en Archivos

| Archivo | Acción | PR |
|---|---|---|
| `database/migrations/2026_07_28_000001_add_signature_to_bitacoras.php` | Crear | 1 |
| `app/Enums/EstadoFirma.php` | Modificar — añadir `NoFirmada` | 1 |
| `app/Models/Bitacora.php` | Modificar — campos firma, métodos helper | 1, 4 |
| `app/Http/Controllers/Api/BitacoraController.php` | Modificar — store, firmar, reSolicitarCodigo, update | 1, 4 |
| `routes/api.php` | Modificar — rutas firma + re-solicitud | 1 |
| `resources/js/components/SignatureCodeDisplay.tsx` | Crear | 1 |
| `resources/js/components/SignatureCodeInput.tsx` | Crear | 1 |
| `resources/js/pages/estudiante/NuevaBitacora.tsx` | Modificar — mostrar código, semana, renombre | 1, 4 |
| `database/migrations/2026_07_28_000002_create_seguimiento_observaciones.php` | Crear | 2 |
| `app/Models/SeguimientoObservacion.php` | Crear | 2 |
| `app/Http/Controllers/Admin/SeguimientoController.php` | Crear | 2 |
| `app/Services/SeguimientoService.php` | Crear | 2 |
| `routes/api.php` | Modificar — rutas seguimiento | 2 |
| `resources/js/pages/coordinador/SeguimientoSemestre.tsx` | Crear | 3 |
| `resources/js/pages/coordinador/GestionAlertas.tsx` | Modificar — tabs | 3 |
| `database/migrations/2026_07_28_000003_add_semana_to_bitacoras.php` | Crear | 4 |

## Estrategia de Testing

| Capa | Qué probar | Cómo |
|---|---|---|
| Unit PHP | `generateSignatureCode`, `canResendCode`, `calcularEstadoEntrega`, backfill semana | Pest con factories |
| Feature PHP | `POST /firmar` (éxito, expiración, 5 intentos, re-solicitud), `PUT /bitacoras/{id}` con ventana 15 min | Pest con `travelTo()` |
| Integration PHP | Endpoint seguimiento devuelve estructura JSON exacta, observaciones upsert | Pest, assertJsonStructure |
| E2E | Estudiante crea bitácora → ve código → director firma; coordinador ve seguimiento | Playwright |

## Matriz de Amenazas

N/A — no hay routing de shell, subprocessos, automatización VCS/PR, clasificación de ejecutables ni integración de procesos. Todos los endpoints son rutas HTTP estándar de Laravel.

## Migración / Rollout

1. Aplicar PR 1 en staging; verificar que `signature_code_plain` solo viaja en respuesta de creación.
2. PR 4 requiere backfill semana: ejecutar migración en copia de prod antes de desplegar para validar tiempos.
3. PR 2 y 3 son puro agregado; pueden desplegarse sin downtime.

## Preguntas Abiertas

- [ ] ¿Existe relación `versiones` en `Entrega` o se llama `VersionDocumento`? Afecta `calcularEstadoEntrega`.
- [ ] ¿El director firma desde una vista propia (`/director/proyectos/{id}/bitacoras`) o desde la vista del estudiante?
