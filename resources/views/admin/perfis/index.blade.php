<x-app-layout titulo="Perfis e permissões">
    <x-cabecalho titulo="Perfis e permissões" subtitulo="As permissões são verificadas no servidor em todas as operações."
        :migalhas="['Sistema' => null, 'Perfis e Permissões' => null]" />

    <div class="row g-3">
        @foreach ($perfis as $p)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 class="h6 fw-semibold mb-0 text-primary">{{ $p->nome }}</h2>
                            <span class="badge badge-suave">{{ $p->ambito_label }}</span>
                        </div>
                        <p class="small text-secondary flex-grow-1">{{ $p->descricao }}</p>
                        <div class="d-flex justify-content-between align-items-center small">
                            <span><i class="bi bi-people me-1"></i>{{ $p->utilizadores_count }} utilizador(es)</span>
                            <span><i class="bi bi-key me-1"></i>{{ $p->isSuperutilizador() ? 'Todas' : $p->permissoes_count.' de '.$totalPermissoes }}</span>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        @if ($p->isSuperutilizador())
                            <span class="small text-secondary"><i class="bi bi-lock me-1"></i>Acesso global (não editável)</span>
                        @else
                            <a href="{{ route('perfis.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders me-1"></i>Gerir permissões</a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
