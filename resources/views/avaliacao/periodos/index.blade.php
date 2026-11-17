<x-app-layout titulo="Fecho de períodos">
    <x-cabecalho titulo="Fecho de períodos" :subtitulo="$escolaActiva->nome ?? ''" :migalhas="['Avaliação' => null, 'Fecho de períodos' => null]" />

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Ano lectivo</label>
            <select name="ano_lectivo_id" class="form-select" data-auto-submit>
                @foreach ($anos as $a)<option value="{{ $a->id }}" @selected($anoId == $a->id)>{{ $a->designacao }}</option>@endforeach
            </select>
        </div>
    </form>

    <div class="alert alert-light border small"><i class="bi bi-shield-lock me-1 text-primary"></i>
        Depois de fechado, nenhum utilizador pode alterar as notas do período. A reabertura exige permissão, a indicação do motivo e fica registada no histórico de actividades.
    </div>

    <div class="row g-3 mb-4">
        @foreach ($periodos as $p)
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 {{ $p->fechado ? 'border-secondary' : '' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="fw-semibold text-primary">{{ $p->rotulo }}</div>
                            <span class="badge {{ $p->fechado ? 'text-bg-secondary' : 'text-bg-success' }}"><i class="bi bi-{{ $p->fechado ? 'lock-fill' : 'unlock' }} me-1"></i>{{ $p->fechado ? 'Fechado' : 'Aberto' }}</span>
                        </div>
                        <div class="small text-secondary mt-2">{{ number_format($p->notas, 0, ',', ' ') }} nota(s) lançada(s)</div>
                        @if ($p->registo?->alterado_em)
                            <div class="small text-secondary mt-1">{{ $p->fechado ? 'Fechado' : 'Reaberto' }} por {{ $p->registo->alteradoPor?->nome ?? '—' }} em {{ $p->registo->alterado_em->format('d/m/Y H:i') }}</div>
                            @if ($p->registo->motivo)<div class="small fst-italic mt-1">"{{ \Illuminate\Support\Str::limit($p->registo->motivo, 90) }}"</div>@endif
                        @endif
                    </div>
                    <div class="card-footer bg-white">
                        @if ($p->fechado)
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalReabrir" data-periodo="{{ $p->periodo }}" data-rotulo="{{ $p->rotulo }}">
                                <i class="bi bi-unlock me-1"></i>Reabrir</button>
                        @else
                            <form method="POST" action="{{ route('avaliacao.periodos.fechar') }}"
                                  data-confirmar="Fechar o {{ $p->rotulo }}? As notas deste período ficarão bloqueadas para todos os professores da escola." data-confirmar-texto="Fechar período">
                                @csrf
                                <input type="hidden" name="ano_lectivo_id" value="{{ $anoId }}"><input type="hidden" name="periodo" value="{{ $p->periodo }}">
                                <button class="btn btn-sm btn-primary"><i class="bi bi-lock me-1"></i>Fechar</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h2 class="titulo">Recálculo controlado por turma</h2></div>
        <div class="card-body small text-secondary pb-0">
            Os resultados são calculados no momento do lançamento e guardam a versão da configuração utilizada. Quando uma configuração é alterada,
            os resultados existentes <strong>não são alterados retroactivamente</strong>; o recálculo só é feito aqui, de forma explícita e registada.
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Turma</th><th>Resultados com versão anterior da configuração</th><th></th></tr></thead>
                <tbody>
                @forelse ($turmas as $t)
                    <tr>
                        <td class="fw-semibold">{{ $t->designacao }}</td>
                        <td>@if ($desactualizados[$t->id] ?? 0)<span class="badge text-bg-warning">{{ $desactualizados[$t->id] }}</span>@else<span class="text-secondary small">Actualizados</span>@endif</td>
                        <td class="accoes">
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#modalRecalcular" data-accao="{{ route('avaliacao.periodos.recalcular', $t) }}" data-turma="{{ $t->designacao }}">
                                <i class="bi bi-arrow-repeat me-1"></i>Recalcular</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="tabela-vazia">Sem turmas neste ano lectivo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalReabrir" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('avaliacao.periodos.reabrir') }}" class="modal-content">
                @csrf
                <input type="hidden" name="ano_lectivo_id" value="{{ $anoId }}"><input type="hidden" name="periodo" id="reabrirPeriodo" value="{{ old('periodo') }}">
                <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Reabrir <span id="reabrirRotulo"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small text-secondary">A reabertura permite alterar notas já fechadas. Indique o motivo (obrigatório).</p>
                    <textarea name="motivo" rows="3" maxlength="500" class="form-control @error('motivo') is-invalid @enderror" placeholder="Ex.: correcção de erro de lançamento detectado pelo director de turma.">{{ old('motivo') }}</textarea>
                    <x-campo-erro campo="motivo" />
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger">Reabrir período</button></div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="modalRecalcular" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content" id="formRecalcular">
                @csrf
                <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Recalcular resultados — <span id="recalcTurma"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small text-secondary">Todas as disciplinas da turma serão recalculadas com a versão actual das configurações de avaliação. As notas não são alteradas.</p>
                    <label class="form-label obrigatorio">Motivo</label>
                    <textarea name="motivo" rows="3" maxlength="500" class="form-control" required minlength="10"></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Recalcular</button></div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('modalReabrir').addEventListener('show.bs.modal', function (e) {
                if (!e.relatedTarget) { return; }
                document.getElementById('reabrirPeriodo').value = e.relatedTarget.dataset.periodo;
                document.getElementById('reabrirRotulo').textContent = e.relatedTarget.dataset.rotulo;
            });
            document.getElementById('modalRecalcular').addEventListener('show.bs.modal', function (e) {
                document.getElementById('formRecalcular').action = e.relatedTarget.dataset.accao;
                document.getElementById('recalcTurma').textContent = e.relatedTarget.dataset.turma;
            });
            @if ($errors->has('motivo') && old('periodo'))
                document.addEventListener('DOMContentLoaded', function () { new bootstrap.Modal(document.getElementById('modalReabrir')).show(); });
            @endif
        </script>
    @endpush
</x-app-layout>
