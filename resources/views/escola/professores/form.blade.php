@php $editar = $professor->exists; @endphp
<x-app-layout :titulo="$editar ? 'Editar professor' : 'Novo professor'">
    <x-cabecalho :titulo="$editar ? 'Editar professor' : 'Novo professor'"
        :migalhas="['Gestão Académica' => null, 'Professores' => route('professores.index'), ($editar ? $professor->nome : 'Novo') => null]" />

    <form method="POST" action="{{ $editar ? route('professores.update', $professor) : route('professores.store') }}" novalidate>
        @csrf
        @if ($editar) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card h-100"><div class="card-body">
                    <div class="seccao-form">Identificação</div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label obrigatorio" for="nome">Nome completo</label>
                            <input id="nome" name="nome" value="{{ old('nome', $professor->nome) }}" maxlength="150" class="form-control @error('nome') is-invalid @enderror">
                            <x-campo-erro campo="nome" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio" for="genero">Género</label>
                            <select id="genero" name="genero" class="form-select">
                                <option value="M" @selected(old('genero', $professor->genero) === 'M')>Masculino</option>
                                <option value="F" @selected(old('genero', $professor->genero) === 'F')>Feminino</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="data_nascimento">Data de nascimento</label>
                            <input type="date" id="data_nascimento" name="data_nascimento" value="{{ old('data_nascimento', $professor->data_nascimento?->toDateString()) }}" class="form-control @error('data_nascimento') is-invalid @enderror">
                            <x-campo-erro campo="data_nascimento" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio" for="numero_identificacao">N.º de identificação (BI)</label>
                            <input id="numero_identificacao" name="numero_identificacao" value="{{ old('numero_identificacao', $professor->numero_identificacao) }}" maxlength="30" class="form-control text-uppercase @error('numero_identificacao') is-invalid @enderror">
                            <x-campo-erro campo="numero_identificacao" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="telefone">Telefone</label>
                            <input id="telefone" name="telefone" value="{{ old('telefone', $professor->telefone) }}" maxlength="30" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">E-mail</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $professor->email) }}" maxlength="150" class="form-control @error('email') is-invalid @enderror">
                            <x-campo-erro campo="email" />
                        </div>
                    </div>
                </div></div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100"><div class="card-body">
                    <div class="seccao-form">Dados profissionais</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label obrigatorio" for="habilitacoes">Habilitações académicas</label>
                            <select id="habilitacoes" name="habilitacoes" class="form-select @error('habilitacoes') is-invalid @enderror">
                                <option value="">Seleccione…</option>
                                @foreach (\App\Models\Professor::HABILITACOES as $v => $r)<option value="{{ $v }}" @selected(old('habilitacoes', $professor->habilitacoes) === $v)>{{ $r }}</option>@endforeach
                            </select>
                            <x-campo-erro campo="habilitacoes" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label obrigatorio" for="situacao_profissional">Situação profissional</label>
                            <select id="situacao_profissional" name="situacao_profissional" class="form-select @error('situacao_profissional') is-invalid @enderror">
                                <option value="">Seleccione…</option>
                                @foreach (\App\Models\Professor::SITUACOES as $v => $r)<option value="{{ $v }}" @selected(old('situacao_profissional', $professor->situacao_profissional) === $v)>{{ $r }}</option>@endforeach
                            </select>
                            <x-campo-erro campo="situacao_profissional" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="especialidade">Especialidade</label>
                            <input id="especialidade" name="especialidade" value="{{ old('especialidade', $professor->especialidade) }}" maxlength="150" class="form-control" placeholder="Ex.: Matemática / Física">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="data_admissao">Data de admissão</label>
                            <input type="date" id="data_admissao" name="data_admissao" value="{{ old('data_admissao', $professor->data_admissao?->toDateString()) }}" class="form-control @error('data_admissao') is-invalid @enderror">
                            <x-campo-erro campo="data_admissao" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="utilizador_id">Conta de acesso ao sistema</label>
                            <select id="utilizador_id" name="utilizador_id" class="form-select @error('utilizador_id') is-invalid @enderror">
                                <option value="">Sem conta associada</option>
                                @foreach ($contas as $c)<option value="{{ $c->id }}" @selected(old('utilizador_id', $professor->utilizador_id) == $c->id)>{{ $c->nome }} ({{ $c->email }})</option>@endforeach
                            </select>
                            <x-campo-erro campo="utilizador_id" />
                            <div class="form-text">Liga esta ficha a uma conta com perfil Professor desta escola, para que possa lançar avaliações e faltas nas turmas atribuídas.</div>
                        </div>
                    </div>
                </div></div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ $editar ? route('professores.show', $professor) : route('professores.index') }}" class="btn btn-light">Cancelar</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $editar ? 'Guardar alterações' : 'Registar professor' }}</button>
        </div>
    </form>
</x-app-layout>
