@php $editar = $aluno->exists; @endphp
<x-app-layout :titulo="$editar ? 'Editar aluno' : 'Cadastrar aluno'">
    <x-cabecalho :titulo="$editar ? 'Editar aluno' : 'Cadastrar aluno'"
        :subtitulo="$editar ? 'Processo n.º '.$aluno->numero_processo : 'O número de processo é atribuído automaticamente.'"
        :migalhas="['Gestão Académica' => null, 'Alunos' => route('alunos.index'), ($editar ? $aluno->nome : 'Novo') => null]" />

    <form method="POST" action="{{ $editar ? route('alunos.update', $aluno) : route('alunos.store') }}" novalidate>
        @csrf
        @if ($editar) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card h-100"><div class="card-body">
                    <div class="seccao-form">Dados pessoais</div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label obrigatorio" for="nome">Nome completo</label>
                            <input id="nome" name="nome" value="{{ old('nome', $aluno->nome) }}" maxlength="150" class="form-control @error('nome') is-invalid @enderror" autofocus>
                            <x-campo-erro campo="nome" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio" for="genero">Género</label>
                            <select id="genero" name="genero" class="form-select @error('genero') is-invalid @enderror">
                                <option value="">Seleccione…</option>
                                <option value="M" @selected(old('genero', $aluno->genero) === 'M')>Masculino</option>
                                <option value="F" @selected(old('genero', $aluno->genero) === 'F')>Feminino</option>
                            </select>
                            <x-campo-erro campo="genero" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio" for="data_nascimento">Data de nascimento</label>
                            <input type="date" id="data_nascimento" name="data_nascimento" value="{{ old('data_nascimento', $aluno->data_nascimento?->toDateString()) }}" class="form-control @error('data_nascimento') is-invalid @enderror">
                            <x-campo-erro campo="data_nascimento" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="naturalidade">Naturalidade</label>
                            <input id="naturalidade" name="naturalidade" value="{{ old('naturalidade', $aluno->naturalidade) }}" maxlength="120" class="form-control" placeholder="Município / Província">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio" for="nacionalidade">Nacionalidade</label>
                            <input id="nacionalidade" name="nacionalidade" value="{{ old('nacionalidade', $aluno->nacionalidade) }}" maxlength="60" class="form-control @error('nacionalidade') is-invalid @enderror">
                            <x-campo-erro campo="nacionalidade" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tipo_documento">Documento</label>
                            <select id="tipo_documento" name="tipo_documento" class="form-select @error('tipo_documento') is-invalid @enderror">
                                <option value="">Nenhum (quando não aplicável)</option>
                                @foreach (\App\Models\Aluno::TIPOS_DOCUMENTO as $v => $r)<option value="{{ $v }}" @selected(old('tipo_documento', $aluno->tipo_documento) === $v)>{{ $r }}</option>@endforeach
                            </select>
                            <x-campo-erro campo="tipo_documento" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="numero_identificacao">N.º do documento</label>
                            <input id="numero_identificacao" name="numero_identificacao" value="{{ old('numero_identificacao', $aluno->numero_identificacao) }}" maxlength="30" class="form-control text-uppercase @error('numero_identificacao') is-invalid @enderror">
                            <x-campo-erro campo="numero_identificacao" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="endereco">Endereço</label>
                            <input id="endereco" name="endereco" value="{{ old('endereco', $aluno->endereco) }}" maxlength="255" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="telefone">Telefone do aluno</label>
                            <input id="telefone" name="telefone" value="{{ old('telefone', $aluno->telefone) }}" maxlength="30" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">E-mail do aluno</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $aluno->email) }}" maxlength="150" class="form-control @error('email') is-invalid @enderror">
                            <x-campo-erro campo="email" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="necessidades_especiais">Necessidades educativas especiais</label>
                            <textarea id="necessidades_especiais" name="necessidades_especiais" rows="2" maxlength="1000" class="form-control">{{ old('necessidades_especiais', $aluno->necessidades_especiais) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="observacoes">Observações</label>
                            <textarea id="observacoes" name="observacoes" rows="2" maxlength="1000" class="form-control">{{ old('observacoes', $aluno->observacoes) }}</textarea>
                        </div>
                    </div>
                </div></div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100"><div class="card-body">
                    <div class="seccao-form">Encarregado de educação</div>
                    <p class="small text-secondary mt-n2">Obrigatório para alunos menores de 18 anos.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="enc_nome">Nome</label>
                            <input id="enc_nome" name="encarregado[nome]" value="{{ old('encarregado.nome', $encarregado->nome) }}" maxlength="150" class="form-control @error('encarregado.nome') is-invalid @enderror">
                            <x-campo-erro campo="encarregado.nome" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="enc_parentesco">Parentesco</label>
                            <select id="enc_parentesco" name="encarregado[parentesco]" class="form-select">
                                <option value="">—</option>
                                @foreach (['Pai', 'Mãe', 'Avô', 'Avó', 'Tio(a)', 'Irmão(ã)', 'Tutor(a)', 'Outro'] as $p)<option @selected(old('encarregado.parentesco', $encarregado->parentesco) === $p)>{{ $p }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="enc_bi">N.º de identificação</label>
                            <input id="enc_bi" name="encarregado[numero_identificacao]" value="{{ old('encarregado.numero_identificacao', $encarregado->numero_identificacao) }}" maxlength="30" class="form-control text-uppercase">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="enc_tel">Telefone</label>
                            <input id="enc_tel" name="encarregado[telefone]" value="{{ old('encarregado.telefone', $encarregado->telefone) }}" maxlength="30" class="form-control @error('encarregado.telefone') is-invalid @enderror">
                            <x-campo-erro campo="encarregado.telefone" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="enc_tel2">Telefone alternativo</label>
                            <input id="enc_tel2" name="encarregado[telefone_alternativo]" value="{{ old('encarregado.telefone_alternativo', $encarregado->telefone_alternativo) }}" maxlength="30" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="enc_email">E-mail</label>
                            <input type="email" id="enc_email" name="encarregado[email]" value="{{ old('encarregado.email', $encarregado->email) }}" maxlength="150" class="form-control @error('encarregado.email') is-invalid @enderror">
                            <x-campo-erro campo="encarregado.email" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="enc_prof">Profissão</label>
                            <input id="enc_prof" name="encarregado[profissao]" value="{{ old('encarregado.profissao', $encarregado->profissao) }}" maxlength="100" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="enc_end">Endereço</label>
                            <input id="enc_end" name="encarregado[endereco]" value="{{ old('encarregado.endereco', $encarregado->endereco) }}" maxlength="255" class="form-control">
                        </div>
                    </div>
                </div></div>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
            <a href="{{ $editar ? route('alunos.show', $aluno) : route('alunos.index') }}" class="btn btn-light">Cancelar</a>
            @if ($editar)
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar alterações</button>
            @else
                <button class="btn btn-outline-primary" name="accao" value="guardar"><i class="bi bi-check2 me-1"></i>Guardar</button>
                @can('matriculas.gerir')
                    <button class="btn btn-primary" name="accao" value="matricular"><i class="bi bi-journal-plus me-1"></i>Guardar e matricular</button>
                @endcan
            @endif
        </div>
    </form>
</x-app-layout>
