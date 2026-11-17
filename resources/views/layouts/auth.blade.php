<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Entrar') — {{ config('sige.nome_curto') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('libs/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('libs/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('sige.versao') }}">
</head>
<body>
<div class="pagina-auth">
    <section class="auth-institucional">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ asset('img/binary-simbolo-branco.png') }}" alt="Binary" width="64" height="36">
            <div>
                <div class="fw-semibold">{{ config('sige.entidade') }}</div>
                <div class="small" style="color:#9FB3C8">República de Angola · Província do Bengo</div>
            </div>
        </div>
        <div>
            <h1>Sistema Integrado de Gestão Escolar</h1>
            <p class="mt-3">Plataforma centralizada para a gestão, monitorização e acompanhamento das instituições de ensino da província.</p>
            <div class="pilares">
                <div class="pilar"><i class="bi bi-diagram-3"></i><div><strong class="d-block text-white">Rede escolar</strong>Província, municípios e escolas numa única plataforma.</div></div>
                <div class="pilar"><i class="bi bi-journal-check"></i><div><strong class="d-block text-white">Gestão pedagógica</strong>Alunos, matrículas, turmas, avaliações e pautas.</div></div>
                <div class="pilar"><i class="bi bi-bar-chart-line"></i><div><strong class="d-block text-white">Indicadores</strong>Dados das escolas a alimentar a monitorização provincial.</div></div>
            </div>
        </div>
        <div class="d-flex align-items-end gap-3 small" style="color:#7F95AE">
            <img src="{{ asset('img/binary-logo-branco.png') }}" alt="{{ config('sige.desenvolvimento') }}" width="120" height="83" style="opacity:.9">
            <span>Desenvolvido por {{ config('sige.desenvolvimento') }}<br>Versão {{ config('sige.versao') }} (MVP)</span>
        </div>
    </section>
    <section class="auth-formulario">
        <div class="auth-caixa">
            <img src="{{ asset('img/binary-logo.png') }}" alt="{{ config('sige.desenvolvimento') }}" class="auth-logo mb-4" width="150" height="104">
            @yield('conteudo')
            @if (config('sige.modo_demonstracao'))
                <div class="alert alert-warning small mt-4 mb-0 py-2"><i class="bi bi-info-circle me-1"></i>Ambiente de demonstração com dados fictícios.</div>
            @endif
        </div>
    </section>
</div>
<script src="{{ asset('libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
