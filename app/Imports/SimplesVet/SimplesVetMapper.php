<?php

namespace App\Imports\SimplesVet;

/**
 * Mapeador SimplesVet -> VetEssence
 *
 * Só transformação: entra o registro cru do ERP, sai a linha pronta pro banco.
 * Nada de I/O aqui de propósito — é a parte que precisa de teste, e testar
 * I/O exige banco.
 *
 * ── Decisões que valem saber ──────────────────────────────────────────────
 *
 * 1) **CPF é importado como vem, sem corrigir.**
 *    7.620 dos 9.380 CPFs da origem falham no dígito verificador, mas 7.605
 *    deles têm o PRIMEIRO dígito certo — ou seja, é erro de digitação no
 *    último dígito, não CPF inventado. Adivinhar o dígito certo geraria
 *    documento falso. O dado entra cru e a improdutividade fica visível.
 *
 * 2) **Sexo 'I' vira null.**
 *    A coluna `pets.gender` é enum('male','female'). O SimplesVet usa 'I'
 *    (112 animais) e null (376) — provavelmente castrado e indefinido. Não
 *    inventar valor: fica null, que a coluna aceita.
 *
 * 3) **Óbito vira is_active = false.**
 *    Não existe coluna de data de óbito em `pets`. Dos 1.436 doentes, 110 não
 *    têm nem a data de nascimento para inferir. A informação some na
 *    importação — limitação do schema de destino, registrada aqui.
 *
 * 4) **Endereço entra do jeito que vier.**
 *    Só 28,9% dos tutores têm endereço preenchido no ERP; os outros 7.055 têm
 *    um objeto vazio, não um endereço legível.
 */
class SimplesVetMapper
{
    /** Grafia que o ERP usa -> valor aceito pelo enum. */
    private const SEXO = [
        'M' => 'male',
        'F' => 'female',
        // 'I' (castrado) e null ficam de fora de propósito.
    ];

    /**
     * Grafia suja que aparece na origem e o valor limpo.
     * Só entra o que realmente aparece no dado — ver analyze_source.py.
     */
    private const ESPECIES = [
        'CANINA'  => 'Canina',
        'FELINA'  => 'Felina',
        'AVÍCOLA' => 'Avícola',
        'AVICOLA' => 'Avícola',
        'ROEDOR'  => 'Roedor',
        'EXÓTICO' => 'Exótico',
        'EXOTICO' => 'Exótico',
        'PRIMATAS' => 'Primatas',
        'EQUINA'  => 'Equina',
        'OUTRAS'  => 'Outras',
    ];

    private const RACA = [
        'shih- tzu' => 'Shih-Tzu',   // espaço antes do hífen na origem
        'shih tzu'  => 'Shih-Tzu',
    ];

    /** Telefone com 5 dígitos é lixo de digitação; não é telefone válido. */
    private const MIN_DIGITOS_TELEFONE = 10;

    /** @var array<string,int> Limite de cada coluna de texto do destino. */
    public const LIMITES = [
        'name' => 255, 'address' => 255, 'number' => 20, 'complement' => 50,
        'neighborhood' => 100, 'city' => 100, 'email' => 255, 'coat' => 50,
        'breed' => 100, 'species' => 50, 'notes' => 2000,
        'microchip_number' => 50, 'photo_url' => 255, 'phone' => 20,
    ];

