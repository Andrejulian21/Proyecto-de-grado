<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anuncio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnuncioController extends Controller
{
    public function index(): JsonResponse
    {
        $anuncios = Anuncio::where('is_active', true)
            ->orderByDesc('published_at')
            ->get();

        return response()->json(['data' => $anuncios]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'content' => 'required|string',
            'published_at' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['author_id'] = $request->user()->id;
        $data['published_at'] ??= now();

        $anuncio = Anuncio::create($data);

        return response()->json(['data' => $anuncio], 201);
    }

    public function update(Request $request, Anuncio $anuncio): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'string|max:500',
            'content' => 'string',
            'published_at' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $anuncio->update($validator->validated());

        return response()->json(['data' => $anuncio->fresh()]);
    }

    public function destroy(Anuncio $anuncio): JsonResponse
    {
        $anuncio->delete();

        return response()->json(['message' => 'Anuncio eliminado']);
    }
}
