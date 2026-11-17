{{-- Modal de criação/edição de ano lectivo --}}
<x-modal-form id="modalAno" titulo-criar="Novo ano lectivo" titulo-editar="Editar ano lectivo" :accao-criar="route('cadastros.anos.store')">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label obrigatorio" for="anoDesignacao">Designação</label>
            <input id="anoDesignacao" name="designacao" class="form-control @error('designacao') is-invalid @enderror" placeholder="2027/2028" value="{{ old('designacao') }}">
            <x-campo-erro campo="designacao" />
        </div>
        <div class="col-md-6">
            <label class="form-label obrigatorio" for="anoEstado">Estado</label>
            <select id="anoEstado" name="estado" class="form-select @error('estado') is-invalid @enderror">
                @foreach (\App\Models\AnoLectivo::ESTADOS as $v => $r)
                    <option value="{{ $v }}" @selected(old('estado', 'planeado') === $v)>{{ $r }}</option>
                @endforeach
            </select>
            <x-campo-erro campo="estado" />
        </div>
        <div class="col-6">
            <label class="form-label obrigatorio" for="anoInicio">Data de início</label>
            <input type="date" id="anoInicio" name="data_inicio" class="form-control @error('data_inicio') is-invalid @enderror" value="{{ old('data_inicio') }}">
            <x-campo-erro campo="data_inicio" />
        </div>
        <div class="col-6">
            <label class="form-label obrigatorio" for="anoFim">Data de fim</label>
            <input type="date" id="anoFim" name="data_fim" class="form-control @error('data_fim') is-invalid @enderror" value="{{ old('data_fim') }}">
            <x-campo-erro campo="data_fim" />
        </div>
    </div>
    <p class="small text-secondary mt-3 mb-0" data-so-criacao>Os três trimestres são criados automaticamente; as datas são definidas em <em>Períodos de Avaliação</em>.</p>
</x-modal-form>
