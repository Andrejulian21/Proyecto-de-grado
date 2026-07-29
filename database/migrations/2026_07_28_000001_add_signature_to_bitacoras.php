<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PR 1 — Seguimiento y Firma: add signature-code fields to bitacoras.
     *
     * RF-SIG-01: a 6-digit numeric code is generated when a bitacora is created
     * and stored hashed in `signature_code`, with `signature_code_expires_at`
     * set to +2 minutes. `signature_retries` tracks how many times the student
     * has re-requested a new code (max 1 per spec).
     */
    public function up(): void
    {
        Schema::table('bitacoras', function (Blueprint $table): void {
            $table->string('signature_code', 255)->nullable()->after('signature_status');
            $table->timestamp('signature_code_expires_at')->nullable()->after('signature_code');
            $table->unsignedTinyInteger('signature_retries')->default(0)->after('signature_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('bitacoras', function (Blueprint $table): void {
            $table->dropColumn(['signature_code', 'signature_code_expires_at', 'signature_retries']);
        });
    }
};
