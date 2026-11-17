<x-app-layout titulo="Configuração de Avaliações">
    <x-cabecalho titulo="Configuração de Avaliações" subtitulo="Sistemas, componentes, fórmulas e regras — referência: Decreto Executivo n.º 424/25"
        :migalhas="['Avaliação' => null, 'Configuração de Avaliações' => null]">
        <a href="{{ route('avaliacao.escalas.index') }}" class="btn btn-light"><i class="bi bi-rulers me-1"></i>Escalas e modelos de fórmula</a>
        <a href="{{ route('avaliacao.configuracoes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nova configuração</a>
    </x-cabecalho>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Ano lectivo de vigência</label>
            <select name="ano_lectivo_id" class="form-select" data-auto-submit>
                @foreach ($anos as $a)<option value="{{ $a->id }}" @selected($anoId == $a->id)>{{ $a->designacao }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach (\App\Models\ConfiguracaoAvaliacao::ESTADOS as $v => $r)<option value="{{ $v }}" @selected(request('estado') === $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
    </form>

    <div class="alert alert-light border small"><i class="bi bi-info-circle me-1 text-primary"></i>
        As regras regulamentares estão disponíveis como configurações iniciais, <strong>editáveis sem alterar código</strong>. Cada alteração cria uma nova versão
        com o responsável, a data e o motivo; os resultados já calculados guardam a versão utilizada e não são alterados retroactivamente.
    </div>

    @forelse ($configs as $sistemaId => $lista)
        <div class="card mb-3">
            <div class="card-header">
                <h2 class="titulo">{{ $sistemas[$sistemaId]->nome }}</h2>
                <span class="small text-secondary">{{ $sistemas[$sistemaId]->base_legal }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Configuração</th><th>Nível / Classes</th><th>Classificação</th><th>Fórmula final</th><th>Versão</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($lista as $c)
                        <tr>
                            <td><a href="{{ route('avaliacao.configuracoes.show', $c) }}" class="fw-semibold text-decoration-none">{{ $c->nome }}</a>
                                @if ($c->disciplina)<div class="small"><span class="badge badge-suave">Disciplina: {{ $c->disciplina->nome }}</span></div>@endif</td>
                            <td class="small">{{ $c->nivelEnsino->nome }}<br><span class="text-secondary">{{ $c->classes->sortBy('ordem')->pluck('nome')->join(', ') }}</span></td>
                            <td class="small">{{ \App\Models\ConfiguracaoAvaliacao::TIPOS[$c->tipo_classificacao] }}<br><span class="text-secondary">Mínimo {{ rtrim(rtrim(number_format($c->nota_minima_aprovacao, 2, ',', ''), '0'), ',') }} · {{ $c->escala->nome }}</span></td>
                            <td><code>{{ $c->variavel_final }}</code></td>
                            <td class="small">v{{ $c->versao }}<br><span class="text-secondary">{{ $c->actualizadoPor?->nome ?? 'Sistema' }}</span></td>
                            <td><span class="badge {{ ['activa' => 'text-bg-success', 'rascunho' => 'text-bg-light border', 'inactiva' => 'text-bg-secondary'][$c->estado] }}">{{ $c->estado_label }}</span></td>
                            <td class="accoes">
                                <a href="{{ route('avaliacao.configuracoes.show', $c) }}" class="btn btn-light btn-icon" title="Ver"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('avaliacao.configuracoes.edit', $c) }}" class="btn btn-light btn-icon" title="Editar"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body tabela-vazia"><i class="bi bi-calculator"></i>Não há configurações para este ano lectivo. Pode duplicar as de um ano anterior a partir da página de cada configuração.</div></div>
    @endforelse
</x-app-layout>
