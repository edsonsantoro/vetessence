
{{--
    Paginação no estilo do AdminLTE 3 / Bootstrap 4.

    Por que existe: a view padrão do Laravel vem em Tailwind e, neste app que
    é Bootstrap, renderiza setas gigantes sem tamanho e mostra as duas versões
    (mobile + desktop) ao mesmo tempo. Publicar a view do framework resolve,
    mas aí o upstream passa a ver um arquivo nosso como se fosse dele — então
    a versão Bootstrap fica em resources/views/vendor/pagination/, que é
    território do fork (camada B) e nunca conflita no merge.

    Ver AGROVERDE.md §11.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginação">
        <ul class="pagination pagination-sm mb-0">
             {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">&laquo;</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Anterior">&laquo;</a>
                </li>
            @endif

             {{-- Números --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page">
                                <span class="page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

             {{-- Próxima --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Próxima">&raquo;</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">&raquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
