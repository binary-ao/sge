<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('codigo') — {{ config('sige.nome_curto', 'SIGE Bengo') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('libs/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center px-3" style="max-width: 520px">
        <img src="{{ asset('img/binary-logo.png') }}" alt="Binary" class="d-block mx-auto mb-3" width="120" height="83">
        <div class="display-5 fw-bold" style="color:#0B2A4A">@yield('codigo')</div>
        <h1 class="h5 fw-semibold mt-2">@yield('titulo')</h1>
        <p class="text-secondary">@yield('mensagem')</p>
        <div class="d-flex gap-2 justify-content-center mt-3">
            <a href="javascript:history.back()" class="btn btn-light">Voltar</a>
            <a href="{{ url('/') }}" class="btn btn-primary">Página inicial</a>
        </div>
        <p class="small text-secondary mt-4">{{ config('sige.nome_completo', 'Sistema Integrado de Gestão Escolar') }} · {{ config('sige.entidade') }}</p>
    </div>
</body>
</html>
