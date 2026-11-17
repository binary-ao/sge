{{-- Modais de classe e de ciclo. Requer: $niveis, $ciclos --}}
<x-modal-form id="modalClasse" titulo-criar="Nova classe" titulo-editar="Editar classe" :accao-criar="route('cadastros.classes.store')">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label obrigatorio" for="classeNivel">Nível de ensino</label>
            <select name="nivel_ensino_id" class="form-select @error('nivel_ensino_id') is-invalid @enderror" id="classeNivel">
                @foreach ($niveis as $n)
                    <option value="{{ $n->id }}" @selected(old('nivel_ensino_id') == $n->id) @disabled(! $n->activo)>{{ $n->nome }}{{ $n->activo ? '' : ' (inactivo)' }}</option>
                @endforeach
            </select>
            <x-campo-erro campo="nivel_ensino_id" />
        </div>
        <div class="col-12">
            <label class="form-label" for="classeCiclo">Ciclo</label>
            <select name="ciclo_id" class="form-select @error('ciclo_id') is-invalid @enderror" id="classeCiclo">
                <option value="">— Sem ciclo —</option>
                @foreach ($ciclos as $c)<option value="{{ $c->id }}" data-nivel="{{ $c->nivel_ensino_id }}" @selected(old('ciclo_id') == $c->id)>{{ $c->nome }}</option>@endforeach
            </select>
            <x-campo-erro campo="ciclo_id" />
        </div>
        <div class="col-md-6">
            <label class="form-label obrigatorio" for="classeNome">Nome</label>
            <input id="classeNome" name="nome" maxlength="60" class="form-control @error('nome') is-invalid @enderror" placeholder="7.ª Classe" value="{{ old('nome') }}">
            <x-campo-erro campo="nome" />
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label" for="classeNumero">Número</label>
            <input type="number" id="classeNumero" name="numero" min="0" max="20" class="form-control @error('numero') is-invalid @enderror" value="{{ old('numero') }}">
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label" for="classeOrdem">Ordem</label>
            <input type="number" id="classeOrdem" name="ordem" min="0" max="999" class="form-control" value="{{ old('ordem') }}">
        </div>
        <div class="col-12">
            <label class="form-label obrigatorio" for="classeRegime">Regime de avaliação</label>
            <select id="classeRegime" name="regime" class="form-select @error('regime') is-invalid @enderror">
                @foreach (\App\Models\Classe::REGIMES as $v => $r)<option value="{{ $v }}" @selected(old('regime') === $v)>{{ $r }}</option>@endforeach
            </select>
            <div class="form-text">Determina as fórmulas e regras aplicadas pelo motor de avaliação.</div>
        </div>
    </div>
</x-modal-form>

<x-modal-form id="modalCiclo" titulo-criar="Novo ciclo" titulo-editar="Editar ciclo" :accao-criar="route('cadastros.ciclos.store')">
    <div class="mb-3">
        <label class="form-label obrigatorio" for="cicloNivel">Nível de ensino</label>
        <select id="cicloNivel" name="nivel_ensino_id" class="form-select @error('nivel_ensino_id') is-invalid @enderror">
            @foreach ($niveis as $n)
                <option value="{{ $n->id }}" @selected(old('nivel_ensino_id') == $n->id) @disabled(! $n->activo)>{{ $n->nome }}</option>
            @endforeach
        </select>
        <x-campo-erro campo="nivel_ensino_id" />
    </div>
    <div class="row g-3">
        <div class="col-9">
            <label class="form-label obrigatorio" for="cicloNome">Nome</label>
            <input id="cicloNome" name="nome" maxlength="100" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" placeholder="1.º Ciclo">
            <x-campo-erro campo="nome" />
        </div>
        <div class="col-3"><label class="form-label" for="cicloOrdem">Ordem</label><input type="number" id="cicloOrdem" name="ordem" min="0" max="99" class="form-control" value="{{ old('ordem') }}"></div>
    </div>
</x-modal-form>

@push('scripts')
    <script>
        // Mostrar apenas os ciclos do nível seleccionado.
        (function () {
            var nivel = document.getElementById('classeNivel'), ciclo = document.getElementById('classeCiclo');
            if (!nivel || !ciclo) { return; }
            function filtrar() {
                Array.prototype.forEach.call(ciclo.options, function (o) {
                    if (!o.dataset.nivel) { return; }
                    o.hidden = o.dataset.nivel !== nivel.value;
                    if (o.hidden && o.selected) { ciclo.value = ''; }
                });
            }
            nivel.addEventListener('change', filtrar);
            filtrar();
        })();
    </script>
@endpush
