<x-app-layout titulo="Escolher escola">
    <x-cabecalho titulo="Entrar na área de uma escola" :migalhas="['Escola activa' => null]"
        subtitulo="A escola activa define o contexto dos módulos de gestão (alunos, turmas, avaliações…).">
        @if ($podeSair && $escolaActivaId)
            <form method="POST" action="{{ route('contexto.definir') }}">@csrf
                <input type="hidden" name="escola_id" value="">
                <button class="btn btn-light"><i class="bi bi-globe2 me-1"></i>Regressar à visão geral</button>
            </form>
        @endif
    </x-cabecalho>

    @if (! auth()->user()->can('alunos.gerir') && auth()->user()->perfil->ambito !== 'escola')
        <div class="alert alert-info small"><i class="bi bi-eye me-1"></i>O seu perfil tem acesso de <strong>consulta</strong>: poderá visualizar os dados da escola, mas não alterar registos operacionais.</div>
    @endif

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label">Pesquisar</label>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome ou código da escola">
        </div>
        <div class="col-md-4">
            <label class="form-label">Município</label>
            <select name="municipio_id" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($municipios as $m)
                    <option value="{{ $m->id }}" @selected(request('municipio_id') == $m->id)>{{ $m->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Escola</th><th>Município</th><th>Tipologia</th><th></th></tr></thead>
                <tbody>
                @forelse ($escolas as $e)
                    <tr>
                        <td><div class="fw-semibold">{{ $e->nome }}</div><div class="small text-secondary">{{ $e->codigo }}</div></td>
                        <td>{{ $e->municipio->nome }}</td>
                        <td class="small">{{ $e->tipologia->nome }}</td>
                        <td class="accoes">
                            @if ($escolaActivaId === $e->id)
                                <span class="badge text-bg-success">Activa</span>
                            @else
                                <form method="POST" action="{{ route('contexto.definir') }}">@csrf
                                    <input type="hidden" name="escola_id" value="{{ $e->id }}">
                                    <button class="btn btn-sm btn-outline-primary">Entrar <i class="bi bi-arrow-right"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="tabela-vazia"><i class="bi bi-building"></i>Nenhuma escola encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $escolas->links() }}</div>
</x-app-layout>
