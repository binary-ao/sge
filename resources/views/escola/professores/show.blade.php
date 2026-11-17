<x-app-layout :titulo="$professor->nome">
    <x-cabecalho :titulo="$professor->nome" :subtitulo="$professor->especialidade"
        :migalhas="['Gestão Académica' => null, 'Professores' => route('professores.index'), $professor->nome => null]">
        @can('professores.gerir')
            <form method="POST" action="{{ route('professores.estado', $professor) }}"
                  data-confirmar="{{ $professor->activo ? 'Marcar '.$professor->nome.' como inactivo? Deixará de poder receber novas atribuições.' : 'Reactivar '.$professor->nome.'?' }}">
                @csrf @method('PATCH')
                <button class="btn btn-light">{{ $professor->activo ? 'Marcar como inactivo' : 'Reactivar' }}</button>
            </form>
            <a href="{{ route('professores.edit', $professor) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
        @endcan
    </x-cabecalho>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Ficha do professor</h2>
                    <span class="estado text-{{ $professor->activo ? 'success' : 'secondary' }}">{{ $professor->activo ? 'Activo' : 'Inactivo' }}</span></div>
                <div class="card-body">
                    <dl class="lista-detalhes">
                        <dt>Nome completo</dt><dd>{{ $professor->nome }}</dd>
                        <dt>Género</dt><dd>{{ $professor->genero === 'F' ? 'Feminino' : 'Masculino' }}</dd>
                        <dt>Data de nascimento</dt><dd>{{ $professor->data_nascimento?->format('d/m/Y') ?? '—' }}</dd>
                        <dt>N.º de identificação</dt><dd><code>{{ $professor->numero_identificacao }}</code></dd>
                        <dt>Telefone</dt><dd>{{ $professor->telefone ?? '—' }}</dd>
                        <dt>E-mail</dt><dd class="text-break">{{ $professor->email ?? '—' }}</dd>
                        <dt>Habilitações</dt><dd>{{ $professor->habilitacoes_label }}</dd>
                        <dt>Especialidade</dt><dd>{{ $professor->especialidade ?? '—' }}</dd>
                        <dt>Situação profissional</dt><dd>{{ $professor->situacao_label }}</dd>
                        <dt>Data de admissão</dt><dd>{{ $professor->data_admissao?->format('d/m/Y') ?? '—' }}</dd>
                        <dt>Conta de acesso</dt><dd>{{ $professor->utilizador?->email ?? 'Sem conta associada' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Disciplinas e turmas atribuídas — {{ $ano?->designacao }}</h2>
                    <span class="small text-secondary">{{ $professor->atribuicoes->count() }}</span></div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Turma</th><th>Disciplina</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($professor->atribuicoes->sortBy('turma.designacao') as $a)
                            <tr>
                                <td class="fw-semibold">{{ $a->turma->designacao }}</td>
                                <td>{{ $a->disciplina->nome }}</td>
                                <td class="accoes">@can('turmas.ver')<a href="{{ route('turmas.show', $a->turma) }}" class="btn btn-light btn-icon"><i class="bi bi-arrow-right"></i></a>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="tabela-vazia py-4">Sem atribuições neste ano lectivo. As atribuições são feitas na página de cada turma.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($turmasDirigidas->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="titulo">Director de turma</h2></div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        @foreach ($turmasDirigidas as $t)<span class="badge badge-suave fs-6 fw-normal">{{ $t->designacao }}</span>@endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
