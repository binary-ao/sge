{{-- Modal de criação/edição de disciplina. Requer: $niveis, $escolaActiva, $controlador --}}
<x-modal-form id="modalDisciplina" titulo-criar="Nova disciplina" titulo-editar="Editar disciplina" :accao-criar="route('cadastros.disciplinas.store')" tamanho="lg">
    @include('academico._ambito-registo', ['id' => 'Disciplina'])
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label obrigatorio" for="discNome">Nome</label>
            <input id="discNome" name="nome" maxlength="150" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}">
            <x-campo-erro campo="nome" />
        </div>
        <div class="col-md-4">
            <label class="form-label obrigatorio" for="discSigla">Código</label>
            <input id="discSigla" name="sigla" maxlength="15" class="form-control text-uppercase @error('sigla') is-invalid @enderror" value="{{ old('sigla') }}" placeholder="MAT">
            <x-campo-erro campo="sigla" />
        </div>
        <div class="col-md-8">
            <label class="form-label obrigatorio" for="discComponente">Componente de formação</label>
            <select id="discComponente" name="componente" class="form-select @error('componente') is-invalid @enderror">
                @foreach (\App\Models\Disciplina::COMPONENTES as $v => $r)<option value="{{ $v }}" @selected(old('componente') === $v)>{{ $r }}</option>@endforeach
            </select>
            <x-campo-erro campo="componente" />
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="nuclear" value="1" id="discNuclear" @checked(old('nuclear'))>
                <label class="form-check-label small" for="discNuclear">Disciplina nuclear</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label obrigatorio">Níveis de ensino em que é leccionada</label>
            <div class="row row-cols-1 row-cols-md-2 g-1 border rounded p-2 @error('niveis') border-danger @enderror">
                @php $niveisAntigos = collect(old('niveis', []))->map(fn ($i) => (int) $i)->all(); @endphp
                @foreach ($niveis->where('activo', true) as $n)
                    <div class="col">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="niveis[]" value="{{ $n->id }}" id="discNivel{{ $n->id }}" @checked(in_array($n->id, $niveisAntigos, true))>
                            <label class="form-check-label small" for="discNivel{{ $n->id }}">{{ $n->nome }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('niveis')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @error('niveis.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>
    <p class="small text-secondary mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>As classes em que a disciplina é aplicada, a carga horária e a indicação de obrigatória ou opcional
        definem-se no <strong>plano curricular</strong> de cada classe (Classes e Ciclos › ficha da classe).
        Disciplina nuclear (ex.: Língua Portuguesa, Matemática): restrições especiais nas regras de transição.
    </p>
</x-modal-form>
