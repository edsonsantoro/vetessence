<?php

namespace Tests\Unit\Imports;

use App\Imports\SimplesVet\SimplesVetMapper;
use PHPUnit\Framework\TestCase;

/**
 * Testa o mapeamento SimplesVet → VetEssence.
 *
 * Usa TestCase puro (sem RefreshDatabase): o mapper é função pura de string
 * para array, não toca no banco. Testar banco aqui seria testar o Laravel.
 *
 * Os casos vêm dos valores reais achados em `analyze_source.py` — inclusive
 * as sujeiras (espaço no "Shih- Tzu", CPF com dígito errado, endereço vazio).
 */
class SimplesVetMapperTest extends TestCase
{
    // ── CPF ────────────────────────────────────────────────────────────────

    public function test_formata_cpf_para_o_padrao_brasileiro(): void
    {
        $this->assertSame('877.213.921-87', SimplesVetMapper::cpf('877.213.921-87'));
        $this->assertSame('877.213.921-87', SimplesVetMapper::cpf('87721392187'));
        // Zeros à esquerda não podem ser perdidos.
        $this->assertSame('007.275.701-96', SimplesVetMapper::cpf('00727570196'));
        $this->assertSame('007.275.701-96', SimplesVetMapper::cpf('007.275.701-96'));
    }

    public function test_cpf_com_tamanho_errado_vira_nulo(): void
    {
        $this->assertNull(SimplesVetMapper::cpf(null));
        $this->assertNull(SimplesVetMapper::cpf(''));
        $this->assertNull(SimplesVetMapper::cpf('123.456'));
        $this->assertNull(SimplesVetMapper::cpf('abcdefghijk'));
    }

    /** O ERP traz 7.620 CPFs com o último dígito errado. Não tentamos corrigir. */
    public function test_cpf_invalido_e_importado_cru_e_nao_descartado(): void
    {
        $linha = SimplesVetMapper::tutor([
            'nome' => 'ABIANCI SILVA',
            'cpf' => '877.213.921-87',
            'chave' => '4276',
        ]);

        $this->assertSame('877.213.921-87', $linha['cpf']);
    }

    // ── Telefone ───────────────────────────────────────────────────────────

    public function test_formata_telefone_por_tamanho(): void
    {
        $this->assertSame('66 9643-8197', SimplesVetMapper::telefone('(66) 9643-8197'));
        $this->assertSame('11 99999-8888', SimplesVetMapper::telefone('11999998888'));
        $this->assertSame('11 3333-4444', SimplesVetMapper::telefone('1133334444'));
    }

    /** Existe 1 registro com telefone de 5 dígitos — lixo de digitação. */
    public function test_telefone_curto_demais_e_descartado(): void
    {
        $this->assertNull(SimplesVetMapper::telefone('12345'));
        $this->assertNull(SimplesVetMapper::telefone('(00) 123'));
        $this->assertNull(SimplesVetMapper::telefone(null));
    }

    /** `tutors.phone` é NOT NULL e 16 clientes não têm telefone nenhum. */
    public function test_phone_cai_para_celular_e_para_string_vazia(): void
    {
        $soCelular = SimplesVetMapper::tutor([
            'nome' => 'X', 'telefone' => null, 'celularLista' => '(66) 9643-8197',
        ]);
        $this->assertSame('66 9643-8197', $soCelular['phone']);
        $this->assertNull($soCelular['phone_secondary'], 'não vira secundário de si mesmo');

        $nenhum = SimplesVetMapper::tutor([
            'nome' => 'Y', 'telefone' => null, 'celularLista' => null,
        ]);
        $this->assertSame('', $nenhum['phone']);
    }

    // ── Sexo ───────────────────────────────────────────────────────────────

    public function test_sexo_mapeia_para_o_enum_do_destino(): void
    {
        $this->assertSame('male', SimplesVetMapper::genero('M'));
        $this->assertSame('female', SimplesVetMapper::genero('F'));
    }

