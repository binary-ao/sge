{{-- Campo para escolher se um novo registo é de referência provincial ou da escola activa. --}}
@php
    $podeGlobal = auth()->user()->can('academico.global');
    $podeEscola = $escolaActiva && $controlador->podeEditar($escolaActiva->id);
@endphp
@if ($podeGlobal && $podeEscola)
    <div class="form-check mb-3" data-so-criacao>
        <input class="form-check-input" type="checkbox" name="referencia_provincial" value="1" id="ref{{ $id }}" @checked(old('referencia_provincial'))>
        <label class="form-check-label small" for="ref{{ $id }}">Registar como <strong>referência provincial</strong> (disponível para todas as escolas)</label>
    </div>
@elseif ($podeGlobal)
    <div class="alert alert-info small py-2"><i class="bi bi-globe2 me-1"></i>O registo será criado como <strong>referência provincial</strong>, disponível para todas as escolas.</div>
@else
    <div class="alert alert-light border small py-2"><i class="bi bi-building me-1"></i>O registo ficará associado a <strong>{{ $escolaActiva?->nome }}</strong>.</div>
@endif
