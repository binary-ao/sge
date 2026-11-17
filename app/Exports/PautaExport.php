<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Exportação das pautas para Excel, a partir da mesma estrutura de dados das vistas. */
class PautaExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(private string $vista, private array $dados, private string $titulo = 'Pauta') {}

    public function view(): View
    {
        return view($this->vista, $this->dados);
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $this->titulo), 0, 31);
    }
}
