<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\CopiaSeguranca;
use App\Http\Controllers\Controller;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CopiaSegurancaController extends Controller
{
    public function index(): View
    {
        $copias = collect(File::glob(CopiaSeguranca::pasta().DIRECTORY_SEPARATOR.'sige_*.zip'))->sortDesc()
            ->map(fn ($f) => ['nome' => basename($f), 'tamanho' => filesize($f), 'data' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($f))])->values();

        return view('admin.copias.index', ['copias' => $copias]);
    }

    public function criar(): RedirectResponse
    {
        $codigo = Artisan::call('sige:backup');

        return $codigo === 0
            ? back()->with('sucesso', 'Cópia de segurança criada.')
            : back()->with('erro', 'Falha na cópia de segurança: '.trim(Artisan::output()));
    }

    public function descarregar(string $ficheiro): BinaryFileResponse
    {
        // Apenas nomes gerados pelo sistema: impede o acesso a outros ficheiros (path traversal).
        abort_unless(preg_match('/^sige_\d{8}_\d{6}\.zip$/', $ficheiro), 404);
        $caminho = CopiaSeguranca::pasta().DIRECTORY_SEPARATOR.$ficheiro;
        abort_unless(is_file($caminho), 404);
        Auditoria::registar('exportar', 'Descarregou a cópia de segurança '.$ficheiro);

        return response()->download($caminho);
    }
}
