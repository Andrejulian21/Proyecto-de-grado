<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('director_academic_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('research_lines')->nullable();
            $table->json('technologies')->nullable();
            $table->json('methodologies')->nullable();
            $table->text('academic_experience')->nullable();
            $table->timestamps();
        });

        $directors = DB::table('users')
            ->where('role', 'Director')
            ->whereNotNull('areas')
            ->where('areas', '!=', '')
            ->get(['id', 'areas']);

        foreach ($directors as $director) {
            $lines = array_values(array_filter(array_map(
                static fn (string $line): string => trim($line),
                preg_split('/\r\n|\r|\n/', (string) $director->areas) ?: [],
            )));

            if ($lines === []) {
                continue;
            }

            DB::table('director_academic_profiles')->insert([
                'user_id' => $director->id,
                'research_lines' => json_encode($lines, JSON_UNESCAPED_UNICODE),
                'technologies' => json_encode([], JSON_UNESCAPED_UNICODE),
                'methodologies' => json_encode([], JSON_UNESCAPED_UNICODE),
                'academic_experience' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('director_academic_profiles');
    }
};
