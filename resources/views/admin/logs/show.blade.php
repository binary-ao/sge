<x-app-layout titulo="Registo de actividade">
    <x-cabecalho titulo="Registo de actividade #{{ $log->id }}"
        :migalhas="['Sistema' => null, 'Histórico de Actividades' => route('logs.index'), '#'.$log->id => null]" />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <dl class="lista-detalhes">
                        <dt>Data</dt><dd>{{ $log->created_at->format('d/m/Y H:i:s') }}</dd>
                        <dt>Utilizador</dt><dd>{{ $log->utilizador?->nome ?? 'Sistema' }}@if($log->utilizador)<br><span class="small text-secondary">{{ $log->utilizador->perfil->nome }}</span>@endif</dd>
                        <dt>Acção</dt><dd><span class="badge text-bg-{{ $log->accao_cor }}">{{ $log->accao_label }}</span></dd>
                        <dt>Descrição</dt><dd>{{ $log->descricao }}</dd>
                        <dt>Registo</dt><dd>{{ $log->modelo_tipo ? $log->modelo_tipo.' #'.$log->modelo_id : '—' }}</dd>
                        <dt>Escola</dt><dd>{{ $log->escola?->nome ?? '—' }}</dd>
                        <dt>Endereço IP</dt><dd>{{ $log->ip ?? '—' }}</dd>
                        <dt>Navegador</dt><dd class="small text-break">{{ $log->user_agent ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h2 class="titulo">Dados registados</h2></div>
                <div class="card-body">
                    @if (! empty($log->dados['anterior']) || ! empty($log->dados['novo']))
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Campo</th><th>Anterior</th><th>Novo</th></tr></thead>
                                <tbody>
                                @foreach (array_unique(array_merge(array_keys($log->dados['anterior'] ?? []), array_keys($log->dados['novo'] ?? []))) as $campo)
                                    <tr>
                                        <td><code>{{ $campo }}</code></td>
                                        <td class="small text-danger">{{ is_array($v = $log->dados['anterior'][$campo] ?? null) ? json_encode($v, JSON_UNESCAPED_UNICODE) : ($v ?? '—') }}</td>
                                        <td class="small text-success">{{ is_array($v = $log->dados['novo'][$campo] ?? null) ? json_encode($v, JSON_UNESCAPED_UNICODE) : ($v ?? '—') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($log->dados)
                        <pre class="small bg-light p-3 rounded mb-0">{{ json_encode($log->dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @else
                        <p class="text-secondary mb-0">Sem dados adicionais.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
