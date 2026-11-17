@php $editar = $municipio->exists; @endphp
<x-app-layout :titulo="$editar ? 'Editar município' : 'Novo município'">
    <x-cabecalho :titulo="$editar ? 'Editar município' : 'Novo município'" subtitulo="Divisão administrativa da província"
        :migalhas="['Cadastros e Configurações' => null, 'Municípios' => route('municipios.index'), ($editar ? $municipio->nome : 'Novo') => null]" />

    <div class="card" style="max-width: 760px">
        <div class="card-body">
            <form method="POST" action="{{ $editar ? route('municipios.update', $municipio) : route('municipios.store') }}" novalidate>
                @csrf
                @if ($editar) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label obrigatorio" for="provincia_id">Província</label>
                        <select id="provincia_id" name="provincia_id" class="form-select @error('provincia_id') is-invalid @enderror">
                            @foreach ($provincias as $p)
                                <option value="{{ $p->id }}" @selected(old('provincia_id', $municipio->provincia_id) == $p->id)>{{ $p->nome }}</option>
                            @endforeach
                        </select>
                        <x-campo-erro campo="provincia_id" />
                    </div>
                    <div class="col-md-8">
                        <label class="form-label obrigatorio" for="nome">Nome</label>
                        <input id="nome" name="nome" value="{{ old('nome', $municipio->nome) }}" maxlength="100" class="form-control @error('nome') is-invalid @enderror" required>
                        <x-campo-erro campo="nome" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label obrigatorio" for="codigo">Código</label>
                        <input id="codigo" name="codigo" value="{{ old('codigo', $municipio->codigo) }}" maxlength="20" class="form-control text-uppercase @error('codigo') is-invalid @enderror" placeholder="BGO-XXX" required>
                        <x-campo-erro campo="codigo" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sede">Sede municipal</label>
                        <input id="sede" name="sede" value="{{ old('sede', $municipio->sede) }}" maxlength="100" class="form-control @error('sede') is-invalid @enderror">
                        <x-campo-erro campo="sede" />
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="latitude">Latitude</label>
                        <input id="latitude" name="latitude" value="{{ old('latitude', $municipio->latitude) }}" class="form-control @error('latitude') is-invalid @enderror" inputmode="decimal">
                        <x-campo-erro campo="latitude" />
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="longitude">Longitude</label>
                        <input id="longitude" name="longitude" value="{{ old('longitude', $municipio->longitude) }}" class="form-control @error('longitude') is-invalid @enderror" inputmode="decimal">
                        <x-campo-erro campo="longitude" />
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" @checked(old('activo', $municipio->activo))>
                            <label class="form-check-label" for="activo">Município activo</label>
                        </div>
                        <x-campo-erro campo="activo" />
                        @if ($editar && $municipio->activo)
                            <div class="form-text">Só é possível desactivar um município sem escolas activas.</div>
                        @endif
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('municipios.index') }}" class="btn btn-light">Cancelar</a>
                    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
