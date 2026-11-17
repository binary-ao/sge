<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\AlunoDocumento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos do aluno, guardados em disco privado (fora da pasta pública)
 * e descarregados apenas através do sistema, com verificação de âmbito.
 */
class AlunoDocumentoController extends Controller
{
    use RestringeProfessor;

    private const DISCO = 'local';

    public function store(Request $request, Aluno $aluno): RedirectResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(AlunoDocumento::TIPOS))],
            'descricao' => ['nullable', 'string', 'max:150'],
            'ficheiro' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], ['ficheiro.mimes' => 'Apenas são aceites ficheiros PDF, JPG ou PNG.'], ['ficheiro' => 'ficheiro']);

        $ficheiro = $request->file('ficheiro');
        $nome = Str::uuid().'.'.$ficheiro->extension();
        $caminho = $ficheiro->storeAs("escolas/{$aluno->escola_id}/alunos/{$aluno->id}", $nome, self::DISCO);

        $aluno->documentos()->create([
            'tipo' => $dados['tipo'],
            'descricao' => $dados['descricao'] ?? null,
            'caminho' => $caminho,
            'nome_original' => mb_substr($ficheiro->getClientOriginalName(), 0, 200),
            'mime' => $ficheiro->getMimeType(),
            'tamanho' => $ficheiro->getSize(),
            'carregado_por' => $request->user()->id,
        ]);

        return back()->with('sucesso', 'Documento carregado.');
    }

    public function download(Aluno $aluno, AlunoDocumento $documento): StreamedResponse
    {
        abort_unless($documento->aluno_id === $aluno->id, 404);
        $this->exigirAlunoPermitido($aluno);
        abort_unless(Storage::disk(self::DISCO)->exists($documento->caminho), 404, 'Ficheiro não encontrado.');

        return Storage::disk(self::DISCO)->download($documento->caminho, $documento->nome_original);
    }

    public function destroy(Aluno $aluno, AlunoDocumento $documento): RedirectResponse
    {
        abort_unless($documento->aluno_id === $aluno->id, 404);
        Storage::disk(self::DISCO)->delete($documento->caminho);
        $documento->delete();

        return back()->with('sucesso', 'Documento removido.');
    }
}
