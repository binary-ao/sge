<x-app-layout titulo="Pagamentos em atraso">
    @php $kz = fn ($v) => \App\Services\Administrativo\ServicoPagamentos::kz((float) $v); @endphp
    <x-cabecalho titulo="Mensalidades em atraso" :subtitulo="$escolaActiva->nome.' · '.$ano?->designacao.' · vencimento ao dia '.config('administrativo.pagamentos.dia_vencimento').' de cada mês'"
        :migalhas="['Administrativo' => null, 'Pagamentos' => route('pagamentos.index'), 'Em atraso' => null]">
        @can('relatorios.escolares')<a href="{{ route('relatorios.show', 'pagamentos') }}" class="btn btn-light"><i class="bi bi-file-earmark-bar-graph me-1"></i>Relatório</a>@endcan
    </x-cabecalho>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><x-cartao-estatistico rotulo="Alunos com atraso" :valor="$lista->count()" icone="people" tom="alerta" /></div>
        <div class="col-md-4"><x-cartao-estatistico rotulo="Mensalidades em atraso" :valor="$meses" icone="calendar-x" tom="erro" /></div>
        <div class="col-md-4"><x-cartao-estatistico rotulo="Valor em dívida" :valor="$kz($total)" icone="cash" tom="erro" /></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Aluno</th><th>Classe / Turma</th><th>Encarregado</th><th>Meses em atraso</th><th class="text-end">Valor</th><th></th></tr></thead>
                <tbody>
                @forelse ($lista as $l)
                    <tr>
                        <td><a href="{{ route('alunos.show', $l['matricula']->aluno_id) }}" class="fw-semibold text-decoration-none">{{ $l['matricula']->aluno->nome }}</a></td>
                        <td class="small">{{ $l['matricula']->classe->nome }}{{ $l['matricula']->turma ? ' · '.$l['matricula']->turma->nome : '' }}</td>
                        <td class="small">{{ $l['matricula']->aluno->encarregado?->nome ?? '—' }}<br><span class="text-secondary">{{ $l['matricula']->aluno->encarregado?->telefone }}</span></td>
                        <td class="small">{{ $l['meses']->pluck('rotulo')->join(', ') }}</td>
                        <td class="text-end fw-semibold text-danger text-nowrap">{{ $kz($l['meses']->sum('valor')) }}</td>
                        <td class="accoes">@can('pagamentos.registar')<a href="{{ route('pagamentos.create', ['aluno' => $l['matricula']->aluno_id]) }}" class="btn btn-sm btn-outline-primary">Registar pagamento</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabela-vazia"><i class="bi bi-check2-circle"></i>Sem mensalidades em atraso{{ \App\Models\ServicoPagavel::where('tipo', 'mensalidade')->where('activo', true)->exists() ? '' : ' (a escola não tem mensalidade configurada)' }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
