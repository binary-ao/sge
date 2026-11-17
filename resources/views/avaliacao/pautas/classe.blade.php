<x-app-layout :titulo="'Pauta da '.$classe->nome">
    <x-cabecalho :titulo="'Resultados finais — '.$classe->nome_completo" :subtitulo="$escola->nome.' · '.$ano?->designacao"
        :migalhas="['Avaliação' => null, 'Pautas' => route('avaliacao.pautas.index'), $classe->nome => null]" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-cartao-estatistico rotulo="Alunos" :valor="$total" icone="people" /></div>
        @foreach ($resumo as $s => $n)
            <div class="col-6 col-lg-3"><x-cartao-estatistico :rotulo="config('avaliacao.situacoes.'.$s.'.rotulo')" :valor="$n" icone="clipboard-check" :tom="['success' => 'sucesso', 'danger' => 'erro', 'warning' => 'alerta', 'info' => 'info'][config('avaliacao.situacoes.'.$s.'.cor')] ?? 'azul'" /></div>
        @endforeach
    </div>

    @forelse ($turmas as $bloco)
        <div class="card mb-3">
            <div class="card-header"><h2 class="titulo">Turma {{ $bloco['turma']->nome }}{{ $bloco['turma']->curso ? ' · '.$bloco['turma']->curso->nome : '' }}</h2>
                <a href="{{ route('avaliacao.pautas.turma', [$bloco['turma'], 'periodo' => 0]) }}" class="small">Pauta final da turma</a></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Aluno</th><th>Processo</th><th class="text-end">Média</th><th class="text-end">Negativas</th><th>Resultado</th><th>Justificação</th></tr></thead>
                    <tbody>
                    @foreach ($bloco['linhas'] as $l)
                        <tr>
                            <td>{{ $l['matricula']->aluno->nome }}</td>
                            <td><code>{{ $l['matricula']->aluno->numero_processo }}</code></td>
                            <td class="text-end">{{ $l['final']?->media !== null ? number_format($l['final']->media, 1, ',', '') : '—' }}</td>
                            <td class="text-end">{{ $l['final']?->disciplinas_negativas ?? '—' }}</td>
                            <td>@if ($l['final'])<span class="badge text-bg-{{ $l['final']->cor }}">{{ $l['final']->rotulo }}</span>@else — @endif</td>
                            <td class="small text-secondary">{{ $l['final'] ? implode(' ', $l['final']->justificacao) : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body tabela-vazia">Sem turmas desta classe no ano lectivo seleccionado.</div></div>
    @endforelse
</x-app-layout>
