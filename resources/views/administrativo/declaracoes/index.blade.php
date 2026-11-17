<x-app-layout titulo="Declarações">
    <x-cabecalho titulo="Declarações" subtitulo="Histórico de documentos emitidos" :migalhas="['Administrativo' => null, 'Declarações' => null]">
        <a href="{{ route('declaracoes.create') }}" class="btn btn-primary"><i class="bi bi-file-earmark-plus me-1"></i>Emitir declaração</a>
    </x-cabecalho>

    @if (session('descarregar'))
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="bi bi-file-earmark-pdf"></i>
            <span>A declaração está pronta.</span><a href="{{ session('descarregar') }}" class="btn btn-sm btn-success ms-auto">Descarregar PDF</a></div>
    @endif

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label">Pesquisar</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Aluno, n.º da declaração ou código de verificação"></div>
        <div class="col-md-4"><label class="form-label">Tipo</label>
            <select name="tipo" class="form-select form-select-sm" data-auto-submit><option value="">Todos</option>
                @foreach (config('administrativo.declaracoes.tipos') as $v => $r)<option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $r }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Número</th><th>Tipo</th><th>Aluno</th><th>Finalidade</th><th>Emitida</th><th>Código</th><th></th></tr></thead>
                <tbody>
                @forelse ($declaracoes as $d)
                    <tr>
                        <td class="fw-semibold text-nowrap">{{ $d->numero }}</td>
                        <td class="small">{{ $d->tipo_label }}</td>
                        <td class="small">{{ $d->aluno->nome }}</td>
                        <td class="small">{{ $d->finalidade ?? '—' }}</td>
                        <td class="small">{{ $d->created_at->format('d/m/Y H:i') }}<br><span class="text-secondary">{{ $d->emitidaPor?->nome }}</span></td>
                        <td><code>{{ $d->codigo_verificacao }}</code></td>
                        <td class="accoes"><a href="{{ route('declaracoes.pdf', $d) }}" class="btn btn-light btn-icon" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tabela-vazia"><i class="bi bi-file-earmark-text"></i>Nenhuma declaração emitida.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $declaracoes->links() }}</div>
</x-app-layout>
