<x-app-layout titulo="Escalas e fórmulas">
    @php $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ','); @endphp
    <x-cabecalho titulo="Escalas de classificação e modelos de fórmula" subtitulo="Editáveis e versionados; as alterações aplicam-se aos cálculos seguintes"
        :migalhas="['Avaliação' => null, 'Configuração de Avaliações' => route('avaliacao.configuracoes.index'), 'Escalas e fórmulas' => null]">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalModelo"><i class="bi bi-plus-lg me-1"></i>Novo modelo de fórmula</button>
    </x-cabecalho>

    <div class="row g-3">
        @foreach ($escalas as $escala)
            <div class="col-xl-4 col-lg-6">
                <form method="POST" action="{{ route('avaliacao.escalas.update', $escala) }}" class="card h-100">
                    @csrf @method('PUT')
                    <div class="card-header"><h2 class="titulo">{{ $escala->nome }}</h2><span class="small text-secondary">v{{ $escala->versao }}</span></div>
                    <div class="card-body">
                        <p class="small text-secondary">{{ $escala->descricao }}</p>
                        <input type="hidden" name="nome" value="{{ $escala->nome }}">
                        <input type="hidden" name="descricao" value="{{ $escala->descricao }}">
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label small">Mínimo</label><input name="nota_minima" value="{{ $fmt($escala->nota_minima) }}" class="form-control form-control-sm"></div>
                            <div class="col-6"><label class="form-label small">Máximo</label><input name="nota_maxima" value="{{ $fmt($escala->nota_maxima) }}" class="form-control form-control-sm"></div>
                        </div>
                        <table class="table table-sm align-middle">
                            <thead><tr><th>De</th><th>Até</th><th>Classificação</th><th>Sigla</th><th title="Valor usado quando a nota é lançada de forma qualitativa">Ref.</th></tr></thead>
                            <tbody>
                            @foreach ($escala->intervalos as $i => $int)
                                <tr>
                                    <td><input name="intervalos[{{ $i }}][minimo]" value="{{ $fmt($int->minimo) }}" class="form-control form-control-sm" style="width:58px"></td>
                                    <td><input name="intervalos[{{ $i }}][maximo]" value="{{ $fmt($int->maximo) }}" class="form-control form-control-sm" style="width:58px"></td>
                                    <td><input name="intervalos[{{ $i }}][rotulo]" value="{{ $int->rotulo }}" class="form-control form-control-sm"></td>
                                    <td><input name="intervalos[{{ $i }}][sigla]" value="{{ $int->sigla }}" class="form-control form-control-sm" style="width:50px"></td>
                                    <td><input name="intervalos[{{ $i }}][valor_referencia]" value="{{ $fmt($int->valor_referencia) }}" class="form-control form-control-sm" style="width:50px"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <label class="form-label small obrigatorio">Motivo da alteração</label>
                        <input name="motivo" class="form-control form-control-sm" required minlength="5">
                    </div>
                    <div class="card-footer bg-white text-end"><button class="btn btn-sm btn-primary">Guardar nova versão</button></div>
                </form>
            </div>
        @endforeach
    </div>

    <div class="card mt-3">
        <div class="card-header"><h2 class="titulo">Modelos de fórmula (Anexo III do Decreto Executivo n.º 424/25)</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Ref.</th><th>Fórmula</th><th>Nível</th><th>Aplicação</th><th>Versão</th><th></th></tr></thead>
                <tbody>
                @foreach ($modelos as $m)
                    <tr class="{{ $m->activo ? '' : 'text-secondary' }}">
                        <td><strong>{{ $m->referencia }}</strong></td>
                        <td><code>{{ $m->codigo }} = {{ $m->expressao }}</code><div class="small text-secondary">{{ $m->nome }}</div></td>
                        <td class="small">{{ ucfirst($m->nivel) }}</td>
                        <td class="small">{{ $m->aplicacao }}</td>
                        <td class="small">v{{ $m->versao }}</td>
                        <td class="accoes">
                            <button class="btn btn-light btn-icon" data-bs-toggle="modal" data-bs-target="#modalModelo" data-accao="{{ route('avaliacao.modelos.update', $m) }}"
                                    data-preencher="{{ json_encode($m->only('referencia', 'codigo', 'nome', 'expressao', 'nivel', 'descricao', 'aplicacao', 'activo')) }}"><i class="bi bi-pencil"></i></button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body small text-secondary border-top">A edição de um modelo não altera as configurações que já o utilizam: cada configuração guarda a sua própria cópia da expressão.</div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><h2 class="titulo">Alterações recentes</h2></div>
        <ul class="list-group list-group-flush">
            @forelse ($historico as $h)
                <li class="list-group-item small">{{ $h->created_at->format('d/m/Y H:i') }} · {{ $h->entidade === 'escala' ? 'Escala' : 'Modelo' }} #{{ $h->entidade_id }} v{{ $h->versao }} · {{ $h->accao }} · {{ $h->utilizador?->nome ?? 'Sistema' }}
                    @if ($h->motivo)<span class="text-secondary">— {{ $h->motivo }}</span>@endif</li>
            @empty
                <li class="list-group-item small text-secondary">Sem alterações.</li>
            @endforelse
        </ul>
    </div>

    <x-modal-form id="modalModelo" titulo-criar="Novo modelo de fórmula" titulo-editar="Editar modelo de fórmula" :accao-criar="route('avaliacao.modelos.store')" tamanho="lg">
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label obrigatorio">Referência</label><input name="referencia" class="form-control" value="{{ old('referencia') }}"></div>
            <div class="col-md-3"><label class="form-label obrigatorio">Código</label><input name="codigo" class="form-control text-uppercase" value="{{ old('codigo') }}"></div>
            <div class="col-md-6"><label class="form-label obrigatorio">Nome</label><input name="nome" class="form-control" value="{{ old('nome') }}"></div>
            <div class="col-md-9"><label class="form-label obrigatorio">Expressão</label><input name="expressao" class="form-control @error('expressao') is-invalid @enderror" style="font-family:monospace" value="{{ old('expressao') }}"><x-campo-erro campo="expressao" /></div>
            <div class="col-md-3"><label class="form-label">Nível</label><select name="nivel" class="form-select"><option value="trimestral">Trimestral</option><option value="anual">Anual</option></select></div>
            <div class="col-12"><label class="form-label">Aplicação</label><input name="aplicacao" class="form-control" value="{{ old('aplicacao') }}"></div>
            <div class="col-12"><label class="form-label">Descrição</label><textarea name="descricao" rows="2" class="form-control">{{ old('descricao') }}</textarea></div>
            <div class="col-md-8"><label class="form-label">Motivo (obrigatório na edição)</label><input name="motivo" class="form-control @error('motivo') is-invalid @enderror"><x-campo-erro campo="motivo" /></div>
            <div class="col-md-4 d-flex align-items-end"><label class="form-check"><input type="checkbox" name="activo" value="1" class="form-check-input" data-padrao="1" checked> Activo</label></div>
        </div>
    </x-modal-form>
</x-app-layout>
