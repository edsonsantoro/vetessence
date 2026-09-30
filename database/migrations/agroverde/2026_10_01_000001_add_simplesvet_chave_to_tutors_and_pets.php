<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona a chave de origem do SimplesVet em tutores e pets.
 *
 * ── Por que ───────────────────────────────────────────────────────────────
 * A importação do ERP não tem ID próprio. O `chave` do SimplesVet é a única
 * identidade estável que sobrevive à migração e é o que o endpoint
 * `/v1/calendar/appointments` devolve em `customer.key` e `animal.key`.
 *
 * Sem essas colunas, a agenda que vier depois não tem como casar com os
 * dados já importados — e reimportar do zero a cada rodada seria o único
 * jeito de atualizar, o que duplicaria tudo.
 *
 * Unique para tornar o importador idempotente: rodar duas vezes atualiza em
 * vez de duplicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->string('simplesvet_chave', 32)->nullable()->unique()
                ->after('cpf')
                ->comment('chave do cliente no SimplesVet (fonte da migração)');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->string('simplesvet_chave', 32)->nullable()->unique()
                ->after('name')
                ->comment('chave do animal no SimplesVet (fonte da migração)');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropUnique(['simplesvet_chave']);
            $table->dropColumn('simplesvet_chave');
        });

        Schema::table('tutors', function (Blueprint $table) {
            $table->dropUnique(['simplesvet_chave']);
            $table->dropColumn('simplesvet_chave');
        });
    }
};
