<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the default Laravel users table with the columns required
     * by the auth-access-module spec (T-008):
     *   - role: enum-backed string column (Estudiante|Director|Coordinador|EvaluadorExterno)
     *   - es_externo: bool, default false (true for credential-based evaluadores externos)
     *   - google_id: unique nullable (Google sub claim)
     *   - avatar: nullable URL/path for the profile photo
     *   - last_activity_at: nullable timestamp (8h inactivity timeout anchor)
     *   - totp_secret: nullable string (TOTP module is future, nullable from day one per RNF05)
     *   - password: nullable (Google OAuth users have no password)
     *
     * Indexes: email (unique — default), role, es_externo, google_id (unique).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // -- auth-access-module extensions --
            $table->string('role', 32)->index();
            $table->boolean('es_externo')->default(false)->index();
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('totp_secret')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
