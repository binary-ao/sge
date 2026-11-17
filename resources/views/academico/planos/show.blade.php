<x-app-layout :titulo="$plano->nome">
    <x-cabecalho :titulo="$plano->nome" :subtitulo="$plano->classe->nome_completo.($plano->curso ? ' · '.$plano->curso->nome : '')"
        :migalhas="['Configuração Académica' => route('academico.index'), 'Planos curriculares' => route('academico.planos.index'), $plano->classe->nome => null]">
        @if ($podeEditar)
            <a href="{{ route('academico.planos.edit', $plano->id) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
        @endif
    </x-cabecalho>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <dl class="lista-detalhes">
                        <dt>Âmbito</dt><dd>{{ $plano->escola?->nome ?? 'Referência provincial' }}</dd>
                        <dt>Classe</dt><dd><a href="{{ route('cadastros.classes.show', $plano->classe_id) }}">{{ $plano->classe->nome }}</a></dd>
                        <dt>Nível</dt><dd>{{ $plano->classe->nivelEnsino->nome }}</dd>
                        <dt>Curso</dt><dd>{{ $plano->curso?->nome ?? '—' }}</dd>
                        <dt>Vigência</dt><dd>{{ $plano->anoLectivo?->designacao ?? 'Vigente (todos os anos)' }}</dd>
                        <dt>Estado</dt><dd>{{ $plano->activo ? 'Activo' : 'Inactivo' }}</dd>
                        <dt>Carga semanal</dt><dd>{{ $plano->disciplinas->sum('pivot.carga_horaria') }} tempos</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h2 class="titulo">Disciplinas ({{ $plano->disciplinas->count() }})</h2></div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>#</th><th>Disciplina</th><th>Componente</th><th class="text-end">Tempos/semana</th><th>Observações</th></tr></thead>
                        <tbody>
                        @foreach ($plano->disciplinas as $d)
                            <tr>
                                <td class="text-secondary">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $d->nome }} <code class="ms-1">{{ $d->sigla }}</code></td>
                                <td class="small">{{ $d->componente_label }}</td>
                                <td class="text-end">{{ $d->pivot->carga_horaria ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $d->pivot->obrigatoria ? 'text-bg-primary' : 'text-bg-light border' }}">{{ $d->pivot->obrigatoria ? 'Obrigatória' : 'Opcional' }}</span>
                                    @if ($d->nuclear)<span class="badge text-bg-warning">Nuclear</span>@endif
                                    @if ($d->pivot->especifica)<span class="badge badge-suave">Específica da formação</span>@endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
