@php $editar = $turma->exists; @endphp
<x-app-layout :titulo="$editar ? 'Editar turma' : 'Nova turma'">
    <x-cabecalho :titulo="$editar ? 'Editar turma '.$turma->designacao : 'Nova turma'"
        :migalhas="['Gestão Académica' => null, 'Turmas' => route('turmas.index'), ($editar ? $turma->designacao : 'Nova') => null]" />

    <form method="POST" action="{{ $editar ? route('turmas.update', $turma) : route('turmas.store') }}" novalidate>
        @csrf
        @if ($editar) @method('PUT') @endif
        <div class="card" style="max-width: 900px"><div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label obrigatorio" for="ano">Ano lectivo</label>
                    <select id="ano" name="ano_lectivo_id" class="form-select @error('ano_lectivo_id') is-invalid @enderror">
                        @foreach ($anos as $a)<option value="{{ $a->id }}" @selected(old('ano_lectivo_id', $turma->ano_lectivo_id) == $a->id)>{{ $a->designacao }}</option>@endforeach
                    </select>
                    <x-campo-erro campo="ano_lectivo_id" />
                </div>
                <div class="col-md-4">
                    <label class="form-label obrigatorio" for="classe">Classe</label>
                    <select id="classe" name="classe_id" class="form-select @error('classe_id') is-invalid @enderror">
                        <option value="">Seleccione…</option>
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}" data-nivel="{{ $c->nivel_ensino_id }}" data-exige-curso="{{ $c->nivelEnsino->exige_curso ? 1 : 0 }}"
                                @selected(old('classe_id', $turma->classe_id) == $c->id)>{{ $c->nome_completo }}</option>
                        @endforeach
                    </select>
                    <x-campo-erro campo="classe_id" />
                    <div class="form-text">Apenas classes dos níveis ministrados pela escola.</div>
                </div>
                <div class="col-md-4" id="blocoCurso">
                    <label class="form-label obrigatorio" for="curso">Curso</label>
                    <select id="curso" name="curso_id" class="form-select @error('curso_id') is-invalid @enderror">
                        <option value="">Seleccione…</option>
                        @foreach ($cursos as $c)<option value="{{ $c->id }}" data-nivel="{{ $c->nivel_ensino_id }}" @selected(old('curso_id', $turma->curso_id) == $c->id)>{{ $c->nome }}</option>@endforeach
                    </select>
                    <x-campo-erro campo="curso_id" />
                </div>
                <div class="col-md-3">
                    <label class="form-label obrigatorio" for="nome">Designação</label>
                    <input id="nome" name="nome" value="{{ old('nome', $turma->nome) }}" maxlength="30" placeholder="A" class="form-control text-uppercase @error('nome') is-invalid @enderror">
                    <x-campo-erro campo="nome" />
                </div>
                <div class="col-md-3">
                    <label class="form-label obrigatorio" for="turno">Turno</label>
                    <select id="turno" name="turno_id" class="form-select @error('turno_id') is-invalid @enderror">
                        @foreach ($turnos as $t)<option value="{{ $t->id }}" @selected(old('turno_id', $turma->turno_id) == $t->id)>{{ $t->nome }} ({{ $t->horario }})</option>@endforeach
                    </select>
                    <x-campo-erro campo="turno_id" />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="sala">Sala</label>
                    <input id="sala" name="sala" value="{{ old('sala', $turma->sala) }}" maxlength="30" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label obrigatorio" for="capacidade">Capacidade</label>
                    <input type="number" id="capacidade" name="capacidade" min="1" max="120" value="{{ old('capacidade', $turma->capacidade) }}" class="form-control @error('capacidade') is-invalid @enderror">
                    <x-campo-erro campo="capacidade" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="director">Director de turma</label>
                    <select id="director" name="director_turma_id" class="form-select">
                        <option value="">Por designar</option>
                        @foreach ($professores as $p)<option value="{{ $p->id }}" @selected(old('director_turma_id', $turma->director_turma_id) == $p->id)>{{ $p->nome }}</option>@endforeach
                    </select>
                </div>
                @if ($editar)
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1" id="activo" @checked(old('activo', $turma->activo))>
                            <label class="form-check-label" for="activo">Turma activa</label>
                        </div>
                    </div>
                @endif
            </div>
        </div></div>
        <div class="d-flex justify-content-end gap-2 mt-3" style="max-width: 900px">
            <a href="{{ $editar ? route('turmas.show', $turma) : route('turmas.index') }}" class="btn btn-light">Cancelar</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $editar ? 'Guardar alterações' : 'Criar turma' }}</button>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                var classe = document.getElementById('classe'), curso = document.getElementById('curso'), bloco = document.getElementById('blocoCurso');
                function actualizar() {
                    var op = classe.options[classe.selectedIndex];
                    var exige = op && op.dataset.exigeCurso === '1';
                    bloco.style.display = exige ? '' : 'none';
                    if (!exige) { curso.value = ''; }
                    Array.prototype.forEach.call(curso.options, function (o) {
                        if (!o.dataset.nivel) { return; }
                        o.hidden = !op || o.dataset.nivel !== op.dataset.nivel;
                        if (o.hidden && o.selected) { curso.value = ''; }
                    });
                }
                classe.addEventListener('change', actualizar);
                actualizar();
            })();
        </script>
    @endpush
</x-app-layout>
