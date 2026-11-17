<x-app-layout titulo="Lançamento de notas">
    <x-cabecalho :titulo="$turma->designacao.' · '.$disciplina->nome" :migalhas="['Avaliação' => null, 'Lançamento de notas' => route('avaliacao.lancamento.index'), $turma->designacao => null]" />
    <div class="card"><div class="card-body bloco-desenvolvimento">
        <div class="icone tom-alerta"><i class="bi bi-calculator"></i></div>
        <h2 class="h5">Sem configuração de avaliação activa</h2>
        <p class="text-secondary">Não existe uma configuração de avaliação activa para a {{ $turma->classe->nome }} no ano lectivo {{ $turma->anoLectivo->designacao }}.
            As notas só podem ser lançadas depois de a configuração ser definida e activada em <em>Configuração de Avaliações</em>.</p>
        <a href="{{ route('avaliacao.lancamento.index') }}" class="btn btn-light">Voltar</a>
    </div></div>
</x-app-layout>