    /**
     * 112 animais vêm com sexo 'I' e 376 sem sexo. A coluna destino é
     * enum('male','female') — inventar valor quebraria o insert.
     */
    public function test_sexo_i_e_nulo_viram_null_em_vez_de_adivinhado(): void
    {
        $this->assertNull(SimplesVetMapper::genero('I'));
        $this->assertNull(SimplesVetMapper::genero(null));
        $this->assertNull(SimplesVetMapper::genero(''));
        $this->assertNull(SimplesVetMapper::genero('X'));
    }

    // ── Espécie ────────────────────────────────────────────────────────────

    public function test_normaliza_grafia_da_especie(): void
    {
        $this->assertSame('Canina', SimplesVetMapper::especie(['nome' => 'Canina']));
        $this->assertSame('Avícola', SimplesVetMapper::especie(['nome' => 'Avícola']));
        $this->assertSame('Avícola', SimplesVetMapper::especie(['nome' => 'AVICOLA']));
        $this->assertSame('Exótico', SimplesVetMapper::especie(['nome' => 'EXOTICO']));
    }

    public function test_especie_exotica_desconhecida_preserva_o_nome_original(): void
    {
        // Nomes científicos existem no dado (Erethizontidae, CERVIDAE).
        // Descartar seria perder informação; o varchar(50) comporta.
        $this->assertSame('Erethizontidae', SimplesVetMapper::especie(['nome' => 'Erethizontidae']));
        $this->assertSame('AVE DE RAPINA', SimplesVetMapper::especie(['nome' => 'AVE DE RAPINA']));
    }

    public function test_especie_ausente_vira_valor_explicito_e_nao_vazio(): void
    {
        $this->assertSame('Não informada', SimplesVetMapper::especie(null));
        $this->assertSame('Não informada', SimplesVetMapper::especie(['nome' => null]));
        $this->assertSame('Não informada', SimplesVetMapper::especie(['nome' => '   ']));
    }

    // ── Raça ───────────────────────────────────────────────────────────────

    /** 2.679 pets vêm como "Shih- Tzu" — espaço antes do hífen. */
    public function test_corrige_espaco_indevido_no_nome_da_raca(): void
    {
        $this->assertSame('Shih-Tzu', SimplesVetMapper::raca(['nome' => 'Shih- Tzu']));
        $this->assertSame('Shih-Tzu', SimplesVetMapper::raca(['nome' => 'shih tzu']));
    }

    public function test_raca_comum_passa_por_uma_pele(): void
    {
        $this->assertSame('SRD', SimplesVetMapper::raca(['nome' => 'SRD']));
        $this->assertSame('Pinscher', SimplesVetMapper::raca(['nome' => 'Pinscher']));
    }

    public function test_raca_ausente_vira_nulo(): void
    {
        $this->assertNull(SimplesVetMapper::raca(null));
        $this->assertNull(SimplesVetMapper::raca(['nome' => null]));
        $this->assertNull(SimplesVetMapper::raca(['nome' => '']));
    }

    /** A coluna é varchar(100); nomes maiores precisam ser cortados, não estourar. */
    public function test_raca_acima_de_100_chars_e_truncada(): void
    {
        $longa = SimplesVetMapper::raca(['nome' => str_repeat('X', 250)]);

        $this->assertNotNull($longa);
        $this->assertSame(100, mb_strlen($longa));
    }

    // ── Óbito ──────────────────────────────────────────────────────────────

    public function test_animal_morto_vira_inativo(): void
    {
        $pet = SimplesVetMapper::pet(['nome' => 'JOE', 'morto' => 'S', 'vivo_morto' => 'Óbito']);

        $this->assertSame(0, $pet['is_active']);
        $this->assertStringContainsString('Óbito', $pet['notes']);
    }

    public function test_animal_vivo_vira_ativo_sem_nota(): void
    {
        $pet = SimplesVetMapper::pet(['nome' => 'PANDORA', 'morto' => 'N']);

        $this->assertSame(1, $pet['is_active']);
        $this->assertNull($pet['notes']);
    }

    // ── Endereço ───────────────────────────────────────────────────────────

