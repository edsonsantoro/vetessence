<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Utilitários do fork — camada A (arquivo novo, nunca conflita no merge).
 */
class AgroVerde
{
    /**
     * Tamanhos de página aceitos. Alinhado com o lengthMenu do DataTable que
     * o layout usa nas tabelas não paginadas: quem conhece uma tabela do app
     * já sabe estes números.
     */
    public const TAMANHOS = [10, 25, 50, 100];

    /**
     * Pagina uma listagem preservando os filtros da query string.
     *
     * ── Por que existe ──────────────────────────────────────────────────────
     * 30 dos 87 controllers do upstream fazem `->get()` no index. Isso era
     * invisível com a base de demonstração (10 pets) e virou erro 500 assim que
     * entraram os ~16 mil pets e ~10 mil tutores do SimplesVet: "Allowed
     * memory size exhausted", porque a tabela inteira era carregada e
     * renderizada.
     *
     * Centralizar aqui evita 30 cópias de `$request->input('per_page', 50)`
     * com valores diferentes cada uma.
     *
     * ── Por que limitar os tamanhos ─────────────────────────────────────────
     * Sem whitelist, `?per_page=100000` numa tabela de 16 mil linhas volta a
     * estourar a memória — o mesmo bug que estamos corrigindo, por outra
     * porta. Valor fora da lista cai no padrão em vez de ser aceito.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function paginar($query, int $padrao = 50, ?Request $request = null): LengthAwarePaginator
    {
        $request ??= request();

        $pedido = (int) $request->input('per_page', $padrao);
        $porPagina = in_array($pedido, self::TAMANHOS, true) ? $pedido : $padrao;

        return $query->paginate($porPagina)->appends($request->query());
    }
}
