<x-app-layout :titulo="$classe->nome_completo">
    @php
        $u = auth()->user();
        $global = $u->can('academico.global');
        $configuraPlanos = $global || $u->can('academico.configurar');
        $referencia = $planos->first(fn ($p) => $p->escola_id === null && $p->curso_id === null);
    @endphp
    <x-cabecalho :titulo="$classe->nome" :subtitulo="$classe->nivelEnsino->nome.($classe->ciclo ? ' · '.$classe->ciclo->nome : '')"
        :migalhas="['Cadastros e Configurações' => null, 'Classes e Ciclos' => route('cadastros.classes.index'), $classe->nome_completo => null]">
        @if ($global)
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalClasse"
                    data-accao="{{ route('cadastros.classes.update', $classe) }}"
                    data-preencher="{{ json_encode($classe->only('nivel_ensino_id', 'ciclo_id', 'nome', 'numero', 'regime', 'ordem')) }}"><i class="bi bi-pencil me-1"></i>Editar</button>
            <form method="POST" action="{{ route('cadastros.classes.estado', $classe) }}"
                  data-confirmar="{{ $classe->activo ? 'Desactivar esta classe? O histórico mantém-se.' : 'Activar esta classe?' }}"
                  data-confirmar-classe="{{ $classe->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $classe->activo ? 'Desactivar' : 'Activar' }}">
                @csrf @method('PATCH')
                <button class="btn {{ $classe->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $classe->activo ? 'Desactivar' : 'Activar' }}</button>
            </form>
        @endif
    </x-cabecalho>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Identificação</h2>
                    <span class="estado text-{{ $classe->activo ? 'success' : 'secondary' }}">{{ $classe->activo ? 'Activa' : 'Inactiva' }}</span></div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Nível de ensino</dt><dd class="col-7"><a href="{{ route('cadastros.niveis.show', $classe->nivelEnsino) }}">{{ $classe->nivelEnsino->nome }}</a></dd>
                        <dt class="col-5 text-secondary fw-normal">Ciclo</dt><dd class="col-7">{{ $classe->ciclo?->nome ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Número</dt><dd class="col-7">{{ $classe->numero ?? '—' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Regime</dt><dd class="col-7">{{ $classe->regime_label }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Turmas no ano actual</dt><dd class="col-7">{{ $turmasAno }}</dd>
                    </dl>
                </div>
            </div>
            <div class="alert alert-light border small">
                <i class="bi bi-info-circle text-primary me-1"></i>
                As disciplinas de cada classe são definidas no <strong>plano curricular</strong>. O plano de referência provincial aplica-se a todas as escolas;
                uma escola pode ter um plano próprio, que prevalece sobre o de referência.
            </div>
        </div>

        <div class="col-xl-8">
            @forelse ($planos as $plano)
                <div class="card mb-3">
                    <div class="card-header">
                        <h2 class="titulo">
                            {{ $plano->nome }}
                            @if ($plano->escola_id)<span class="badge badge-suave ms-1">{{ $plano->escola->nome }}</span>@else<span class="badge text-bg-light border ms-1">Referência provincial</span>@endif
                            @unless ($plano->activo)<span class="badge text-bg-secondary ms-1">Inactivo</span>@endunless
                        </h2>
                        <span class="small text-secondary">{{ $plano->curso?->nome ? 'Curso: '.$plano->curso->nome.' · ' : '' }}{{ $plano->anoLectivo?->designacao ?? 'Todos os anos' }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Disciplina</th><th>Código</th><th class="text-center">Tempos/semana</th><th>Tipo</th></tr></thead>
                            <tbody>
                            @foreach ($plano->disciplinas as $d)
                                <tr class="{{ $d->activo ? '' : 'text-secondary' }}">
                                    <td><a href="{{ route('cadastros.disciplinas.show', $d->id) }}" class="text-decoration-none">{{ $d->nome }}</a></td>
                                    <td><code>{{ $d->sigla }}</code></td>
                                    <td class="text-center">{{ $d->pivot->carga_horaria ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $d->pivot->obrigatoria ? 'text-bg-primary' : 'text-bg-light border' }}">{{ $d->pivot->obrigatoria ? 'Obrigatória' : 'Opcional' }}</span>
                                        @if ($d->pivot->especifica)<span class="badge badge-suave">Específica</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($configuraPlanos)
                        <div class="card-footer bg-white d-flex gap-2 justify-content-end">
                            <a href="{{ route('academico.planos.show', $plano->id) }}" class="btn btn-sm btn-light"><i class="bi bi-eye me-1"></i>Ver plano</a>
                            @php $podePlano = app(\App\Http\Controllers\Academico\PlanoCurricularController::class)->podeEditar($plano->escola_id); @endphp
                            @if ($podePlano)
                                <a href="{{ route('academico.planos.edit', $plano->id) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Configurar disciplinas</a>
                            @elseif ($plano->escola_id === null && $u->can('academico.configurar'))
                                <a href="{{ route('academico.planos.create', ['copiar' => $plano->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-copy me-1"></i>Adaptar para a escola</a>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="card">
                    <div class="card-body tabela-vazia">
                        <i class="bi bi-list-check"></i>Ainda não existe plano curricular para esta classe.
                        @if ($configuraPlanos)
                            <div class="mt-3"><a href="{{ route('academico.planos.create', ['classe_id' => $classe->id]) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Configurar disciplinas da classe</a></div>
                        @endif
                    </div>
                </div>
            @endforelse

            @if ($planos->isNotEmpty() && $global && ! $referencia && $classe->activo)
                <a href="{{ route('academico.planos.create', ['classe_id' => $classe->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Criar plano de referência provincial</a>
            @endif
        </div>
    </div>

    @if ($global)
        @include('cadastros.classes._modais')
    @endif
</x-app-layout>
