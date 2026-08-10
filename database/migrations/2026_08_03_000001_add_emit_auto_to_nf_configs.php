<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfe_configs', function (Blueprint $table) {
            $table->boolean('emit_auto')->default(true)->after('ambiente');
        });

        Schema::table('nfse_configs', function (Blueprint $table) {
            $table->boolean('emit_auto')->default(true)->after('ambiente');
        });
    }

    public function down(): void
    {
        Schema::table('nfe_configs', function (Blueprint $table) {
            $table->dropColumn('emit_auto');
        });

        Schema::table('nfse_configs', function (Blueprint $table) {
            $table->dropColumn('emit_auto');
        });
    }
};
