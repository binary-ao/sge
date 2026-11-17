<x-app-layout :titulo="$turma->designacao">
    @php
        $ocupados = $matriculas->count();
        $vagas = max(0, $turma->capacidade - $ocupados);
        $podeDistribuir = auth()->user()->can('turmas.gerir') || auth()->user()->can('matriculas.gerir');
    @endphp
    <x-cabecalho :titulo="$turma->classe->nome.' — Turma '.$turma->nome"
        :subtitulo="$turma->classe->nivelEnsino->nome.($turma->curso ? ' · '.$turma->curso->nome : '').' · '.$turma->anoLectivo->designacao"
        :migalhas="['Gestão Académica' => null, 'Turmas' => route('turmas.index'), $turma->designacao => null]">
        @can('turmas.gerir')
            <a href="{{ route('turmas.edit', $turma) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar turma</a>
        @endcan
    </x-cabecalho>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Alunos" :valor="$ocupados.' / '.$turma->capacidade" icone="people" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Lugares disponíveis" :valor="$vagas" icone="door-open" :tom="$vagas ? 'sucesso' : 'erro'" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Raparigas / Rapazes" :valor="$matriculas->where('aluno.genero', 'F')->count().' / '.$matriculas->where('aluno.genero', 'M')->count()" icone="gender-ambiguous" tom="info" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Turno · Sala" :valor="$turma->turno->nome" :detalhe="$turma->sala ?? 'Sem sala definida'" icone="clock" tom="alerta" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Alunos da turma</h2>
                    @if ($podeDistribuir && $elegiveis->isNotEmpty())
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalDistribuir"><i class="bi bi-person-plus me-1"></i>Distribuir alunos ({{ $elegiveis->count() }})</button>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>N.º</th><th>Aluno</th><th>Processo</th><th>Género</th><th>Idade</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($matriculas as $m)
                            <tr>
                                <td class="text-secondary">{{ $loop->iteration }}</td>
                                <td>@can('alunos.ver')<a href="{{ route('alunos.show', $m->aluno) }}" class="text-decoration-none fw-semibold">{{ $m->aluno->nome }}</a>@else{{ $m->aluno->nome }}@endcan</td>
                                <td><code>{{ $m->aluno->numero_processo }}</code></td>
                                <td>{{ $m->aluno->genero }}</td>
                                <td>{{ $m->aluno->idade }}</td>
                                <td class="accoes">
                                    @if ($podeDistribuir)
                                        <form method="POST" action="{{ route('turmas.retirar', [$turma, $m]) }}" data-confirmar="Retirar {{ $m->aluno->nome }} da turma? A matrícula mantém-se activa, sem turma.">
                                            @csrf @method('DELETE')<button class="btn btn-light btn-icon" title="Retirar da turma"><i class="bi bi-box-arrow-right"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="tabela-vazia"><i class="bi bi-people"></i>A turma ainda não tem alunos.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><h2 class="titulo">Disciplinas e professores</h2>
                    @if ($plano)<a href="{{ auth()->user()->can('academico.configurar') || auth()->user()->can('academico.global') ? route('academico.planos.show', $plano->id) : '#' }}" class="small">{{ $plano->escola_id ? 'Plano da escola' : 'Plano de referência' }}</a>@endif
                </div>
                @if (! $plano)
                    <div class="card-body small">
                        <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Não existe plano curricular para esta classe{{ $turma->curso ? ' e curso' : '' }}. Configure-o em <em>Configuração Académica › Planos curriculares</em>.</div>
                    </div>
                @else
                    @can('turmas.gerir')
                        <form method="POST" action="{{ route('turmas.professores', $turma) }}">
                            @csrf @method('PUT')
                            <div class="card-body pb-0">
                                <label class="form-label">Director de turma</label>
                                <select name="director_turma_id" class="form-select form-select-sm mb-3">
                                    <option value="">Por designar</option>
                                    @foreach ($professores as $p)<option value="{{ $p->id }}" @selected($turma->director_turma_id === $p->id)>{{ $p->nome }}</option>@endforeach
                                </select>
                            </div>
                            <ul class="list-group list-group-flush">
                                @foreach ($plano->disciplinas as $d)
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <strong>{{ $d->nome }}</strong>
                                            <span class="text-secondary">{{ $d->pivot->carga_horaria ? $d->pivot->carga_horaria.' t/s' : '' }}</span>
                                        </div>
                                        <select name="professores[{{ $d->id }}]" class="form-select form-select-sm {{ isset($atribuicoes[$d->id]) ? '' : 'border-warning' }}">
                                            <option value="">Sem professor atribuído</option>
                                            @foreach ($professores as $p)
                                                <option value="{{ $p->id }}" @selected(($atribuicoes[$d->id]->professor_id ?? null) === $p->id)>{{ $p->nome }}{{ $p->especialidade ? ' — '.$p->especialidade : '' }}</option>
                                            @endforeach
                                        </select>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="card-body text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i>Guardar atribuições</button></div>
                        </form>
                    @else
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item small"><span class="text-secondary">Director de turma:</span> <strong>{{ $turma->directorTurma?->nome ?? 'por designar' }}</strong></li>
                            @foreach ($plano->disciplinas as $d)
                                <li class="list-group-item d-flex justify-content-between small">
                                    <span>{{ $d->nome }}</span>
                                    <span class="{{ isset($atribuicoes[$d->id]) ? '' : 'text-warning' }}">{{ $atribuicoes[$d->id]->professor->nome ?? 'Sem professor' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endcan
                @endif
            </div>
        </div>
    </div>

    @if ($podeDistribuir && $elegiveis->isNotEmpty())
        <div class="modal fade" id="modalDistribuir" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form method="POST" action="{{ route('turmas.distribuir', $turma) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Distribuir alunos — {{ $turma->designacao }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="small text-secondary">Matrículas activas em {{ $turma->classe->nome }}{{ $turma->curso ? ' ('.$turma->curso->codigo.')' : '' }} sem turma. Lugares disponíveis: <strong id="vagasRestantes" data-vagas="{{ $vagas }}">{{ $vagas }}</strong>.</p>
                        <div class="form-check mb-2 border-bottom pb-2">
                            <input class="form-check-input" type="checkbox" id="seleccionarTodos">
                            <label class="form-check-label small fw-semibold" for="seleccionarTodos">Seleccionar todos (até ao limite de lugares)</label>
                        </div>
                        @foreach ($elegiveis as $m)
                            <div class="form-check">
                                <input class="form-check-input caixa-aluno" type="checkbox" name="matriculas[]" value="{{ $m->id }}" id="mat{{ $m->id }}">
                                <label class="form-check-label small" for="mat{{ $m->id }}">{{ $m->aluno->nome }} <span class="text-secondary">· {{ $m->numero }}</span></label>
                            </div>
                        @endforeach
                        <x-campo-erro campo="matriculas" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Colocar na turma</button></div>
                </form>
            </div>
        </div>
        @push('scripts')
            <script>
                (function () {
                    var caixas = document.querySelectorAll('.caixa-aluno'), info = document.getElementById('vagasRestantes');
                    var vagas = parseInt(info.dataset.vagas, 10);
                    function actualizar() {
                        var n = document.querySelectorAll('.caixa-aluno:checked').length;
                        info.textContent = vagas - n;
                        caixas.forEach(function (c) { c.disabled = !c.checked && n >= vagas; });
                    }
                    caixas.forEach(function (c) { c.addEventListener('change', actualizar); });
                    document.getElementById('seleccionarTodos').addEventListener('change', function () {
                        var marcar = this.checked, n = 0;
                        caixas.forEach(function (c) { c.checked = marcar && n++ < vagas; });
                        actualizar();
                    });
                    actualizar();
                })();
            </script>
        @endpush
        @if ($errors->has('matriculas'))
            @push('scripts')<script>document.addEventListener('DOMContentLoaded', function () { new bootstrap.Modal(document.getElementById('modalDistribuir')).show(); });</script>@endpush
        @endif
    @endif
</x-app-layout>
