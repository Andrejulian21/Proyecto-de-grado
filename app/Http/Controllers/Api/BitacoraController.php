<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EstadoFirma;
use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Notificacion;
use App\Models\Proyecto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BitacoraController extends Controller
{
    /**
     * GET /api/bitacoras?proyecto_id=
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|exists:proyectos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $proyectoId = (int) $request->input('proyecto_id');

        // Issue #38: central rule via BitacoraPolicy (coordinator, director
        // or student of the project; external evaluators denied).
        $this->authorize('view', [Bitacora::class, Proyecto::findOrFail($proyectoId)]);

        // Auto-expire codes before listing
        Bitacora::expireAutomaticamente();

        $bitacoras = Bitacora::where('proyecto_id', $proyectoId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $bitacoras]);
    }

    /**
     * POST /api/bitacoras
     *
     * PR 1 — RF-SIG-01: when a bitacora is created, generate a 6-digit
     * signature code, store its hash and a +2 minute expiration, and
     * return the plain code so the student can share it with the
     * director. The plain text is never persisted.
     */
    public function store(Request $request): JsonResponse
    {
        // PR 4 — RF-WK-03: `semana` is required, in 1..32, and must be
        // unique within the same proyecto. The unique rule is scoped via
        // a where() on proyecto_id so two different projects can both
        // use the same week number.
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|exists:proyectos,id',
            'topic' => 'required|string|max:500',
            'notes' => 'nullable|string',
            'evidence_file' => 'nullable|string|max:500',
            'meeting_date' => 'required|date',
            'semana' => [
                'required',
                'integer',
                'between:1,32',
                Rule::unique('bitacoras', 'semana')->where(
                    'proyecto_id',
                    $request->input('proyecto_id'),
                ),
            ],
            'duration_hours' => 'nullable|numeric|min:0|max:999.99',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Issue #38: central rule via BitacoraPolicy.
        $this->authorize('create', [Bitacora::class, Proyecto::findOrFail((int) $data['proyecto_id'])]);

        // PR 4 — RF-WK-05: validar que no exista otra bitácora en la misma
        // semana calendario (lunes a domingo) para el mismo proyecto.
        $weekStart = Carbon::parse($data['meeting_date'])->startOfWeek();
        $weekEnd = Carbon::parse($data['meeting_date'])->endOfWeek();
        $existingWeek = Bitacora::where('proyecto_id', (int) $data['proyecto_id'])
            ->whereBetween('meeting_date', [$weekStart, $weekEnd])
            ->exists();

        if ($existingWeek) {
            return response()->json([
                'error' => 'Ya existe una bitácora para esta semana. Solo puedes crear una por semana.',
            ], 422);
        }

        // Validar que la semana sea mayor a la maxima existente
        $maxSemana = Bitacora::where('proyecto_id', (int) $data['proyecto_id'])->max('semana');

        if ($maxSemana !== null && (int) $data['semana'] <= (int) $maxSemana) {
            return response()->json([
                'error' => 'No puedes crear una bitacora con una semana anterior o igual a la ultima creada (Semana '.$maxSemana.').',
            ], 422);
        }

        $data['signature_status'] = EstadoFirma::Pendiente->value;

        $bitacora = Bitacora::create($data);

        // PR 1 — RF-SIG-01: generate code and expose only the plain text
        // in the response. The hash lives in the DB.
        $plainCode = $bitacora->generateSignatureCode();

        return response()->json([
            'data' => $bitacora->fresh()->loadMissing('proyecto')->setAttribute(
                'signature_code_plain',
                $plainCode
            ),
        ], 201);
    }

    /**
     * GET /api/bitacoras/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $bitacora = Bitacora::findOrFail($id);

        // Issue #38: central rule via BitacoraPolicy.
        $this->authorize('view', $bitacora);

        // Auto-expire if the signature code has expired
        $bitacora->checkExpiration();

        return response()->json(['data' => $bitacora->fresh()]);
    }

    /**
     * PUT /api/bitacoras/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $bitacora = Bitacora::findOrFail($id);

        // Issue #38: central rule via BitacoraPolicy.
        $this->authorize('update', $bitacora);

        // PR 4 — RF-WK-04: edits are only allowed inside a 15-minute
        // window from creation. The check runs before the firma-status
        // check so unsigned bitacoras older than 15 min are rejected
        // with the same message regardless of their signature state.
        if ($bitacora->created_at->addMinutes(15)->isPast()) {
            return response()->json([
                'error' => 'El tiempo de edición ha expirado (15 minutos desde la creación).',
            ], 422);
        }

        // Auto-expire if the signature code has expired before checking edit rules
        $bitacora->checkExpiration();

        if ($bitacora->fresh()->signature_status->value !== EstadoFirma::Pendiente->value) {
            return response()->json([
                'error' => 'No se puede modificar una bitácora que ya fue firmada.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'topic' => 'sometimes|string|max:500',
            'notes' => 'nullable|string',
            'evidence_file' => 'nullable|string|max:500',
            'meeting_date' => 'sometimes|date',
            'duration_hours' => 'nullable|numeric|min:0|max:999.99',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bitacora->update($validator->validated());

        return response()->json(['data' => $bitacora->fresh()]);
    }

    /**
     * POST /api/bitacoras/{id}/firmar
     *
     * PR 1 — RF-SIG-02: director signs a bitacora with a 6-digit code.
     * The endpoint enforces:
     *  - the bitacora must be in `Pendiente`
     *  - the code must not be expired (otherwise transition to NoFirmada)
     *  - the per-(bitacora, user) rate limiter allows at most 5 failed
     *    attempts inside a 2-minute window. Hitting that ceiling
     *    transitions the bitacora to NoFirmada.
     *  - a successful `Hash::check` transitions to `FirmadaDirector` and
     *    records `director_signed_at`.
     */
    public function firmar(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bitacora = Bitacora::findOrFail($id);

        // Issue #38 / derived #45: only the director of the project may
        // sign. The audit event and flow hardening belong to issue #45.
        $this->authorize('sign', $bitacora);

        // Issue #45: key the attempt budget per (bitacora, user) so a
        // third party cannot exhaust the director's attempts on a
        // bitacora that does not belong to them.
        $throttleKey = 'firmar:'.$id.':'.$request->user()->id;

        if ($bitacora->signature_status !== EstadoFirma::Pendiente) {
            return response()->json([
                'error' => 'La bitácora no está pendiente de firma.',
                'data' => $bitacora->fresh(),
            ], 422);
        }

        // Expiration takes precedence over attempt counting: an expired
        // code is always rejected, regardless of remaining budget.
        if ($bitacora->signature_code_expires_at === null
            || $bitacora->signature_code_expires_at->isPast()) {
            $bitacora->update(['signature_status' => EstadoFirma::NoFirmada->value]);
            RateLimiter::clear($throttleKey);

            return response()->json([
                'error' => 'El código de firma ha expirado.',
                'data' => $bitacora->fresh(),
            ], 422);
        }

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $bitacora->update(['signature_status' => EstadoFirma::NoFirmada->value]);
            RateLimiter::clear($throttleKey);

            return response()->json([
                'error' => 'Se agotaron los intentos de firma.',
                'data' => $bitacora->fresh(),
            ], 422);
        }

        $code = (string) $request->input('code');
        $hash = (string) $bitacora->signature_code;

        if (Hash::check($code, $hash)) {
            $bitacora->update([
                'signature_status' => EstadoFirma::FirmadaDirector->value,
                'director_signed_at' => now(),
            ]);
            RateLimiter::clear($throttleKey);

            // Issue #45: a signature has academic consequences (it
            // certifies that a follow-up meeting occurred and feeds the
            // hours indicator). Leave an immutable trace of who signed
            // and which bitacora, following the AuditEvent pipeline.
            AuditEvent::dispatch(
                $request->user(),
                'bitacora.firmada',
                "Bitácora #{$bitacora->id} firmada por el director del proyecto.",
                [
                    'bitacora_id' => $bitacora->id,
                    'proyecto_id' => $bitacora->proyecto_id,
                ],
            );

            // Notify the project's students that their bitacora is now
            // signed by the director. Kept from the previous multi-step
            // flow so the existing NotificacionTest behavior is
            // preserved under the new TOTP design.
            $proyecto = Proyecto::find($bitacora->proyecto_id);

            if ($proyecto) {
                $estudiantes = $proyecto->estudiantes()->pluck('user_id');

                foreach ($estudiantes as $estudianteId) {
                    Notificacion::create([
                        'user_id' => $estudianteId,
                        'sender_id' => $request->user()->id,
                        'type' => 'bitacora.firmada_director',
                        'title' => 'Bitacora firmada por director',
                        'content' => "El director ha firmado la bitacora '{$bitacora->topic}'.",
                        'sent_at' => now(),
                    ]);
                }
            }

            return response()->json(['data' => $bitacora->fresh()]);
        }

        // Wrong code: register the attempt and, if the limit is now
        // reached, transition to NoFirmada so the student can re-request
        // a new code exactly once.
        RateLimiter::hit($throttleKey, 120); // 2-minute decay

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $bitacora->update(['signature_status' => EstadoFirma::NoFirmada->value]);
            RateLimiter::clear($throttleKey);
        }

        return response()->json([
            'error' => 'Código de firma incorrecto.',
            'data' => $bitacora->fresh(),
        ], 422);
    }

    /**
     * POST /api/bitacoras/{id}/re-solicitar-codigo
     *
     * PR 1 — RF-SIG-03: the student can request a fresh code at most once
     * and only after the bitacora has been transitioned to `NoFirmada`
     * (either by exhausting the 5 attempts or by letting the 2-minute
     * window expire).
     */
    public function reSolicitarCodigo(Request $request, int $id): JsonResponse
    {
        $bitacora = Bitacora::findOrFail($id);

        // Issue #38: central rule via BitacoraPolicy.
        $this->authorize('update', $bitacora);

        if (! $bitacora->canResendCode()) {
            return response()->json([
                'error' => 'No se puede re-solicitar el código en el estado actual.',
                'data' => $bitacora->fresh(),
            ], 422);
        }

        $bitacora->signature_retries = (int) $bitacora->signature_retries + 1;
        // After re-requesting, the bitacora goes back to Pendiente so the
        // new code can actually be used by /firmar. Without this, the
        // freshly-issued code would be unusable because /firmar only
        // accepts bitacoras in Pendiente.
        $bitacora->signature_status = EstadoFirma::Pendiente;
        $bitacora->save();

        $plain = $bitacora->generateSignatureCode();
        // Issue #45: clear only the current user's attempt budget for
        // this bitacora. Each user keeps an independent counter, so a
        // re-request never wipes another account's history.
        RateLimiter::clear('firmar:'.$id.':'.$request->user()->id);

        return response()->json([
            'data' => $bitacora->fresh()->setAttribute('signature_code_plain', $plain),
        ]);
    }

    /**
     * GET /api/admin/proyectos/{proyecto}/bitacoras
     *
     * Devuelve las bitácoras de un proyecto para la vista de directores.
     * Accesible solo por coordinadores (ruta en grupo admin).
     */
    public function porProyecto(int $proyectoId): JsonResponse
    {
        $proyecto = Proyecto::with('director')->findOrFail($proyectoId);

        $bitacoras = Bitacora::where('proyecto_id', $proyectoId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($bitacora) use ($proyecto) {
                return [
                    'id' => $bitacora->id,
                    'topic' => $bitacora->topic,
                    'notes' => $bitacora->notes,
                    'meeting_date' => $bitacora->meeting_date?->toDateString(),
                    'semana' => $bitacora->semana,
                    'duration_hours' => $bitacora->duration_hours,
                    'signature_status' => $bitacora->signature_status?->value,
                    'fecha' => $bitacora->created_at->toISO8601String(),
                    'contenido' => $bitacora->notes ?? '',
                    'firmada' => $bitacora->signature_status->value === EstadoFirma::Completada->value,
                    'director_name' => $proyecto->director?->name ?? 'Sin asignar',
                    'created_at' => $bitacora->created_at->toISO8601String(),
                ];
            });

        return response()->json(['data' => $bitacoras]);
    }

    /**
     * GET /api/director/proyectos/{id}/horas
     *
     * T-014: Total accumulated bitácora hours for a project.
     * Available to the project's director and coordinators.
     */
    public function horas(Request $request, int $id): JsonResponse
    {
        $proyecto = Proyecto::findOrFail($id);
        $user = $request->user();

        // Only the project's director or a coordinator can view hours
        $esCoordinador = $user->role === UserRole::Coordinador;
        $esDirector = $proyecto->director_id === $user->id;

        if (! $esCoordinador && ! $esDirector) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $totalHoras = (float) Bitacora::where('proyecto_id', $id)
            ->sum('duration_hours');

        $totalBitacoras = Bitacora::where('proyecto_id', $id)->count();

        return response()->json([
            'total_horas' => round($totalHoras, 2),
            'total_bitacoras' => $totalBitacoras,
            'proyecto_id' => $id,
        ]);
    }
}
