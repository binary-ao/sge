@php $editar = $matricula->exists; @endphp
<x-app-layout :titulo="$editar ? 'Editar matrícula' : 'Nova matrícula'">
    <x-cabecalho :titulo="$editar ? 'Editar matrícula '.$matricula->numero : 'Efectuar matrícula'"
        :subtitulo="$aluno ? $aluno->nome.' · Processo n.º '.$aluno->numero_processo : 'Seleccione o aluno a matricular'"
        :migalhas="['Gestão Académica' => null, 'Matrículas' => route('matriculas.index'), ($editar ? $matricula->numero : 'Nova') => null]" />

    <form method="POST" action="{{ $editar ? route('matriculas.update', $matricula) : route('matriculas.store') }}" novalidate>
        @csrf
        @if ($editar) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card"><div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label obrigatorio">Aluno</label>
                            @if ($aluno)
                                <input type="hidden" name="aluno_id" value="{{ $aluno->id }}">
                                <div class="form-control bg-light">{{ $aluno->nome }} <span class="text-secondary">· {{ $aluno->numero_processo }} · {{ $aluno->idade }} anos</span></div>
                            @else
                                <select name="aluno_id" class="form-select @error('aluno_id') is-invalid @enderror">
                                    <option value="">Seleccione um aluno activo sem matrícula neste ano…</option>
                                    @foreach ($alunosSemMatricula as $a)<option value="{{ $a->id }}" @selected(old('aluno_id') == $a->id)>{{ $a->nome }} ({{ $a->numero_processo }})</option>@endforeach
                                </select>
                                <div class="form-text">Aluno novo? <a href="{{ route('alunos.create') }}">Cadastre-o primeiro</a>.</div>
                            @endif
                            <x-campo-erro campo="aluno_id" />
                        </div>

                        <div class="col-md-4">
                            <label class="form-label obrigatorio" for="ano">Ano lectivo</label>
                            @if ($editar)
                                <div class="form-control bg-light">{{ $matricula->anoLectivo->designacao }}</div>
                                <input type="hidden" id="ano" value="{{ $matricula->ano_lectivo_id }}">
                            @else
                                <select id="ano" name="ano_lectivo_id" class="form-select @error('ano_lectivo_id') is-invalid @enderror">
                                    @foreach ($anos as $a)<option value="{{ $a->id }}" @selected(old('ano_lectivo_id', $matricula->ano_lectivo_id) == $a->id)>{{ $a->designacao }}</option>@endforeach
                                </select>
                            @endif
                            <x-campo-erro campo="ano_lectivo_id" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio" for="classe">Classe</label>
                            <select id="classe" name="classe_id" class="form-select @error('classe_id') is-invalid @enderror">
                                <option value="">Seleccione…</option>
                                @foreach ($classes as $c)
                                    <option value="{{ $c->id }}" data-nivel="{{ $c->nivel_ensino_id }}" data-exige-curso="{{ $c->nivelEnsino->exige_curso ? 1 : 0 }}"
                                        @selected(old('classe_id', $matricula->classe_id) == $c->id)>{{ $c->nome_completo }}</option>
                                @endforeach
                            </select>
                            <x-campo-erro campo="classe_id" />
                        </div>
                        <div class="col-md-4" id="blocoCurso">
                            <label class="form-label obrigatorio" for="curso">Curso</label>
                            <select id="curso" name="curso_id" class="form-select @error('curso_id') is-invalid @enderror">
                                <option value="">Seleccione…</option>
                                @foreach ($cursos as $c)<option value="{{ $c->id }}" data-nivel="{{ $c->nivel_ensino_id }}" @selected(old('curso_id', $matricula->curso_id) == $c->id)>{{ $c->nome }}</option>@endforeach
                            </select>
                            <x-campo-erro campo="curso_id" />
                        </div>

                        <div class="col-md-8">
                            <label class="form-label" for="turma">Turma</label>
                            <select id="turma" name="turma_id" class="form-select @error('turma_id') is-invalid @enderror" data-valor="{{ old('turma_id', $matricula->turma_id) }}"></select>
                            <x-campo-erro campo="turma_id" />
                            <div class="form-text" id="ajudaTurma">Pode deixar a turma por atribuir e distribuir os alunos mais tarde, na página da turma.</div>
                        </div>
                        <div class="col-md-4" id="blocoTurno">
                            <label class="form-label obrigatorio" for="turno">Turno</label>
                            <select id="turno" name="turno_id" class="form-select @error('turno_id') is-invalid @enderror">
                                @foreach ($turnos as $t)<option value="{{ $t->id }}" @selected(old('turno_id', $matricula->turno_id) == $t->id)>{{ $t->nome }}</option>@endforeach
                            </select>
                            <x-campo-erro campo="turno_id" />
                        </div>

                        <div class="col-md-4">
                            <label class="form-label obrigatorio" for="tipo">Tipo</label>
                            <select id="tipo" name="tipo" class="form-select">
                                @foreach (\App\Models\Matricula::TIPOS as $v => $r)<option value="{{ $v }}" @selected(old('tipo', $matricula->tipo) === $v)>{{ $r }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio" for="data_matricula">Data da matrícula</label>
                            <input type="date" id="data_matricula" name="data_matricula" max="{{ now()->toDateString() }}" value="{{ old('data_matricula', $matricula->data_matricula?->toDateString()) }}" class="form-control @error('data_matricula') is-invalid @enderror">
                            <x-campo-erro campo="data_matricula" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="observacoes">Observações</label>
                            <textarea id="observacoes" name="observacoes" rows="2" maxlength="1000" class="form-control">{{ old('observacoes', $matricula->observacoes) }}</textarea>
                        </div>
                    </div>
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card"><div class="card-body small">
                    <div class="fw-semibold text-primary mb-2"><i class="bi bi-shield-check me-1"></i>Validações aplicadas</div>
                    <ul class="text-secondary ps-3 mb-0">
                        <li>Um aluno não pode ter duas matrículas no mesmo ano lectivo.</li>
                        <li>A classe tem de pertencer a um nível ministrado pela escola.</li>
                        <li>II Ciclo e Técnico-Profissional exigem curso.</li>
                        <li>A turma tem de corresponder ao ano, à classe e ao curso, e ter lugares disponíveis.</li>
                        <li>O número de matrícula é atribuído automaticamente, em sequência por escola.</li>
                    </ul>
                </div></div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ $aluno ? route('alunos.show', $aluno) : route('matriculas.index') }}" class="btn btn-light">Cancelar</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $editar ? 'Guardar alterações' : 'Confirmar matrícula' }}</button>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                var turmas = @json($turmas);
                var ano = document.getElementById('ano'), classe = document.getElementById('classe'), curso = document.getElementById('curso');
                var turma = document.getElementById('turma'), blocoCurso = document.getElementById('blocoCurso'), blocoTurno = document.getElementById('blocoTurno');
                var inicial = turma.dataset.valor;

                function actualizar() {
                    var op = classe.options[classe.selectedIndex];
                    var exige = op && op.dataset.exigeCurso === '1';
                    blocoCurso.style.display = exige ? '' : 'none';
                    if (!exige) { curso.value = ''; }
                    Array.prototype.forEach.call(curso.options, function (o) {
                        if (!o.dataset.nivel) { return; }
                        o.hidden = !op || o.dataset.nivel !== op.dataset.nivel;
                        if (o.hidden && o.selected) { curso.value = ''; }
                    });

                    var anterior = turma.value || inicial;
                    inicial = null;
                    turma.innerHTML = '<option value="">Por atribuir</option>';
                    turmas.filter(function (t) {
                        return String(t.ano) === ano.value && String(t.classe) === classe.value && String(t.curso || '') === (curso.value || '');
                    }).forEach(function (t) {
                        var o = document.createElement('option');
                        o.value = t.id;
                        o.textContent = t.nome + ' — ' + (t.vagas > 0 ? t.vagas + ' lugar(es) disponível(is)' : 'completa');
                        o.disabled = t.vagas <= 0 && String(t.id) !== String(anterior);
                        o.selected = String(t.id) === String(anterior);
                        turma.appendChild(o);
                    });
                    blocoTurno.style.display = turma.value ? 'none' : '';
                }
                [ano, classe, curso].forEach(function (el) { el.addEventListener('change', actualizar); });
                turma.addEventListener('change', function () { blocoTurno.style.display = turma.value ? 'none' : ''; });
                actualizar();
            })();
        </script>
    @endpush
</x-app-layout>
