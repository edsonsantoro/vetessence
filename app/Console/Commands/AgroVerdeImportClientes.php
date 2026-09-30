<?php

namespace App\Console\Commands;

use App\Imports\SimplesVet\SimplesVetMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa tutores e pets do SimplesVet.
 *
 * Lê o JSON extraído do ERP e popula `tutors`, `pets` e `pet_tutor`.
 * Não fala com o SimplesVet: consome um arquivo local, gerado antes.
 *
 * ── Idempotência ───────────────────────────────────────────────────────────
 * Upsert pela coluna `simplesvet_chave` (unique, migration 2026_10_01_000001).
 * Rodar de novo atualiza; nunca duplica. Quando a agenda for importada, basta
 * repetir o processo.
 *
 * ── Por que bulk insert ────────────────────────────────────────────────────
 * São ~9.900 tutores + ~16.200 pets + ~16.200 vínculos = 42 mil linhas.
 * Eloquent um-a-um levaria dezenas de minutos. Aqui vai em lote.
 *
 * ── Ordem dentro de cada lote ──────────────────────────────────────────────
 * tutores → pets → vínculos. O pet_tutor depende das duas tabelas já
 * gravadas no mesmo lote, senão os ids não existiriam.
 *
 * Uso:
 *   php artisan agroverde:import-clientes
 *   php artisan agroverde:import-clientes --file=/caminho.json
 *   php artisan agroverde:import-clientes --dry-run
 */
class AgroVerdeImportClientes extends Command
{
    protected $signature = 'agroverde:import-clientes
        {--file= : Caminho do clientes_completos.json (padrão: config agroverde.import.source_path)}
        {--dry-run : Processa e relata, sem gravar no banco}
        {--chunk=1000 : Tutores por lote}';

    protected $description = 'Importa tutores e pets do JSON do SimplesVet (upsert por chave)';

    /** Total de clientes do arquivo, base das porcentagens do relatório. */
    private int $totalClientes = 0;

