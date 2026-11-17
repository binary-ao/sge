<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($titulo) ? $titulo.' — ' : '' }}{{ config('sige.nome_curto') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('libs/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('libs/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('sige.versao') }}">
    <script>try{if(localStorage.getItem('sige.sidebar.recolhida')==='1'){document.documentElement.classList.add('sidebar-recolhida')}}catch(e){}</script>
    @stack('estilos')
</head>
<body>
    <aside class="sige-sidebar" aria-label="Menu principal">
        <a href="{{ route('inicio') }}" class="marca">
            <img src="{{ asset('img/binary-simbolo-branco.png') }}" alt="Binary" class="marca-simbolo" width="44" height="25">
            <span class="marca-texto">
                <strong>SIGE Bengo</strong>
                <small>Gestão Escolar Integrada</small>
            </span>
        </a>
        <nav>
            @foreach ($menu as $seccao)
                <div class="seccao">
                    <div class="seccao-titulo">{{ $seccao['titulo'] }}</div>
                    @foreach ($seccao['itens'] as $item)
                        <a href="{{ $item['url'] }}" class="item {{ $item['actual'] ? 'actual' : '' }}"
                           title="{{ $item['rotulo'] }}{{ !empty($item['em_desenvolvimento']) ? ' (em desenvolvimento)' : '' }}">
                            <i class="bi bi-{{ $item['icone'] }}"></i>
                            <span class="rotulo">{{ $item['rotulo'] }}</span>
                            @if (!empty($item['em_desenvolvimento']))
                                <span class="etiqueta-dev">Em breve</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
        <div class="rodape">
            <div>{{ config('sige.entidade') }}</div>
            <div class="rodape-binary"><img src="{{ asset('img/binary-simbolo-branco.png') }}" alt="" width="25" height="14"> Desenvolvido por {{ config('sige.desenvolvimento') }}</div>
        </div>
    </aside>
    <div class="sige-sombra"></div>

    <div class="sige-principal">
        @if (config('sige.modo_demonstracao'))
            <div class="faixa-demo"><i class="bi bi-info-circle me-1"></i>Ambiente de demonstração — os dados apresentados são fictícios e não constituem estatísticas oficiais.</div>
        @endif

        <header class="sige-topbar">
            <button type="button" class="btn btn-light btn-icon" data-sige-toggle="sidebar" aria-label="Mostrar ou recolher o menu">
                <i class="bi bi-list fs-5"></i>
            </button>

            @php $ambito = auth()->user()->perfil->ambito; @endphp
            @if ($escolaActiva)
                <div class="contexto-escola d-none d-md-flex" title="Escola activa">
                    <i class="bi bi-building text-primary"></i>
                    <span><strong>{{ $escolaActiva->nome }}</strong> · {{ $escolaActiva->municipio->nome }}</span>
                    @if ($ambito !== 'escola' || auth()->user()->escolas->count() > 1)
                        <a href="{{ route('contexto.escolher') }}" class="ms-1 text-decoration-none small">Alterar</a>
                    @endif
                </div>
            @elseif ($ambito !== 'escola')
                <a href="{{ route('contexto.escolher') }}" class="contexto-escola d-none d-md-flex text-decoration-none">
                    <i class="bi bi-globe2 text-primary"></i>
                    <span>{{ $ambito === 'municipio' ? 'Município: '.auth()->user()->municipio?->nome : 'Visão provincial' }}</span>
                    <span class="small text-primary ms-1">Entrar numa escola</span>
                </a>
            @endif

            <div class="ms-auto d-flex align-items-center gap-2">
                <div class="dropdown">
                    <a href="#" class="utilizador-menu" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar">{{ auth()->user()->iniciais }}</span>
                        <span class="d-flex flex-column">
                            <span class="nome">{{ auth()->user()->nome }}</span>
                            <span class="perfil">{{ auth()->user()->perfil->nome }}</span>
                        </span>
                        <i class="bi bi-chevron-down small text-secondary"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="px-3 py-2 small text-secondary" style="max-width:280px">{{ auth()->user()->ambito_descricao }}</li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('conta.edit') }}"><i class="bi bi-person-circle me-2"></i>A minha conta</a></li>
                        @if ($ambito !== 'escola' || auth()->user()->escolas->count() > 1)
                            <li><a class="dropdown-item" href="{{ route('contexto.escolher') }}"><i class="bi bi-arrow-left-right me-2"></i>Alterar escola activa</a></li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Terminar sessão</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="sige-conteudo">
            {{ $slot }}
        </main>

        <footer class="sige-rodape d-flex flex-wrap justify-content-between gap-2">
            <span>{{ config('sige.nome_completo') }} — {{ config('sige.entidade') }}</span>
            <span class="d-inline-flex align-items-center gap-2">Versão {{ config('sige.versao') }} (MVP) · Desenvolvido por <img src="{{ asset('img/binary-simbolo.png') }}" alt="" width="32" height="18"><strong>{{ config('sige.desenvolvimento') }}</strong></span>
        </footer>
    </div>

    {{-- Notificações --}}
    <div class="zona-toasts">
        @foreach (['sucesso' => ['check-circle-fill', 'text-success', 'toast-sucesso'], 'erro' => ['x-circle-fill', 'text-danger', 'toast-erro'], 'aviso' => ['exclamation-triangle-fill', 'text-warning', 'toast-aviso']] as $tipo => [$icone, $cor, $classe])
            @if (session($tipo))
                <div class="toast {{ $classe }} mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-body d-flex gap-2 align-items-start">
                        <i class="bi bi-{{ $icone }} {{ $cor }} mt-1"></i>
                        <div class="flex-grow-1">{{ session($tipo) }}</div>
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="toast" aria-label="Fechar"></button>
                    </div>
                </div>
            @endif
        @endforeach
        @if ($errors->any())
            <div class="toast toast-erro mb-2" role="alert">
                <div class="toast-body d-flex gap-2 align-items-start">
                    <i class="bi bi-x-circle-fill text-danger mt-1"></i>
                    <div class="flex-grow-1">Verifique os dados do formulário: {{ $errors->count() }} {{ $errors->count() === 1 ? 'campo precisa' : 'campos precisam' }} de correcção.</div>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="toast" aria-label="Fechar"></button>
                </div>
            </div>
        @endif
    </div>

    {{-- Modal de confirmação reutilizável --}}
    <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fs-6 fw-semibold">Confirmar operação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body"><p class="mb-0" data-mensagem></p></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" data-confirmar-btn>Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}?v={{ config('sige.versao') }}"></script>
    @stack('scripts')
</body>
</html>
