<x-app-layout titulo="Registar pagamento">
    @php $kz = fn ($v) => \App\Services\Administrativo\ServicoPagamentos::kz((float) $v); @endphp
    <x-cabecalho titulo="Registar pagamento" :subtitulo="$aluno ? $aluno->nome.' · Processo '.$aluno->numero_processo : 'Seleccione o aluno'"
        :migalhas="['Administrativo' => null, 'Pagamentos' => route('pagamentos.index'), 'Registar' => null]" />

    @if (! $aluno)
        <form method="GET" class="card"><div class="card-body row g-2 align-items-end">
            <div class="col-md-9"><label class="form-label">Aluno</label>
                <select name="aluno" class="form-select" required><option value="">Seleccione…</option>
                    @foreach ($alunos as $a)<option value="{{ $a->id }}">{{ $a->nome }} ({{ $a->numero_processo }})</option>@endforeach
                </select></div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Continuar</button></div>
        </div></form>
    @else
        <form method="POST" action="{{ route('pagamentos.store') }}">
            @csrf
            <input type="hidden" name="aluno_id" value="{{ $aluno->id }}">
            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="card mb-3"><div class="card-body small">
                        <strong>{{ $aluno->nome }}</strong> ·
                        @if ($matricula){{ $matricula->classe->nome }}{{ $matricula->turma ? ' · Turma '.$matricula->turma->nome : '' }} · {{ $ano->designacao }}
                        @else <span class="text-warning">Sem matrícula em {{ $ano?->designacao }}</span>@endif
                        @if ($aluno->encarregado) · Encarregado: {{ $aluno->encarregado->nome }} ({{ $aluno->encarregado->telefone }})@endif
                    </div></div>

                    @php $mensalidade = $servicos->firstWhere('tipo', 'mensalidade'); @endphp
                    @if ($mensalidade && $matricula)
                        <input type="hidden" name="mensalidade_id" value="{{ $mensalidade->id }}">
                        <div class="card mb-3">
                            <div class="card-header"><h2 class="titulo">Mensalidades — {{ $mensalidade->nome }} ({{ $kz($mensalidade->valor) }})</h2></div>
                            <div class="card-body"><div class="row g-2">
                                @foreach ($meses as $mm)
                                    @php $chave = $mm['ano'].'-'.$mm['mes']; $pago = in_array($chave, $pagos, true); $atraso = in_array($chave, $emDivida, true); @endphp
                                    <div class="col-md-3 col-6">
                                        <label class="form-check opcao-check {{ $pago ? 'opacity-50' : '' }}">
                                            <input class="form-check-input item-pagar" type="checkbox" name="meses[]" value="{{ $chave }}" data-valor="{{ $mensalidade->valor }}" @disabled($pago) @checked(in_array($chave, old('meses', []), true))>
                                            <span class="small">{{ \App\Models\Pagamento::MESES[$mm['mes']] }} {{ $mm['ano'] }}</span>
                                            @if ($pago)<span class="badge text-bg-success ms-1">Pago</span>@elseif ($atraso)<span class="badge text-bg-danger ms-1">Em atraso</span>@endif
                                        </label>
                                    </div>
                                @endforeach
                            </div></div>
                        </div>
                    @endif

                    <div class="card mb-3">
                        <div class="card-header"><h2 class="titulo">Outros serviços</h2></div>
                        <ul class="list-group list-group-flush">
                            @forelse ($servicos->where('tipo', '!=', 'mensalidade') as $s)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <label class="form-check mb-0"><input class="form-check-input item-pagar" type="checkbox" name="servicos[]" value="{{ $s->id }}" data-valor="{{ $s->valor }}" @checked(in_array($s->id, old('servicos', [])))>
                                        {{ $s->nome }} <span class="small text-secondary">· {{ $s->tipo_label }}{{ $s->classe ? ' · '.$s->classe->nome : '' }}</span></label>
                                    <strong>{{ $kz($s->valor) }}</strong>
                                </li>
                            @empty
                                <li class="list-group-item small text-secondary">Sem outros serviços configurados. @can('servicos.gerir')<a href="{{ route('servicos.index') }}">Configurar serviços</a>@endcan</li>
                            @endforelse
                        </ul>
                    </div>
                    <x-campo-erro campo="itens" />
                </div>

                <div class="col-xl-4">
                    <div class="card" style="position: sticky; top: 76px"><div class="card-body">
                        <div class="small text-secondary">Total a pagar</div>
                        <div class="fs-3 fw-bold text-primary mb-3" id="total">0,00 Kz</div>
                        <label class="form-label obrigatorio">Forma de pagamento</label>
                        <select name="forma_pagamento" id="forma" class="form-select mb-3">
                            @foreach (config('administrativo.pagamentos.formas') as $v => $r)<option value="{{ $v }}" @selected(old('forma_pagamento') === $v)>{{ $r }}</option>@endforeach
                        </select>
                        <div id="blocoRef">
                            <label class="form-label obrigatorio">Referência</label>
                            <input name="referencia" value="{{ old('referencia') }}" maxlength="60" class="form-control mb-3 @error('referencia') is-invalid @enderror" placeholder="N.º da transferência ou talão TPA">
                            <x-campo-erro campo="referencia" />
                        </div>
                        <label class="form-label obrigatorio">Data do pagamento</label>
                        <input type="date" name="data" value="{{ old('data', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control mb-3">
                        <button class="btn btn-primary w-100" id="btnPagar" disabled><i class="bi bi-receipt me-1"></i>Registar e emitir recibo</button>
                        <p class="small text-secondary mt-2 mb-0">O recibo recebe numeração sequencial da escola. Recibos emitidos não podem ser eliminados, apenas anulados.</p>
                    </div></div>
                </div>
            </div>
        </form>
        @push('scripts')
            <script>
                (function () {
                    var caixas = document.querySelectorAll('.item-pagar'), forma = document.getElementById('forma');
                    function actualizar() {
                        var total = 0, n = 0;
                        caixas.forEach(function (c) { if (c.checked) { total += parseFloat(c.dataset.valor); n++; } });
                        document.getElementById('total').textContent = total.toLocaleString('pt-PT', {minimumFractionDigits: 2}) + ' Kz';
                        document.getElementById('btnPagar').disabled = n === 0;
                    }
                    function ref() { document.getElementById('blocoRef').style.display = forma.value === 'numerario' ? 'none' : ''; }
                    caixas.forEach(function (c) { c.addEventListener('change', actualizar); });
                    forma.addEventListener('change', ref); ref(); actualizar();
                })();
            </script>
        @endpush
    @endif
</x-app-layout>