    public static function tutor(array $cli): array
    {
        $endereco = is_array($cli['endereco'] ?? null) ? $cli['endereco'] : [];

        $telefone     = static::telefone($cli['telefone'] ?? null);
        $celular      = static::telefone($cli['celularLista'] ?? null);

        // `phone` é NOT NULL no destino. Menos de 2% dos registros ficam sem
        // telefone; preferimos string vazia a um número inventado.
        if ($telefone === null) {
            $telefone = $celular ?? '';
        }

        $telefoneSecundario = ($celular !== null && $celular !== $telefone) ? $celular : null;

        return [
            'name'                 => static::texto($cli['nome'] ?? null, self::LIMITES['name']) ?: 'SEM NOME',
            'cpf'                  => static::cpf($cli['cpf'] ?? null),
            'phone'                => static::limitar($telefone, self::LIMITES['phone']),
            'phone_secondary'      => static::limitar($telefoneSecundario, self::LIMITES['phone']),
            'email'                => static::limitar(
                static::email($cli['email'] ?? $cli['emailLista'] ?? null),
                self::LIMITES['email']
            ),
            'zipcode'              => static::cep($endereco['cep'] ?? null),
            'address'              => static::texto($endereco['endereco'] ?? null, self::LIMITES['address']),
            'number'               => static::texto($endereco['numero'] ?? null, self::LIMITES['number']),
            'complement'           => static::texto($endereco['complemento'] ?? null, self::LIMITES['complement']),
            'neighborhood'         => static::texto($endereco['bairro'] ?? null, self::LIMITES['neighborhood']),
            'city'                 => static::texto($endereco['municipio'] ?? null, self::LIMITES['city']),
            'state'                => static::uf($endereco['uf'] ?? null),
            'notify_sms'           => static::booleano($cli['aceitaSms'] ?? false),
            'notify_whatsapp'      => static::booleano($cli['aceitaWhatsapp'] ?? false),
            'notify_email'         => static::booleano($cli['aceitaEmail'] ?? false),
            'simplesvet_chave'     => static::chave($cli['chave'] ?? null),
            'created_at_branch_id' => null,   // preenchido pelo command (filial corrente)
            'created_at'           => now(),
            'updated_at'           => now(),
        ];
    }

    public static function pet(array $animal): array
    {
        return [
            'name'                 => static::texto($animal['nome'] ?? null, self::LIMITES['name']) ?: 'SEM NOME',
            'simplesvet_chave'     => static::chave($animal['chave'] ?? null),
            'species'              => static::limitar(
                static::especie($animal['especie'] ?? null),
                self::LIMITES['species']
            ),
            'breed'                => static::raca($animal['raca'] ?? null),
            'gender'               => static::genero($animal['sexo'] ?? null),
            'birth_date'           => static::data($animal['nascimento'] ?? null),
            'coat'                 => static::texto(($animal['pelagem'] ?? null)['nome'] ?? null, self::LIMITES['coat']),
            'microchip_number'     => static::texto($animal['chip'] ?? null, self::LIMITES['microchip_number']),
            'photo_url'            => static::limitar(
                static::url($animal['foto'] ?? null),
                self::LIMITES['photo_url']
            ),
            // Sem coluna de óbito em pets: quem morreu sai do histórico ativo.
            'is_active'            => ($animal['morto'] ?? 'N') !== 'S' ? 1 : 0,
            'notes'                => static::limitar(
                static::notaAnimal($animal),
                self::LIMITES['notes']
            ),
            'created_at_branch_id' => null,   // preenchido pelo command
            'created_at'           => now(),
            'updated_at'           => now(),
        ];
    }

    // ── Normalizadores ──────────────────────────────────────────────────────

    /**
     * Limpa espaços e corta no limite da coluna.
     *
     * O corte não é cosmético: uma linha mais longa que a coluna estoura o
     * INSERT e mata o lote inteiro. Sem `strict` o MySQL trunca em silêncio
     * e a perda fica invisível; com `strict` (padrão do Laravel 10+) o
     * lote inteiro aborta. Melhor cortar na importação.
     */
    public static function texto(?string $valor, ?int $max = null): ?string
    {
        if ($valor === null) {
            return null;
        }
        $v = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');

        if ($v === '') {
            return null;
        }

        return static::limitar($v, $max);
    }

    public static function limitar(?string $valor, ?int $max): ?string
    {
        if ($valor === null || $max === null) {
            return $valor;
        }

        return mb_strlen($valor) > $max ? mb_substr($valor, 0, $max) : $valor;
    }

    public static function cpf(?string $valor): ?string
    {
        $d = preg_replace('/\D/', '', $valor ?? '');

        return strlen($d) === 11 ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.'
            . substr($d, 6, 3) . '-' . substr($d, 9, 2) : null;
    }

    /**
     * Telefone só em dígitos, formatado no padrão brasileiro.
     *
     * 11 dígitos = celular com 9 na frente  → AA NNNNN-XXXX
     * 10 dígitos = fixo com 8 na frente     → AA NNNN-XXXX
     *
     * A diferença é um dígito: cortar no lugar errado deforma o número em vez
     * de só mudar a pontuação, e telefone deformado não liga.
     */
    public static function telefone(?string $valor): ?string
    {
        $d = preg_replace('/\D/', '', $valor ?? '');
        if ($d === '' || strlen($d) < self::MIN_DIGITOS_TELEFONE) {
            return null;
        }

        if (strlen($d) === 11) {
            return substr($d, 0, 2) . ' ' . substr($d, 2, 5) . '-' . substr($d, 7, 4);
        }

        if (strlen($d) === 10) {
            return substr($d, 0, 2) . ' ' . substr($d, 2, 4) . '-' . substr($d, 6, 4);
        }

        // 12+ dígitos: não é telefone brasileiro. Não inventar formato.
        return $d;
    }

