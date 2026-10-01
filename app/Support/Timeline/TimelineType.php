<?php

namespace App\Support\Timeline;

use Illuminate\Support\Collection;

/**
 * Definição de um tipo de evento do prontuário.
 *
 * ── Por que um objeto em vez de mais um foreach ────────────────────────────
 * O PatientTimelineController do upstream tem sete blocos `foreach` com
 * `->push()`, repetindo rótulo, ícone, cor e rota em cada um. Adicionar o
 * oitavo tipo significava editar o controller E a view, em lockstep, com o
 * risco de um lado mudar e o outro não.
 *
 * Aqui cada tipo é um arquivo só, e a timeline (montagem) e a view (filtros)
 * leem a mesma definição. Um tipo novo é um arquivo novo — camada A, sem
 * conflito de merge com o upstream.
 */
class TimelineType
{
    public function __construct(
        /** Chave estável: base da URL do filtro (?tipos=consulta). */
        public readonly string $slug,
        /** Rótulo exibido e usado nos filtros. */
        public readonly string $label,
        public readonly string $icon,
        public readonly string $color,
        /** Ordem no agrupamento dos filtros. */
        public readonly int $ordem = 50,
    ) {
    }

    /**
     * Eventos deste tipo para um pet.
     *
     * @param  \App\Models\Pet  $pet
     * @return Collection<int, array{date: mixed, type: string, icon: string, color: string, summary: string, url: ?string, tipo: string}>
     */
    public function eventos($pet): Collection
    {
        return collect();
    }
}
