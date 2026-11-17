<x-app-layout :titulo="'Recibo '.$recibo->numero">
    @php $kz = fn ($v) => \App\Services\Administrativo\ServicoPagamentos::kz((float) $v); @endphp
    <x-cabecalho :titulo="'Recibo '.$recibo->numero" :subtitulo="$recibo->aluno->nome.' · '.$recibo->data->format('d/m/Y')"
        :migalhas="['Administrativo' => null, 'Pagamentos' => route('pagamentos.index'), $recibo->numero => null]">
        <a href="{{ route('pagamentos.recibo.pdf', $recibo) }}" class="btn btn-primary"><i class="bi bi-file-earmark-pdf me-1"></i>Descarregar PDF</a>
        @if ($recibo->estado === 'emitido')
            @can('pagamentos.anular')<button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAnular"><i class="bi bi-x-octagon me-1"></i>Anular recibo</button>@endcan
        @endif
    </x-cabecalho>

    @if ($recibo->estado === 'anulado')
        <div class="alert alert-danger"><strong>Recibo anulado</strong> em {{ $recibo->anulado_em->format('d/m/Y H:i') }} por {{ $recibo->anuladoPor?->nome }}. Motivo: {{ $recibo->motivo_anulacao }}</div>
    @endif

    <div class="card" style="max-width: 820px">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                <div><div class="fw-bold text-primary">{{ $recibo->escola->nome }}</div><div class="small text-secondary">{{ $recibo->escola->codigo }} · {{ $recibo->escola->municipio->nome }}</div></div>
                <div class="text-end"><div class="fs-5 fw-bold">{{ $recibo->numero }}</div><div class="small text-secondary">{{ $recibo->data->format('d/m/Y') }}</div></div>
            </div>
            <dl class="lista-detalhes mb-3">
                <dt>Aluno</dt><dd>{{ $recibo->aluno->nome }} (processo {{ $recibo->aluno->numero_processo }})</dd>
                <dt>Forma de pagamento</dt><dd>{{ $recibo->forma_label }}{{ $recibo->referencia ? ' · Ref. '.$recibo->referencia : '' }}</dd>
                <dt>Emitido por</dt><dd>{{ $recibo->emitidoPor?->nome ?? '—' }}</dd>
            </dl>
            <table class="table table-sm">
                <thead><tr><th>Descrição</th><th class="text-end">Valor</th></tr></thead>
                <tbody>@foreach ($recibo->pagamentos as $p)<tr><td>{{ $p->descricao }}</td><td class="text-end">{{ $kz($p->valor) }}</td></tr>@endforeach</tbody>
                <tfoot><tr><th>Total</th><th class="text-end">{{ $kz($recibo->total) }}</th></tr></tfoot>
            </table>
        </div>
    </div>

    @can('pagamentos.anular')
        <div class="modal fade" id="modalAnular" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('pagamentos.anular', $recibo) }}" class="modal-content">
                    @csrf @method('PATCH')
                    <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Anular recibo {{ $recibo->numero }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="small text-secondary">O recibo não é eliminado: fica anulado, com o seu nome, a data e o motivo. Os pagamentos associados deixam de contar.</p>
                        <label class="form-label obrigatorio">Motivo</label>
                        <textarea name="motivo" rows="3" class="form-control @error('motivo') is-invalid @enderror" required minlength="10" maxlength="500">{{ old('motivo') }}</textarea>
                        <x-campo-erro campo="motivo" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger">Anular recibo</button></div>
                </form>
            </div>
        </div>
    @endcan
</x-app-layout>
