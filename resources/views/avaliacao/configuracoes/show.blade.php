<x-app-layout :titulo="$config->nome">
    @php $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ','); @endphp
    <x-cabecalho :titulo="$config->nome" :subtitulo="$config->sistema->nome.' · '.$config->anoLectivo->designacao.' · versão '.$config->versao"
        :migalhas="['Avaliação' => null, 'Configuração de Avaliações' => route('avaliacao.configuracoes.index', ['ano_lectivo_id' => $config->ano_lectivo_id]), $config->nome => null]">
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalDuplicar"><i class="bi bi-copy me-1"></i>Duplicar para outro ano</button>
        <a href="{{ route('avaliacao.configuracoes.create', ['copiar' => $config->id]) }}" class="btn btn-light"><i class="bi bi-files me-1"></i>Usar como base</a>
        @if ($config->estado !== 'activa')
            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalEstado" data-estado="activa">Activar</button>
        @else
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEstado" data-estado="inactiva">Desactivar</button>
        @endif
        <a href="{{ route('avaliacao.configuracoes.edit', $config) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    </x-cabecalho>

    @if ($config->descricao)
        <div class="alert {{ $config->estado === 'rascunho' ? 'alert-warning' : 'alert-light border' }} small">{{ $config->descricao }}</div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Identificação e classificação</h2>
                    <span class="badge {{ ['activa' => 'text-bg-success', 'rascunho' => 'text-bg-light border', 'inactiva' => 'text-bg-secondary'][$config->estado] }}">{{ $config->estado_label }}</span></div>
                <div class="card-body">
                    <dl class="lista-detalhes">
                        <dt>Nível de ensino</dt><dd>{{ $config->nivelEnsino->nome }}{{ $config->ciclo ? ' · '.$config->ciclo->nome : '' }}</dd>
                        <dt>Classes</dt><dd>@foreach ($config->classes->sortBy('ordem') as $c)<span class="badge badge-suave me-1">{{ $c->nome }} · {{ $c->regime_label }}</span>@endforeach</dd>
                        <dt>Disciplina</dt><dd>{{ $config->disciplina?->nome ?? 'Todas as disciplinas' }}</dd>
                        <dt>Ano lectivo</dt><dd>{{ $config->anoLectivo->designacao }}</dd>
                        <dt>Tipo de classificação</dt><dd>{{ \App\Models\ConfiguracaoAvaliacao::TIPOS[$config->tipo_classificacao] }}</dd>
                        <dt>Escala</dt><dd>{{ $config->escala->nome }}</dd>
                        <dt>Nota mínima de aprovação</dt><dd>{{ $fmt($config->nota_minima_aprovacao) }}</dd>
                        <dt>Arredondamento</dt><dd>{{ \App\Models\ConfiguracaoAvaliacao::ARREDONDAMENTOS[$config->arredondamento] }} (fórmulas assinaladas)</dd>
                        <dt>Classificação trimestral</dt><dd><code>{{ $config->variavel_trimestral }}</code> (→ {{ $config->variavel_trimestral }}1, {{ $config->variavel_trimestral }}2, {{ $config->variavel_trimestral }}3)</dd>
                        <dt>Classificação final</dt><dd><code>{{ $config->variavel_final }}</code></dd>
                        <dt>Criado por</dt><dd>{{ $config->criadoPor?->nome ?? 'Sistema (configuração inicial)' }} · {{ $config->created_at->format('d/m/Y') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Componentes de avaliação</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Código</th><th>Componente</th><th>Tipo</th><th>Momento</th><th>Intervalo</th></tr></thead>
                        <tbody>
                        @foreach ($config->componentes as $c)
                            <tr>
                                <td><code>{{ $c->codigo }}</code></td><td>{{ $c->nome }}</td>
                                <td class="small">{{ $c->tipo === 'lista' ? 'Lista ('.$c->itens_previstos.' previstas)' : 'Valor único' }}</td>
                                <td class="small">{{ $c->momento === 'exame' ? 'Exame' : 'Trimestral'.($c->trimestres ? ' ('.str_replace(',', '.º, ', $c->trimestres).'.º)' : '') }}</td>
                                <td class="small">{{ $fmt($c->nota_minima) }}–{{ $fmt($c->nota_maxima) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Fórmulas (por ordem de cálculo)</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Código</th><th>Fórmula</th><th>Nível</th><th>Arred.</th><th>Modelo</th></tr></thead>
                        <tbody>
                        @foreach ($config->formulas as $f)
                            <tr>
                                <td><code class="fw-bold">{{ $f->codigo }}</code></td>
                                <td><code>{{ $f->codigo }} = {{ $f->expressao }}</code><div class="small text-secondary">{{ $f->nome }}</div></td>
                                <td class="small">{{ $f->nivel === 'anual' ? 'Anual' : 'Trimestral'.($f->trimestres ? ' ('.$f->trimestres.')' : '') }}</td>
                                <td>{!! $f->arredondar ? '<i class="bi bi-check2 text-success"></i>' : '—' !!}</td>
                                <td class="small">{{ $f->modelo ? 'Anexo III — '.$f->modelo->referencia : 'Personalizada' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="titulo">Critérios de aprovação, transição e exames</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($config->regras as $r)
                        <li class="list-group-item {{ $r->activo ? '' : 'text-secondary' }}">
                            <div class="fw-semibold small">{{ $r->definicao['nome'] }} @unless ($r->activo)<span class="badge text-bg-secondary">Inactiva</span>@endunless</div>
                            <div class="small text-secondary">{{ $r->definicao['descricao'] ?? '' }}</div>
                            @if ($r->parametros)
                                <div class="small mt-1">
                                    @foreach ($r->definicao['parametros'] as $k => $def)
                                        @php $v = $r->parametros[$k] ?? null; @endphp
                                        <span class="badge text-bg-light border me-1 fw-normal">{{ $def['rotulo'] }}:
                                            <strong>{{ is_bool($v) ? ($v ? 'Sim' : 'Não') : (is_array($v) ? collect($v)->map(fn ($x) => \App\Models\Classe::REGIMES[$x] ?? $x)->join(', ') : (is_numeric($v) ? $fmt($v) : $v)) }}</strong></span>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item small text-secondary">Sem regras definidas.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Escala de classificação</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach ($config->escala->intervalos as $i)
                        <li class="list-group-item d-flex justify-content-between small"><span>{{ $fmt($i->minimo) }} – {{ $fmt(floor($i->maximo)) }}</span><strong>{{ $i->rotulo }}</strong></li>
                    @endforeach
                </ul>
            </div>
            <div class="card mb-3">
                <div class="card-body small">
                    <div><strong>{{ number_format($resultados, 0, ',', ' ') }}</strong> resultados calculados com esta configuração.</div>
                    @if ($desactualizados)<div class="text-warning mt-1"><i class="bi bi-exclamation-triangle"></i> {{ $desactualizados }} com versão anterior (não alterados retroactivamente).</div>@endif
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="titulo">Histórico de versões</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach ($historico as $h)
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('avaliacao.configuracoes.versao', [$config, $h->versao]) }}" class="fw-semibold">Versão {{ $h->versao }}</a>
                                <span class="text-secondary">{{ $h->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="text-secondary">{{ ucfirst($h->accao) }} · {{ $h->utilizador?->nome ?? 'Sistema' }}</div>
                            @if ($h->motivo)<div class="fst-italic">"{{ \Illuminate\Support\Str::limit($h->motivo, 120) }}"</div>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDuplicar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('avaliacao.configuracoes.duplicar', $config) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Duplicar configuração</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small text-secondary">A cópia fica em rascunho no ano lectivo seleccionado, para revisão antes de ser activada.</p>
                    <label class="form-label">Ano lectivo de destino</label>
                    <select name="ano_lectivo_id" class="form-select">@foreach ($anos as $a)<option value="{{ $a->id }}">{{ $a->designacao }}</option>@endforeach</select>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Duplicar</button></div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="modalEstado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('avaliacao.configuracoes.estado', $config) }}" class="modal-content">
                @csrf @method('PATCH')
                <input type="hidden" name="estado" value="{{ $config->estado === 'activa' ? 'inactiva' : 'activa' }}">
                <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">{{ $config->estado === 'activa' ? 'Desactivar' : 'Activar' }} configuração</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label obrigatorio">Motivo</label>
                    <textarea name="motivo" rows="3" class="form-control" required minlength="5"></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Confirmar</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
