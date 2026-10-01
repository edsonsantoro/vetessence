<?php

namespace App\Support\Timeline;

use App\Models\Exam;
use App\Models\Hospitalization;
use App\Models\Invoice;
use App\Models\ParasiteControl;
use App\Models\Pet;
use App\Models\Prescription;
use App\Models\Surgery;
use App\Models\TreatmentPlan;
use App\Models\WeightRecord;
use App\Models\DentalChart;
use App\Models\Appointment;
use Illuminate\Support\Collection;

/**
 * Registro de Appointment — o prontuário do animal.
 *
 * Consolida tudo o que aconteceu com um pet numa linha do tempo, na ordem
 * cronológica. É a peça que o SimplesVet trata como "coração do modelo": o
 * ERP guarda 11 tipos de registro ligados ao animal, e a recepção precisa
 * ver isso junto.
 *
 * ── As 11 categorias do SimplesVet × o que existe aqui ─────────────────────
 *  1. Atendimento    → MedicalRecord          ✅
 *  2. Vacina          → Vaccination            ✅
 *  3. Consulta        → Appointment            ✅
 *  4. Exame           → Exam                   ✅
 *  5. Internação      → Hospitalization        ✅
 *  6. Fatura          → Invoice                ✅
 *  7. Receita         → Prescription           ✅ (via medical_record)
 *  8. Peso            → WeightRecord           ✅
 *  9. Parasitário     → ParasiteControl        ✅
 * 10. Tratamento      → TreatmentPlan          ✅
 * 11. Odontograma     → DentalChart            ✅
 *
 * Fora do alcance por falta de tabela: Patologia, Observação, Documento,
 * Foto e Vídeo. Ver AGROVERDE.md §12 — são migrations novas, não adaptação.
 */
class Timeline
{
    /**
     * Todos os tipos, na ordem de exibição dos filtros.
     *
     * @return array<int, TimelineType>
     */
    public static function tipos(): array
    {
        return [
            new TimelineType('prontuario', 'Prontuário', 'fa-notes-medical', 'success', 10),
            new TimelineType('consulta', 'Consulta', 'fa-calendar-check', 'primary', 20),
            new TimelineType('internacao', 'Internação', 'fa-procedures', 'secondary', 30),
            new TimelineType('cirurgia', 'Cirurgia', 'fa-user-md', 'danger', 40),
            new TimelineType('exame', 'Exame', 'fa-flask', 'warning', 50),
            new TimelineType('vacina', 'Vacina', 'fa-syringe', 'info', 60),
            new TimelineType('prescricao', 'Prescrição', 'fa-prescription-bottle-medical', 'teal', 70),
            new TimelineType('peso', 'Peso', 'fa-weight-hanging', 'teal', 80),
            new TimelineType('parasitario', 'Parasitário', 'fa-bug', 'olive', 90),
            new TimelineType('tratamento', 'Tratamento', 'fa-heart-pulse', 'pink', 100),
            new TimelineType('odontograma', 'Odontograma', 'fa-tooth', 'cyan', 110),
            new TimelineType('fatura', 'Fatura', 'fa-file-invoice-dollar', 'dark', 120),
        ];
    }

    public static function tipoPorSlug(string $slug): ?TimelineType
    {
        foreach (self::tipos() as $tipo) {
            if ($tipo->slug === $slug) {
                return $tipo;
            }
        }

        return null;
    }

    /**
     * Contagem de eventos por tipo, SEM nenhum filtro aplicado.
     *
     * A view usa isso para mostrar nos botões de filtro quantos registros o
     * paciente tem de cada tipo. Sem isso, ao filtrar por período, os botões
     * esmaeciariam tipo por tipo e a pessoa perderia a noção do que existe
     * fora da janela — o filtro pareceria "apagou" o histórico.
     *
     * @return array<string, int>
     */
    public static function totalPorTipo(Pet $pet): array
    {
        $todos = self::paraPet($pet)->countBy('tipo');

        $contagem = [];
        foreach (self::tipos() as $tipo) {
            $contagem[$tipo->slug] = (int) ($todos[$tipo->slug] ?? 0);
        }

        return $contagem;
    }

    /**
     * Monta a linha do tempo de um pet.
     *
     * @param  array<int,string>|null  $tipos
     *         null = todos os tipos (sem filtro pedido);
     *         []   = ninguém pediu nada válido — resultado vazio, e não "todos";
     *         lista = só estes slugs.
     * @return Collection<int, array>
     */
    public static function paraPet(Pet $pet, ?array $tipos = null, ?string $de = null, ?string $ate = null): Collection
    {
        $selecionados = $tipos === null
            ? self::tipos()
            : array_values(array_filter(self::tipos(), fn ($t) => in_array($t->slug, $tipos, true)));

        $eventos = collect();

        foreach ($selecionados as $tipo) {
            foreach (self::eventosDoTipo($tipo, $pet) as $evento) {
                if (! $evento['date']) {
                    continue;
                }
                $eventos->push($evento + ['tipo' => $tipo->slug]);
            }
        }

        $eventos = $eventos->filter(function (array $e) use ($de, $ate) {
            $ts = strtotime((string) $e['date']);
            if ($ts === false) {
                return true;
            }
            if ($de && $ts < strtotime($de)) {
                return false;
            }
            if ($ate && $ts > strtotime($ate . ' 23:59:59')) {
                return false;
            }

            return true;
        });

        return $eventos->sortByDesc(fn ($e) => strtotime((string) $e['date']) ?: 0)->values();
    }