    public static function email(?string $valor): ?string
    {
        $v = filter_var(trim($valor ?? ''), FILTER_VALIDATE_EMAIL);

        return $v === false ? null : $v;
    }

    public static function cep(?string $valor): ?string
    {
        $d = preg_replace('/\D/', '', $valor ?? '');

        return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : null;
    }

    /** Só aceita sigla de 2 letras: o ERP manda 'MT', não 'SINOP-MT'. */
    public static function uf(?string $valor): ?string
    {
        $v = strtoupper(trim($valor ?? ''));

        return preg_match('/^[A-Z]{2}$/', $v) ? $v : null;
    }

    /**
     * Normaliza data de nascimento.
     *
     * O ERP manda ISO (`nascimento`) mas também há campos em dd/mm/aaaa.
     * `strtotime` não é confiável para o formato brasileiro — depende do
     * locale e chega a devolver false — então os dois formatos são tratados
     * explicitamente antes de cair no strtotime como último recurso.
     */
    public static function data(?string $valor): ?string
    {
        $v = trim($valor ?? '');
        if ($v === '') {
            return null;
        }

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $v, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                ? "{$m[1]}-{$m[2]}-{$m[3]}"
                : null;
        }

        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $v, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                ? "{$m[3]}-{$m[2]}-{$m[1]}"
                : null;
        }

        $ts = strtotime($v);

        return $ts === false ? null : date('Y-m-d', $ts);
    }

    public static function genero(?string $valor): ?string
    {
        return self::SEXO[strtoupper(trim($valor ?? ''))] ?? null;
    }

    public static function especie($valor): string
    {
        $nome = is_array($valor) ? ($valor['nome'] ?? null) : $valor;
        $nome = static::texto($nome);

        if ($nome === null) {
            return 'Não informada';
        }

        return self::ESPECIES[strtoupper($nome)] ?? $nome;
    }

    public static function raca($valor): ?string
    {
        $nome = is_array($valor) ? ($valor['nome'] ?? null) : $valor;
        $nome = static::texto($nome);

        if ($nome === null) {
            return null;
        }

        return static::limitar(self::RACA[strtolower($nome)] ?? $nome, self::LIMITES['breed']);
    }

    public static function chave($valor): ?string
    {
        $v = static::texto(is_scalar($valor) ? (string) $valor : null);

        return $v === null ? null : mb_substr($v, 0, 32);
    }

    public static function url(?string $valor): ?string
    {
        $v = static::texto($valor);
        if ($v === null) {
            return null;
        }

        return preg_match('#^https?://#i', $v) ? $v : null;
    }

    public static function booleano($valor): int
    {
        if (is_string($valor)) {
            return in_array(strtoupper(trim($valor)), ['S', 'SIM', 'TRUE', '1', 'Y'], true) ? 1 : 0;
        }

        return $valor ? 1 : 0;
    }

    /**
     * Preserva o que não tem coluna no destino.
     * Sem isso o óbito — 1.436 animais — some sem rastro.
     */
    private static function notaAnimal(array $animal): ?string
    {
        $partes = [];

        $morto = $animal['morto'] ?? 'N';
        if ($morto === 'S') {
            $partes[] = 'Óbito (' . ($animal['vivo_morto'] ?? 'sim') . ') — importado do SimplesVet';
        }
        if (! empty($animal['esterilizacao'])) {
            $partes[] = 'Esterilizado: ' . (is_array($animal['esterilizacao'])
                ? ($animal['esterilizacao']['nome'] ?? json_encode($animal['esterilizacao']))
                : $animal['esterilizacao']);
        }
        if (! empty($animal['planoSaude'])) {
            $plano = $animal['planoSaude'];
            $partes[] = 'Plano de saúde: ' . (is_array($plano)
                ? ($plano['nome'] ?? json_encode($plano))
                : $plano);
        }

        return $partes ? implode(' | ', $partes) : null;
    }
}
