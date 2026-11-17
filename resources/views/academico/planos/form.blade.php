@php $editar = $plano->exists; @endphp
<x-app-layout :titulo="$editar ? 'Editar plano curricular' : 'Novo plano curricular'">
    <x-cabecalho :titulo="$editar ? 'Editar plano curricular' : 'Novo plano curricular'"
        :migalhas="['Configuração Académica' => route('academico.index'), 'Planos curriculares' => route('academico.planos.index'), ($editar ? $plano->nome : 'Novo') => null]" />

    <form method="POST" action="{{ $editar ? route('academico.planos.update', $plano->id) : route('academico.planos.store') }}" novalidate>
        @csrf
        @if ($editar) @method('PUT') @endif

        <div class="card mb-3">
            <div class="card-body">
                @if (! $editar)
                    @include('academico._ambito-registo', ['id' => 'Plano'])
                @else
                    <div class="alert alert-light border small py-2">Âmbito: <strong>{{ $plano->escola?->nome ?? 'Referência provincial' }}</strong></div>
                @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label obrigatorio" for="nome">Nome do plano</label>
                        <input id="nome" name="nome" value="{{ old('nome', $plano->nome) }}" class="form-control @error('nome') is-invalid @enderror" maxlength="150">
                        <x-campo-erro campo="nome" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vigência</label>
                        <select name="ano_lectivo_id" class="form-select @error('ano_lectivo_id') is-invalid @enderror">
                            <option value="">Vigente em todos os anos lectivos</option>
                            @foreach ($anos as $a)<option value="{{ $a->id }}" @selected(old('ano_lectivo_id', $plano->ano_lectivo_id) == $a->id)>Apenas {{ $a->designacao }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label obrigatorio" for="classe_id">Classe</label>
                        <select id="classe_id" name="classe_id" class="form-select @error('classe_id') is-invalid @enderror">
                            <option value="">Seleccione…</option>
                            @foreach ($classes as $c)
                                <option value="{{ $c->id }}" data-nivel="{{ $c->nivel_ensino_id }}" data-exige-curso="{{ $c->nivelEnsino->exige_curso ? 1 : 0 }}"
                                    @selected(old('classe_id', $plano->classe_id) == $c->id)>{{ $c->nome_completo }}</option>
                            @endforeach
                        </select>
                        <x-campo-erro campo="classe_id" />
                    </div>
                    <div class="col-md-6" id="blocoCurso">
                        <label class="form-label obrigatorio" for="curso_id">Curso</label>
                        <select id="curso_id" name="curso_id" class="form-select @error('curso_id') is-invalid @enderror">
                            <option value="">Seleccione…</option>
                            @foreach ($cursos as $c)
                                <option value="{{ $c->id }}" data-nivel="{{ $c->nivel_ensino_id }}" @selected(old('curso_id', $plano->curso_id) == $c->id)>{{ $c->nome }}</option>
                            @endforeach
                        </select>
                        <x-campo-erro campo="curso_id" />
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1" id="activo" @checked(old('activo', $plano->activo))>
                            <label class="form-check-label" for="activo">Plano activo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header flex-wrap gap-2"><h2 class="titulo">Disciplinas do plano</h2>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="form-check form-switch mb-0 small">
                        <input class="form-check-input" type="checkbox" role="switch" id="soDoNivel" checked>
                        <label class="form-check-label" for="soDoNivel">Só disciplinas do nível da classe</label>
                    </div>
                    <input type="search" class="form-control form-control-sm" style="max-width:240px" placeholder="Filtrar disciplinas…" id="filtroDisc">
                </div></div>
            <x-campo-erro campo="disciplinas" />
            <div class="table-responsive" style="max-height: 520px">
                <table class="table table-sm align-middle">
                    <thead class="sticky-top"><tr><th style="width:40px"></th><th>Disciplina</th><th>Componente</th><th style="width:130px">Tempos/semana</th><th style="width:110px">Específica</th><th style="width:110px" title="Por omissão as disciplinas são obrigatórias">Opcional</th></tr></thead>
                    <tbody>
                    @foreach ($disciplinas as $d)
                        @php $sel = $seleccionadas[$d->id] ?? null; @endphp
                        <tr data-disc="{{ mb_strtolower($d->nome.' '.$d->sigla) }}" data-niveis=",{{ $d->niveisEnsino->pluck('id')->implode(',') }},">
                            <td><input class="form-check-input" type="checkbox" name="disciplinas[{{ $d->id }}][incluir]" value="1" id="d{{ $d->id }}" @checked(! empty($sel['incluir']))></td>
                            <td><label for="d{{ $d->id }}" class="mb-0">{{ $d->nome }} <code class="small">{{ $d->sigla }}</code>
                                @if ($d->escola_id)<span class="badge badge-suave">Escola</span>@endif</label></td>
                            <td class="small text-secondary">{{ $d->componente_label }}</td>
                            <td><input type="number" min="0" max="40" name="disciplinas[{{ $d->id }}][carga_horaria]" value="{{ $sel['carga_horaria'] ?? '' }}" class="form-control form-control-sm"></td>
                            <td><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="disciplinas[{{ $d->id }}][especifica]" value="1" @checked(! empty($sel['especifica']))></div></td>
                            <td><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="disciplinas[{{ $d->id }}][opcional]" value="1" @checked(! empty($sel['opcional']))></div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ $editar ? route('academico.planos.show', $plano->id) : route('academico.planos.index') }}" class="btn btn-light">Cancelar</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar plano</button>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                var classe = document.getElementById('classe_id'), curso = document.getElementById('curso_id'), bloco = document.getElementById('blocoCurso');
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
                // Filtro por texto e pelo nível de ensino da classe (as disciplinas já incluídas ficam sempre visíveis).
                var filtro = document.getElementById('filtroDisc'), soNivel = document.getElementById('soDoNivel');
                function filtrarDisciplinas() {
                    var t = filtro.value.toLowerCase();
                    var op = classe.options[classe.selectedIndex];
                    var nivel = op && op.dataset.nivel ? ',' + op.dataset.nivel + ',' : null;
                    document.querySelectorAll('[data-disc]').forEach(function (tr) {
                        var incluida = tr.querySelector('input[name$="[incluir]"]').checked;
                        var doNivel = !soNivel.checked || !nivel || tr.dataset.niveis.indexOf(nivel) >= 0;
                        tr.style.display = tr.dataset.disc.indexOf(t) >= 0 && (doNivel || incluida) ? '' : 'none';
                    });
                }
                classe.addEventListener('change', function () { actualizar(); filtrarDisciplinas(); });
                actualizar();
                filtro.addEventListener('input', filtrarDisciplinas);
                soNivel.addEventListener('change', filtrarDisciplinas);
                filtrarDisciplinas();
            })();
        </script>
    @endpush
</x-app-layout>
