<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Append-only audit log viewer (T-024).
 *
 *   GET /api/admin/audit-logs  → paginated, filterable list
 *
 * Gated by `role:Coordinador` — only Coordinadores may view the
 * audit trail.
 *
 * The underlying table is immutable: no UPDATE, DELETE, or soft-delete
 * routes are exposed. Every mutating endpoint in the system writes
 * rows through the AuditEvent/WriteAuditLog event pipeline instead.
 */
class AuditLogController extends Controller
{
    /**
     * GET /api/admin/audit-logs
     *
     * Returns a paginated, filterable list of audit log entries.
     *
     * Query parameters:
     *   - user_id     (int, optional)     — filter by user
     *   - action      (string, optional)  — filter by action type
     *   - date_from   (string, optional)  — ISO 8601 / Y-m-d inclusive start
     *   - date_to     (string, optional)  — ISO 8601 / Y-m-d exclusive end
     *   - per_page    (int, optional)     — page size (default 50, max 200)
     *
     * Results are ordered by created_at DESC, id DESC.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->ordered();

        // -- optional filters -----------------------------------------

        if ($request->filled('user_id')) {
            $query->forUser((int) $request->query('user_id'));
        }

        if ($request->filled('action')) {
            $query->forAction((string) $request->query('action'));
        }

        if ($request->filled('date_from')) {
            $from = Carbon::parse((string) $request->string('date_from'));
            $query->betweenDates($from, null);
        }

        if ($request->filled('date_to')) {
            $to = Carbon::parse((string) $request->string('date_to'));
            $query->betweenDates(null, $to);
        }

        $perPage = min((int) $request->query('per_page', 50), 200);

        return response()->json($query->paginate($perPage));
    }
}
