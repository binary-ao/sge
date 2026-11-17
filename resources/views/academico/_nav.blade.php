{{-- Navegação da Configuração Académica --}}
@php
    $separadores = [
        'academico.planos.*' => ['Planos curriculares', 'list-check', 'academico.planos.index'],
        'academico.cursos.*' => ['Cursos', 'mortarboard', 'academico.cursos.index'],
        'academico.turnos.*' => ['Turnos', 'clock', 'academico.turnos.index'],
    ];
    $escolaCtx = app(\App\Support\ContextoEscola::class)->escolaActiva();
@endphp
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" style="scrollbar-width:none">
    @foreach ($separadores as $padrao => [$rotulo, $icone, $rota])
        <li class="nav-item">
            <a class="nav-link text-nowrap {{ request()->routeIs($padrao) ? 'active fw-semibold' : 'text-secondary' }}" href="{{ route($rota) }}">
                <i class="bi bi-{{ $icone }} me-1"></i>{{ $rotulo }}
            </a>
        </li>
    @endforeach
</ul>
<div class="alert alert-light border small py-2 d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle text-primary mt-1"></i>
    <div>
        Cursos e planos curriculares podem ser de referência provincial ou próprios de cada escola; os turnos são referências provinciais.
        Disciplinas, níveis, classes e ciclos, anos lectivos e períodos estão em
        <a href="{{ route('cadastros.disciplinas.index') }}">Cadastros e Configurações</a>.
        @if ($escolaCtx)
            Escola activa: <strong>{{ $escolaCtx->nome }}</strong>.
        @else
            Sem escola activa: está a ver apenas as referências provinciais.
        @endif
    </div>
</div>
