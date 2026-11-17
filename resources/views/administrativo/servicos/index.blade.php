<x-app-layout titulo="Serviços e preços">
    @php $kz = fn ($v) => \App\Services\Administrativo\ServicoPagamentos::kz((float) $v); @endphp
    <x-cabecalho titulo="Serviços e preços" :subtitulo="$escolaActiva->nome.' — os valores podem variar por classe'"
        :migalhas="['Administrativo' => null, 'Pagamentos' => route('pagamentos.index'), 'Serviços e preços' => null]">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalServico"><i class="bi bi-plus-lg me-1"></i>Novo serviço</button>
    </x-cabecalho>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Serviço</th><th>Tipo</th><th>Classe</th><th class="text-end">Valor</th><th class="text-end">Pagamentos</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($servicos as $s)
                    <tr class="{{ $s->activo ? '' : 'text-secondary' }}">
                        <td class="fw-semibold">{{ $s->nome }}</td>
                        <td class="small">{{ $s->tipo_label }}</td>
                        <td class="small">{{ $s->classe ? $s->classe->nome_completo : 'Todas as classes' }}</td>
                        <td class="text-end text-nowrap">{{ $kz($s->valor) }}</td>
                        <td class="text-end">{{ $s->pagamentos_count }}</td>
                        <td><span class="estado text-{{ $s->activo ? 'success' : 'secondary' }}">{{ $s->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="accoes">
                            <button class="btn btn-light btn-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalServico" data-accao="{{ route('servicos.update', $s) }}"
                                    data-preencher="{{ json_encode(['tipo' => $s->tipo, 'nome' => $s->nome, 'classe_id' => $s->classe_id, 'valor' => $s->valor]) }}"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('servicos.estado', $s) }}" class="d-inline">@csrf @method('PATCH')
                                <button class="btn btn-light btn-icon" title="{{ $s->activo ? 'Desactivar' : 'Activar' }}"><i class="bi bi-{{ $s->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tabela-vazia"><i class="bi bi-tags"></i>Sem serviços configurados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body small text-secondary border-top">Uma mensalidade específica de uma classe substitui a mensalidade geral nessa classe. Os recibos emitidos guardam o valor cobrado; alterar o preço não altera recibos anteriores.</div>
    </div>

    <x-modal-form id="modalServico" titulo-criar="Novo serviço" titulo-editar="Editar serviço" :accao-criar="route('servicos.store')">
        <div class="row g-3">
            <div class="col-md-5"><label class="form-label obrigatorio">Tipo</label>
                <select name="tipo" class="form-select">@foreach (config('administrativo.pagamentos.tipos_servico') as $v => $r)<option value="{{ $v }}" @selected(old('tipo') === $v)>{{ $r }}</option>@endforeach</select></div>
            <div class="col-md-7"><label class="form-label obrigatorio">Nome</label><input name="nome" value="{{ old('nome') }}" maxlength="150" class="form-control @error('nome') is-invalid @enderror"><x-campo-erro campo="nome" /></div>
            <div class="col-md-7"><label class="form-label">Classe</label>
                <select name="classe_id" class="form-select @error('classe_id') is-invalid @enderror"><option value="">Todas as classes</option>
                    @foreach ($classes as $c)<option value="{{ $c->id }}" @selected(old('classe_id') == $c->id)>{{ $c->nome_completo }}</option>@endforeach</select>
                <x-campo-erro campo="classe_id" /></div>
            <div class="col-md-5"><label class="form-label obrigatorio">Valor (Kz)</label><input name="valor" value="{{ old('valor') }}" inputmode="decimal" class="form-control @error('valor') is-invalid @enderror"><x-campo-erro campo="valor" /></div>
        </div>
    </x-modal-form>
</x-app-layout>
