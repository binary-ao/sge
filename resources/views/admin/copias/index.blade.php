<x-app-layout titulo="Cópias de segurança">
    <x-cabecalho titulo="Cópias de segurança" subtitulo="Base de dados e documentos carregados, num único ficheiro ZIP"
        :migalhas="['Sistema' => null, 'Cópias de segurança' => null]">
        <form method="POST" action="{{ route('copias.criar') }}" data-confirmar="Criar agora uma cópia de segurança completa? A operação pode demorar alguns segundos.">
            @csrf<button class="btn btn-primary" data-a-carregar="A criar cópia…"><i class="bi bi-database-down me-1"></i>Criar cópia agora</button>
        </form>
    </x-cabecalho>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>Ficheiro</th><th>Data</th><th class="text-end">Tamanho</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($copias as $c)
                            <tr>
                                <td><code>{{ $c['nome'] }}</code></td>
                                <td class="small">{{ $c['data']->format('d/m/Y H:i') }}</td>
                                <td class="text-end small">{{ number_format($c['tamanho'] / 1048576, 2, ',', ' ') }} MB</td>
                                <td class="accoes"><a href="{{ route('copias.descarregar', $c['nome']) }}" class="btn btn-sm btn-light"><i class="bi bi-download me-1"></i>Descarregar</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tabela-vazia"><i class="bi bi-database"></i>Ainda não existem cópias de segurança.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card"><div class="card-body small">
                <div class="fw-semibold text-primary mb-2">Política de cópias</div>
                <ul class="ps-3 text-secondary">
                    <li>Cópia automática diária às {{ config('sige.backups.hora') }} (requer o agendador activo: <code>php artisan schedule:run</code> a cada minuto).</li>
                    <li>São mantidas as {{ config('sige.backups.manter') }} cópias mais recentes.</li>
                    <li>As cópias ficam fora da pasta pública e só o superutilizador as pode descarregar.</li>
                    <li>Recomenda-se guardar periodicamente uma cópia num suporte externo.</li>
                </ul>
                <div class="fw-semibold text-primary mb-2">Restauro</div>
                <p class="text-secondary mb-0">O restauro é feito pelo técnico responsável (procedimento no ficheiro LEIA-ME de cada cópia e no README do projecto), para evitar substituições acidentais de dados.</p>
            </div></div>
        </div>
    </div>
</x-app-layout>
