<x-app-layout titulo="Emitir declaração">
    <x-cabecalho titulo="Emitir declaração" :subtitulo="$aluno ? $aluno->nome.' · Processo '.$aluno->numero_processo : 'Seleccione o aluno'"
        :migalhas="['Administrativo' => null, 'Declarações' => route('declaracoes.index'), 'Emitir' => null]" />

    @if (! $aluno)
        <form method="GET" class="card"><div class="card-body row g-2 align-items-end">
            <div class="col-md-9"><label class="form-label">Aluno</label>
                <select name="aluno" class="form-select" required><option value="">Seleccione…</option>
                    @foreach ($alunos as $a)<option value="{{ $a->id }}">{{ $a->nome }} ({{ $a->numero_processo }})</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Continuar</button></div>
        </div></form>
    @else
        <form method="POST" action="{{ route('declaracoes.store') }}" class="card" style="max-width: 760px">
            @csrf
            <input type="hidden" name="aluno_id" value="{{ $aluno->id }}">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label obrigatorio">Tipo de declaração</label>
                    <div class="row g-2">
                        @foreach (config('administrativo.declaracoes.tipos') as $v => $r)
                            <div class="col-md-4"><label class="form-check opcao-check"><input class="form-check-input" type="radio" name="tipo" value="{{ $v }}" @checked(old('tipo', 'matricula') === $v)> {{ $r }}</label></div>
                        @endforeach
                    </div>
                    <x-campo-erro campo="tipo" />
                </div>
                <div class="mb-3">
                    <label class="form-label obrigatorio">Matrícula</label>
                    <select name="matricula_id" class="form-select @error('matricula_id') is-invalid @enderror">
                        @forelse ($matriculas as $m)
                            <option value="{{ $m->id }}" @selected(old('matricula_id') == $m->id)>{{ $m->anoLectivo->designacao }} · {{ $m->classe->nome }}{{ $m->turma ? ' · Turma '.$m->turma->nome : '' }} · {{ $m->estado_label }}</option>
                        @empty
                            <option value="">O aluno não tem matrículas</option>
                        @endforelse
                    </select>
                    <x-campo-erro campo="matricula_id" />
                </div>
                <div class="mb-3">
                    <label class="form-label">Finalidade</label>
                    <input name="finalidade" value="{{ old('finalidade') }}" maxlength="255" class="form-control" placeholder="Ex.: para efeitos de abono de família">
                </div>
                <p class="small text-secondary">O documento é preenchido automaticamente com os dados do aluno, da matrícula e da escola, numerado em sequência e guardado no histórico com um código de verificação.</p>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('declaracoes.index') }}" class="btn btn-light">Cancelar</a>
                <button class="btn btn-primary" @disabled($matriculas->isEmpty())><i class="bi bi-file-earmark-check me-1"></i>Emitir</button>
            </div>
        </form>
    @endif
</x-app-layout>