    public function handle(): int
    {
        $caminho = $this->option('file') ?: config('agroverde.import.source_path');

        if (! is_readable($caminho)) {
            $this->error("Arquivo não encontrado ou ilegível: {$caminho}");
            $this->line('  → Coloque o JSON exportado do SimplesVet nesse caminho,');
            $this->line('    ou aponte outro com --file=/caminho/absoluto.json');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(50, (int) $this->option('chunk'));

        $filial = DB::table('branches')->orderBy('id')->value('id');
        if (! $filial) {
            $this->error('Nenhuma filial cadastrada. Rode migrate --seed antes.');

            return self::FAILURE;
        }

        $this->info('Importando SimplesVet → VetEssence');
        $this->line('  arquivo : ' . $caminho);
        $this->line('  tamanho : ' . number_format(filesize($caminho)) . ' bytes');
        $this->line('  filial  : #' . $filial);
        $this->line('  modo    : ' . ($dryRun ? 'DRY-RUN (nada é gravado)' : 'ESCRITA'));
        $this->newLine();

        $dados = json_decode(file_get_contents($caminho), true);
        if (! is_array($dados)) {
            $this->error('JSON inválido ou vazio.');

            return self::FAILURE;
        }

        $this->totalClientes = count($dados);
        $this->line('  clientes no arquivo: ' . number_format($this->totalClientes));
        $this->newLine();

        $c = [
            'pets' => 0, 'vinculos' => 0,
            'sem_cpf' => 0, 'sem_telefone' => 0, 'sem_endereco' => 0,
            'pets_obito' => 0, 'pets_sem_nascimento' => 0, 'pets_sem_especie' => 0,
        ];

        $bar = $this->output->createProgressBar($this->totalClientes);
        $bar->start();

        $loteTutores = [];
        $lotePets = [];
        $lotePares = [];

        foreach ($dados as $cli) {
            $bar->advance();

            $chaveTutor = SimplesVetMapper::chave($cli['chave'] ?? null);

            $tutor = SimplesVetMapper::tutor($cli);
            $tutor['created_at_branch_id'] = $filial;

            if (! $tutor['cpf']) {
                $c['sem_cpf']++;
            }
            if ($tutor['phone'] === '') {
                $c['sem_telefone']++;
            }
            if (! $tutor['address']) {
                $c['sem_endereco']++;
            }

            $loteTutores[] = $tutor;

            foreach (($cli['animais'] ?? []) as $animal) {
                $pet = SimplesVetMapper::pet($animal);
                $pet['created_at_branch_id'] = $filial;

                $c['pets']++;
                if (! $pet['is_active']) {
                    $c['pets_obito']++;
                }
                if (! $pet['birth_date']) {
                    $c['pets_sem_nascimento']++;
                }
                if ($pet['species'] === 'Não informada') {
                    $c['pets_sem_especie']++;
                }

                $lotePets[] = $pet;

                if ($chaveTutor && $pet['simplesvet_chave']) {
                    $lotePares[] = [$chaveTutor, $pet['simplesvet_chave']];
                }
            }

            if (count($loteTutores) >= $chunk) {
                $this->gravarLote($loteTutores, $lotePets, $lotePares, $dryRun);
                $c['vinculos'] += count($lotePares);
                $loteTutores = [];
                $lotePets = [];
                $lotePares = [];
            }
        }

        // Flush do que sobrou — sem isso o último lote nunca entra no banco.
        if ($loteTutores) {
            $this->gravarLote($loteTutores, $lotePets, $lotePares, $dryRun);
            $c['vinculos'] += count($lotePares);
        }

        $bar->finish();
        $this->newLine(2);

        $this->relatorio($c, $dryRun);

        return self::SUCCESS;
    }

    private function gravarLote(array $tutores, array $pets, array $pares, bool $dryRun): void
    {
        // Mesmo no dry-run o lote é montado e conferido: erro de SQL (como
        // placeholder faltando) só apareceria na gravação, depois de todo o
        // trabalho de mapeamento. O dry-run precisa pegar isso.
        if ($tutores) {
            $this->upsert('tutors', $tutores, 'simplesvet_chave', ! $dryRun);
        }

        if ($pets) {
            $this->upsert('pets', $pets, 'simplesvet_chave', ! $dryRun);
        }

        if (! $dryRun) {
            $this->gravarVinculos($pares);
        }
    }

    /**
     * Upsert em lote via query builder.
     *
     * ── Por que NÃO escrever o INSERT à mão ────────────────────────────────
     * `DB::statement($sql, $bindings)` recebe bindings **planos**. Passar
     * `[[...19 valores...], [...19 valores...]]` faz o PDO tentar vincular a
     * própria array como valor dos placeholders 1 e 2 — e morre com
     * "Array to string conversion", sem dizer qual coluna.
     *
     * `upsert()` monta o multi-row, achata os bindings e gera o
     * ON DUPLICATE KEY UPDATE. Menos código e sem essa classe de bug.
     *
     * `created_at` fica fora da lista de atualização de propósito: reimportar
     * não deve fingir que o cadastro foi feito agora — a data é da origem.
     */
    private function upsert(string $tabela, array $linhas, string $chaveColuna, bool $executar): void
    {
        // Monta o SQL mesmo no dry-run, só para validar o lote.
        $updates = array_values(array_diff(
            array_keys($linhas[0]),
            [$chaveColuna, 'created_at']
        ));

        if (! $executar) {
            $this->conferirLote($tabela, $linhas, $chaveColuna, $updates);

            return;
        }

        $this->conferirLote($tabela, $linhas, $chaveColuna, $updates);

        DB::table($tabela)->upsert($linhas, [$chaveColuna], $updates);
    }

    /**
     * Barreira contra lote malformado: toda linha precisa ter exatamente as
     * mesmas colunas da primeira, e o número de valores precisa bater.
     *
     * Sem isso, uma linha com coluna a mais faria o insert inteiro ser
     * gravado com os valores deslocados — dados silenciosamente corrompidos
     * em 42 mil linhas.
     */
    private function conferirLote(string $tabela, array $linhas, string $chaveColuna, array $updates): void
    {
        if (! $linhas) {
            return;
        }

        $esperadas = array_keys($linhas[0]);
        $n = count($esperadas);

        foreach ($linhas as $i => $linha) {
            if (count($linha) !== $n) {
                throw new \RuntimeException(sprintf(
                    'Lote de `%s` inconsistente na linha %d: %d colunas, esperado %d.',
                    $tabela, $i, count($linha), $n
                ));
            }
            if (array_keys($linha) !== $esperadas) {
                throw new \RuntimeException(sprintf(
                    'Lote de `%s` inconsistente na linha %d: colunas fora de ordem ou ausentes.',
                    $tabela, $i
                ));
            }
        }

        if (! in_array($chaveColuna, $esperadas, true)) {
            throw new \RuntimeException("Lote de `{$tabela}` sem a coluna de chave {$chaveColuna}.");
        }
    }

    /** Resolve chave do ERP → id local e grava o pet_tutor que falta. */
    private function gravarVinculos(array $pares): void
    {
        if (! $pares) {
            return;
        }

        $tutorIds = DB::table('tutors')
            ->whereIn('simplesvet_chave', array_column($pares, 0))
            ->pluck('id', 'simplesvet_chave');

        $petIds = DB::table('pets')
            ->whereIn('simplesvet_chave', array_column($pares, 1))
            ->pluck('id', 'simplesvet_chave');

        $agora = now();
        $vistos = [];

        foreach ($pares as [$chaveTutor, $chavePet]) {
            $tutorId = $tutorIds[$chaveTutor] ?? null;
            $petId   = $petIds[$chavePet] ?? null;

            if ($tutorId === null || $petId === null) {
                continue;
            }

            // pet_tutor não tem índice único. Deduplicar aqui evita refazer a
            // mesma consulta, mas o exists no banco continua sendo a garantia.
            $id = $tutorId . ':' . $petId;
            if (isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;

            $existe = DB::table('pet_tutor')
                ->where('pet_id', $petId)
                ->where('tutor_id', $tutorId)
                ->exists();

            if (! $existe) {
                DB::table('pet_tutor')->insert([
                    'pet_id' => $petId,
                    'tutor_id' => $tutorId,
                    'is_primary' => 1,
                    'relationship' => 'Responsável',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        }
    }

    private function relatorio(array $c, bool $dryRun): void
    {
        $total = $this->totalClientes;

        $this->info('Origem lida');
        $this->table(['Métrica', 'Valor'], [
            ['Clientes', number_format($total)],
            ['Pets',     number_format($c['pets'])],
            ['Vínculos', number_format($c['vinculos'])],
        ]);

        $this->newLine();
        $this->comment('Lacunas na origem (falta no ERP, não é erro de importação):');
        $this->table(['Métrica', 'Qtd', '% do total'], [
            ['Sem CPF',      number_format($c['sem_cpf']),      $this->pct($c['sem_cpf'], $total)],
            ['Sem telefone', number_format($c['sem_telefone']), $this->pct($c['sem_telefone'], $total)],
            ['Sem endereço', number_format($c['sem_endereco']), $this->pct($c['sem_endereco'], $total)],
        ]);

        $this->newLine();
        $this->comment('Pets:');
        $this->table(['Métrica', 'Qtd', '% dos pets'], [
            ['Óbito (is_active=0)',    number_format($c['pets_obito']),          $this->pct($c['pets_obito'], $c['pets'])],
            ['Sem data de nascimento', number_format($c['pets_sem_nascimento']), $this->pct($c['pets_sem_nascimento'], $c['pets'])],
            ['Sem espécie',            number_format($c['pets_sem_especie']),    $this->pct($c['pets_sem_especie'], $c['pets'])],
        ]);

        if (! $dryRun) {
            $this->newLine();
            $this->info('Confirmado no banco');
            $this->table(['Tabela', 'Total', 'Vindos do SimplesVet'], [
                ['tutors',    number_format(DB::table('tutors')->count()),    number_format(DB::table('tutors')->whereNotNull('simplesvet_chave')->count())],
                ['pets',      number_format(DB::table('pets')->count()),      number_format(DB::table('pets')->whereNotNull('simplesvet_chave')->count())],
                ['pet_tutor', number_format(DB::table('pet_tutor')->count()), '—'],
            ]);
        }
    }

    private function pct(int $n, int $total): string
    {
        return $total > 0 ? round($n * 100 / $total, 1) . '%' : '—';
    }
}
