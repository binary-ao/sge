<x-app-layout :titulo="$escola->nome">
    <x-cabecalho :titulo="$escola->nome" subtitulo="{{ $escola->codigo }} · {{ $escola->tipologia->nome }}"
        :migalhas="['Cadastros e Configurações' => null, 'Escolas' => route('escolas.index'), $escola->nome => null]">
        @if ($podeEntrar && ! $eActiva && $escola->activo)
            <form method="POST" action="{{ route('contexto.definir') }}">@csrf
                <input type="hidden" name="escola_id" value="{{ $escola->id }}">
                <button class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Entrar na área da escola</button>
            </form>
        @elseif ($eActiva)
            <span class="badge text-bg-success align-self-center px-3 py-2"><i class="bi bi-check2 me-1"></i>Escola activa</span>
        @endif
        @can('escolas.gerir')
            <a href="{{ route('escolas.edit', $escola) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
            <form method="POST" action="{{ route('escolas.estado', $escola) }}"
                  data-confirmar="{{ $escola->activo ? 'Desactivar esta escola? O histórico mantém-se, mas deixa de contar nos indicadores e de estar disponível para operações.' : 'Activar esta escola?' }}"
                  data-confirmar-classe="{{ $escola->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $escola->activo ? 'Desactivar' : 'Activar' }}">
                @csrf @method('PATCH')
                <button class="btn {{ $escola->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $escola->activo ? 'Desactivar' : 'Activar' }}</button>
            </form>
        @endcan
    </x-cabecalho>

    @unless ($escola->activo)
        <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>Este registo está <strong>desactivado</strong>: o histórico mantém-se, mas a escola não conta nos indicadores e não pode ser escolhida como escola activa.</div>
    @endunless

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Identificação institucional</h2>
                    <span class="estado text-{{ $escola->estado_cor }}">{{ $escola->estado_label }}</span></div>
                <div class="card-body">
                    <dl class="lista-detalhes">
                        <dt>Nome oficial</dt><dd>{{ $escola->nome }}</dd>
                        <dt>Código</dt><dd><code>{{ $escola->codigo }}</code></dd>
                        <dt>Tipologia institucional</dt><dd>{{ $escola->tipologia->nome }}</dd>
                        <dt>Natureza</dt><dd>{{ $escola->natureza_label }}</dd>
                        <dt>Província / Município</dt><dd>{{ $escola->municipio->provincia->nome }} · {{ $escola->municipio->nome }}</dd>
                        <dt>Ano de início de actividade</dt><dd>{{ $escola->ano_inicio_actividade ?? '—' }}</dd>
                        <dt>Número de salas</dt><dd>{{ $escola->numero_salas ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Níveis de ensino ministrados</h2>
                    <span class="small text-secondary">{{ $escola->niveisEnsino->count() }}</span></div>
                <div class="card-body d-flex flex-wrap gap-2">
                    @forelse ($escola->niveisEnsino as $n)
                        <span class="badge badge-suave fs-6 fw-normal px-3 py-2"><i class="bi bi-layers me-1"></i>{{ $n->nome }}</span>
                    @empty
                        <span class="text-secondary">Nenhum nível registado.</span>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="titulo">Indicadores da escola</h2><span class="small text-secondary">Ano lectivo {{ $ano?->designacao }}</span></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3"><x-cartao-estatistico rotulo="Alunos activos" :valor="$indicadores->total_alunos"
                            :detalhe="($porGenero['F'] ?? 0).' F · '.($porGenero['M'] ?? 0).' M'" icone="people" /></div>
                        <div class="col-6 col-md-3"><x-cartao-estatistico rotulo="Professores" :valor="$indicadores->total_professores" icone="person-badge" tom="info" /></div>
                        <div class="col-6 col-md-3"><x-cartao-estatistico rotulo="Turmas" :valor="$indicadores->total_turmas" icone="grid-3x3-gap" tom="sucesso" /></div>
                        <div class="col-6 col-md-3"><x-cartao-estatistico rotulo="Matrículas activas" :valor="$matriculasAno" icone="journal-check" tom="alerta" /></div>
                    </div>
                    <p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle"></i> As taxas de aprovação e reprovação serão calculadas com o módulo de Avaliação (Fase 4).</p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Localização</h2></div>
                <div class="card-body">
                    <dl class="lista-detalhes">
                        <dt>Comuna</dt><dd>{{ $escola->comuna ?? '—' }}</dd>
                        <dt>Bairro</dt><dd>{{ $escola->bairro ?? '—' }}</dd>
                        <dt>Endereço</dt><dd>{{ $escola->endereco ?? '—' }}</dd>
                        <dt>Coordenadas</dt>
                        <dd>
                            @if ($escola->latitude !== null)
                                {{ number_format($escola->latitude, 5, ',', '') }}, {{ number_format($escola->longitude, 5, ',', '') }}
                                @if ($escola->coordenadas_demonstrativas)
                                    <span class="badge text-bg-warning ms-1">Demonstrativas</span>
                                @endif
                            @else — @endif
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Contactos e direcção</h2></div>
                <div class="card-body">
                    <dl class="lista-detalhes">
                        <dt>Telefone</dt><dd>{{ $escola->telefone ?? '—' }}</dd>
                        @if ($escola->telefone_alternativo)<dt>Alternativo</dt><dd>{{ $escola->telefone_alternativo }}</dd>@endif
                        <dt>E-mail</dt><dd class="text-break">{{ $escola->email ?? '—' }}</dd>
                        <dt>Director(a)</dt><dd>{{ $escola->director_nome ?? '—' }}</dd>
                        <dt>Tel. director(a)</dt><dd>{{ $escola->director_telefone ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
            @can('utilizadores.gerir')
                <div class="card">
                    <div class="card-header"><h2 class="titulo">Utilizadores atribuídos</h2></div>
                    <ul class="list-group list-group-flush">
                        @forelse ($escola->utilizadores as $u)
                            <li class="list-group-item d-flex justify-content-between align-items-center small">
                                <span>{{ $u->nome }}<br><span class="text-secondary">{{ $u->perfil->nome }}</span></span>
                                @unless ($u->activo)<span class="badge text-bg-secondary">Inactivo</span>@endunless
                            </li>
                        @empty
                            <li class="list-group-item small text-secondary">Nenhum utilizador atribuído.</li>
                        @endforelse
                    </ul>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
