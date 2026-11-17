<x-app-layout :titulo="'Notas — '.$turma->designacao">
    @php
        $qualitativa = $config->eQualitativa();
        $intervalos = $config->escala->intervalos;
        $temExames = $config->componentes->where('momento', 'exame')->isNotEmpty();
        $periodoResultado = $periodo === 4 ? 0 : $periodo;
        $fmt = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
        $formulasPeriodo = $periodo === 4
            ? $config->formulas->where('nivel', 'anual')->pluck('codigo')
            : $config->formulas->where('nivel', 'trimestral')->filter(fn ($f) => \App\Services\Avaliacao\DefinicaoAvaliacao::aplicaAoTrimestre($f->trimestres, $periodo))->pluck('codigo');
        if ($periodo === 3 && $config->formulas->where('nivel', 'anual')->contains('codigo', $config->variavel_trimestral.'3')) {
            $formulasPeriodo = $formulasPeriodo->push($config->variavel_trimestral);
        }
    @endphp
    <x-cabecalho :titulo="$disciplina->nome.' — '.$turma->designacao"
        :subtitulo="$turma->classe->nivelEnsino->nome.' · '.$turma->anoLectivo->designacao.' · '.$config->nome.' (v'.$config->versao.')'"
        :migalhas="['Avaliação' => null, 'Lançamento de notas' => route('avaliacao.lancamento.index', ['periodo' => $periodo]), $turma->designacao => null]">
        @can('pautas.ver')
            <a href="{{ route('avaliacao.pautas.disciplina', [$turma, $disciplina->id]) }}" class="btn btn-light"><i class="bi bi-table me-1"></i>Pauta da disciplina</a>
        @endcan
    </x-cabecalho>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        @foreach ($periodos as $p)
            @continue($p->periodo === 4 && ! $temExames)
            <a href="{{ route('avaliacao.lancamento.grelha', [$turma, $disciplina->id, 'periodo' => $p->periodo]) }}"
               class="btn btn-sm {{ $periodo === $p->periodo ? 'btn-primary' : 'btn-light' }}">
                {{ $p->rotulo }}@if ($p->estado === 'fechado')<i class="bi bi-lock-fill ms-1"></i>@endif
            </a>
        @endforeach
        <span class="ms-auto small text-secondary">
            Escala {{ $fmt($config->escala->nota_minima) }}–{{ $fmt($config->escala->nota_maxima) }} ·
            mínimo para positiva: <strong>{{ $fmt($config->nota_minima_aprovacao) }}</strong> ·
            {{ \App\Models\ConfiguracaoAvaliacao::TIPOS[$config->tipo_classificacao] }}
        </span>
    </div>

    @if ($fechado)
        <div class="alert alert-secondary d-flex align-items-center gap-2"><i class="bi bi-lock-fill"></i>
            <span>O período está <strong>fechado</strong>. As notas estão bloqueadas; a reabertura exige autorização e fica registada.</span></div>
    @elseif (! $podeLancar)
        <div class="alert alert-info small"><i class="bi bi-eye me-1"></i>Modo de consulta.</div>
    @endif
    @if ($qualitativa && $podeLancar)
        <div class="alert alert-light border small"><i class="bi bi-info-circle me-1 text-primary"></i>
            Avaliação qualitativa: seleccione o nível de desempenho. Cada nível corresponde a um valor de referência interno, usado apenas para agregar as classificações.</div>
    @endif

    <form method="POST" action="{{ route('avaliacao.lancamento.guardar', [$turma, $disciplina->id]) }}" id="formNotas">
        @csrf
        <input type="hidden" name="periodo" value="{{ $periodo }}">
        <div class="card">
            <div class="card-header">
                <h2 class="titulo">{{ \App\Models\PeriodoLancamento::PERIODOS[$periodo] }} · {{ $matriculas->count() }} alunos</h2>
                @if ($podeLancar)
                    <div class="d-flex gap-2">
                        @foreach ($componentes->where('tipo', 'lista') as $c)
                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#modalColuna" data-componente="{{ $c->codigo }}">
                                <i class="bi bi-plus-lg me-1"></i>Coluna {{ $c->codigo }}
                            </button>
                        @endforeach
                        <button class="btn btn-sm btn-primary" data-a-carregar="A gravar…"><i class="bi bi-check2 me-1"></i>Gravar e calcular</button>
                    </div>
                @endif
            </div>
            <div class="table-responsive" style="max-height: 70vh">
                <table class="table table-sm table-hover align-middle mb-0 grelha-notas">
                    <thead class="sticky-top">
                        <tr>
                            <th style="width:40px">N.º</th>
                            <th style="min-width:220px">Aluno</th>
                            @foreach ($colunas as $col)
                                <th class="text-center" title="{{ $col->descricao }}">{{ $col->rotulo }}</th>
                            @endforeach
                            @foreach ($formulasPeriodo as $codigo)
                                <th class="text-center bg-light" title="Calculado automaticamente">{{ $codigo }}</th>
                            @endforeach
                            @if ($periodo !== 4)<th class="text-center bg-light">Final</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($matriculas as $m)
                        @php
                            $rp = $resultados[$m->id] ?? collect();
                            $rPeriodo = $rp->firstWhere('periodo', $periodoResultado);
                            $rFinal = $rp->firstWhere('periodo', 0);
                        @endphp
                        <tr>
                            <td class="text-secondary">{{ $loop->iteration }}</td>
                            <td class="fw-semibold small">{{ $m->aluno->nome }}</td>
                            @foreach ($colunas as $col)
                                @php $valor = $col->id ? ($notas[$m->id][$col->id] ?? null) : null; $nome = "notas[{$m->id}][{$col->componente}][{$col->indice}]"; @endphp
                                <td class="text-center p-1">
                                    @if (! $podeLancar)
                                        {{ $qualitativa && $valor !== null ? ($config->escala->intervaloPara($valor)?->sigla ?? $fmt($valor)) : $fmt($valor) }}
                                    @elseif ($qualitativa)
                                        <select name="{{ $nome }}" class="form-select form-select-sm celula-nota" style="min-width:80px">
                                            <option value=""></option>
                                            @foreach ($intervalos as $i)
                                                <option value="{{ $i->valor_referencia }}" @selected($valor !== null && abs($valor - $i->valor_referencia) < 0.001)>{{ $i->sigla ?? $i->rotulo }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" inputmode="decimal" name="{{ $nome }}" value="{{ old(str_replace(['[', ']'], ['.', ''], $nome), $fmt($valor)) }}"
                                               class="form-control form-control-sm text-center celula-nota @error('notas.'.$m->id.'.'.$col->componente.'.'.$col->indice) is-invalid @enderror"
                                               data-min="{{ $col->min }}" data-max="{{ $col->max }}" style="width:68px" autocomplete="off">
                                    @endif
                                </td>
                            @endforeach
                            @foreach ($formulasPeriodo as $codigo)
                                @php $v = $periodo === 4 ? ($rFinal?->valores[$codigo] ?? null) : ($rPeriodo?->valores[$codigo] ?? null); @endphp
                                <td class="text-center bg-light small {{ $codigo === $config->variavel_trimestral || $codigo === $config->variavel_final ? 'fw-bold' : '' }}
                                    {{ $v !== null && in_array($codigo, [$config->variavel_trimestral, $config->variavel_final]) && $v < $config->nota_minima_aprovacao ? 'text-danger' : '' }}">
                                    @if ($v === null) <span class="text-secondary">—</span>
                                    @elseif ($qualitativa && in_array($codigo, [$config->variavel_trimestral, $config->variavel_final])) {{ $config->escala->intervaloPara($v)?->rotulo }}
                                    @else {{ $fmt($v) }} @endif
                                </td>
                            @endforeach
                            @if ($periodo !== 4)
                                <td class="text-center bg-light small">
                                    @if ($rFinal?->classificacao !== null)
                                        <span class="{{ $rFinal->situacao === 'negativa' ? 'text-danger' : '' }}">{!! \App\Services\Avaliacao\ConstrutorPautas::exibir($rFinal, $config) !!}</span>
                                    @else <span class="text-secondary" title="Classificação final ainda incompleta">—</span> @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ 3 + $colunas->count() + $formulasPeriodo->count() }}" class="tabela-vazia">A turma não tem alunos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body small text-secondary border-top">
                <i class="bi bi-calculator me-1"></i>
                Colunas a cinzento: calculadas pelo motor de avaliação com as fórmulas da configuração
                @foreach ($config->formulas as $f)<code class="ms-1">{{ $f->codigo }} = {{ $f->expressao }}</code>@if (! $loop->last);@endif @endforeach.
                Os valores em branco não são considerados; as médias que dependem deles ficam incompletas.
            </div>
        </div>
    </form>

    @if ($podeLancar)
        <div class="modal fade" id="modalColuna" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('avaliacao.lancamento.coluna', [$turma, $disciplina->id]) }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="periodo" value="{{ $periodo }}">
                    <input type="hidden" name="componente" id="colunaComponente">
                    <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Nova coluna de avaliação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-8"><label class="form-label">Descrição</label><input name="descricao" maxlength="100" class="form-control" placeholder="Ex.: Trabalho de grupo"></div>
                        <div class="col-4"><label class="form-label">Data</label><input type="date" name="data" class="form-control"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Acrescentar</button></div>
                </form>
            </div>
        </div>
    @endif

    @push('estilos')
        <style>
            .grelha-notas thead th { position: sticky; top: 0; z-index: 2; }
            .grelha-notas td:nth-child(2), .grelha-notas th:nth-child(2) { position: sticky; left: 0; background: #fff; z-index: 1; }
            .grelha-notas thead th:nth-child(2) { z-index: 3; background: #F8FAFC; }
            .celula-nota.fora { border-color: var(--sige-erro); background: #FBE9E7; }
        </style>
    @endpush
    @push('scripts')
        <script>
            (function () {
                var celulas = Array.prototype.slice.call(document.querySelectorAll('input.celula-nota'));
                var colunas = {{ $colunas->count() }};
                celulas.forEach(function (el, i) {
                    el.addEventListener('input', function () {
                        var v = parseFloat(el.value.replace(',', '.'));
                        el.classList.toggle('fora', el.value.trim() !== '' && (isNaN(v) || v < parseFloat(el.dataset.min) || v > parseFloat(el.dataset.max)));
                    });
                    // Enter: desce para o aluno seguinte, na mesma coluna
                    el.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') { e.preventDefault(); var prox = celulas[i + colunas]; if (prox) { prox.focus(); prox.select(); } }
                    });
                });
                var modal = document.getElementById('modalColuna');
                if (modal) {
                    modal.addEventListener('show.bs.modal', function (e) {
                        document.getElementById('colunaComponente').value = e.relatedTarget.dataset.componente;
                    });
                }
                // Aviso ao sair com alterações por gravar
                var form = document.getElementById('formNotas'), alterado = false;
                form.addEventListener('input', function () { alterado = true; });
                form.addEventListener('change', function () { alterado = true; });
                form.addEventListener('submit', function () { alterado = false; });
                window.addEventListener('beforeunload', function (e) { if (alterado) { e.preventDefault(); e.returnValue = ''; } });
            })();
        </script>
    @endpush
</x-app-layout>
