<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecursoInformativo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RecursoController extends Controller
{
    /**
     * Whitelist of file types a coordinator may upload as an informative
     * resource. Mirrors the entregas upload whitelist (issue #56, defect 1):
     * resources are served from the public disk, so only document MIME types
     * that are not script-capable in a browser context are accepted.
     *
     * @var list<string>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function index(): JsonResponse
    {
        $recursos = RecursoInformativo::with('author:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $recursos]);
    }

    public function show(RecursoInformativo $recurso): JsonResponse
    {
        $recurso->load('author:id,name');
        $recurso->increment('access_count');

        return response()->json(['data' => $recurso->fresh()->load('author:id,name')]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'file' => [
                'nullable',
                'file',
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
                'max:10240', // max 10MB
            ],
            'link' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->safe()->except('file');
        $data['author_id'] = $request->user()->id;

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->storeAs('recursos', $this->storedFileName($request->file('file')), 'public');
        }

        $recurso = RecursoInformativo::create($data);

        return response()->json(['data' => $recurso], 201);
    }

    public function update(Request $request, RecursoInformativo $recurso): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'string|max:500',
            'category' => 'string|max:100',
            'description' => 'nullable|string',
            'file' => [
                'nullable',
                'file',
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
                'max:10240', // max 10MB
            ],
            'link' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->safe()->except('file');

        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($recurso->file_path) {
                Storage::disk('public')->delete($recurso->file_path);
            }
            $data['file_path'] = $request->file('file')->storeAs('recursos', $this->storedFileName($request->file('file')), 'public');
        }

        $recurso->update($data);

        return response()->json(['data' => $recurso->fresh()]);
    }

    public function destroy(RecursoInformativo $recurso): JsonResponse
    {
        $recurso->delete();

        return response()->json(['message' => 'Recurso eliminado']);
    }

    /**
     * Generate a server-side file name for a resource (issue #56, defect 1).
     *
     * The extension is derived from the actual detected type (which has already
     * passed the `mimetypes` whitelist), and the base is normalized with a slug
     * plus a timestamp. The client-provided name is never used as the stored
     * file name, which prevents path/name based attacks and keeps the stored
     * name predictable and safe to serve from the public disk.
     */
    private function storedFileName(UploadedFile $file): string
    {
        $base = Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME));

        return sprintf(
            '%s_%s.%s',
            $base !== '' ? $base : 'recurso',
            now()->format('Ymd_His'),
            $file->guessExtension() ?: 'pdf'
        );
    }
}