    public function test_extrai_o_endereco_quando_existe(): void
    {
        $linha = SimplesVetMapper::tutor([
            'nome' => 'X',
            'endereco' => [
                'endereco' => 'RUA ANTONIO LUCIANO',
                'numero' => '205',
                'bairro' => 'BOA ESPERANÇA',
                'municipio' => 'SINOP',
                'uf' => 'MT',
                'cep' => '78100000',
            ],
        ]);

        $this->assertSame('RUA ANTONIO LUCIANO', $linha['address']);
        $this->assertSame('205', $linha['number']);
        $this->assertSame('BOA ESPERANÇA', $linha['neighborhood']);
        $this->assertSame('SINOP', $linha['city']);
        $this->assertSame('MT', $linha['state']);
        $this->assertSame('78100-000', $linha['zipcode']);
    }

    /** 7.055 dos 9.928 tutores têm um objeto `endereco` com tudo nulo. */
    public function test_endereco_vazio_nao_quebra_a_importacao(): void
    {
        $linha = SimplesVetMapper::tutor(['nome' => 'X', 'endereco' => [
            'id' => null, 'endereco' => null, 'numero' => null,
            'bairro' => null, 'municipio' => null, 'uf' => null, 'cep' => null,
        ]]);

        $this->assertNull($linha['address']);
        $this->assertNull($linha['city']);
        $this->assertNull($linha['state']);
    }

    public function test_endereco_ausente_ou_de_tipo_inesperado_e_tratado(): void
    {
        $this->assertNull(SimplesVetMapper::tutor(['nome' => 'X'])['address']);
        $this->assertNull(SimplesVetMapper::tutor(['nome' => 'X', 'endereco' => null])['address']);
        $this->assertNull(SimplesVetMapper::tutor(['nome' => 'X', 'endereco' => 'texto'])['address']);
    }

    public function test_uf_invalida_vira_nulo(): void
    {
        $this->assertSame('MT', SimplesVetMapper::uf('mt'));
        $this->assertNull(SimplesVetMapper::uf('SINOP-MT'));
        $this->assertNull(SimplesVetMapper::uf(''));
        $this->assertNull(SimplesVetMapper::uf(null));
    }

    // ── Campos diversos ────────────────────────────────────────────────────

    public function test_email_invalido_vira_nulo(): void
    {
        $this->assertSame('a@b.com.br', SimplesVetMapper::email('a@b.com.br'));
        $this->assertNull(SimplesVetMapper::email('nao-e-email'));
        $this->assertNull(SimplesVetMapper::email(null));
        $this->assertNull(SimplesVetMapper::email(''));
    }

    public function test_cep_formata_e_rejeita_tamanho_errado(): void
    {
        $this->assertSame('78100-000', SimplesVetMapper::cep('78100-000'));
        $this->assertNull(SimplesVetMapper::cep('7800'));
        $this->assertNull(SimplesVetMapper::cep(null));
    }

    public function test_booleano_aceita_as_grafias_do_erp(): void
    {
        $this->assertSame(1, SimplesVetMapper::booleano('S'));
        $this->assertSame(1, SimplesVetMapper::booleano('sim'));
        $this->assertSame(1, SimplesVetMapper::booleano(true));
        $this->assertSame(0, SimplesVetMapper::booleano('N'));
        $this->assertSame(0, SimplesVetMapper::booleano(false));
        $this->assertSame(0, SimplesVetMapper::booleano(null));
    }

    public function test_url_aceita_somente_http_e_https(): void
    {
        $this->assertSame(
            'https://s3.amazonaws.com/a.jpg',
            SimplesVetMapper::url('https://s3.amazonaws.com/a.jpg')
        );
        $this->assertNull(SimplesVetMapper::url('javascript:alert(1)'));
        $this->assertNull(SimplesVetMapper::url('/caminho/local.jpg'));
        $this->assertNull(SimplesVetMapper::url(null));
    }

