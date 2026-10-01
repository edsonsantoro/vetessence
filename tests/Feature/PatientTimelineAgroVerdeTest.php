<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DentalChart;
use App\Models\ParasiteControl;
use App\Models\Pet;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Models\WeightRecord;
use App\Support\Timeline\Timeline;
use Tests\ModuleTestCase;

/**
 * Cobre o que o fork acrescenta ao prontuário: os tipos que já existiam como
 * tabela mas não apareciam na timeline, e os filtros.
 *
 * Os testes do upstream (PatientTimelineTest) continuam válidos e não foram
 * tocados.
 */
class PatientTimelineAgroVerdeTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs('veterinario');
    }

    // ── Tipos que faltavam ───────────────────────────────────────────────

    public function test_timeline_inclui_peso_que_existia_como_tabela_mas_nao_aparecia()
    {
        $pet = Pet::factory()->create();
        WeightRecord::create([
            'pet_id' => $pet->id,
            'weight' => 14.75,
            'measurement_date' => now()->subDays(3)->toDateString(),
        ]);

        $resposta = $this->get(route('pets.timeline', $pet));

        $resposta->assertOk();
        $resposta->assertSee('Peso');
        $resposta->assertSee('14,75');
    }

    public function test_timeline_inclui_parasitario()
    {
        $pet = Pet::factory()->create();
        ParasiteControl::create([
            'pet_id' => $pet->id,
            'product_name' => 'Drontal Plus',
            'type' => 'Vermífugo',
            'application_date' => now()->subDays(10)->toDateString(),
            'next_due_date' => now()->addDays(20)->toDateString(),
        ]);

        $resposta = $this->get(route('pets.timeline', $pet));

        $resposta->assertOk();
        $resposta->assertSee('Parasitário');
        $resposta->assertSee('Drontal Plus');
    }

    public function test_timeline_inclui_odontograma()
    {
        $pet = Pet::factory()->create();
        DentalChart::create([
            'pet_id' => $pet->id,
            'examination_date' => now()->subDays(2)->toDateString(),
            'procedure_type' => 'Limpeza dentária',
        ]);

        $resposta = $this->get(route('pets.timeline', $pet));

        $resposta->assertOk();
        $resposta->assertSee('Odontograma');
        $resposta->assertSee('Limpeza dentária');
    }

    public function test_timeline_inclui_plano_de_tratamento()
    {
        $pet = Pet::factory()->create();
        TreatmentPlan::create([
            'plan_number' => 'TP-1',
            'pet_id' => $pet->id,
            'title' => 'Tratamento de dermatite',
            'status' => 'active',
        ]);

        $resposta = $this->get(route('pets.timeline', $pet));

        $resposta->assertOk();
        $resposta->assertSee('Tratamento');
        $resposta->assertSee('Tratamento de dermatite');
    }

    /**
     * Prescrição não tem `pet_id`: liga pelo atendimento. Se a ligação fosse
     * por pet_id o tipo apareceria vazio e silenciosamente.
     */
    public function test_timeline_inclui_prescricao_via_atendimento()
    {
        $pet = Pet::factory()->create();
        $atendimento = \App\Models\MedicalRecord::factory()->create(['pet_id' => $pet->id]);

        Prescription::create([
            'medical_record_id' => $atendimento->id,
            'medication' => 'Amoxicilina 500mg',
            'dosage' => '1 cápsula',
            'frequency' => '8/8h por 7 dias',
            'duration' => '7 dias',
        ]);

        $resposta = $this->get(route('pets.timeline', $pet));

        $resposta->assertOk();
        $resposta->assertSee('Prescrição');
        $resposta->assertSee('Amoxicilina');
    }

    public function test_prescricao_de_outro_pet_nao_vaza_para_a_timeline()
    {
        $meuPet = Pet::factory()->create();
        $outroPet = Pet::factory()->create();

        $atendimentoAlheio = \App\Models\MedicalRecord::factory()->create(['pet_id' => $outroPet->id]);
        Prescription::create([
            'medical_record_id' => $atendimentoAlheio->id,
            'medication' => 'REMEDIO DO OUTRO PET',
            'dosage' => '1',
            'frequency' => '1x',
            'duration' => '1 dia',
        ]);

        $resposta = $this->get(route('pets.timeline', $meuPet));

        $resposta->assertOk();
        $resposta->assertDontSee('REMEDIO DO OUTRO PET');
    }

    // ── Filtros ──────────────────────────────────────────────────────────

    public function test_filtro_por_tipo_mantem_apenas_o_tipo_pedido()
    {
        $pet = Pet::factory()->create();
        WeightRecord::create([
            'pet_id' => $pet->id,
            'weight' => 10.0,
            'measurement_date' => now()->subDay()->toDateString(),
        ]);
        Appointment::factory()->create([
            'pet_id' => $pet->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'completed',
            // Texto próprio: o rótulo "Consulta" também aparece nos botões de
            // filtro, então_assertSee nele não provaria que o evento sumiu.
            'reason' => 'RAZAO UNICA DA CONSULTA',
        ]);

        $resposta = $this->get(route('pets.timeline', $pet) . '?tipos=peso');

        $resposta->assertOk();
        $resposta->assertSee('Peso');
        $resposta->assertSee('10,00');
        $resposta->assertDontSee('RAZAO UNICA DA CONSULTA');
    }

    public function test_filtro_por_periodo_corta_o_que_esta_fora_da_janela()
    {
        $pet = Pet::factory()->create();
        WeightRecord::create([
            'pet_id' => $pet->id, 'weight' => 1.0,
            'measurement_date' => now()->subYear()->toDateString(),
        ]);
        WeightRecord::create([
            'pet_id' => $pet->id, 'weight' => 2.0,
            'measurement_date' => now()->subDay()->toDateString(),
        ]);

        $de = now()->subDays(7)->toDateString();
        $resposta = $this->get(route('pets.timeline', $pet) . '?de=' . $de);

        $resposta->assertOk();
        // 2,00 dentro da janela, 1,00 fora dela.
        $resposta->assertSee('2,00');
        $resposta->assertDontSee('1,00');
    }

    /** Slug inventado não pode virar "mostrar tudo" — é o oposto do pedido. */
    public function test_slug_desconhecido_nao_transforma_em_todos()
    {
        $pet = Pet::factory()->create();
        WeightRecord::create([
            'pet_id' => $pet->id, 'weight' => 7.7,
            'measurement_date' => now()->subDay()->toDateString(),
        ]);

        $resposta = $this->get(route('pets.timeline', $pet) . '?tipos=inexistente');

        $resposta->assertOk();
        // Com filtro=none, o único evento (peso) não aparece.
        $resposta->assertDontSee('7,70');
    }

    /** strtotime aceita "amanhã"; data em formato inesperado vira epoch. */
    public function test_data_invalida_e_ignorada_sem_erro()
    {
        $pet = Pet::factory()->create();
        WeightRecord::create([
            'pet_id' => $pet->id, 'weight' => 5.5,
            'measurement_date' => now()->subDay()->toDateString(),
        ]);

        $resposta = $this->get(route('pets.timeline', $pet) . '?de=amanha&ate=barbaz');

        $resposta->assertOk();
        $resposta->assertSee('5,50');
    }

    // ── Registro de tipos ─────────────────────────────────────────────────

    public function test_registro_tem_os_doze_tipos_do_prontuario()
    {
        $slugs = array_map(fn ($t) => $t->slug, Timeline::tipos());

        $this->assertSame([
            'prontuario', 'consulta', 'internacao', 'cirurgia', 'exame', 'vacina',
            'prescricao', 'peso', 'parasitario', 'tratamento', 'odontograma', 'fatura',
        ], $slugs);
    }

    public function test_tipo_por_slug_encontra_e_rejeita()
    {
        $this->assertNotNull(Timeline::tipoPorSlug('peso'));
        $this->assertNull(Timeline::tipoPorSlug('nao-existe'));
    }

    public function test_eventos_ficam_em_ordem_cronologica_decrescente()
    {
        $pet = Pet::factory()->create();
        foreach ([5, 1, 3] as $diasAtras) {
            WeightRecord::create([
                'pet_id' => $pet->id, 'weight' => $diasAtras,
                'measurement_date' => now()->subDays($diasAtras)->toDateString(),
            ]);
        }

        $eventos = Timeline::paraPet($pet);

        $this->assertSame(3, $eventos->count());
        $datas = $eventos->pluck('date')->map(fn ($d) => strtotime((string) $d))->all();
        $ordenado = $datas;
        rsort($ordenado);
        $this->assertSame($ordenado, $datas, 'eventos devem vir do mais recente ao mais antigo');
    }

    public function test_total_por_tipo_ignora_o_filtro_de_periodo()
    {
        $pet = Pet::factory()->create();
        WeightRecord::create([
            'pet_id' => $pet->id, 'weight' => 1.0,
            'measurement_date' => now()->subYear()->toDateString(),
        ]);
        WeightRecord::create([
            'pet_id' => $pet->id, 'weight' => 2.0,
            'measurement_date' => now()->toDateString(),
        ]);

        $janela = Timeline::paraPet($pet, null, now()->subDays(2)->toDateString(), null);
        $total = Timeline::totalPorTipo($pet);

        $this->assertSame(1, $janela->count(), 'a janela corta');
        $this->assertSame(2, $total['peso'], 'mas o total do paciente continua 2');
    }
}
