@extends('layouts.adminlte', ['title' => 'Pets'])

@section('content')
{{-- AgroVerde: data-server-paginated desliga o DataTables desta tabela.
     A lista tem ~16 mil pets vindos do SimplesVet; sem isso o PHP estoura
     a memória. Ver AGROVERDE.md §11. --}}
<div class="card" data-server-paginated>
    <div class="card-header">
        <h3 class="card-title">Pets</h3>
        <div class="card-tools">
            <button onclick="openCreateModal()" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Novo
            </button>
        </div>
    </div>
    <div class="card-body">
        @if($pets->count() > 0)
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Pet</th>
                    <th>Espécie</th>
                    <th>Raça</th>
                    <th>Tutor</th>
                    <th>Idade</th>
                    <th style="width: 150px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pets as $pet)
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            @if($pet->photo_url)
                                <img src="{{ $pet->photo_url }}" alt="{{ $pet->name }}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                            @else
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px; background: color-mix(in srgb, var(--brand-primary, #455e36) 15%, white); color: var(--brand-primary, #455e36);">
                                    <i class="fas fa-paw"></i>
                                </div>
                            @endif
                            <div class="ml-2">
                                <div class="font-weight-bold">{{ $pet->name }}</div>
                                <small class="text-muted">{{ $pet->gender === 'male' ? 'Macho' : 'Fêmea' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        @php
                            $speciesLabels = config('species');
                        @endphp
                        {{ $speciesLabels[$pet->species] ?? $pet->species }}
                    </td>
                    <td>{{ $pet->breedRelation?->name ?? $pet->breed ?? 'SRD' }}</td>
                    <td>
                        @php $firstTutor = $pet->tutors->first(); @endphp
                        {{ $firstTutor ? $firstTutor->name : '-' }}
                    </td>
                    <td>{{ $pet->age ?? '-' }}</td>
                    <td>
                        <a href="{{ route('pets.show', $pet) }}" class="btn btn-action btn-info" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button onclick="openEditModal({{ $pet->id }})" class="btn btn-action btn-primary" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="{{ route('pets.destroy', $pet) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" data-confirm="Tem certeza?" class="btn btn-action btn-danger" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-center text-muted">Nenhum registro encontrado.</p>
        @endif
    </div>
</div>

<!-- AgroVerde: paginação. A lista não cabe em uma tela com ~16 mil pets
     vindos do SimplesVet. Ver AGROVERDE.md §11. -->
@if($pets->hasPages())
<div class="card-footer d-flex align-items-center justify-content-between">
    <small class="text-muted">
        Mostrando {{ $pets->firstItem() }}–{{ $pets->lastItem() }} de {{ $pets->total() }}
    </small>
    {{ $pets->onEachSide(1)->links() }}
</div>
@endif

<!-- Pet Modal -->
<div class="modal fade" id="petModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="petModalTitle">Novo Pet</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                @livewire('pet-form', key('pet-form'))
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', function() {
        Livewire.on('close-modal', function() { $('#petModal').modal('hide'); });
        Livewire.on('pet-saved', function() { location.reload(); });
    });
    function openCreateModal() {
        Livewire.dispatch('resetForm');
        document.getElementById('petModalTitle').textContent = 'Novo Pet';
        $('#petModal').modal('show');
    }
    function openEditModal(id) {
        Livewire.dispatch('editPet', { id: id });
        document.getElementById('petModalTitle').textContent = 'Editar Pet';
        $('#petModal').modal('show');
    }
</script>
@endpush
