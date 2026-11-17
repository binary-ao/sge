<x-app-layout :titulo="$disciplina->nome">
    <x-cabecalho :titulo="$disciplina->nome" :subtitulo="($disciplina->escola_id ? 'Disciplina própria de '.$disciplina->escola->nome : 'Disciplina de referência provincial').' · '.$disciplina->componente_label"
        :migalhas="['Cadastros e Configurações' => null, 'Disciplinas' => route('cadastros.disciplinas.index'), $disciplina->nome => null]">
        @if ($podeEditar)
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalDisciplina"
                    data-accao="{{ route('cadastros.disciplinas.update', $disciplina->id) }}"
                    data-preencher="{{ json_encode($disciplina->only('nome', 'sigla', 'componente', 'nuclear') + ['niveis' => $disciplina->niveisEnsino->pluck('id')]) }}"><i class="bi bi-pencil me-1"></i>Editar</button>
            <form method="POST" action="{{ route('cadastros.disciplinas.estado', $disciplina->id) }}"
                  data-confirmar="{{ $disciplina->activo ? 'Desactivar a disciplina? Deixa de estar disponível em novos planos; as notas e pautas existentes mantêm-se.' : 'Activar a disciplina?' }}"
                  data-confirmar-classe="{{ $disciplina->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $disciplina->activo ? 'Desactivar' : 'Activar' }}">
                @csrf @method('PATCH')
                <button class="btn {{ $disciplina->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">
                    <i class="bi bi-{{ $disciplina->activo ? 'toggle-off' : 'toggle-on' }} me-1"></i>{{ $disciplina->activo ? 'Desactivar' : 'Activar' }}
                </button>
            </form>
        @endif
    </x-cabecalho>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h2 class="titulo">Identificação</h2>
                    <span class="estado text-{{ $disciplina->activo ? 'success' : 'secondary' }}">{{ $disciplina->activo ? 'Activa' : 'Inactiva' }}</span></div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Código</dt><dd class="col-7"><code>{{ $disciplina->sigla }}</code></dd>
                        <dt class="col-5 text-secondary fw-normal">Componente</dt><dd class="col-7">{{ $disciplina->componente_label }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Nuclear</dt><dd class="col-7">{{ $disciplina->nuclear ? 'Sim (regras de transição especiais)' : 'Não' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Âmbito</dt><dd class="col-7">{{ $disciplina->escola?->nome ?? 'Referência provincial' }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Turmas no ano actual</dt><dd class="col-7">{{ $turmasAno }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><h2 class="titulo">Níveis de ensino</h2></div>
                <div class="card-body">
                    @forelse ($disciplina->niveisEnsino as $n)
                        <a href="{{ route('cadastros.niveis.show', $n) }}" class="badge text-bg-light border text-decoration-none me-1 mb-1 fs-6 fw-normal">{{ $n->nome }}</a>
                    @empty
                        <div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>A disciplina ainda não está associada a nenhum nível de ensino.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="titulo">Classes em que é aplicada <span class="text-secondary fw-normal small">(planos curriculares)</span></h2></div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Classe</th><th>Plano curricular</th><th>Curso</th><th>Vigência</th><th class="text-center">Tempos/semana</th><th>Tipo</th><th></th></tr></thead>
                <tbody>
                @forelse ($planos as $p)
                    <tr>
                        <td class="fw-semibold"><a href="{{ route('cadastros.classes.show', $p->classe_id) }}" class="text-decoration-none">{{ $p->classe->nome_completo }}</a></td>
                        <td class="small">{{ $p->nome }} @if ($p->escola_id)<span class="badge badge-suave">Escola</span>@endif @unless ($p->activo)<span class="badge text-bg-secondary">Inactivo</span>@endunless</td>
                        <td class="small">{{ $p->curso?->nome ?? '—' }}</td>
                        <td class="small">{{ $p->anoLectivo?->designacao ?? 'Todos os anos' }}</td>
                        <td class="text-center">{{ $p->pivot->carga_horaria ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $p->pivot->obrigatoria ? 'text-bg-primary' : 'text-bg-light border' }}">{{ $p->pivot->obrigatoria ? 'Obrigatória' : 'Opcional' }}</span>
                            @if ($p->pivot->especifica)<span class="badge badge-suave">Específica</span>@endif
                        </td>
                        <td class="accoes">
                            @if (auth()->user()->can('academico.configurar') || auth()->user()->can('academico.global'))
                                <a href="{{ route('academico.planos.show', $p->id) }}" class="btn btn-light btn-icon" title="Ver plano"><i class="bi bi-eye"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tabela-vazia"><i class="bi bi-list-check"></i>A disciplina ainda não foi incluída em nenhum plano curricular.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($podeEditar)
        @include('cadastros.disciplinas._modal')
    @endif
</x-app-layout>