    /** `pets.name` é NOT NULL e todos os 16.202 vieram preenchidos. */
    public function test_nome_vazio_cai_para_marcador_em_vez_de_estourar_o_insert(): void
    {
        $this->assertSame('SEM NOME', SimplesVetMapper::pet(['nome' => '  '])['name']);
        $this->assertSame('SEM NOME', SimplesVetMapper::tutor(['nome' => ''])['name']);
    }

    public function test_data_de_nascimento_e_normalizada(): void
    {
        $this->assertSame('2023-10-25', SimplesVetMapper::data('2023-10-25'));
        $this->assertSame('2023-10-25', SimplesVetMapper::data('25/10/2023'));
        $this->assertNull(SimplesVetMapper::data(''));
        $this->assertNull(SimplesVetMapper::data(null));
    }

    // ── Chave (join com a agenda) ──────────────────────────────────────────

    public function test_chave_e_normalizada_para_o_tamanho_da_coluna(): void
    {
        $this->assertSame('10155', SimplesVetMapper::chave('10155'));
        $this->assertSame('10155', SimplesVetMapper::chave(10155));
        $this->assertSame(32, mb_strlen((string) SimplesVetMapper::chave(str_repeat('9', 60))));
        $this->assertNull(SimplesVetMapper::chave(null));
    }

    /** As duas colunas precisam existir para o upsert ser idempotente. */
    public function test_linhas_do_mapeamento_trazem_a_chave_de_origem(): void
    {
        $tutor = SimplesVetMapper::tutor(['nome' => 'X', 'chave' => '4276']);
        $pet = SimplesVetMapper::pet(['nome' => 'Y', 'chave' => '6269']);

        $this->assertSame('4276', $tutor['simplesvet_chave']);
        $this->assertSame('6269', $pet['simplesvet_chave']);
    }

    // ── Estrutura das linhas ───────────────────────────────────────────────

    public function test_linha_do_tutor_traz_exatamente_as_colunas_da_tabela(): void
    {
        $colunas = array_keys(SimplesVetMapper::tutor(['nome' => 'X']));

        $this->assertSame([
            'name', 'cpf', 'phone', 'phone_secondary', 'email', 'zipcode',
            'address', 'number', 'complement', 'neighborhood', 'city', 'state',
            'notify_sms', 'notify_whatsapp', 'notify_email', 'simplesvet_chave',
            'created_at_branch_id', 'created_at', 'updated_at',
        ], $colunas);
    }

    public function test_linha_do_pet_traz_exatamente_as_colunas_da_tabela(): void
    {
        $colunas = array_keys(SimplesVetMapper::pet(['nome' => 'Y']));

        $this->assertSame([
            'name', 'simplesvet_chave', 'species', 'breed', 'gender',
            'birth_date', 'coat', 'microchip_number', 'photo_url',
            'is_active', 'notes', 'created_at_branch_id', 'created_at', 'updated_at',
        ], $colunas);
    }

    /**
     * Se o ERP ganhar um campo novo e o mapper esquecer de mapear, o insert
     * quebra no meio de 42 mil linhas. Este teste falha antes.
     */
    public function test_nenhuma_coluna_excede_o_tamanho_da_coluna(): void
    {
        $tutor = SimplesVetMapper::tutor([
            'nome' => str_repeat('N', 300),
            'endereco' => ['endereco' => str_repeat('E', 300), 'bairro' => str_repeat('B', 120)],
        ]);
        $pet = SimplesVetMapper::pet([
            'nome' => str_repeat('P', 300),
            'chip' => str_repeat('C', 80),
            'foto' => 'https://x.com/' . str_repeat('f', 400),
        ], '1');

        $this->assertLessThanOrEqual(255, mb_strlen($tutor['name']));
        $this->assertLessThanOrEqual(255, mb_strlen($tutor['address']));
        $this->assertLessThanOrEqual(100, mb_strlen($tutor['neighborhood']));
        $this->assertLessThanOrEqual(50, mb_strlen($pet['microchip_number']));
        $this->assertLessThanOrEqual(255, mb_strlen($pet['photo_url']));
    }
}
