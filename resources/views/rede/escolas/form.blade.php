@php $editar = $escola->exists; @endphp
<x-app-layout :titulo="$editar ? 'Editar escola' : 'Nova escola'">
    <x-cabecalho :titulo="$editar ? 'Editar escola' : 'Cadastrar escola'"
        subtitulo="A tipologia institucional e os níveis de ensino ministrados são classificações independentes."
        :migalhas="['Cadastros e Configurações' => null, 'Escolas' => route('escolas.index'), ($editar ? $escola->nome : 'Nova') => null]" />

    <form method="POST" action="{{ $editar ? route('escolas.update', $escola) : route('escolas.store') }}" novalidate>
        @csrf
        @if ($editar) @method('PUT') @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="seccao-form">Identificação</div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label obrigatorio" for="nome">Nome oficial</label>
                        <input id="nome" name="nome" value="{{ old('nome', $escola->nome) }}" maxlength="200" class="form-control @error('nome') is-invalid @enderror" required>
                        <x-campo-erro campo="nome" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label obrigatorio" for="codigo">Código da instituição</label>
                        <input id="codigo" name="codigo" value="{{ old('codigo', $escola->codigo) }}" maxlength="30" class="form-control text-uppercase @error('codigo') is-invalid @enderror" placeholder="BGO-DAN-0001" required>
                        <x-campo-erro campo="codigo" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label obrigatorio" for="municipio_id">Município</label>
                        <select id="municipio_id" name="municipio_id" class="form-select @error('municipio_id') is-invalid @enderror" required>
                            <option value="">Seleccione…</option>
                            @foreach ($municipios as $m)
                                <option value="{{ $m->id }}" @selected(old('municipio_id', $escola->municipio_id) == $m->id)>{{ $m->nome }}</option>
                            @endforeach
                        </select>
                        <x-campo-erro campo="municipio_id" />
                    </div>
                    <div class="col-md-5">
                        <label class="form-label obrigatorio" for="tipologia_id">Tipologia institucional</label>
                        <select id="tipologia_id" name="tipologia_id" class="form-select @error('tipologia_id') is-invalid @enderror" required>
                            <option value="">Seleccione…</option>
                            @foreach ($tipologias as $t)
                                <option value="{{ $t->id }}" @selected(old('tipologia_id', $escola->tipologia_id) == $t->id)>{{ $t->nome }}</option>
                            @endforeach
                        </select>
                        <x-campo-erro campo="tipologia_id" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label obrigatorio" for="natureza">Natureza</label>
                        <select id="natureza" name="natureza" class="form-select @error('natureza') is-invalid @enderror" required>
                            @foreach (\App\Models\Escola::NATUREZAS as $v => $r)
                                <option value="{{ $v }}" @selected(old('natureza', $escola->natureza) === $v)>{{ $r }}</option>
                            @endforeach
                        </select>
                        <x-campo-erro campo="natureza" />
                    </div>
                </div>

                <div class="seccao-form mt-4">Níveis de ensino ministrados <span class="text-danger">*</span></div>
                <p class="small text-secondary mt-n2">Seleccione todos os níveis que a instituição ministra.</p>
                <div class="row g-2">
                    @foreach ($niveis as $n)
                        <div class="col-md-6 col-lg-4">
                            <label class="form-check opcao-check">
                                <input class="form-check-input" type="checkbox" name="niveis[]" value="{{ $n->id }}" @checked(in_array($n->id, $niveisSeleccionados, true))>
                                <span class="form-check-label">{{ $n->nome }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <x-campo-erro campo="niveis" />
                <x-campo-erro campo="niveis.*" />
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="seccao-form">Localização</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="comuna">Comuna</label>
                                <input id="comuna" name="comuna" value="{{ old('comuna', $escola->comuna) }}" maxlength="100" class="form-control @error('comuna') is-invalid @enderror">
                                <x-campo-erro campo="comuna" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="bairro">Bairro / Localidade</label>
                                <input id="bairro" name="bairro" value="{{ old('bairro', $escola->bairro) }}" maxlength="150" class="form-control @error('bairro') is-invalid @enderror">
                                <x-campo-erro campo="bairro" />
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="endereco">Endereço</label>
                                <input id="endereco" name="endereco" value="{{ old('endereco', $escola->endereco) }}" maxlength="255" class="form-control @error('endereco') is-invalid @enderror">
                                <x-campo-erro campo="endereco" />
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="latitude">Latitude</label>
                                <input id="latitude" name="latitude" value="{{ old('latitude', $escola->latitude) }}" class="form-control @error('latitude') is-invalid @enderror" placeholder="-8.5785" inputmode="decimal">
                                <x-campo-erro campo="latitude" />
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="longitude">Longitude</label>
                                <input id="longitude" name="longitude" value="{{ old('longitude', $escola->longitude) }}" class="form-control @error('longitude') is-invalid @enderror" placeholder="13.6643" inputmode="decimal">
                                <x-campo-erro campo="longitude" />
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="hidden" name="coordenadas_demonstrativas" value="0">
                                    <input class="form-check-input" type="checkbox" id="coordenadas_demonstrativas" name="coordenadas_demonstrativas" value="1" @checked(old('coordenadas_demonstrativas', $escola->coordenadas_demonstrativas))>
                                    <label class="form-check-label small" for="coordenadas_demonstrativas">Coordenadas demonstrativas (não confirmadas no terreno)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="seccao-form">Contactos e direcção</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="telefone">Telefone</label>
                                <input id="telefone" name="telefone" value="{{ old('telefone', $escola->telefone) }}" maxlength="30" class="form-control @error('telefone') is-invalid @enderror">
                                <x-campo-erro campo="telefone" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="telefone_alternativo">Telefone alternativo</label>
                                <input id="telefone_alternativo" name="telefone_alternativo" value="{{ old('telefone_alternativo', $escola->telefone_alternativo) }}" maxlength="30" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="email">E-mail</label>
                                <input type="email" id="email" name="email" value="{{ old('email', $escola->email) }}" maxlength="150" class="form-control @error('email') is-invalid @enderror">
                                <x-campo-erro campo="email" />
                            </div>
                            <div class="col-md-7">
                                <label class="form-label" for="director_nome">Director(a)</label>
                                <input id="director_nome" name="director_nome" value="{{ old('director_nome', $escola->director_nome) }}" maxlength="150" class="form-control @error('director_nome') is-invalid @enderror">
                                <x-campo-erro campo="director_nome" />
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="director_telefone">Telefone do director(a)</label>
                                <input id="director_telefone" name="director_telefone" value="{{ old('director_telefone', $escola->director_telefone) }}" maxlength="30" class="form-control">
                            </div>
                        </div>
                        <div class="seccao-form mt-4">Funcionamento</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label obrigatorio" for="estado_funcionamento">Estado</label>
                                <select id="estado_funcionamento" name="estado_funcionamento" class="form-select @error('estado_funcionamento') is-invalid @enderror">
                                    @foreach (\App\Models\Escola::ESTADOS as $v => $r)
                                        <option value="{{ $v }}" @selected(old('estado_funcionamento', $escola->estado_funcionamento) === $v)>{{ $r }}</option>
                                    @endforeach
                                </select>
                                <x-campo-erro campo="estado_funcionamento" />
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label" for="ano_inicio_actividade">Início actividade</label>
                                <input type="number" id="ano_inicio_actividade" name="ano_inicio_actividade" value="{{ old('ano_inicio_actividade', $escola->ano_inicio_actividade) }}" min="1800" max="{{ date('Y') }}" class="form-control @error('ano_inicio_actividade') is-invalid @enderror">
                                <x-campo-erro campo="ano_inicio_actividade" />
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label" for="numero_salas">N.º de salas</label>
                                <input type="number" id="numero_salas" name="numero_salas" value="{{ old('numero_salas', $escola->numero_salas) }}" min="0" max="500" class="form-control @error('numero_salas') is-invalid @enderror">
                                <x-campo-erro campo="numero_salas" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ $editar ? route('escolas.show', $escola) : route('escolas.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary" data-a-carregar="A guardar…"><i class="bi bi-check2 me-1"></i>{{ $editar ? 'Guardar alterações' : 'Registar escola' }}</button>
        </div>
    </form>
</x-app-layout>
