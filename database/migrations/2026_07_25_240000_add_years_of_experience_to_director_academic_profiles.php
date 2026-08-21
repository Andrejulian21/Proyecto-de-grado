<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('director_academic_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('years_of_experience')->nullable()->after('academic_experience');
        });
    }

    public function down(): void
    {
        Schema::table('director_academic_profiles', function (Blueprint $table) {
            $table->dropColumn('years_of_experience');
        });
    }
};
