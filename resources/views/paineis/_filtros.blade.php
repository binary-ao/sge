{{-- Filtros globais: actualizam cartões, gráficos e tabelas. $fixarMunicipio = true no painel municipal de um técnico municipal. --}}
<form method="GET" class="barra-filtros">
    <div class="row g-2 align-items-end">
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Ano lectivo</label>
            <select name="ano_lectivo_id" class="form-select form-select-sm" data-auto-submit>
                @foreach ($opcoes['anos'] as $a)<option value="{{ $a->id }}" @selected($filtros->anoLectivoId == $a->id)>{{ $a->designacao }}</option>@endforeach
            </select>
        </div>
        @unless ($fixarMunicipio ?? false)
            <div class="col-xl-2 col-md-4">
                <label class="form-label">Município</label>
                <select name="municipio_id" class="form-select form-select-sm" data-auto-submit>
                    @if (! ($municipioObrigatorio ?? false))<option value="">Todos</option>@endif
                    @foreach ($opcoes['municipios'] as $m)<option value="{{ $m->id }}" @selected($filtros->municipioId == $m->id)>{{ $m->nome }}</option>@endforeach
                </select>
            </div>
        @endunless
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Escola</label>
            <select name="escola_id" class="form-select form-select-sm" data-auto-submit>
                <option value="">Todas</option>
                @foreach ($opcoes['escolas'] as $e)<option value="{{ $e->id }}" @selected($filtros->escolaId == $e->id)>{{ $e->nome }}</option>@endforeach
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Tipologia</label>
            <select name="tipologia_id" class="form-select form-select-sm" data-auto-submit>
                <option value="">Todas</option>
                @foreach ($opcoes['tipologias'] as $t)<option value="{{ $t->id }}" @selected($filtros->tipologiaId == $t->id)>{{ $t->nome }}</option>@endforeach
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Nível de ensino</label>
            <select name="nivel_id" class="form-select form-select-sm" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($opcoes['niveis'] as $n)<option value="{{ $n->id }}" @selected($filtros->nivelId == $n->id)>{{ $n->nome }}</option>@endforeach
            </select>
        </div>
        <div class="col-xl-1 col-md-4">
            <label class="form-label">Natureza</label>
            <select name="natureza" class="form-select form-select-sm" data-auto-submit>
                <option value="">Todas</option>
                @foreach (\App\Models\Escola::NATUREZAS as $v => $r)<option value="{{ $v }}" @selected($filtros->natureza === $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="col-xl-1 col-md-4">
            <label class="form-label">Classe</label>
            <select name="classe_id" class="form-select form-select-sm" data-auto-submit>
                <option value="">Todas</option>
                @foreach ($opcoes['classes'] as $c)<option value="{{ $c->id }}" @selected($filtros->classeId == $c->id)>{{ $c->nome_completo }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
        <noscript><button class="btn btn-primary btn-sm">Aplicar</button></noscript>
        @if ($filtros->activos())
            <span class="badge badge-suave">{{ $filtros->activos() }} filtro(s) activo(s)</span>
            <a href="{{ url()->current() }}" class="btn btn-light btn-sm">Limpar filtros</a>
        @endif
        <span class="ms-auto small text-secondary">Os gráficos são interactivos: clique numa categoria para consultar as instituições correspondentes.</span>
    </div>
</form>
