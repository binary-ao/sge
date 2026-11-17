<x-app-layout titulo="Faltas">
    <x-cabecalho titulo="Faltas" :subtitulo="$escolaActiva->nome.' · '.$ano?->designacao" :migalhas="['Administrativo' => null, 'Faltas' => null]">
        @can('relatorios.escolares')<a href="{{ route('relatorios.show', 'faltas') }}" class="btn btn-light"><i class="bi bi-file-earmark-bar-graph me-1"></i>Relatórios de faltas</a>@endcan
        @can('faltas.registar')<a href="{{ route('faltas.chamada') }}" class="btn btn-primary"><i class="bi bi-check2-square me-1"></i>Registar faltas</a>@endcan
    </x-cabecalho>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Faltas registadas" :valor="$resumo['faltas']" icone="calendar-x" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Tempos lectivos em falta" :valor="$resumo['tempos']" icone="clock-history" tom="info" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Justificativos pendentes" :valor="$resumo['pendentes']" icone="hourglass-split" tom="alerta" :href="route('faltas.index', ['justificacao' => 'pendente'])" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Faltas justificadas" :valor="$resumo['justificadas']" icone="check2-circle" tom="sucesso" /></div>
    </div>

    <form method="GET" class="barra-filtros">
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6"><label class="form-label">Aluno</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nome ou processo"></div>
            <div class="col-lg-2 col-md-6"><label class="form-label">Turma</label>
                <select name="turma_id" class="form-select form-select-sm" data-auto-submit><option value="">Todas</option>
                    @foreach ($turmas as $t)<option value="{{ $t->id }}" @selected(request('turma_id') == $t->id)>{{ $t->designacao }}</option>@endforeach
                </select></div>
            <div class="col-lg-2 col-md-4"><label class="form-label">Disciplina</label>
                <select name="disciplina_id" class="form-select form-select-sm" data-auto-submit><option value="">Todas</option>
                    @foreach ($disciplinas as $d)<option value="{{ $d->id }}" @selected(request('disciplina_id') == $d->id)>{{ $d->nome }}</option>@endforeach
                </select></div>
            <div class="col-lg-2 col-md-4"><label class="form-label">Justificação</label>
                <select name="justificacao" class="form-select form-select-sm" data-auto-submit><option value="">Todas</option>
                    @foreach (\App\Models\Falta::JUSTIFICACOES as $v => $r)<option value="{{ $v }}" @selected(request('justificacao') === $v)>{{ $r }}</option>@endforeach
                </select></div>
            <div class="col-lg-1 col-md-2 col-6"><label class="form-label">De</label><input type="date" name="de" value="{{ request('de') }}" class="form-control form-control-sm"></div>
            <div class="col-lg-1 col-md-2 col-6"><label class="form-label">Até</label><input type="date" name="ate" value="{{ request('ate') }}" class="form-control form-control-sm"></div>
            <div class="col-lg-1"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Data</th><th>Aluno</th><th>Turma</th><th>Disciplina</th><th class="text-end">Tempos</th><th>Motivo</th><th>Justificação</th><th></th></tr></thead>
                <tbody>
                @forelse ($faltas as $f)
                    <tr>
                        <td class="small text-nowrap">{{ $f->data->format('d/m/Y') }}</td>
                        <td>@can('alunos.ver')<a href="{{ route('alunos.show', $f->matricula->aluno_id) }}" class="text-decoration-none fw-semibold">{{ $f->matricula->aluno->nome }}</a>@else{{ $f->matricula->aluno->nome }}@endcan</td>
                        <td class="small">{{ $f->turma->designacao }}</td>
                        <td class="small">{{ $f->disciplina->nome }}</td>
                        <td class="text-end">{{ $f->tempos }}</td>
                        <td class="small">{{ $f->motivo ?? '—' }}</td>
                        <td>
                            <span class="badge text-bg-{{ $f->justificacao_cor }}">{{ $f->justificacao_label }}</span>
                            @if ($f->justificativo)
                                <div class="small text-secondary mt-1" style="max-width:240px">{{ \Illuminate\Support\Str::limit($f->justificativo->motivo, 70) }}
                                    @if ($f->justificativo->documento) · <a href="{{ route('faltas.documento', $f->justificativo) }}">documento</a>@endif
                                    @if ($f->justificativo->observacao)<br><em>{{ $f->justificativo->observacao }}</em>@endif
                                </div>
                            @endif
                        </td>
                        <td class="accoes">
                            @if (in_array($f->justificacao, ['nao_justificada', 'rejeitado'], true) && (auth()->user()->can('faltas.registar') || auth()->user()->can('faltas.justificar')))
                                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#modalJustificar" data-accao="{{ route('faltas.justificar', $f) }}"
                                        data-aluno="{{ $f->matricula->aluno->nome }} · {{ $f->data->format('d/m/Y') }}">Justificar</button>
                            @endif
                            @if ($f->justificacao === 'pendente' && auth()->user()->can('faltas.justificar'))
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAnalisar" data-accao="{{ route('faltas.analisar', $f->justificativo) }}"
                                        data-motivo="{{ $f->justificativo->motivo }}">Analisar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tabela-vazia"><i class="bi bi-calendar-check"></i>Sem faltas para os filtros indicados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $faltas->links() }}</div>

    <div class="modal fade" id="modalJustificar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content" enctype="multipart/form-data" id="formJustificar">
                @csrf
                <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Submeter justificativo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small text-secondary mb-2" id="justAluno"></p>
                    <label class="form-label obrigatorio">Motivo</label>
                    <textarea name="motivo" rows="3" class="form-control" required maxlength="1000" placeholder="Ex.: doença, com atestado médico."></textarea>
                    <label class="form-label mt-3">Documento comprovativo (PDF, JPG ou PNG, opcional)</label>
                    <input type="file" name="documento" accept=".pdf,.jpg,.jpeg,.png" class="form-control">
                    <p class="small text-secondary mt-2 mb-0">O justificativo fica pendente até ser analisado pela direcção.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Submeter</button></div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="modalAnalisar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content" id="formAnalisar">
                @csrf @method('PATCH')
                <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Analisar justificativo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small" id="anaMotivo"></p>
                    <label class="form-label">Observação (obrigatória em caso de rejeição)</label>
                    <textarea name="observacao" rows="2" maxlength="500" class="form-control"></textarea>
                </div>
                <div class="modal-footer">
                    <button name="estado" value="rejeitado" class="btn btn-outline-danger">Rejeitar</button>
                    <button name="estado" value="aprovado" class="btn btn-success">Aprovar</button>
                </div>
            </form>
        </div>
    </div>
    @push('scripts')
        <script>
            document.getElementById('modalJustificar').addEventListener('show.bs.modal', function (e) {
                document.getElementById('formJustificar').action = e.relatedTarget.dataset.accao;
                document.getElementById('justAluno').textContent = e.relatedTarget.dataset.aluno;
            });
            document.getElementById('modalAnalisar').addEventListener('show.bs.modal', function (e) {
                document.getElementById('formAnalisar').action = e.relatedTarget.dataset.accao;
                document.getElementById('anaMotivo').textContent = 'Motivo apresentado: ' + e.relatedTarget.dataset.motivo;
            });
        </script>
    @endpush
</x-app-layout>
