<x-app-layout titulo="Histórico de actividades">
    <x-cabecalho titulo="Histórico de actividades" subtitulo="Registo das operações efectuadas no sistema"
        :migalhas="['Sistema' => null, 'Histórico de Actividades' => null]" />

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label" for="q">Descrição</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Pesquisar…">
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Utilizador</label>
            <select name="utilizador_id" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($utilizadores as $u)
                    <option value="{{ $u->id }}" @selected(request('utilizador_id') == $u->id)>{{ $u->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label">Acção</label>
            <select name="accao" class="form-select" data-auto-submit>
                <option value="">Todas</option>
                @foreach (\App\Models\LogActividade::ACCOES as $v => $r)
                    <option value="{{ $v }}" @selected(request('accao') === $v)>{{ $r }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label class="form-label">De</label>
            <input type="date" name="de" value="{{ request('de') }}" class="form-control">
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label class="form-label">Até</label>
            <input type="date" name="ate" value="{{ request('ate') }}" class="form-control">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Aplicar</button>
            <a href="{{ route('logs.index') }}" class="btn btn-light btn-sm">Limpar</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Data</th><th>Utilizador</th><th>Acção</th><th>Descrição</th><th>Escola</th><th>IP</th><th></th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="small">{{ $log->utilizador?->nome ?? 'Sistema' }}</td>
                        <td><span class="badge text-bg-{{ $log->accao_cor }} bg-opacity-75">{{ $log->accao_label }}</span></td>
                        <td class="small">{{ $log->descricao }}</td>
                        <td class="small">{{ $log->escola?->nome ?? '—' }}</td>
                        <td class="small text-secondary">{{ $log->ip ?? '—' }}</td>
                        <td class="accoes"><a href="{{ route('logs.show', $log) }}" class="btn btn-light btn-icon" title="Detalhes"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tabela-vazia"><i class="bi bi-clock-history"></i>Sem registos para os filtros indicados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>
</x-app-layout>
