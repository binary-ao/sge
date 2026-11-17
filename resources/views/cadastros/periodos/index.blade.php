<x-app-layout titulo="Períodos de Avaliação">
    @php $global = auth()->user()->can('academico.global'); @endphp
    <x-cabecalho titulo="Períodos de Avaliação" subtitulo="Calendário dos trimestres de cada ano lectivo, comum a todas as escolas"
        :migalhas="['Cadastros e Configurações' => null, 'Períodos de Avaliação' => null]">
        @if ($global && $emFalta->isNotEmpty())
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoPeriodo"><i class="bi bi-plus-lg me-1"></i>Novo período</button>
        @endif
        @can('pautas.fechar')
            <a href="{{ route('avaliacao.periodos.index') }}" class="btn btn-light"><i class="bi bi-lock me-1"></i>Fecho de períodos da escola</a>
        @endcan
    </x-cabecalho>

    <div class="alert alert-light border small py-2 d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle text-primary mt-1"></i>
        <div>
            As datas definem o trimestre em curso no lançamento de notas e no registo de faltas. Têm de estar dentro do ano lectivo, sem sobreposições e pela ordem I → II → III.
            A abertura e o fecho do lançamento de notas são feitos por cada escola em <em>Avaliação › Fecho de Períodos</em>; aqui consulta-se essa situação.
        </div>
    </div>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label" for="q">Pesquisar</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Período ou ano lectivo">
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Ano lectivo</label>
            <select name="ano_lectivo_id" class="form-select" data-auto-submit>
                <option value="0" @selected(! $anoId)>Todos</option>
                @foreach ($anos as $a)<option value="{{ $a->id }}" @selected($anoId == $a->id)>{{ $a->designacao }}{{ $a->actual ? ' (activo)' : '' }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label">Período</label>
            <select name="numero" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach ([1 => 'I Trimestre', 2 => 'II Trimestre', 3 => 'III Trimestre'] as $v => $r)<option value="{{ $v }}" @selected(request('numero') == $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label">Situação</label>
            <select name="situacao" class="form-select" data-auto-submit>
                <option value="">Todas</option>
                @foreach (\App\Models\Trimestre::SITUACOES as $v => [$r]) <option value="{{ $v }}" @selected(request('situacao') === $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Pesquisar</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Período</th><th>Ano lectivo</th><th>Início</th><th>Fim</th><th class="text-end">Semanas</th><th>Situação</th><th class="text-end" title="Escolas activas no seu âmbito com o lançamento de notas fechado">Lançamento fechado</th><th></th></tr></thead>
                <tbody>
                @forelse ($trimestres as $t)
                    @php [$rotulo, $cor] = \App\Models\Trimestre::SITUACOES[$t->situacao]; @endphp
                    <tr>
                        <td><a href="{{ route('cadastros.periodos.show', $t) }}" class="fw-semibold text-decoration-none">{{ $t->designacao }}</a></td>
                        <td>
                            <a href="{{ route('cadastros.anos.show', $t->ano_lectivo_id) }}" class="text-decoration-none">{{ $t->anoLectivo->designacao }}</a>
                            @if ($t->anoLectivo->actual)<span class="badge text-bg-primary ms-1">Activo</span>@endif
                        </td>
                        <td>{{ $t->data_inicio?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $t->data_fim?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-end">{{ $t->data_inicio && $t->data_fim ? (int) ceil(($t->data_inicio->diffInDays($t->data_fim) + 1) / 7) : '—' }}</td>
                        <td><span class="badge text-bg-{{ $cor }}">{{ $rotulo }}</span></td>
                        <td class="text-end">{{ $fechados[$t->ano_lectivo_id.'-'.$t->numero] ?? 0 }} / {{ $totalEscolas }}</td>
                        <td class="accoes">
                            <a href="{{ route('cadastros.periodos.show', $t) }}" class="btn btn-light btn-icon" title="Ver situação por escola"><i class="bi bi-eye"></i></a>
                            @if ($global)
                                <button class="btn btn-light btn-icon" title="Editar datas" data-bs-toggle="modal" data-bs-target="#modalPeriodo"
                                        data-accao="{{ route('cadastros.periodos.update', $t) }}"
                                        data-preencher="{{ json_encode(['designacao' => $t->designacao, 'data_inicio' => $t->data_inicio?->toDateString(), 'data_fim' => $t->data_fim?->toDateString()]) }}"><i class="bi bi-pencil"></i></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tabela-vazia"><i class="bi bi-calendar-range"></i>Nenhum período encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $trimestres->links() }}</div>

    @if ($global)
        <x-modal-form id="modalPeriodo" titulo-criar="Editar período" titulo-editar="Editar período" :accao-criar="route('cadastros.periodos.index')">
            <div class="mb-3">
                <label class="form-label" for="perDesignacao">Designação</label>
                <input id="perDesignacao" name="designacao" maxlength="30" class="form-control @error('designacao') is-invalid @enderror" value="{{ old('designacao') }}">
                <x-campo-erro campo="designacao" />
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label obrigatorio" for="perInicio">Início</label>
                    <input type="date" id="perInicio" name="data_inicio" class="form-control @error('data_inicio') is-invalid @enderror" value="{{ old('data_inicio') }}">
                    <x-campo-erro campo="data_inicio" />
                </div>
                <div class="col-6">
                    <label class="form-label obrigatorio" for="perFim">Fim</label>
                    <input type="date" id="perFim" name="data_fim" class="form-control @error('data_fim') is-invalid @enderror" value="{{ old('data_fim') }}">
                    <x-campo-erro campo="data_fim" />
                </div>
            </div>
        </x-modal-form>

        @if ($emFalta->isNotEmpty())
            <x-modal-form id="modalNovoPeriodo" titulo-criar="Novo período de avaliação" :accao-criar="route('cadastros.periodos.store')">
                <p class="small text-secondary">Os três trimestres são criados com o ano lectivo. Use esta opção para repor um trimestre em falta.</p>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label obrigatorio" for="novoAno">Ano lectivo</label>
                        <select id="novoAno" name="ano_lectivo_id" class="form-select @error('ano_lectivo_id') is-invalid @enderror">
                            @foreach ($anos->whereIn('id', $emFalta->keys()) as $a)<option value="{{ $a->id }}" data-em-falta="{{ implode(',', $emFalta[$a->id]) }}" @selected(old('ano_lectivo_id') == $a->id)>{{ $a->designacao }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label obrigatorio" for="novoNumero">Período</label>
                        <select id="novoNumero" name="numero" class="form-select @error('numero') is-invalid @enderror">
                            @foreach ([1 => 'I Trimestre', 2 => 'II Trimestre', 3 => 'III Trimestre'] as $v => $r)<option value="{{ $v }}" @selected(old('numero') == $v)>{{ $r }}</option>@endforeach
                        </select>
                        <x-campo-erro campo="numero" />
                    </div>
                    <div class="col-6">
                        <label class="form-label obrigatorio">Início</label>
                        <input type="date" name="data_inicio" class="form-control @error('data_inicio') is-invalid @enderror" value="{{ old('data_inicio') }}">
                        <x-campo-erro campo="data_inicio" />
                    </div>
                    <div class="col-6">
                        <label class="form-label obrigatorio">Fim</label>
                        <input type="date" name="data_fim" class="form-control @error('data_fim') is-invalid @enderror" value="{{ old('data_fim') }}">
                        <x-campo-erro campo="data_fim" />
                    </div>
                </div>
            </x-modal-form>
            @push('scripts')
                <script>
                    (function () {
                        var ano = document.getElementById('novoAno'), num = document.getElementById('novoNumero');
                        function filtrar() {
                            var op = ano.options[ano.selectedIndex];
                            var falta = op ? op.dataset.emFalta.split(',') : [];
                            Array.prototype.forEach.call(num.options, function (o) { o.hidden = falta.indexOf(o.value) < 0; });
                            if (num.selectedOptions[0] && num.selectedOptions[0].hidden) { num.value = falta[0] || ''; }
                        }
                        ano.addEventListener('change', filtrar);
                        filtrar();
                    })();
                </script>
            @endpush
        @endif
    @endif
</x-app-layout>
