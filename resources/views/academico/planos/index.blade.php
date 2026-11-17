<x-app-layout titulo="Planos curriculares">
    @php $podeCriar = auth()->user()->can('academico.global') || ($escolaActiva && $controlador->podeEditar($escolaActiva->id)); @endphp
    <x-cabecalho titulo="Configuração Académica" subtitulo="Planos curriculares: disciplinas por classe e curso"
        :migalhas="['Gestão Académica' => null, 'Configuração Académica' => route('academico.index'), 'Planos curriculares' => null]">
        @if ($podeCriar)
            <a href="{{ route('academico.planos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo plano</a>
        @endif
    </x-cabecalho>

    @include('academico._nav')

    <div class="alert alert-warning small py-2"><i class="bi bi-exclamation-triangle me-1"></i>
        Os planos de referência fornecidos são <strong>simplificados para demonstração</strong> e devem ser validados face aos planos curriculares oficiais em vigor.
        Uma escola pode criar o seu próprio plano (por exemplo, copiando a referência); o plano da escola tem prioridade sobre a referência provincial.
    </div>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Classe</label>
            <select name="classe_id" class="form-select" data-auto-submit>
                <option value="">Todas</option>
                @foreach ($classes as $c)<option value="{{ $c->id }}" @selected(request('classe_id') == $c->id)>{{ $c->nome_completo }}</option>@endforeach
            </select>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Plano</th><th>Classe</th><th>Curso</th><th>Vigência</th><th class="text-end">Disciplinas</th><th>Âmbito</th><th></th></tr></thead>
                <tbody>
                @forelse ($planos as $p)
                    <tr class="{{ $p->activo ? '' : 'text-secondary' }}">
                        <td><a href="{{ route('academico.planos.show', $p->id) }}" class="fw-semibold text-decoration-none">{{ $p->nome }}</a></td>
                        <td class="small">{{ $p->classe->nome }}<br><span class="text-secondary">{{ $p->classe->nivelEnsino->nome }}</span></td>
                        <td class="small">{{ $p->curso?->nome ?? '—' }}</td>
                        <td class="small">{{ $p->anoLectivo?->designacao ?? 'Vigente' }}</td>
                        <td class="text-end">{{ $p->disciplinas_count }}</td>
                        <td>@if ($p->escola_id)<span class="badge badge-suave">Escola</span>@else<span class="badge text-bg-light border">Referência</span>@endif</td>
                        <td class="accoes">
                            <a href="{{ route('academico.planos.show', $p->id) }}" class="btn btn-light btn-icon" title="Ver"><i class="bi bi-eye"></i></a>
                            @if ($controlador->podeEditar($p->escola_id))
                                <a href="{{ route('academico.planos.edit', $p->id) }}" class="btn btn-light btn-icon" title="Editar"><i class="bi bi-pencil"></i></a>
                            @endif
                            @if (! $p->escola_id && $escolaActiva && $controlador->podeEditar($escolaActiva->id))
                                <a href="{{ route('academico.planos.create', ['copiar' => $p->id]) }}" class="btn btn-light btn-icon" title="Criar plano da escola a partir desta referência"><i class="bi bi-copy"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tabela-vazia"><i class="bi bi-list-check"></i>Sem planos curriculares.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
