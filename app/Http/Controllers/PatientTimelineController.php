<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Support\Timeline\Timeline;
use Illuminate\Http\Request;

class PatientTimelineController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:pets.view');
    }

    /**
     * Prontuário do animal: tudo o que aconteceu com o pet, em uma linha do
     * tempo.
     *
     * ── AgroVerde (camada C — edição mínima no core) ────────────────────────
     * O upstream montava a timeline com sete blocos `foreach` inline, cada um
     * repetindo rótulo, ícone, cor e rota. Este método só delega para
     * App\Support\Timeline\Timeline (camada A), que é quem sabe os tipos.
     *
     * Por que não overlay: a montagem acontece no controller, e o overlay de
     * view (camada B) não alcança código PHP. A única alternativa seria
     * duplicar os sete blocos num controller nosso e trocar a rota — mais
     * código e mais atrito de merge do que uma delegação.
     *
     * Filtros: `?tipos=consulta,exame` (vazio = todos), `?de=2026-01-01`,
     * `?ate=2026-12-31`. Slug ou data inválidos são ignorados, não viram erro.
     */
    public function index(Request $request, Pet $pet)
    {
        $pet->load('tutors');

        $tipos = $this->tiposPedidos($request);
        $de = $this->dataValida($request->query('de'));
        $ate = $this->dataValida($request->query('ate'));

        $eventos = Timeline::paraPet($pet, $tipos, $de, $ate);

        return view('pets.timeline', [
            'pet' => $pet,
            'events' => $eventos,
            'tiposDisponiveis' => Timeline::tipos(),
            'tiposAtivos' => $tipos,
            'totalPorTipo' => Timeline::totalPorTipo($pet),
            'de' => $de,
            'ate' => $ate,
        ]);
    }

    /**
     * Slugs válidos pedidos na URL.
     *
     * null = nenhum filtro (todos). [] = pediu, mas nada era válido.
     *
     * A distinção importa: `?tipos=xyz` com "nada válido" devolvendo null
     * faria a tela mostrar TUDO, que é o oposto do que a pessoa pediu. O
     * comentário anterior neste método dizia uma coisa e o código fazia
     * outra — agora a semântica bate.
     *
     * @return array<int,string>|null
     */
    private function tiposPedidos(Request $request): ?array
    {
        $bruto = $request->query('tipos');

        if ($bruto === null || $bruto === '') {
            return null;
        }

        $pedidos = array_filter(
            array_map('trim', explode(',', is_array($bruto) ? implode(',', $bruto) : (string) $bruto)),
            fn ($slug) => $slug !== ''
        );

        return array_values(array_filter(
            $pedidos,
            fn ($slug) => Timeline::tipoPorSlug($slug) !== null
        ));
    }

    private function dataValida($valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        $ts = strtotime($valor);

        // strtotime aceita "amanhã" e devolve epoch para data inválida, o que
        // finjaria um filtro que não existe. Exigir YYYY-MM-DD resolve.
        return $ts !== false && preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)
            ? date('Y-m-d', $ts)
            : null;
    }
}
