<x-app-layout titulo="Registar faltas">
    <x-cabecalho titulo="Registar faltas" subtitulo="Marque os alunos ausentes na aula" :migalhas="['Administrativo' => null, 'Faltas' => route('faltas.index'), 'Registar' => null]" />

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label">Turma</label>
            <select name="turma_id" class="form-select" data-auto-submit><option value="">Seleccione…</option>
                @foreach ($turmas as $t)<option value="{{ $t->id }}" @selected($turma?->id === $t->id)>{{ $t->designacao }}</option>@endforeach
            </select></div>
        <div class="col-md-4"><label class="form-label">Disciplina</label>
            <select name="disciplina_id" class="form-select" data-auto-submit @disabled(! $turma)><option value="">Seleccione…</option>
                @foreach ($disciplinas as $d)<option value="{{ $d->id }}" @selected($disciplina?->id === $d->id)>{{ $d->nome }}</option>@endforeach
            </select></div>
        <div class="col-md-3"><label class="form-label">Data da aula</label>
            <input type="date" name="data" value="{{ $data->toDateString() }}" max="{{ now()->toDateString() }}" min="{{ $ano?->data_inicio->toDateString() }}" class="form-control" data-auto-submit></div>
        <div class="col-md-1"><button class="btn btn-primary w-100">Abrir</button></div>
    </form>

    @if ($turma && $turmas->isNotEmpty() && $disciplinas->isEmpty())
        <div class="alert alert-warning">Não tem disciplinas atribuídas nesta turma.</div>
    @endif

    @if ($turma && $disciplina)
        @if ($bloqueio)<div class="alert alert-secondary"><i class="bi bi-lock-fill me-1"></i>{{ $bloqueio }}</div>@endif
        <form method="POST" action="{{ route('faltas.chamada.guardar') }}">
            @csrf
            <input type="hidden" name="turma_id" value="{{ $turma->id }}"><input type="hidden" name="disciplina_id" value="{{ $disciplina->id }}"><input type="hidden" name="data" value="{{ $data->toDateString() }}">
            <div class="card">
                <div class="card-header">
                    <h2 class="titulo">{{ $turma->designacao }} · {{ $disciplina->nome }} · {{ $data->translatedFormat('d/m/Y') }}</h2>
                    <span class="small text-secondary"><span id="contador">{{ $existentes->count() }}</span> ausente(s) de {{ $matriculas->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th style="width:40px">N.º</th><th>Aluno</th><th class="text-center" style="width:90px">Faltou</th><th style="width:110px">Tempos</th><th>Motivo (opcional)</th><th>Situação</th></tr></thead>
                        <tbody>
                        @foreach ($matriculas as $m)
                            @php $f = $existentes[$m->id] ?? null; @endphp
                            <tr class="{{ $f ? 'table-warning' : '' }}">
                                <td class="text-secondary">{{ $loop->iteration }}</td>
                                <td class="fw-semibold small">{{ $m->aluno->nome }}</td>
                                <td class="text-center"><input type="checkbox" class="form-check-input caixa-falta" name="faltas[{{ $m->id }}][falta]" value="1" @checked($f) @disabled($bloqueio)></td>
                                <td><input type="number" name="faltas[{{ $m->id }}][tempos]" value="{{ $f?->tempos ?? 1 }}" min="1" max="8" class="form-control form-control-sm" @disabled($bloqueio)></td>
                                <td><input name="faltas[{{ $m->id }}][motivo]" value="{{ $f?->motivo }}" maxlength="255" class="form-control form-control-sm" @disabled($bloqueio)></td>
                                <td>@if ($f)<span class="badge text-bg-{{ $f->justificacao_cor }}">{{ $f->justificacao_label }}</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @unless ($bloqueio)
                    <div class="card-body border-top d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">Desmarcar um aluno remove a falta, excepto se já tiver justificativo.</span>
                        <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar chamada</button>
                    </div>
                @endunless
            </div>
        </form>
        @push('scripts')
            <script>
                document.querySelectorAll('.caixa-falta').forEach(function (c) {
                    c.addEventListener('change', function () {
                        c.closest('tr').classList.toggle('table-warning', c.checked);
                        document.getElementById('contador').textContent = document.querySelectorAll('.caixa-falta:checked').length;
                    });
                });
            </script>
        @endpush
    @elseif ($turmas->isEmpty())
        <div class="card"><div class="card-body tabela-vazia">Sem turmas disponíveis.</div></div>
    @endif
</x-app-layout>
