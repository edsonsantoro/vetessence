{{--
    Prontuário do animal — overlay AgroVerde (camada B).

    Substitui resources/views/pets/timeline.blade.php do upstream. O motivo
    documentado no PatientTimelineController: a montagem da linha do tempo
    mora no controller, então o overlay de view não bastava — este arquivo
    é o outro lado do mesmo trabalho e precisa ficar junto no fork.

    O que muda em relação ao upstream:
      - filtro por tipo (12 tipos, com contador de eventos)
      - filtro por período
      - resumo no topo: total de eventos e tipos presentes
      - a linha do tempo continua com os mesmos rótulos ('Prontuário',
        'Vacina') e o estado vazio ('Nenhum evento'), que o
        PatientTimelineTest fixa.

    Ver AGROVERDE.md §12.
--}}
@extends('layouts.adminlte', ['title' => 'Prontuário'])
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h4 class="mb-0"><i class="fas fa-history"></i> Prontuário — {{ $pet->name }}</h4>
        <a href="{{ route('pets.show', $pet) }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar ao Pet
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>{{ $pet->name }}</strong> —
                {{ $pet->species ?? '' }} {{ $pet->breed ?? '' }}
                @if($pet->gender) ({{ $pet->gender === 'male' ? 'Macho' : 'Fêmea' }}) @endif
                @if($pet->age) | {{ $pet->age }} @endif
                @if($pet->weight) | {{ $pet->weight }} kg @endif
            </div>
            <div class="text-muted small">
                <i class="fas fa-layer-group"></i>
                {{ count($events) }} {{ count($events) === 1 ? 'evento' : 'eventos' }}
                @if ($tiposAtivos !== null)
                    <span class="badge badge-info ml-1">
                        {{ count($tiposAtivos) }} {{ count($tiposAtivos) === 1 ? 'tipo filtrado' : 'tipos filtrados' }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Filtros ──────────────────────────────────────────────────────── --}}
    <form method="GET" class="card mb-3">
        <div class="card-body py-3">
            <label class="form-label small font-weight-bold text-muted mb-2">
                <i class="fas fa-filter"></i> Tipo de registro
            </label>
            <div class="mb-3">
                @php
                    // Contagem por tipo sobre os eventos JÁ filtrados: um tipo
                    // zerado aparece esmaecido e não é clicável. Sem isso a
                    // tela oferece 12 filtros para um paciente que tem 3
                    // tipos — e clicar num tipo sem nada não dá nenhuma pista
                    // do porquê.
                    $ativos = $tiposAtivos ?? [];
                    $contagem = collect($events)->countBy('tipo');
                    $temFiltro = $ativos !== null && $ativos !== [];
                    // Total do paciente, sem filtro: com "Todos 1" ao lado de
                    // "Fatura 1" não há como saber que existem mais 5 eventos
                    // escondidos pelos outros filtros.
                    $totalGeral = array_sum($totalPorTipo);
                    $hrefTipo = function (string $slug) use ($pet, $ativos, $de, $ate) {
                        $novos = collect($ativos ?? [])
                            ->reject(fn ($s) => $s === $slug)
                            ->push($slug)
                            ->implode(',');

                        return route('pets.timeline', array_merge(
                            ['pet' => $pet],
                            $novos === '' ? [] : ['tipos' => $novos],
                            array_filter(['de' => $de ?? null, 'ate' => $ate ?? null])
                        ));
                    };
                @endphp

                <a href="{{ route('pets.timeline', array_filter(['pet' => $pet, 'de' => $de ?? null, 'ate' => $ate ?? null])) }}"
                   class="btn btn-sm {{ $temFiltro ? 'btn-outline-secondary' : 'btn-secondary' }}">
                    Todos
                    <span class="badge bg-light text-dark ml-1">{{ $totalGeral }}</span>
                </a>

                @foreach ($tiposDisponiveis as $tipo)
                    @php
                        $marcado = in_array($tipo->slug, $ativos, true);
                        // Quando não há filtro nenhum, a contagem é a de todos.
                        // Quando há filtro, um tipo ativo pode legitimately
                        // estar em zero se os dois filtros se cruzam.
                        $n = $temFiltro
                            ? (int) ($contagem[$tipo->slug] ?? 0)
                            : (int) ($totalPorTipo[$tipo->slug] ?? $contagem[$tipo->slug] ?? 0);
                        $vazio = $n === 0 && ! $marcado;
                    @endphp

                    @if ($vazio)
                        <button type="button" class="btn btn-sm btn-outline-secondary disabled"
                                title="Sem registros deste tipo">
                            <i class="fas {{ $tipo->icon }}"></i> {{ $tipo->label }} <span class="ms-1">0</span>
                        </button>
                    @else
                        <a href="{{ $hrefTipo($tipo->slug) }}"
                           class="btn btn-sm {{ $marcado ? 'btn-' . $tipo->color : 'btn-outline-' . $tipo->color }}">
                            <i class="fas {{ $tipo->icon }}"></i> {{ $tipo->label }}
                            <span class="badge bg-light text-dark ms-1">{{ $n }}</span>
                        </a>
                    @endif
                @endforeach
            </div>

            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1" for="filtroDe">De</label>
                    <input type="date" name="de" id="filtroDe" class="form-control form-control-sm"
                           value="{{ $de ?? '' }}">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1" for="filtroAte">Até</label>
                    <input type="date" name="ate" id="filtroAte" class="form-control form-control-sm"
                           value="{{ $ate ?? '' }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-check"></i> Aplicar
                    </button>
                </div>
                @if ($temFiltro || $de || $ate)
                    <div class="col-auto">
                        <a href="{{ route('pets.timeline', $pet) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </form>

    {{-- ── Linha do tempo ───────────────────────────────────────────────── --}}
    @forelse ($events as $event)
        <div class="timeline-item mb-3">
            <div class="row">
                <div class="col-auto text-center" style="width: 90px;">
                    <div class="small text-muted">
                        {{ \Carbon\Carbon::parse($event['date'])->format('d/m/Y') }}
                    </div>
                    <div class="small text-muted">
                        {{ \Carbon\Carbon::parse($event['date'])->format('H:i') }}
                    </div>
                </div>
                <div class="col-auto">
                    <span class="badge badge-{{ $event['color'] }}" style="font-size: 1rem;">
                        <i class="fas {{ $event['icon'] }}"></i>
                    </span>
                </div>
                <div class="col">
                    <div class="card card-{{ $event['color'] }} card-outline">
                        <div class="card-body py-2">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <strong>{{ $event['type'] }}</strong>
                                <small class="text-muted">
                                    {{ \Carbon\Carbon::parse($event['date'])->format('d/m/Y H:i') }}
                                </small>
                            </div>
                            <p class="mb-0">{{ $event['summary'] }}</p>
                            @if (! empty($event['url']))
                                <a href="{{ $event['url'] }}"
                                   class="btn btn-xs btn-outline-{{ $event['color'] }} mt-1">
                                    Ver detalhes
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted text-center">Nenhum evento registrado para este paciente.</p>
    @endforelse
</div>
@endsection
