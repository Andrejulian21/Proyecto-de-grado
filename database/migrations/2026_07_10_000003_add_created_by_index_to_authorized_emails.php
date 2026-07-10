<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorized_emails', function (Blueprint $table) {
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('authorized_emails', function (Blueprint $table) {
            $table->dropIndex('authorized_emails_created_by_index');
        });
    }
};
