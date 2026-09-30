<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Exam;
use App\Models\Invoice;
use App\Models\MedicalRecord;
use App\Models\ParasiteControl;
use App\Models\Pet;
use App\Models\Product;
use App\Models\Tutor;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\VaccinationReminder;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * AgroVerdeDemoSeeder
 *
 * Dados de demonstração com volume realista de uma clínica de pequeno porte
 * brasileira: tutores, pets de várias espécies, agenda distribuída no tempo
 * (passado / hoje / futuro), vacinação e faturamento.
 *
 * ── Por que existe ─────────────────────────────────────────────────────────
 * O SampleDataSeeder do core cria 2 pets e 1 agendamento: insuficiente para
 * avaliar o sistema — os cards do dashboard ficam zerados e não dá para
 * julgar listagens, filtros ou gráficos.
 *
 * ── Idempotência ───────────────────────────────────────────────────────────
 * Tudo usa firstOrCreate com uma chave natural, então rodar de novo não
 * duplica nada. Os CPFs são fictícios e sequenciais (900.xxx.xxx-xx), o que
 * os torna obviamente inválidos para uso real.
 *
 * ── branch_id é obrigatório ───────────────────────────────────────────────
 * Appointment, Invoice, MedicalRecord e ParasiteControl usam o trait
 * BranchScoped, que aplica um global scope `where branch_id = <filial do
 * usuário logado>`. Rodando por CLI o BranchContext está vazio, então o
 * trait não preenche a coluna e tudo cai em NULL — invisível para o
 * dashboard. Aqui forçamos a filial explicitamente.
 *
 * ── paid_at é obrigatório ─────────────────────────────────────────────────
 * O card "Receita do Mês" filtra por `whereMonth('paid_at')`, não por
 * `created_at`. Uma fatura marcada `status = paid` sem `paid_at` some do
 * faturamento.
 *
 * ── Camada A do fork ───────────────────────────────────────────────────────
 * Arquivo novo. Não altera `SampleDataSeeder` do upstream, então sincronizar
 * com hlmitecnologia/vetessence não gera conflito.
 */
