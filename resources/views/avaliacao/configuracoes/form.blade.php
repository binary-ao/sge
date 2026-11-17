@php $editar = $config->exists; @endphp
<x-app-layout :titulo="$editar ? 'Editar configuração' : 'Nova configuração de avaliação'">
    <x-cabecalho :titulo="$editar ? 'Editar: '.$config->nome : 'Nova configuração de avaliação'"
        :subtitulo="$editar ? 'Versão actual: '.$config->versao.'. Ao guardar será criada a versão '.($config->versao + 1).'.' : 'Defina componentes, fórmulas e critérios. Teste o cálculo antes de guardar.'"
        :migalhas="['Avaliação' => null, 'Configuração de Avaliações' => route('avaliacao.configuracoes.index'), ($editar ? $config->nome : 'Nova') => null]" />

    @if ($errors->any())
        <div class="alert alert-danger small">
            <strong>Não foi possível guardar:</strong>
            <ul class="mb-0 mt-1">@foreach ($errors->getMessages() as $campo => $msgs)<li><code>{{ $campo }}</code> — {{ $msgs[0] }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $editar ? route('avaliacao.configuracoes.update', $config) : route('avaliacao.configuracoes.store') }}" id="formConfig" novalidate>
        @csrf
        @if ($editar) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-xxl-8">
                {{-- 1. Identificação --}}
                <div class="card mb-3"><div class="card-body">
                    <div class="seccao-form">1. Identificação e âmbito</div>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label obrigatorio">Nome</label>
                            <input name="nome" value="{{ old('nome', $config->nome) }}" maxlength="150" class="form-control @error('nome') is-invalid @enderror">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label obrigatorio">Sistema de avaliação</label>
                            <select name="sistema_avaliacao_id" class="form-select">
                                @foreach ($sistemas as $s)<option value="{{ $s->id }}" @selected(old('sistema_avaliacao_id', $config->sistema_avaliacao_id) == $s->id)>{{ $s->nome }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" rows="2" maxlength="2000" class="form-control">{{ old('descricao', $config->descricao) }}</textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label obrigatorio">Ano lectivo de vigência</label>
                            <select name="ano_lectivo_id" class="form-select">
                                @foreach ($anos as $a)<option value="{{ $a->id }}" @selected(old('ano_lectivo_id', $config->ano_lectivo_id) == $a->id)>{{ $a->designacao }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio">Nível de ensino</label>
                            <select name="nivel_ensino_id" id="nivel" class="form-select">
                                @foreach ($niveis as $n)<option value="{{ $n->id }}" @selected(old('nivel_ensino_id', $config->nivel_ensino_id) == $n->id)>{{ $n->nome }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Ciclo</label>
                            <select name="ciclo_id" id="ciclo" class="form-select">
                                <option value="">—</option>
                                @foreach ($ciclos as $c)<option value="{{ $c->id }}" data-nivel="{{ $c->nivel_ensino_id }}" @selected(old('ciclo_id', $config->ciclo_id) == $c->id)>{{ $c->nome }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label obrigatorio">Classes abrangidas</label>
                            <div class="d-flex flex-wrap gap-2 border rounded p-2 @error('classes') border-danger @enderror" id="listaClasses">
                                @foreach ($classes as $c)
                                    <label class="form-check form-check-inline mb-0 small" data-nivel="{{ $c->nivel_ensino_id }}">
                                        <input class="form-check-input" type="checkbox" name="classes[]" value="{{ $c->id }}" @checked(in_array($c->id, $classesSeleccionadas, true))>
                                        {{ $c->nome }} <span class="text-secondary">({{ \App\Models\Classe::REGIMES[$c->regime] }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Disciplina específica</label>
                            <select name="disciplina_id" class="form-select">
                                <option value="">Todas as disciplinas</option>
                                @foreach ($disciplinas as $d)<option value="{{ $d->id }}" @selected(old('disciplina_id', $config->disciplina_id) == $d->id)>{{ $d->nome }}</option>@endforeach
                            </select>
                            <div class="form-text">Uma configuração de disciplina tem prioridade sobre a geral da classe.</div>
                        </div>
                        @unless ($editar)
                            <div class="col-md-5">
                                <label class="form-label obrigatorio">Estado inicial</label>
                                <select name="estado" class="form-select">
                                    <option value="rascunho" @selected(old('estado', $config->estado) === 'rascunho')>Rascunho (não aplicado)</option>
                                    <option value="activa" @selected(old('estado') === 'activa')>Activa</option>
                                </select>
                            </div>
                        @endunless
                    </div>
                </div></div>

                {{-- 2. Classificação --}}
                <div class="card mb-3"><div class="card-body">
                    <div class="seccao-form">2. Escala e classificação</div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label obrigatorio">Escala de classificação</label>
                            <select name="escala_id" id="escala" class="form-select">
                                @foreach ($escalas as $e)
                                    <option value="{{ $e->id }}" data-min="{{ $e->nota_minima }}" data-max="{{ $e->nota_maxima }}"
                                        data-intervalos="{{ $e->intervalos->map(fn ($i) => rtrim(rtrim(number_format($i->minimo, 2, ',', ''), '0'), ',').'–'.floor($i->maximo).' '.$i->rotulo)->join(' · ') }}"
                                        @selected(old('escala_id', $config->escala_id) == $e->id)>{{ $e->nome }}</option>
                                @endforeach
                            </select>
                            <div class="form-text" id="escalaIntervalos"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio">Tipo de classificação</label>
                            <select name="tipo_classificacao" class="form-select">
                                @foreach (\App\Models\ConfiguracaoAvaliacao::TIPOS as $v => $r)<option value="{{ $v }}" @selected(old('tipo_classificacao', $config->tipo_classificacao) === $v)>{{ $r }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label obrigatorio">Nota mínima de aprovação</label>
                            <input name="nota_minima_aprovacao" inputmode="decimal" value="{{ old('nota_minima_aprovacao', $config->nota_minima_aprovacao) }}" class="form-control @error('nota_minima_aprovacao') is-invalid @enderror">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio">Arredondamento</label>
                            <select name="arredondamento" class="form-select">
                                @foreach (\App\Models\ConfiguracaoAvaliacao::ARREDONDAMENTOS as $v => $r)<option value="{{ $v }}" @selected(old('arredondamento', $config->arredondamento) === $v)>{{ $r }}</option>@endforeach
                            </select>
                            <div class="form-text">Aplica-se às fórmulas marcadas "arredondar". O regulamento prevê arredondamento à unidade das classificações trimestrais e finais.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio">Variável da classificação trimestral</label>
                            <input name="variavel_trimestral" value="{{ old('variavel_trimestral', $config->variavel_trimestral) }}" maxlength="20" class="form-control text-uppercase @error('variavel_trimestral') is-invalid @enderror">
                            <div class="form-text">Ex.: MT (gera MT1, MT2, MT3)</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label obrigatorio">Variável da classificação final</label>
                            <input name="variavel_final" value="{{ old('variavel_final', $config->variavel_final) }}" maxlength="20" class="form-control text-uppercase @error('variavel_final') is-invalid @enderror">
                            <div class="form-text">Ex.: MFD ou MFED</div>
                        </div>
                    </div>
                </div></div>

                {{-- 3. Componentes --}}
                <div class="card mb-3">
                    <div class="card-header"><h2 class="titulo">3. Componentes de avaliação</h2>
                        <button type="button" class="btn btn-sm btn-light" id="addComponente"><i class="bi bi-plus-lg me-1"></i>Componente</button></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Código</th><th>Nome</th><th>Tipo</th><th>Momento</th><th title="Vazio = todos os trimestres">Trimestres</th><th>Itens</th><th>Mín.</th><th>Máx.</th><th></th></tr></thead>
                            <tbody id="componentes"></tbody>
                        </table>
                    </div>
                    <div class="card-body small text-secondary pt-2">
                        <strong>Lista</strong>: várias avaliações por trimestre (ex.: AC1, AC2…), usadas em MEDIA(AC). <strong>Valor único</strong>: uma nota (ex.: NPT).
                        Componentes <strong>trimestrais</strong> geram também variáveis por trimestre (NPT1, NPT2…); componentes de <strong>exame</strong> são lançados uma vez (ex.: EN).
                    </div>
                </div>

                {{-- 4. Fórmulas --}}
                <div class="card mb-3">
                    <div class="card-header"><h2 class="titulo">4. Fórmulas (calculadas por esta ordem)</h2>
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" id="modeloFormula" style="max-width:300px">
                                <option value="">Inserir modelo regulamentar…</option>
                                @foreach ($modelos as $m)<option value="{{ $m->id }}">{{ $m->referencia }} — {{ $m->codigo }} = {{ $m->expressao }}</option>@endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-light text-nowrap" id="addFormula"><i class="bi bi-plus-lg me-1"></i>Fórmula livre</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th style="width:60px"></th><th>Código</th><th>Nome</th><th style="min-width:260px">Expressão</th><th>Nível</th><th>Trimestres</th><th>Arred.</th><th></th></tr></thead>
                            <tbody id="formulas"></tbody>
                        </table>
                    </div>
                    <div class="card-body small text-secondary pt-2">
                        Operadores <code>+ - * / ^</code>, comparações, funções <code>MEDIA SOMA CONTAR MIN MAX ARRED TRUNCAR ABS SE E OU</code>; separador de argumentos <code>;</code>
                        e vírgula decimal (<code>0,6 * MT3</code>). Uma fórmula trimestral <code>X</code> fica disponível nas anuais como <code>X1</code>, <code>X2</code>, <code>X3</code>.
                        As fórmulas são interpretadas por um motor próprio e seguro — não é executado código.
                    </div>
                </div>

                {{-- 5. Regras --}}
                <div class="card mb-3">
                    <div class="card-header"><h2 class="titulo">5. Critérios de transição, retenção e exames</h2>
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" id="tipoRegra">
                                @foreach ($catalogoRegras as $tipo => $def)<option value="{{ $tipo }}">{{ $def['nome'] }}</option>@endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-light text-nowrap" id="addRegra"><i class="bi bi-plus-lg me-1"></i>Regra</button>
                        </div>
                    </div>
                    <div class="card-body" id="regras"></div>
                </div>

                @if ($editar)
                    <div class="card mb-3 border-warning"><div class="card-body">
                        <label class="form-label obrigatorio">Motivo da alteração</label>
                        <textarea name="motivo" rows="2" maxlength="1000" class="form-control @error('motivo') is-invalid @enderror" placeholder="Ex.: ajuste do peso do exame nacional conforme orientação da Direcção Provincial.">{{ old('motivo') }}</textarea>
                        <div class="form-text">Fica registado no histórico de versões, com o seu nome e a data. Os resultados já calculados não são alterados retroactivamente.</div>
                    </div></div>
                @endif

                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ $editar ? route('avaliacao.configuracoes.show', $config) : route('avaliacao.configuracoes.index') }}" class="btn btn-light">Cancelar</a>
                    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $editar ? 'Guardar nova versão' : 'Criar configuração' }}</button>
                </div>
            </div>

            {{-- Pré-visualização --}}
            <div class="col-xxl-4">
                <div class="card" style="position: sticky; top: 76px">
                    <div class="card-header"><h2 class="titulo"><i class="bi bi-play-circle me-1"></i>Pré-visualização do cálculo</h2></div>
                    <div class="card-body">
                        <p class="small text-secondary">Introduza valores de teste (listas separadas por <code>;</code>) e calcule com a configuração deste formulário, ainda não guardada.</p>
                        <div id="teste"></div>
                        <button type="button" class="btn btn-outline-primary w-100 mt-2" id="calcular"><i class="bi bi-calculator me-1"></i>Calcular</button>
                        <div id="resultadoTeste" class="mt-3 small"></div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                var dados = {
                    componentes: @json(array_values($componentes)),
                    formulas: @json(array_values($formulas)),
                    regras: @json(array_values($regras)),
                    catalogo: @json($catalogoRegras),
                    regimes: @json(\App\Models\Classe::REGIMES),
                    modelos: @json($modelos->keyBy('id')),
                    erros: @json($errors->getMessages()),
                    urlPrevia: @json(route('avaliacao.configuracoes.previsualizar'))
                };
                var form = document.getElementById('formConfig');
                var esc = function (s) { return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };
                var sel = function (nome, opcoes, valor) {
                    return '<select class="form-select form-select-sm" data-campo="' + nome + '">' + Object.keys(opcoes).map(function (k) {
                        return '<option value="' + k + '"' + (String(valor) === k ? ' selected' : '') + '>' + esc(opcoes[k]) + '</option>'; }).join('') + '</select>';
                };
                var inp = function (nome, valor, extra) { return '<input class="form-control form-control-sm" data-campo="' + nome + '" value="' + esc(valor) + '" ' + (extra || '') + '>'; };

                // ---------- Componentes
                function linhaComponente(c) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + inp('codigo', c.codigo, 'style="width:80px" maxlength="20"') + '</td>'
                        + '<td>' + inp('nome', c.nome, 'maxlength="150"') + '</td>'
                        + '<td>' + sel('tipo', {lista: 'Lista', valor: 'Valor único'}, c.tipo) + '</td>'
                        + '<td>' + sel('momento', {trimestral: 'Trimestral', exame: 'Exame'}, c.momento) + '</td>'
                        + '<td>' + inp('trimestres', c.trimestres, 'style="width:70px" placeholder="todos"') + '</td>'
                        + '<td>' + inp('itens_previstos', c.itens_previstos || 1, 'style="width:55px" type="number" min="1" max="10"') + '</td>'
                        + '<td>' + inp('nota_minima', c.nota_minima, 'style="width:60px"') + '</td>'
                        + '<td>' + inp('nota_maxima', c.nota_maxima, 'style="width:60px"') + '</td>'
                        + '<td><button type="button" class="btn btn-light btn-icon" data-remover title="Remover"><i class="bi bi-trash text-danger"></i></button></td>';
                    document.getElementById('componentes').appendChild(tr);
                }
                // ---------- Fórmulas
                function linhaFormula(f) {
                    var tr = document.createElement('tr');
                    tr.dataset.modelo = f.modelo_formula_id || '';
                    tr.innerHTML = '<td class="text-nowrap"><button type="button" class="btn btn-light btn-icon" data-subir title="Subir"><i class="bi bi-arrow-up"></i></button>'
                        + '<button type="button" class="btn btn-light btn-icon" data-descer title="Descer"><i class="bi bi-arrow-down"></i></button></td>'
                        + '<td>' + inp('codigo', f.codigo, 'style="width:80px" maxlength="20"') + '</td>'
                        + '<td>' + inp('nome', f.nome, 'maxlength="150"') + (f.modelo_formula_id ? '<div class="small text-secondary">Modelo do Anexo III</div>' : '') + '</td>'
                        + '<td>' + inp('expressao', f.expressao, 'maxlength="500" style="font-family:monospace"') + '</td>'
                        + '<td>' + sel('nivel', {trimestral: 'Trimestral', anual: 'Anual'}, f.nivel) + '</td>'
                        + '<td>' + inp('trimestres', f.trimestres, 'style="width:70px" placeholder="todos"') + '</td>'
                        + '<td class="text-center"><input type="checkbox" class="form-check-input" data-campo="arredondar" value="1"' + (f.arredondar ? ' checked' : '') + '></td>'
                        + '<td><input type="hidden" data-campo="modelo_formula_id" value="' + esc(f.modelo_formula_id || '') + '">'
                        + '<button type="button" class="btn btn-light btn-icon" data-remover title="Remover"><i class="bi bi-trash text-danger"></i></button></td>';
                    document.getElementById('formulas').appendChild(tr);
                }
                // ---------- Regras
                function blocoRegra(r) {
                    var def = dados.catalogo[r.tipo]; if (!def) { return; }
                    var p = r.parametros || {};
                    var div = document.createElement('div');
                    div.className = 'border rounded p-3 mb-2';
                    div.dataset.tipo = r.tipo;
                    var html = '<div class="d-flex justify-content-between align-items-start gap-2"><div><strong class="small">' + esc(def.nome) + '</strong>'
                        + '<div class="small text-secondary">' + esc(def.descricao) + '</div></div><div class="d-flex align-items-center gap-2">'
                        + '<label class="form-check form-switch small mb-0"><input type="checkbox" class="form-check-input" data-campo="activo" value="1"' + (r.activo !== false ? ' checked' : '') + '> Activa</label>'
                        + '<button type="button" class="btn btn-light btn-icon" data-remover><i class="bi bi-trash text-danger"></i></button></div></div>'
                        + '<input type="hidden" data-campo="tipo" value="' + esc(r.tipo) + '"><div class="row g-2 mt-1">';
                    Object.keys(def.parametros).forEach(function (k) {
                        var d = def.parametros[k], v = p[k] !== undefined ? p[k] : d.padrao;
                        html += '<div class="col-md-' + (d.tipo === 'regimes' || d.tipo === 'texto' ? '12' : '4') + '"><label class="form-label small mb-1">' + esc(d.rotulo) + '</label>';
                        if (d.tipo === 'booleano') {
                            html += '<div class="form-check"><input type="checkbox" class="form-check-input" data-param="' + k + '" value="1"' + (v ? ' checked' : '') + '></div>';
                        } else if (d.tipo === 'regimes') {
                            html += '<div class="d-flex flex-wrap gap-3">' + Object.keys(dados.regimes).map(function (rg) {
                                return '<label class="form-check small mb-0"><input type="checkbox" class="form-check-input" data-param="' + k + '" data-multiplo value="' + rg + '"'
                                    + ((v || []).indexOf(rg) >= 0 ? ' checked' : '') + '> ' + esc(dados.regimes[rg]) + '</label>'; }).join('') + '</div>';
                        } else {
                            html += '<input class="form-control form-control-sm" data-param="' + k + '" value="' + esc(v) + '">';
                        }
                        html += '</div>';
                    });
                    div.innerHTML = html + '</div>';
                    document.getElementById('regras').appendChild(div);
                }

                // Atribui os nomes dos campos por ordem (índices contíguos, iguais aos das mensagens de erro).
                function renomear() {
                    document.querySelectorAll('#componentes tr').forEach(function (tr, i) {
                        tr.querySelectorAll('[data-campo]').forEach(function (el) { el.name = 'componentes[' + i + '][' + el.dataset.campo + ']'; });
                    });
                    document.querySelectorAll('#formulas tr').forEach(function (tr, i) {
                        tr.querySelectorAll('[data-campo]').forEach(function (el) { el.name = 'formulas[' + i + '][' + el.dataset.campo + ']'; });
                    });
                    document.querySelectorAll('#regras > div').forEach(function (div, i) {
                        div.querySelectorAll('[data-campo]').forEach(function (el) { el.name = 'regras[' + i + '][' + el.dataset.campo + ']'; });
                        div.querySelectorAll('[data-param]').forEach(function (el) {
                            el.name = 'regras[' + i + '][parametros][' + el.dataset.param + ']' + (el.dataset.multiplo !== undefined ? '[]' : '');
                        });
                    });
                }
                function marcarErros() {
                    renomear();
                    Object.keys(dados.erros).forEach(function (chave) {
                        var partes = chave.split('.'), nome = partes[0] + partes.slice(1).map(function (p) { return '[' + p + ']'; }).join('');
                        var el = form.querySelector('[name="' + nome + '"]');
                        if (el) {
                            el.classList.add('is-invalid');
                            var fb = document.createElement('div'); fb.className = 'invalid-feedback'; fb.textContent = dados.erros[chave][0];
                            el.parentNode.appendChild(fb);
                        }
                    });
                }

                dados.componentes.forEach(linhaComponente);
                dados.formulas.forEach(linhaFormula);
                dados.regras.forEach(blocoRegra);
                marcarErros();

                document.getElementById('addComponente').addEventListener('click', function () {
                    linhaComponente({codigo: '', nome: '', tipo: 'valor', momento: 'trimestral', itens_previstos: 1, nota_minima: 0, nota_maxima: document.getElementById('escala').selectedOptions[0].dataset.max});
                    renomear(); desenharTeste();
                });
                document.getElementById('addFormula').addEventListener('click', function () {
                    linhaFormula({codigo: '', nome: '', expressao: '', nivel: 'anual', arredondar: false}); renomear();
                });
                document.getElementById('modeloFormula').addEventListener('change', function () {
                    var m = dados.modelos[this.value]; if (!m) { return; }
                    linhaFormula({codigo: m.codigo, nome: m.nome, expressao: m.expressao, nivel: m.nivel, arredondar: m.referencia !== 'A' && m.referencia !== 'D', modelo_formula_id: m.id});
                    this.value = ''; renomear();
                });
                document.getElementById('addRegra').addEventListener('click', function () {
                    blocoRegra({tipo: document.getElementById('tipoRegra').value, parametros: {}, activo: true}); renomear();
                });
                form.addEventListener('click', function (e) {
                    var b = e.target.closest('button'); if (!b) { return; }
                    var linha = b.closest('tr') || b.closest('#regras > div');
                    if (b.hasAttribute('data-remover')) { linha.remove(); renomear(); desenharTeste(); }
                    if (b.hasAttribute('data-subir') && linha.previousElementSibling) { linha.parentNode.insertBefore(linha, linha.previousElementSibling); renomear(); }
                    if (b.hasAttribute('data-descer') && linha.nextElementSibling) { linha.parentNode.insertBefore(linha.nextElementSibling, linha); renomear(); }
                });
                form.addEventListener('submit', renomear);

                // Nível → ciclos e classes
                var nivel = document.getElementById('nivel');
                function filtrarNivel() {
                    document.querySelectorAll('#listaClasses [data-nivel]').forEach(function (l) {
                        var mostrar = l.dataset.nivel === nivel.value; l.style.display = mostrar ? '' : 'none';
                        if (!mostrar) { l.querySelector('input').checked = false; }
                    });
                    Array.prototype.forEach.call(document.getElementById('ciclo').options, function (o) {
                        if (o.dataset.nivel) { o.hidden = o.dataset.nivel !== nivel.value; if (o.hidden && o.selected) { o.parentNode.value = ''; } }
                    });
                }
                nivel.addEventListener('change', filtrarNivel); filtrarNivel();
                var escala = document.getElementById('escala');
                function mostrarEscala() { document.getElementById('escalaIntervalos').textContent = escala.selectedOptions[0].dataset.intervalos; }
                escala.addEventListener('change', mostrarEscala); mostrarEscala();

                // ---------- Pré-visualização
                var valoresTeste = {};
                function desenharTeste() {
                    var caixa = document.getElementById('teste');
                    caixa.querySelectorAll('input').forEach(function (i) { valoresTeste[i.name] = i.value; });
                    var comps = Array.prototype.map.call(document.querySelectorAll('#componentes tr'), function (tr) {
                        var g = function (c) { return tr.querySelector('[data-campo="' + c + '"]').value.trim(); };
                        return {codigo: g('codigo').toUpperCase(), tipo: g('tipo'), momento: g('momento'), trimestres: g('trimestres')};
                    }).filter(function (c) { return c.codigo; });
                    var html = '';
                    [1, 2, 3, 4].forEach(function (p) {
                        var lista = comps.filter(function (c) {
                            if (p === 4) { return c.momento === 'exame'; }
                            return c.momento === 'trimestral' && (!c.trimestres || c.trimestres.split(',').map(function (x) { return x.trim(); }).indexOf(String(p)) >= 0);
                        });
                        if (!lista.length) { return; }
                        html += '<div class="mb-2"><div class="small fw-semibold text-primary">' + (p === 4 ? 'Exames' : p + '.º trimestre') + '</div><div class="row g-1">';
                        lista.forEach(function (c) {
                            var nome = 'teste[' + p + '][' + c.codigo + ']';
                            html += '<div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">' + esc(c.codigo) + '</span>'
                                + '<input class="form-control" name="' + nome + '" value="' + esc(valoresTeste[nome] || '') + '" placeholder="' + (c.tipo === 'lista' ? '12; 14' : '13') + '"></div></div>';
                        });
                        html += '</div></div>';
                    });
                    caixa.innerHTML = html || '<p class="small text-secondary">Defina componentes para testar.</p>';
                }
                document.getElementById('componentes').addEventListener('change', desenharTeste);
                desenharTeste();

                document.getElementById('calcular').addEventListener('click', function () {
                    renomear();
                    var fd = new FormData(form); fd.delete('_method');
                    var saida = document.getElementById('resultadoTeste');
                    saida.innerHTML = '<span class="text-secondary">A calcular…</span>';
                    fetch(dados.urlPrevia, {method: 'POST', body: fd, headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                        .then(function (r) { return r.json().then(function (j) { return {ok: r.ok, j: j}; }); })
                        .then(function (res) {
                            if (!res.ok) {
                                var erros = res.j.erros || res.j.errors || {};
                                saida.innerHTML = '<div class="alert alert-danger small mb-0"><strong>Corrija a configuração:</strong><ul class="mb-0">'
                                    + Object.keys(erros).map(function (k) { return '<li><code>' + esc(k) + '</code> ' + esc([].concat(erros[k])[0]) + '</li>'; }).join('') + '</ul></div>';
                                return;
                            }
                            var r = res.j.resultado, f = function (v) { return v === null || v === undefined ? '—' : String(Math.round(v * 100) / 100).replace('.', ','); };
                            var html = '<table class="table table-sm mb-2"><thead><tr><th>Período</th><th>Valores calculados</th><th>Classif.</th></tr></thead><tbody>';
                            [1, 2, 3].forEach(function (t) {
                                var x = r.trimestres[t];
                                html += '<tr><td>' + t + '.º T</td><td><code>' + Object.keys(x.valores).map(function (k) { return k + '=' + f(x.valores[k]); }).join(' ') + '</code></td>'
                                    + '<td class="' + (x.situacao === 'negativa' ? 'text-danger' : '') + '"><strong>' + f(x.classificacao) + '</strong> ' + esc(x.qualitativa || '') + '</td></tr>';
                            });
                            html += '<tr class="table-light"><td>Final</td><td><code>' + Object.keys(r.final.valores).map(function (k) { return k + '=' + f(r.final.valores[k]); }).join(' ') + '</code></td>'
                                + '<td class="' + (r.final.situacao === 'negativa' ? 'text-danger' : '') + '"><strong>' + f(r.final.classificacao) + '</strong> ' + esc(r.final.qualitativa || '') + '</td></tr></tbody></table>';
                            if (Object.keys(r.erros).length) {
                                html += '<div class="alert alert-warning small">' + Object.keys(r.erros).map(function (k) { return esc(k) + ': ' + esc(r.erros[k]); }).join('<br>') + '</div>';
                            }
                            if (res.j.simulacao) {
                                var s = res.j.simulacao;
                                html += '<div class="border rounded p-2"><div class="small text-secondary">Simulação do critério (aluno com esta classificação final em todas as disciplinas):</div>'
                                    + '<strong>' + esc(s.rotulo) + '</strong><div class="small">' + s.justificacao.map(esc).join(' ') + '</div></div>';
                            } else {
                                html += '<div class="small text-secondary">Classificação final incompleta: faltam valores de teste.</div>';
                            }
                            saida.innerHTML = html;
                        })
                        .catch(function () { saida.innerHTML = '<div class="alert alert-danger small">Não foi possível calcular.</div>'; });
                });
            })();
        </script>
    @endpush
</x-app-layout>