    /** @return array<int, array> */
    private static function eventosDoTipo(TimelineType $tipo, Pet $pet): array
    {
        $e = fn (string $rotulo, $data, string $resumo, ?string $url = null): array => [
            'date' => $data,
            'type' => $rotulo,
            'icon' => $tipo->icon,
            'color' => $tipo->color,
            'summary' => $resumo,
            'url' => $url,
        ];

        return match ($tipo->slug) {
            'prontuario' => $pet->medicalRecords->map(fn ($r) => $e(
                'Prontuário',
                $r->date ?? $r->created_at,
                $r->diagnosis ?: ($r->chief_complaint ?: 'Atendimento'),
                route('medical-records.show', $r),
            ))->all(),

            'consulta' => $pet->appointments->map(fn ($a) => $e(
                'Consulta',
                $a->start_time ?: $a->date,
                trim(($a->reason ?: 'Consulta') . ' — ' . ($a->status ? 'status: ' . $a->status : '')),
                route('appointments.show', $a),
            ))->all(),

            'internacao' => Hospitalization::where('pet_id', $pet->id)->get()->map(fn ($h) => $e(
                'Internação',
                $h->admission_date,
                trim(($h->admission_reason ?: 'Internação')
                    . ($h->discharged_at ? ' | Alta: ' . \Illuminate\Support\Carbon::parse($h->discharged_at)->format('d/m/Y') : '')),
                route('hospitalizations.show', $h),
            ))->all(),

            'cirurgia' => $pet->surgeries->map(fn ($s) => $e(
                'Cirurgia',
                $s->date ?? $s->created_at,
                $s->surgery_type ?: 'Procedimento cirúrgico',
                route('surgeries.show', $s),
            ))->all(),

            'exame' => $pet->exams->map(fn ($x) => $e(
                'Exame',
                $x->result_date ?: $x->requested_date ?: $x->date ?: $x->created_at,
                $x->type ?: 'Exame',
                route('exams.show', $x),
            ))->all(),

            'vacina' => $pet->vaccinations->map(fn ($v) => $e(
                'Vacina',
                $v->date,
                trim($v->vaccine . ($v->batch ? " (lote {$v->batch})" : '')),
                route('vaccinations.show', $v),
            ))->all(),

            // Prescrição não tem pet_id: liga pelo atendimento.
            'prescricao' => Prescription::whereIn('medical_record_id',
                    $pet->medicalRecords->pluck('id'))->get()
                ->map(fn ($p) => $e(
                    'Prescrição',
                    $p->created_at,
                    trim($p->medication
                        . ($p->dosage ? ' ' . $p->dosage : '')
                        . ($p->frequency ? ' — ' . $p->frequency : '')),
                    null,
                ))->all(),

            'peso' => WeightRecord::where('pet_id', $pet->id)->get()->map(fn ($w) => $e(
                'Peso',
                $w->measurement_date,
                number_format((float) $w->weight, 2, ',', '.') . ' kg'
                    . ($w->bcs ? ' | ECC ' . $w->bcs : ''),
                null,
            ))->all(),

            'parasitario' => ParasiteControl::where('pet_id', $pet->id)->get()->map(fn ($p) => $e(
                'Parasitário',
                $p->application_date,
                trim(($p->product_name ?: 'Antiparasitário')
                    . ($p->next_due_date ? ' | próxima: ' . \Illuminate\Support\Carbon::parse($p->next_due_date)->format('d/m/Y') : '')),
                null,
            ))->all(),

            'tratamento' => TreatmentPlan::where('pet_id', $pet->id)->get()->map(fn ($t) => $e(
                'Tratamento',
                $t->created_at,
                trim(($t->title ?: 'Plano de tratamento')
                    . ($t->status ? ' — ' . $t->status : '')),
                null,
            ))->all(),

            'odontograma' => DentalChart::where('pet_id', $pet->id)->get()->map(fn ($d) => $e(
                'Odontograma',
                $d->examination_date,
                trim(($d->procedure_type ?: 'Avaliação odontológica')
                    . ($d->tartar_index !== null ? ' | tártaro: ' . $d->tartar_index : '')),
                null,
            ))->all(),

            'fatura' => $pet->invoices->map(fn ($i) => $e(
                'Fatura',
                $i->created_at,
                'R$ ' . number_format((float) $i->total, 2, ',', '.') . ' — ' . ($i->status ?: ''),
                route('invoices.show', $i),
            ))->all(),

            default => [],
        };
    }
}