class AgroVerdeDemoSeeder extends Seeder
{
    public function run()
    {
        $vetRole = Role::where('slug', 'veterinario')->first();
        $vet = User::where('role_id', $vetRole->id)->first();
        $vet2 = User::where('role_id', $vetRole->id)->skip(1)->first() ?? $vet;

        // Filial de referência. Os usuários seedados pertencem à Matriz (id 1).
        // Precisa existir explicitamente porque o BranchScope do CLI está vazio.
        $branchId = Branch::where('name', 'Matriz')->value('id')
            ?? Branch::orderBy('id')->value('id');

        // ── Tutores ──────────────────────────────────────────────────────────
        $tutorSpecs = [
            ['cpf' => '900.000.001-00', 'name' => 'Carlos Mendes',  'phone' => '(11) 98812-4477', 'email' => 'carlos.mendes@email.com',   'address' => 'Rua Harmonia, 840',          'city' => 'São Paulo',  'state' => 'SP'],
            ['cpf' => '900.000.002-00', 'name' => 'Juliana Prado',  'phone' => '(11) 99731-2260', 'email' => 'juliana.prado@email.com',   'address' => 'Av. Pompéia, 1290',           'city' => 'São Paulo',  'state' => 'SP'],
            ['cpf' => '900.000.003-00', 'name' => 'Roberto Alencar','phone' => '(11) 99204-8813', 'email' => 'roberto.alencar@email.com', 'address' => 'Rua Girassol, 305',           'city' => 'Cotia',     'state' => 'SP'],
            ['cpf' => '900.000.004-00', 'name' => 'Fernanda Lopes','phone' => '(11) 97653-1102', 'email' => 'fernanda.lopes@email.com',  'address' => 'Rua dos Ipês, 77',            'city' => 'Barueri',   'state' => 'SP'],
        ];

        $tutors = collect($tutorSpecs)->map(function (array $spec) {
            return Tutor::firstOrCreate(
                ['cpf' => $spec['cpf']],
                [
                    'phone'   => $spec['phone'],
                    'email'   => $spec['email'],
                    'address' => $spec['address'],
                    'city'    => $spec['city'],
                    'state'   => $spec['state'],
                ]
            );
        });

        // ── Pets ─────────────────────────────────────────────────────────────
        $petSpecs = [
            ['tutor' => 0, 'name' => 'Thor',     'species' => 'canine',  'breed' => 'Golden Retriever', 'gender' => 'male',   'birth' => '2019-03-11', 'color' => 'Dourado',      'weight' => 32.4],
            ['tutor' => 0, 'name' => 'Mel',      'species' => 'canine',  'breed' => 'Border Collie',    'gender' => 'female', 'birth' => '2021-09-02', 'color' => 'Preto e branco','weight' => 17.8],
            ['tutor' => 1, 'name' => 'Simba',    'species' => 'feline',  'breed' => 'Maine Coon',       'gender' => 'male',   'birth' => '2018-01-27', 'color' => 'Marrom rajado','weight' => 7.1],
            ['tutor' => 1, 'name' => 'Nina',     'species' => 'feline',  'breed' => 'Persa',            'gender' => 'female', 'birth' => '2022-06-15', 'color' => 'Branco',       'weight' => 3.9],
            ['tutor' => 2, 'name' => 'Bidu',     'species' => 'canine',  'breed' => 'Bulldog Francês',  'gender' => 'male',   'birth' => '2020-11-30', 'color' => 'Creme',        'weight' => 11.2],
            ['tutor' => 3, 'name' => 'Amora',    'species' => 'canine',  'breed' => 'Poodle Toy',       'gender' => 'female', 'birth' => '2023-04-08', 'color' => 'Preto',        'weight' => 4.6],
            ['tutor' => 3, 'name' => 'Cacau',    'species' => 'canine',  'breed' => 'Shih Tzu',         'gender' => 'male',   'birth' => '2023-07-19', 'color' => 'Cinza e branco','weight' => 6.3],
            ['tutor' => 2, 'name' => 'Kiwi',     'species' => 'feline',  'breed' => 'SRD',              'gender' => 'female', 'birth' => '2020-02-14', 'color' => 'Laranja',      'weight' => 4.8],
        ];

        $pets = collect($petSpecs)->map(function (array $spec, int $i) use ($tutors) {
            $pet = Pet::firstOrCreate(
                ['name' => $spec['name'], 'species' => $spec['species']],
                [
                    'breed'      => $spec['breed'],
                    'gender'     => $spec['gender'],
                    'birth_date' => $spec['birth'],
                    'color'      => $spec['color'],
                    'weight'     => $spec['weight'],
                ]
            );

            $tutor = $tutors[$spec['tutor']];

            if (!\DB::table('pet_tutor')
                ->where('pet_id', $pet->id)
                ->where('tutor_id', $tutor->id)
                ->exists()) {
                \DB::table('pet_tutor')->insert([
                    'pet_id'     => $pet->id,
                    'tutor_id'   => $tutor->id,
                    'is_primary' => true,
                ]);
            }

            return $pet;
        });

        // ── Agenda: passado concluído, hoje, e futuro ────────────────────────
        // Cada par (pet, offset de dia) é único, então o firstOrCreate não
        // colide entre si.
        $appointmentSpecs = [
            // Passado — alimenta "Receita do Mês" e histórico
            ['pet' => 0, 'day' => -18, 'time' => '09:00', 'type' => 'consulta', 'status' => 'completed', 'vet' => 0, 'reason' => 'Check-up anual'],
            ['pet' => 2, 'day' => -14, 'time' => '10:30', 'type' => 'consulta', 'status' => 'completed', 'vet' => 1, 'reason' => 'Dermatite — retorno'],
            ['pet' => 5, 'day' => -11, 'time' => '15:00', 'type' => 'vacina',   'status' => 'completed', 'vet' => 0, 'reason' => 'V10 + antirrábica'],
            ['pet' => 4, 'day' => -9,  'time' => '11:00', 'type' => 'consulta', 'status' => 'completed', 'vet' => 1, 'reason' => 'Otite externa'],
            ['pet' => 7, 'day' => -7,  'time' => '16:30', 'type' => 'exame',    'status' => 'completed', 'vet' => 0, 'reason' => 'Hemograma + perfil bioquímico'],
            ['pet' => 1, 'day' => -5,  'time' => '08:30', 'type' => 'consulta', 'status' => 'completed', 'vet' => 1, 'reason' => 'Dermatopatia alérgica'],
            ['pet' => 3, 'day' => -3,  'time' => '14:00', 'type' => 'consulta', 'status' => 'completed', 'vet' => 0, 'reason' => 'Vacinação felina'],

            // Hoje — acende o card "Consultas Hoje"
            ['pet' => 0, 'day' => 0, 'time' => '09:00', 'type' => 'consulta', 'status' => 'scheduled', 'vet' => 0, 'reason' => 'Retorno pós-operatório'],
            ['pet' => 6, 'day' => 0, 'time' => '10:30', 'type' => 'vacina',   'status' => 'scheduled', 'vet' => 1, 'reason' => 'Antirrábica anual'],
            ['pet' => 2, 'day' => 0, 'time' => '14:30', 'type' => 'consulta', 'status' => 'scheduled', 'vet' => 0, 'reason' => 'Controle de peso'],
            ['pet' => 4, 'day' => 0, 'time' => '16:00', 'type' => 'exame',    'status' => 'scheduled', 'vet' => 1, 'reason' => 'Radiografia de tórax'],

            // Futuro
            ['pet' => 5, 'day' => 2,  'time' => '09:30', 'type' => 'consulta', 'status' => 'scheduled', 'vet' => 0, 'reason' => 'Tosa higiênica e avaliação da pele'],
            ['pet' => 7, 'day' => 3,  'time' => '11:00', 'type' => 'consulta', 'status' => 'scheduled', 'vet' => 1, 'reason' => 'Soronegativo de FUNH — revisão'],
            ['pet' => 1, 'day' => 6,  'time' => '15:30', 'type' => 'exame',    'status' => 'scheduled', 'vet' => 0, 'reason' => 'Ultrassonografia abdominal'],
            ['pet' => 3, 'day' => 9,  'time' => '10:00', 'type' => 'vacina',   'status' => 'scheduled', 'vet' => 1, 'reason' => 'Tríplice felina'],
        ];

        foreach ($appointmentSpecs as $i => $spec) {
            $date = now()->addDays($spec['day'])->format('Y-m-d');

            Appointment::firstOrCreate(
                ['pet_id' => $pets[$spec['pet']]->id, 'date' => $date, 'time' => $spec['time']],
                [
                    'vet_id'    => $spec['vet'] === 0 ? $vet->id : $vet2->id,
                    'type'      => $spec['type'],
                    'status'    => $spec['status'],
                    'reason'    => $spec['reason'],
                    'branch_id' => $branchId,
                ]
            );
        }

        // ── Vacinações ───────────────────────────────────────────────────────
        // next_date no passado -> acende "Lembretes Pendentes" no dashboard.
        $vaccineSpecs = [
            ['pet' => 0, 'vaccine' => 'V10',         'batch' => 'VAC-2401', 'applied' => -400, 'next' => -35],
            ['pet' => 0, 'vaccine' => 'Antirrábica', 'batch' => 'VAC-2402', 'applied' => -400, 'next' => -20],
            ['pet' => 1, 'vaccine' => 'V10',         'batch' => 'VAC-2403', 'applied' => -200, 'next' => 160],
            ['pet' => 2, 'vaccine' => 'Quadrupla felina', 'batch' => 'VAC-2404', 'applied' => -380, 'next' => -10],
            ['pet' => 4, 'vaccine' => 'Antirrábica', 'batch' => 'VAC-2405', 'applied' => -370, 'next' => -5],
            ['pet' => 5, 'vaccine' => 'V10',         'batch' => 'VAC-2406', 'applied' => -150, 'next' => 215],
            ['pet' => 6, 'vaccine' => 'V8',          'batch' => 'VAC-2407', 'applied' => -120, 'next' => 245],
            ['pet' => 7, 'vaccine' => 'Quadrupla felina', 'batch' => 'VAC-2408', 'applied' => -330, 'next' => 30],
        ];

        foreach ($vaccineSpecs as $spec) {
            Vaccination::firstOrCreate(
                [
                    'pet_id'  => $pets[$spec['pet']]->id,
                    'vaccine' => $spec['vaccine'],
                    'batch'   => $spec['batch'],
                ],
                [
                    'vet_id'    => $vet->id,
                    'date'      => now()->addDays($spec['applied']),
                    'next_date' => now()->addDays($spec['next']),
                ]
            );
        }

        // ── Exames ───────────────────────────────────────────────────────────
        $examSpecs = [
            ['pet' => 7, 'type' => 'Hemograma',             'vet' => 0, 'requested' => -7, 'ready' => -5],
            ['pet' => 4, 'type' => 'Raio-X de Tórax',       'vet' => 1, 'requested' => -3, 'ready' => -2],
            ['pet' => 1, 'type' => 'Citologia',             'vet' => 0, 'requested' => -12, 'ready' => -11],
            ['pet' => 2, 'type' => 'Perfil Bioquímico',     'vet' => 1, 'requested' => -1, 'ready' => null],
        ];

        foreach ($examSpecs as $spec) {
            Exam::firstOrCreate(
                ['pet_id' => $pets[$spec['pet']]->id, 'type' => $spec['type'], 'requested_date' => now()->addDays($spec['requested'])->format('Y-m-d')],
                [
                    'vet_id'      => $spec['vet'] === 0 ? $vet->id : $vet2->id,
                    'status'      => $spec['ready'] === null ? 'requested' : 'ready',
                    'result_date' => $spec['ready'] === null ? null : now()->addDays($spec['ready']),
                ]
            );
        }

        // ── Faturamento ──────────────────────────────────────────────────────
        // 'paid' precisa de paid_at: o card "Receita do Mês" soma por esse campo.
        $invoiceSpecs = [
            ['n' => 'FAT-1001', 'tutor' => 0, 'pet' => 0, 'total' => 470.00, 'status' => 'paid',    'due' => -12, 'paid' => -12],
            ['n' => 'FAT-1002', 'tutor' => 1, 'pet' => 2, 'total' => 320.00, 'status' => 'paid',    'due' => -8,  'paid' => -8],
            ['n' => 'FAT-1003', 'tutor' => 2, 'pet' => 4, 'total' => 685.00, 'status' => 'paid',    'due' => -5,  'paid' => -5],
            ['n' => 'FAT-1004', 'tutor' => 3, 'pet' => 5, 'total' => 260.00, 'status' => 'pending', 'due' => 9,   'paid' => null],
            ['n' => 'FAT-1005', 'tutor' => 0, 'pet' => 1, 'total' => 535.00, 'status' => 'pending', 'due' => 18,  'paid' => null],
            ['n' => 'FAT-1006', 'tutor' => 2, 'pet' => 7, 'total' => 390.00, 'status' => 'overdue', 'due' => -6,  'paid' => null],
        ];

        foreach ($invoiceSpecs as $spec) {
            Invoice::firstOrCreate(
                ['invoice_number' => $spec['n']],
                [
                    'tutor_id'  => $tutors[$spec['tutor']]->id,
                    'pet_id'    => $pets[$spec['pet']]->id,
                    'user_id'   => $vet->id,
                    'subtotal'  => $spec['total'],
                    'discount'  => 0,
                    'total'     => $spec['total'],
                    'status'    => $spec['status'],
                    'due_date'  => now()->addDays($spec['due']),
                    'paid_at'   => $spec['paid'] === null ? null : now()->addDays($spec['paid']),
                    'branch_id' => $branchId,
                ]
            );
        }

        // ── Estoque: força o card "Estoque Baixo" ────────────────────────────
        // min_stock padrão é 5-10; baixamos o estoque de 3 itens abaixo dele.
        Product::whereIn('sku', ['ACESS001', 'DRONT001', 'RACAO001'])
            ->update(['stock' => 2]);

        // ── Atendimentos (MedicalRecord) ─────────────────────────────────────
        // Alimenta "Atendimentos Hoje", "Atendimentos por Tipo" e o gráfico de
        // distribuição. `date` é a data do atendimento, não created_at.
        $recordSpecs = [
            ['pet' => 0, 'day' => 0, 'time' => '09:00', 'type' => 'consulta',    'cc' => 'Retorno pós-operatório de castração', 'dx' => 'Cicatrização em evolução, sem sinais de infecção'],
            ['pet' => 6, 'day' => 0, 'time' => '10:30', 'type' => 'vacinacao',   'cc' => 'Antirrábica anual',                       'dx' => 'Animal saudável, apto para aplicação da vacina'],
            ['pet' => 2, 'day' => 0, 'time' => '14:30', 'type' => 'consulta',    'cc' => 'Controle de peso e apetite',               'dx' => 'Sobrepeso grau I — ajuste de ração'],
            ['pet' => 4, 'day' => 0, 'time' => '16:00', 'type' => 'exame',        'cc' => 'Tosse seca há 5 dias',                    'dx' => 'Investigação de tosse — radiografia solicitada'],
            ['pet' => 0, 'day' => -18, 'time' => '09:00', 'type' => 'consulta',  'cc' => 'Check-up anual',                           'dx' => 'Sem alterações — exames de rotina em dia'],
            ['pet' => 2, 'day' => -14, 'time' => '10:30', 'type' => 'consulta',  'cc' => 'Dermatite pruriginosa no dorso',            'dx' => 'Dermatopatia alérgica — início de imunoterapia'],
            ['pet' => 7, 'day' => -7,  'time' => '16:30', 'type' => 'exame',      'cc' => 'Soronegativo de FUNH',                     'dx' => 'Sorologia negativa para FIV/FeLV'],
        ];

        foreach ($recordSpecs as $i => $spec) {
            MedicalRecord::firstOrCreate(
                ['pet_id' => $pets[$spec['pet']]->id, 'date' => now()->addDays($spec['day'])->format('Y-m-d'), 'time' => $spec['time']],
                [
                    'user_id'         => $vet->id,
                    'vet_id'          => $spec['day'] % 2 === 0 ? $vet->id : $vet2->id,
                    'type'            => $spec['type'],
                    'chief_complaint' => $spec['cc'],
                    'diagnosis'       => $spec['dx'],
                    'branch_id'       => $branchId,
                ]
            );
        }

        // ── Lembretes de vacinação ───────────────────────────────────────────
        // O card "Lembretes Pendentes" conta VaccinationReminder com
        // scheduled_date <= hoje. Não é a tabela de Vaccination.
        $reminderSpecs = [
            ['pet' => 0, 'vaccine' => 'V10',         'batch' => 'VAC-2401', 'days' => -35],
            ['pet' => 0, 'vaccine' => 'Antirrábica', 'batch' => 'VAC-2402', 'days' => -20],
            ['pet' => 2, 'vaccine' => 'Quadrupla felina', 'batch' => 'VAC-2404', 'days' => -10],
            ['pet' => 4, 'vaccine' => 'Antirrábica', 'batch' => 'VAC-2405', 'days' => -5],
            ['pet' => 1, 'vaccine' => 'V10',         'batch' => 'VAC-2403', 'days' => 20],
        ];

        foreach ($reminderSpecs as $spec) {
            $vaccination = Vaccination::where('batch', $spec['batch'])->first();
            if (!$vaccination) {
                continue;
            }

            VaccinationReminder::firstOrCreate(
                ['vaccination_id' => $vaccination->id, 'scheduled_date' => now()->addDays($spec['days'])->format('Y-m-d')],
                [
                    'pet_id'  => $pets[$spec['pet']]->id,
                    'channel' => $spec['days'] < 0 ? 'whatsapp' : 'email',
                    'status'  => $spec['days'] < 0 ? 'pending' : 'scheduled',
                ]
            );
        }

        // ── Controle de parasitas ────────────────────────────────────────────
        // next_due_date no passado -> acende "Parasíticos Atrasados".
        $parasiteSpecs = [
            ['pet' => 0, 'product' => 'Drontal Plus', 'type' => 'Vermífugo',   'days' => -12, 'dose' => '1 comprimido', 'batch' => 'DRT-8801'],
            ['pet' => 1, 'product' => 'Advantix',      'type' => 'Endoparasita', 'days' => -4,  'dose' => '1 pipeta',     'batch' => 'ADV-8802'],
            ['pet' => 2, 'product' => 'Drontal Plus', 'type' => 'Vermífugo',   'days' => -8,  'dose' => '1 comprimido', 'batch' => 'DRT-8803'],
            ['pet' => 4, 'product' => 'Simparic 480', 'type' => 'Pulicida',    'days' => 15,  'dose' => '60 mg',        'batch' => 'SIM-8804'],
            ['pet' => 7, 'product' => 'Advantix',      'type' => 'Endoparasita', 'days' => 30,  'dose' => '1 pipeta',     'batch' => 'ADV-8805'],
        ];

        foreach ($parasiteSpecs as $spec) {
            ParasiteControl::firstOrCreate(
                ['pet_id' => $pets[$spec['pet']]->id, 'product_name' => $spec['product'], 'application_date' => now()->addDays($spec['days'] - 30)->format('Y-m-d')],
                [
                    'active_ingredient' => $spec['product'],
                    'type'              => $spec['type'],
                    'next_due_date'     => now()->addDays($spec['days'])->format('Y-m-d'),
                    'dose'              => $spec['dose'],
                    'batch'             => $spec['batch'],
                    'vet_id'            => $vet->id,
                    'branch_id'         => $branchId,
                ]
            );
        }

        $this->command->info(sprintf(
            'Demo AgroVerde: %d tutores, %d pets, %d agendamentos, %d vacinas, %d faturas, %d atendimentos, %d lembretes, %d parasitarios.',
            $tutors->count(),
            $pets->count(),
            count($appointmentSpecs),
            count($vaccineSpecs),
            count($invoiceSpecs),
            count($recordSpecs),
            count($reminderSpecs),
            count($parasiteSpecs)
        ));
    }
}
