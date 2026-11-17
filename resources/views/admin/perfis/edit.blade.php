<x-app-layout :titulo="'Permissões: '.$perfil->nome">
    <x-cabecalho :titulo="'Permissões: '.$perfil->nome" :subtitulo="'Âmbito: '.$perfil->ambito_label.' — '.$perfil->descricao"
        :migalhas="['Sistema' => null, 'Perfis e Permissões' => route('perfis.index'), $perfil->nome => null]" />

    @if (in_array($perfil->ambito, ['provincia', 'municipio']))
        <div class="alert alert-warning small"><i class="bi bi-shield-exclamation me-1"></i>
            Perfis de âmbito {{ mb_strtolower($perfil->ambito_label) }} são de consulta: as permissões de escrita sobre registos operacionais
            das escolas estão bloqueadas no servidor e não podem ser atribuídas.
        </div>
    @endif

    <form method="POST" action="{{ route('perfis.update', $perfil) }}">
        @csrf @method('PUT')
        <div class="row g-3">
            @php
                $bloqueadas = in_array($perfil->ambito, ['provincia', 'municipio'], true) ? config('permissoes.escrita_operacional') : [];
            @endphp
            @foreach ($modulos as $modulo => $permissoes)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h2 class="titulo">{{ $modulo }}</h2>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-marcar-todos>Todos</button>
                        </div>
                        <div class="card-body">
                            @foreach ($permissoes as $perm)
                                <div class="form-check mb-2">
                                    @php $bloq = in_array($perm->codigo, $bloqueadas, true); @endphp
                                    <input class="form-check-input" type="checkbox" name="permissoes[]" value="{{ $perm->id }}" id="p{{ $perm->id }}"
                                           @checked(! $bloq && in_array($perm->id, $seleccionadas)) @disabled($bloq)>
                                    <label class="form-check-label small {{ $bloq ? 'text-secondary' : '' }}" for="p{{ $perm->id }}">{{ $perm->nome }}
                                        @if ($bloq)<span class="badge text-bg-light border ms-1">Indisponível para este âmbito</span>@endif
                                        <br><code class="text-secondary">{{ $perm->codigo }}</code></label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('perfis.index') }}" class="btn btn-light">Cancelar</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar permissões</button>
        </div>
    </form>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-marcar-todos]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var caixas = b.closest('.card').querySelectorAll('input[type=checkbox]');
                    var todas = Array.prototype.every.call(caixas, function (c) { return c.checked; });
                    caixas.forEach(function (c) { c.checked = !todas; });
                });
            });
        </script>
    @endpush
</x-app-layout>
