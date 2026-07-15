<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecursoInformativo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RecursoController extends Controller
{
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

        return response()->json(['data' => $recurso->fresh()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'file' => 'nullable|file|max:10240', // max 10MB
            'link' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->safe()->except('file');
        $data['author_id'] = $request->user()->id;

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->storeAs('recursos', $request->file('file')->getClientOriginalName(), 'public');
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
            'file' => 'nullable|file|max:10240', // max 10MB
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
            $data['file_path'] = $request->file('file')->storeAs('recursos', $request->file('file')->getClientOriginalName(), 'public');
        }

        $recurso->update($data);

        return response()->json(['data' => $recurso->fresh()]);
    }

    public function destroy(RecursoInformativo $recurso): JsonResponse
    {
        $recurso->delete();

        return response()->json(['message' => 'Recurso eliminado']);
    }
}
