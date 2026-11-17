<x-app-layout :titulo="$aluno->nome">
    @php $actual = $aluno->matriculas->firstWhere('ano_lectivo_id', $ano?->id); @endphp
    <x-cabecalho :titulo="$aluno->nome" subtitulo="Processo n.º {{ $aluno->numero_processo }}"
        :migalhas="['Gestão Académica' => null, 'Alunos' => route('alunos.index'), $aluno->nome => null]">
        @can('matriculas.gerir')
            @if (! $actual && $aluno->estado === 'activo')
                <a href="{{ route('matriculas.create', ['aluno' => $aluno->id]) }}" class="btn btn-outline-primary"><i class="bi bi-journal-plus me-1"></i>Matricular em {{ $ano?->designacao }}</a>
            @endif
        @endcan
        @can('alunos.gerir')
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalSituacao"><i class="bi bi-arrow-repeat me-1"></i>Alterar situação</button>
            <a href="{{ route('alunos.edit', $aluno) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
        @endcan
    </x-cabecalho>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2" style="width:64px;height:64px;font-size:1.3rem">{{ mb_strtoupper(mb_substr($aluno->nome, 0, 1).mb_substr(\Illuminate\Support\Str::afterLast($aluno->nome, ' '), 0, 1)) }}</div>
                    <div class="fw-semibold">{{ $aluno->nome }}</div>
                    <span class="estado text-{{ $aluno->estado_cor }} justify-content-center">{{ $aluno->estado_label }}{{ $aluno->data_estado ? ' desde '.$aluno->data_estado->format('d/m/Y') : '' }}</span>
                </div>
                <div class="card-body border-top">
                    <dl class="lista-detalhes">
                        <dt>Género</dt><dd>{{ $aluno->genero === 'F' ? 'Feminino' : 'Masculino' }}</dd>
                        <dt>Nascimento</dt><dd>{{ $aluno->data_nascimento->format('d/m/Y') }} ({{ $aluno->idade }} anos)</dd>
                        <dt>Naturalidade</dt><dd>{{ $aluno->naturalidade ?? '—' }}</dd>
                        <dt>Nacionalidade</dt><dd>{{ $aluno->nacionalidade }}</dd>
                        <dt>Documento</dt><dd>{{ $aluno->tipo_documento ? \App\Models\Aluno::TIPOS_DOCUMENTO[$aluno->tipo_documento] : '—' }}@if ($aluno->numero_identificacao)<br><code>{{ $aluno->numero_identificacao }}</code>@endif</dd>
                        <dt>Endereço</dt><dd>{{ $aluno->endereco ?? '—' }}</dd>
                        <dt>Contacto</dt><dd>{{ $aluno->telefone ?? '—' }}{{ $aluno->email ? ' · '.$aluno->email : '' }}</dd>
                        @if ($aluno->necessidades_especiais)<dt>Necessidades especiais</dt><dd>{{ $aluno->necessidades_especiais }}</dd>@endif
                        @if ($aluno->observacoes)<dt>Observações</dt><dd class="small">{!! nl2br(e($aluno->observacoes)) !!}</dd>@endif
                    </dl>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="titulo">Encarregado de educação</h2></div>
                <div class="card-body">
                    @if ($aluno->encarregado)
                        <dl class="lista-detalhes">
                            <dt>Nome</dt><dd>{{ $aluno->encarregado->nome }}</dd>
                            <dt>Parentesco</dt><dd>{{ $aluno->encarregado->parentesco ?? '—' }}</dd>
                            <dt>Telefone</dt><dd>{{ $aluno->encarregado->telefone ?? '—' }}{{ $aluno->encarregado->telefone_alternativo ? ' / '.$aluno->encarregado->telefone_alternativo : '' }}</dd>
                            <dt>E-mail</dt><dd class="text-break">{{ $aluno->encarregado->email ?? '—' }}</dd>
                            <dt>Profissão</dt><dd>{{ $aluno->encarregado->profissao ?? '—' }}</dd>
                        </dl>
                    @else
                        <p class="text-secondary mb-0 small">Sem encarregado de educação registado.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Matrículas</h2></div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>N.º</th><th>Ano lectivo</th><th>Classe / Curso</th><th>Turma</th><th>Turno</th><th>Tipo</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($aluno->matriculas as $m)
                            <tr>
                                <td><code>{{ $m->numero }}</code><div class="small text-secondary">{{ $m->data_matricula->format('d/m/Y') }}</div></td>
                                <td>{{ $m->anoLectivo->designacao }}</td>
                                <td class="small">{{ $m->classe->nome }}{{ $m->curso ? ' · '.$m->curso->nome : '' }}</td>
                                <td>
                                    @if ($m->turma)
                                        @can('turmas.ver')<a href="{{ route('turmas.show', $m->turma) }}">{{ $m->turma->nome }}</a>@else{{ $m->turma->nome }}@endcan
                                    @elseif ($m->estado === 'activa')<span class="badge text-bg-warning">Por atribuir</span>@else — @endif
                                </td>
                                <td class="small">{{ $m->turno->nome }}</td>
                                <td class="small">{{ $m->tipo_label }}</td>
                                <td><span class="estado text-{{ $m->estado_cor }}">{{ $m->estado_label }}</span></td>
                                <td class="accoes">
                                    @can('matriculas.gerir')
                                        @if ($m->estado === 'activa')
                                            <a href="{{ route('matriculas.edit', $m) }}" class="btn btn-light btn-icon" title="Editar / atribuir turma"><i class="bi bi-pencil"></i></a>
                                            <button class="btn btn-light btn-icon" title="Anular" data-bs-toggle="modal" data-bs-target="#modalAnular{{ $m->id }}"><i class="bi bi-x-circle text-danger"></i></button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                            @if ($m->observacoes)
                                <tr><td colspan="8" class="small text-secondary pt-0 border-top-0">{!! nl2br(e($m->observacoes)) !!}</td></tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="tabela-vazia py-4">Sem matrículas registadas.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach ($boletins as $matriculaId => $b)
                @php $mb = $aluno->matriculas->firstWhere('id', $matriculaId); @endphp
                <div class="card mb-3">
                    <div class="card-header">
                        <h2 class="titulo">Classificações — {{ $mb->anoLectivo->designacao }} · {{ $mb->classe->nome }}{{ $mb->turma ? ' '.$mb->turma->nome : '' }}</h2>
                        @if ($b['final'])
                            <span class="badge text-bg-{{ $b['final']->cor }}">{{ $b['final']->rotulo }}</span>
                        @endif
                    </div>
                    @if (! $b['config'])
                        <div class="card-body small text-secondary">Sem configuração de avaliação activa para esta classe.</div>
                    @elseif ($b['disciplinas']->isEmpty())
                        <div class="card-body small text-secondary">Sem plano curricular.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Disciplina</th><th class="text-center">I Trim.</th><th class="text-center">II Trim.</th><th class="text-center">III Trim.</th><th class="text-center">Final</th></tr></thead>
                                <tbody>
                                @foreach ($b['disciplinas'] as $d)
                                    @php $rd = $b['resultados'][$d->id] ?? collect(); @endphp
                                    <tr>
                                        <td class="small">{{ $d->nome }}</td>
                                        @foreach ([1, 2, 3, 0] as $p)
                                            @php $r = $rd[$p] ?? null; @endphp
                                            <td class="text-center small {{ $r?->situacao === 'negativa' ? 'text-danger fw-semibold' : '' }} {{ $p === 0 ? 'fw-semibold' : '' }}">{!! \App\Services\Avaliacao\ConstrutorPautas::exibir($r, $b['config']) !!}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($b['final'])
                            <div class="card-body small border-top"><strong>{{ $b['final']->rotulo }}.</strong> {{ implode(' ', $b['final']->justificacao) }}</div>
                        @endif
                    @endif
                </div>
            @endforeach
            @if ($assiduidade)
                <div class="card mb-3">
                    <div class="card-header"><h2 class="titulo">Assiduidade — {{ $ano?->designacao }}</h2>
                        @can('faltas.ver')<a href="{{ route('faltas.index', ['q' => $aluno->numero_processo]) }}" class="small">Todas as faltas</a>@endcan</div>
                    <div class="card-body">
                        <div class="row g-3 small mb-2">
                            <div class="col-6 col-md-3"><div class="text-secondary">Frequência</div>
                                <div class="fs-5 fw-bold {{ $assiduidade['frequencia'] !== null && $assiduidade['frequencia'] < 66.67 ? 'text-danger' : '' }}">
                                    {{ $assiduidade['frequencia'] !== null ? number_format($assiduidade['frequencia'], 1, ',', '').'%' : 'Não verificada' }}</div></div>
                            <div class="col-6 col-md-3"><div class="text-secondary">Faltas</div><div class="fs-5 fw-bold">{{ $assiduidade['faltas'] }} <span class="small fw-normal">({{ $assiduidade['tempos'] }} tempos)</span></div></div>
                            <div class="col-6 col-md-3"><div class="text-secondary">Justificadas</div><div class="fs-5 fw-bold">{{ $assiduidade['justificadas'] }} <span class="small fw-normal">tempos</span></div></div>
                            <div class="col-6 col-md-3"><div class="text-secondary">Aulas previstas até hoje</div><div class="fs-5 fw-bold">{{ $assiduidade['aulas_previstas'] }}</div></div>
                        </div>
                        @if ($faltasRecentes->isNotEmpty())
                            <div class="small">@foreach ($faltasRecentes as $f)<span class="badge text-bg-{{ $f->justificacao_cor }} me-1 mb-1 fw-normal">{{ $f->data->format('d/m') }} · {{ $f->disciplina->sigla }} · {{ $f->justificacao_label }}</span>@endforeach</div>
                        @elseif (! $assiduidade['registada'])
                            <div class="small text-secondary">A assiduidade desta turma ainda não está a ser registada: a frequência não é verificada.</div>
                        @endif
                    </div>
                </div>
            @endif

            @if ($recibos !== null)
                <div class="card mb-3">
                    <div class="card-header"><h2 class="titulo">Pagamentos</h2>
                        @can('pagamentos.registar')<a href="{{ route('pagamentos.create', ['aluno' => $aluno->id]) }}" class="btn btn-sm btn-light"><i class="bi bi-plus-lg me-1"></i>Registar pagamento</a>@endcan</div>
                    @if ($emDivida->isNotEmpty())
                        <div class="card-body py-2 border-bottom small text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Mensalidades em atraso: {{ $emDivida->pluck('rotulo')->join(', ') }}
                            ({{ \App\Services\Administrativo\ServicoPagamentos::kz($emDivida->sum('valor')) }})</div>
                    @endif
                    <ul class="list-group list-group-flush">
                        @forelse ($recibos as $r)
                            <li class="list-group-item d-flex justify-content-between align-items-center small {{ $r->estado === 'anulado' ? 'text-secondary' : '' }}">
                                <span><a href="{{ route('pagamentos.recibo', $r) }}" class="fw-semibold">{{ $r->numero }}</a> · {{ $r->data->format('d/m/Y') }} · {{ $r->pagamentos->pluck('descricao')->join('; ') }}
                                    @if ($r->estado === 'anulado')<span class="badge text-bg-danger">Anulado</span>@endif</span>
                                <strong class="text-nowrap">{{ \App\Services\Administrativo\ServicoPagamentos::kz($r->total) }}</strong>
                            </li>
                        @empty
                            <li class="list-group-item small text-secondary">Sem pagamentos registados.</li>
                        @endforelse
                    </ul>
                </div>
            @endif

            @if ($declaracoes !== null)
                <div class="card mb-3">
                    <div class="card-header"><h2 class="titulo">Declarações emitidas</h2>
                        <a href="{{ route('declaracoes.create', ['aluno' => $aluno->id]) }}" class="btn btn-sm btn-light"><i class="bi bi-file-earmark-plus me-1"></i>Emitir</a></div>
                    <ul class="list-group list-group-flush">
                        @forelse ($declaracoes as $d)
                            <li class="list-group-item d-flex justify-content-between small"><span>{{ $d->numero }} · {{ $d->tipo_label }} · {{ $d->created_at->format('d/m/Y') }}</span>
                                <a href="{{ route('declaracoes.pdf', $d) }}"><i class="bi bi-file-earmark-pdf"></i> PDF</a></li>
                        @empty
                            <li class="list-group-item small text-secondary">Nenhuma declaração emitida.</li>
                        @endforelse
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h2 class="titulo">Documentos</h2>
                    @can('alunos.gerir')<button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#formDocumento"><i class="bi bi-upload me-1"></i>Carregar</button>@endcan</div>
                @can('alunos.gerir')
                    <div class="collapse {{ $errors->has('ficheiro') || $errors->has('tipo') ? 'show' : '' }} border-bottom" id="formDocumento">
                        <form method="POST" action="{{ route('alunos.documentos.store', $aluno) }}" enctype="multipart/form-data" class="card-body row g-2 align-items-end">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select form-select-sm">@foreach ($tiposDocumento as $v => $r)<option value="{{ $v }}">{{ $r }}</option>@endforeach</select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Descrição</label>
                                <input name="descricao" maxlength="150" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ficheiro (PDF, JPG, PNG · máx. 5 MB)</label>
                                <input type="file" name="ficheiro" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm @error('ficheiro') is-invalid @enderror">
                                <x-campo-erro campo="ficheiro" />
                            </div>
                            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Carregar</button></div>
                        </form>
                    </div>
                @endcan
                <ul class="list-group list-group-flush">
                    @forelse ($aluno->documentos as $d)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-file-earmark{{ str_contains($d->mime, 'pdf') ? '-pdf' : '-image' }} me-2 text-primary"></i>
                                <strong class="small">{{ $d->tipo_label }}</strong> <span class="small text-secondary">· {{ $d->descricao ?? $d->nome_original }} · {{ number_format($d->tamanho / 1024, 0, ',', ' ') }} KB · {{ $d->created_at->format('d/m/Y') }}</span></span>
                            <span>
                                <a href="{{ route('alunos.documentos.download', [$aluno, $d]) }}" class="btn btn-light btn-icon" title="Descarregar"><i class="bi bi-download"></i></a>
                                @can('alunos.gerir')
                                    <form method="POST" action="{{ route('alunos.documentos.destroy', [$aluno, $d]) }}" class="d-inline" data-confirmar="Remover este documento?" data-confirmar-classe="btn-danger" data-confirmar-texto="Remover">
                                        @csrf @method('DELETE')<button class="btn btn-light btn-icon" title="Remover"><i class="bi bi-trash text-danger"></i></button>
                                    </form>
                                @endcan
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item small text-secondary">Sem documentos carregados.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @can('alunos.gerir')
        <div class="modal fade" id="modalSituacao" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('alunos.estado', $aluno) }}" class="modal-content">
                    @csrf @method('PATCH')
                    <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Alterar situação do aluno</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="small text-secondary">Situação actual: <strong>{{ $aluno->estado_label }}</strong>. A alteração é aplicada também à matrícula de {{ $ano?->designacao }}, se existir.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label obrigatorio">Nova situação</label>
                                <select name="estado" class="form-select @error('estado') is-invalid @enderror">
                                    @foreach (\App\Models\Aluno::ESTADOS as $v => $r)@if ($v !== $aluno->estado)<option value="{{ $v }}" @selected(old('estado') === $v)>{{ $r }}</option>@endif @endforeach
                                </select>
                                <x-campo-erro campo="estado" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label obrigatorio">Data</label>
                                <input type="date" name="data_estado" value="{{ old('data_estado', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control @error('data_estado') is-invalid @enderror">
                                <x-campo-erro campo="data_estado" />
                            </div>
                            <div class="col-12">
                                <label class="form-label obrigatorio">Motivo</label>
                                <textarea name="motivo" rows="3" maxlength="500" class="form-control @error('motivo') is-invalid @enderror" placeholder="Ex.: transferência para a escola X, a pedido do encarregado.">{{ old('motivo') }}</textarea>
                                <x-campo-erro campo="motivo" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Confirmar</button></div>
                </form>
            </div>
        </div>
        @if ($errors->hasAny(['estado', 'data_estado', 'motivo']))
            @push('scripts')<script>document.addEventListener('DOMContentLoaded', function () { new bootstrap.Modal(document.getElementById('modalSituacao')).show(); });</script>@endpush
        @endif
    @endcan

    @can('matriculas.gerir')
        @foreach ($aluno->matriculas->where('estado', 'activa') as $m)
            <div class="modal fade" id="modalAnular{{ $m->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('matriculas.anular', $m) }}" class="modal-content">
                        @csrf @method('PATCH')
                        <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Anular matrícula {{ $m->numero }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <p class="small text-secondary">As matrículas não são eliminadas. A anulação fica registada no histórico e liberta o lugar na turma.</p>
                            <label class="form-label obrigatorio">Motivo da anulação</label>
                            <textarea name="motivo" rows="3" maxlength="500" class="form-control" required></textarea>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger">Anular matrícula</button></div>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan
</x-app-layout>
