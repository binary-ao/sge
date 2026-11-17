<x-app-layout :titulo="$titulo">
    <x-cabecalho :titulo="$titulo" :subtitulo="$escola?->nome ?? 'Âmbito: '.auth()->user()->ambito_descricao"
        :migalhas="['Relatórios' => route('relatorios.index'), $titulo => null]">
        <a href="{{ request()->fullUrlWithQuery(['formato' => 'pdf']) }}" class="btn btn-light"><i class="bi bi-file-earmark-pdf me-1 text-danger"></i>PDF</a>
        <a href="{{ request()->fullUrlWithQuery(['formato' => 'xlsx']) }}" class="btn btn-light"><i class="bi bi-file-earmark-excel me-1 text-success"></i>Excel</a>
    </x-cabecalho>

    @if ($camposFiltro)
        <form method="GET" class="barra-filtros row g-2 align-items-end">
            @if (in_array('ano_lectivo_id', $camposFiltro, true))
                <div class="col-md-2"><label class="form-label">Ano lectivo</label>
                    <select name="ano_lectivo_id" class="form-select form-select-sm" data-auto-submit>
                        @foreach ($opcoes['anos'] as $a)<option value="{{ $a->id }}" @selected($filtros->anoLectivoId == $a->id)>{{ $a->designacao }}</option>@endforeach
                    </select></div>
            @endif
            @if (in_array('municipio_id', $camposFiltro, true) && $opcoes['municipios']->count() > 1)
                <div class="col-md-2"><label class="form-label">Município</label>
                    <select name="municipio_id" class="form-select form-select-sm" data-auto-submit><option value="">Todos</option>
                        @foreach ($opcoes['municipios'] as $m)<option value="{{ $m->id }}" @selected($filtros->municipioId == $m->id)>{{ $m->nome }}</option>@endforeach
                    </select></div>
            @endif
            @if (in_array('tipologia_id', $camposFiltro, true))
                <div class="col-md-3"><label class="form-label">Tipologia</label>
                    <select name="tipologia_id" class="form-select form-select-sm" data-auto-submit><option value="">Todas</option>
                        @foreach ($opcoes['tipologias'] as $t)<option value="{{ $t->id }}" @selected($filtros->tipologiaId == $t->id)>{{ $t->nome }}</option>@endforeach
                    </select></div>
            @endif
            @if (in_array('nivel_id', $camposFiltro, true))
                <div class="col-md-3"><label class="form-label">Nível de ensino</label>
                    <select name="nivel_id" class="form-select form-select-sm" data-auto-submit><option value="">Todos</option>
                        @foreach ($opcoes['niveis'] as $n)<option value="{{ $n->id }}" @selected($filtros->nivelId == $n->id)>{{ $n->nome }}</option>@endforeach
                    </select></div>
            @endif
            @if (in_array('natureza', $camposFiltro, true))
                <div class="col-md-2"><label class="form-label">Natureza</label>
                    <select name="natureza" class="form-select form-select-sm" data-auto-submit><option value="">Todas</option>
                        @foreach (\App\Models\Escola::NATUREZAS as $v => $r)<option value="{{ $v }}" @selected($filtros->natureza === $v)>{{ $r }}</option>@endforeach
                    </select></div>
            @endif
            @if (in_array('agrupamento', $camposFiltro, true))
                <div class="col-md-2"><label class="form-label">Agrupar por</label>
                    <select name="agrupamento" class="form-select form-select-sm" data-auto-submit>
                        @foreach (['aluno' => 'Aluno', 'turma' => 'Turma', 'disciplina' => 'Disciplina', 'mes' => 'Mês'] as $v => $r)<option value="{{ $v }}" @selected(request('agrupamento', 'aluno') === $v)>{{ $r }}</option>@endforeach
                    </select></div>
            @endif
            @if (in_array('de', $camposFiltro, true))
                <div class="col-md-2 col-6"><label class="form-label">De</label><input type="date" name="de" value="{{ request('de') }}" class="form-control form-control-sm" data-auto-submit></div>
                <div class="col-md-2 col-6"><label class="form-label">Até</label><input type="date" name="ate" value="{{ request('ate') }}" class="form-control form-control-sm" data-auto-submit></div>
            @endif
            @if (in_array('turma_id', $camposFiltro, true))
                <div class="col-md-3"><label class="form-label">Turma</label>
                    <select name="turma_id" class="form-select form-select-sm" data-auto-submit><option value="">Todas</option>
                        @foreach ($opcoes['turmas'] as $t)<option value="{{ $t->id }}" @selected(request('turma_id') == $t->id)>{{ $t->designacao }}</option>@endforeach
                    </select></div>
            @endif
        </form>
    @endif

    <div class="card">
        <div class="table-responsive">@include('relatorios._tabela', ['html' => true])</div>
        <div class="card-body small text-secondary border-top">
            @foreach ($notas as $nota)<div>{{ $nota }}</div>@endforeach
            <div>{{ $linhas->count() }} linha(s) · gerado em {{ $gerado_em->format('d/m/Y H:i') }}.</div>
        </div>
    </div>
</x-app-layout>
