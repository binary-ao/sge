{{-- Modal de criação/edição de nível de ensino --}}
<x-modal-form id="modalNivel" titulo-criar="Novo nível de ensino" titulo-editar="Editar nível de ensino" :accao-criar="route('cadastros.niveis.store')">
    <div class="mb-3">
        <label class="form-label obrigatorio" for="nivelNome">Nome</label>
        <input id="nivelNome" name="nome" maxlength="120" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" placeholder="Ensino Primário">
        <x-campo-erro campo="nome" />
    </div>
    <div class="row g-3 mb-3">
        <div class="col-8">
            <label class="form-label obrigatorio" for="nivelCodigo">Código</label>
            <input id="nivelCodigo" name="codigo" maxlength="30" class="form-control text-uppercase @error('codigo') is-invalid @enderror" value="{{ old('codigo') }}" placeholder="PRIM">
            <x-campo-erro campo="codigo" />
        </div>
        <div class="col-4">
            <label class="form-label" for="nivelOrdem">Ordem</label>
            <input type="number" id="nivelOrdem" name="ordem" min="0" max="999" class="form-control" value="{{ old('ordem', 0) }}" data-padrao="0">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="nivelDescricao">Descrição</label>
        <textarea id="nivelDescricao" name="descricao" rows="3" maxlength="1000" class="form-control">{{ old('descricao') }}</textarea>
    </div>
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="exige_curso" value="1" id="nivelCurso" @checked(old('exige_curso'))>
        <label class="form-check-label small" for="nivelCurso">Exige curso na matrícula e na turma (ex.: II Ciclo do Ensino Secundário, Técnico-Profissional)</label>
    </div>
</x-modal-form>
