@props(['id', 'tituloCriar', 'tituloEditar' => null, 'accaoCriar', 'tamanho' => null, 'botao' => 'Guardar'])
{{--
    Modal com formulário de criação/edição.
    Botão de abertura para criar:  data-bs-toggle="modal" data-bs-target="#id"
    Botão de abertura para editar: ... data-accao="URL de update" data-preencher='{"campo": "valor"}'
    Em caso de erro de validação, o modal reabre com os valores submetidos.
--}}
@php $reabrir = $errors->any() && old('_modal') === $id; @endphp
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable {{ $tamanho ? 'modal-'.$tamanho : '' }}">
        <form method="POST" class="modal-content" novalidate data-form-modal
              action="{{ $reabrir ? old('_form_accao', $accaoCriar) : $accaoCriar }}"
              data-accao-criar="{{ $accaoCriar }}"
              data-titulo-criar="{{ $tituloCriar }}" data-titulo-editar="{{ $tituloEditar ?? $tituloCriar }}"
              @if ($reabrir) data-reabrir @endif>
            @csrf
            <input type="hidden" name="_method" value="{{ $reabrir ? old('_method', 'POST') : 'POST' }}">
            <input type="hidden" name="_modal" value="{{ $id }}">
            <input type="hidden" name="_form_accao" value="{{ $reabrir ? old('_form_accao') : '' }}">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-semibold" data-titulo-modal>{{ $reabrir && old('_method') === 'PUT' ? ($tituloEditar ?? $tituloCriar) : $tituloCriar }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">{{ $botao }}</button>
            </div>
        </form>
    </div>
</div>
