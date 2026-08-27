<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageController extends Controller
{
    /**
     * Serve a file from the public disk for authenticated users.
     *
     * The client-provided path is validated against directory traversal:
     *  - Null bytes and `..` segments are rejected outright (403).
     *  - realpath() resolves any symlink/junction and the resolved path must
     *    remain inside the disk root (403 otherwise).
     *  - Missing paths or non-file entries resolve to 404.
     */
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $this->rejectTraversal($path);

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $fullPath = realpath($disk->path($path));

        if ($root === false || $fullPath === false) {
            abort(404);
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($fullPath !== $root && ! str_starts_with($fullPath, $rootPrefix)) {
            abort(403);
        }

        if (! is_file($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath);
    }

    /**
     * Reject null bytes and `..` path segments before any filesystem access.
     */
    private function rejectTraversal(string $path): void
    {
        if (str_contains($path, "\0")) {
            abort(403);
        }

        $segments = preg_split('#[/\\\\]+#', $path) ?: [];

        if (in_array('..', $segments, true)) {
            abort(403);
        }
    }
}
