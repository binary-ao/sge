<x-app-layout titulo="Pagamentos">
    @php $kz = fn ($v) => \App\Services\Administrativo\ServicoPagamentos::kz((float) $v); @endphp
    <x-cabecalho titulo="Pagamentos e recibos" :subtitulo="$escolaActiva->nome" :migalhas="['Administrativo' => null, 'Pagamentos' => null]">
        <a href="{{ route('pagamentos.atraso') }}" class="btn btn-light"><i class="bi bi-exclamation-triangle me-1 text-warning"></i>Em atraso</a>
        @can('servicos.gerir')<a href="{{ route('servicos.index') }}" class="btn btn-light"><i class="bi bi-tags me-1"></i>Serviços e preços</a>@endcan
        @can('pagamentos.registar')<a href="{{ route('pagamentos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Registar pagamento</a>@endcan
    </x-cabecalho>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-lg-2 col-md-3 col-6"><label class="form-label">De</label><input type="date" name="de" value="{{ $de->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-lg-2 col-md-3 col-6"><label class="form-label">Até</label><input type="date" name="ate" value="{{ $ate->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-lg-3 col-md-6"><label class="form-label">Aluno ou n.º de recibo</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Forma</label>
            <select name="forma" class="form-select form-select-sm"><option value="">Todas</option>
                @foreach (config('administrativo.pagamentos.formas') as $v => $r)<option value="{{ $v }}" @selected(request('forma') === $v)>{{ $r }}</option>@endforeach
            </select></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Estado</label>
            <select name="estado" class="form-select form-select-sm"><option value="">Todos</option><option value="emitido" @selected(request('estado') === 'emitido')>Emitidos</option><option value="anulado" @selected(request('estado') === 'anulado')>Anulados</option></select></div>
        <div class="col-lg-1 col-md-4"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><x-cartao-estatistico rotulo="Total recebido no período" :valor="$kz($resumo['total'])" icone="cash-stack" tom="sucesso"
            :detalhe="collect($resumo['porForma'])->map(fn ($v, $k) => config('administrativo.pagamentos.formas.'.$k).': '.$kz($v))->join(' · ')" /></div>
        <div class="col-md-4"><x-cartao-estatistico rotulo="Recibos emitidos" :valor="$resumo['recibos']" icone="receipt" /></div>
        <div class="col-md-4"><x-cartao-estatistico rotulo="Recibos anulados" :valor="$resumo['anulados']" icone="x-octagon" tom="erro" /></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Recibo</th><th>Data</th><th>Aluno</th><th>Descrição</th><th>Forma</th><th class="text-end">Total</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($recibos as $r)
                    <tr class="{{ $r->estado === 'anulado' ? 'text-secondary' : '' }}">
                        <td><a href="{{ route('pagamentos.recibo', $r) }}" class="fw-semibold text-decoration-none text-nowrap">{{ $r->numero }}</a></td>
                        <td class="small">{{ $r->data->format('d/m/Y') }}</td>
                        <td class="small">{{ $r->aluno->nome }}<br><span class="text-secondary">{{ $r->aluno->numero_processo }}</span></td>
                        <td class="small">{{ $r->pagamentos->pluck('descricao')->join('; ') }}</td>
                        <td class="small">{{ $r->forma_label }}</td>
                        <td class="text-end fw-semibold text-nowrap {{ $r->estado === 'anulado' ? 'text-decoration-line-through' : '' }}">{{ $kz($r->total) }}</td>
                        <td><span class="badge {{ $r->estado === 'anulado' ? 'text-bg-danger' : 'text-bg-success' }}">{{ $r->estado === 'anulado' ? 'Anulado' : 'Emitido' }}</span></td>
                        <td class="accoes"><a href="{{ route('pagamentos.recibo.pdf', $r) }}" class="btn btn-light btn-icon" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tabela-vazia"><i class="bi bi-receipt"></i>Sem recibos no período.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $recibos->links() }}</div>
</x-app-layout>
