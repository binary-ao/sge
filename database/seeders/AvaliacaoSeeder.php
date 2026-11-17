<?php

namespace Database\Seeders;

use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\ConfiguracaoAvaliacao;
use App\Models\Disciplina;
use App\Models\EscalaClassificacao;
use App\Models\HistoricoConfiguracao;
use App\Models\ModeloFormula;
use App\Models\NivelEnsino;
use App\Models\SistemaAvaliacao;
use App\Services\Avaliacao\GestorConfiguracoes;
use Illuminate\Database\Seeder;

/**
 * Configurações iniciais do motor de avaliação, tendo como referência o
 * Decreto Executivo n.º 424/25 (Anexo III — fórmulas; escalas de classificação).
 *
 * São CONFIGURAÇÕES INICIAIS EDITÁVEIS na interface. Os parâmetros das regras de
 * transição e de acesso a exames estão assinalados para validação face ao regulamento.
 */
class AvaliacaoSeeder extends Seeder
{
    private const MOTIVO = 'Configuração inicial de referência (Decreto Executivo n.º 424/25). Valores a validar pela Direcção Provincial.';

    private array $escalas = [];
    private array $modelos = [];

    public function run(GestorConfiguracoes $gestor): void
    {
        $this->escalas();
        $this->modelos();

        $sistemas = [
            'PRE' => SistemaAvaliacao::updateOrCreate(['nome' => 'Educação Pré-Escolar'], [
                'descricao' => 'Avaliação exclusivamente qualitativa, sem condições de não progressão.', 'base_legal' => 'Decreto Executivo n.º 424/25', 'activo' => true]),
            'PRIM' => SistemaAvaliacao::updateOrCreate(['nome' => 'Ensino Primário'], [
                'descricao' => '1.º e 2.º ciclos: avaliação qualitativa. 3.º ciclo: qualitativa e quantitativa. Escala de 1 a 10.', 'base_legal' => 'Decreto Executivo n.º 424/25', 'activo' => true]),
            'ESG' => SistemaAvaliacao::updateOrCreate(['nome' => 'Ensino Secundário Geral'], [
                'descricao' => 'Classificação essencialmente quantitativa, numa escala de 0 a 20.', 'base_legal' => 'Decreto Executivo n.º 424/25', 'activo' => true]),
            'ETP' => SistemaAvaliacao::updateOrCreate(['nome' => 'Ensino Técnico-Profissional (configuração própria)'], [
                'descricao' => 'Modalidade não abrangida automaticamente pelo regulamento do Ensino Geral. Configuração própria, sujeita à validação das normas aplicáveis.', 'base_legal' => 'A validar', 'activo' => true]),
            'ADUL' => SistemaAvaliacao::updateOrCreate(['nome' => 'Educação de Adultos'], [
                'descricao' => 'Escala do Ensino Primário Regular e de Adultos.', 'base_legal' => 'Decreto Executivo n.º 424/25', 'activo' => true]),
        ];

        foreach (AnoLectivo::orderBy('data_inicio')->get() as $ano) {
            foreach ($this->definicoes($sistemas) as $def) {
                $existe = ConfiguracaoAvaliacao::where('ano_lectivo_id', $ano->id)->where('nome', $def['nome'])->exists();
                if (! $existe) {
                    $gestor->guardar(new ConfiguracaoAvaliacao, $def + ['ano_lectivo_id' => $ano->id], self::MOTIVO);
                }
            }
        }
    }

    private function escalas(): void
    {
        $definicoes = [
            'PRIM' => ['Ensino Primário Regular e de Adultos (1 a 10)', 0, 10, [
                [9, 10, 'Excelente', 'E', 10], [7, 8.99, 'Bom', 'B', 8], [5, 6.99, 'Suficiente', 'S', 6], [0, 4.99, 'Insuficiente', 'I', 3],
            ], 'Escala do Anexo IV: 9–10 Excelente; 7–8 Bom; 5–6 Suficiente; 1–4 Insuficiente.'],
            'ESG' => ['Ensino Secundário Geral Regular e de Adultos (0 a 20)', 0, 20, [
                [17, 20, 'Excelente', 'E', 18], [14, 16.99, 'Bom', 'B', 15], [10, 13.99, 'Suficiente', 'S', 12], [6, 9.99, 'Insuficiente', 'I', 8], [0, 5.99, 'Mau', 'M', 4],
            ], 'Escala do Anexo IV: 17–20 Excelente; 14–16 Bom; 10–13 Suficiente; 6–9 Insuficiente; até 5 Mau.'],
            'PRE' => ['Educação Pré-Escolar (qualitativa)', 0, 10, [
                [7, 10, 'Adquirido', 'A', 9], [5, 6.99, 'Em aquisição', 'EA', 6], [0, 4.99, 'Não adquirido', 'NA', 3],
            ], 'PROPOSTA para demonstração (níveis qualitativos a validar). A escala numérica é apenas interna, para agregação.'],
        ];

        foreach ($definicoes as $chave => [$nome, $min, $max, $intervalos, $descricao]) {
            $escala = EscalaClassificacao::updateOrCreate(['nome' => $nome], ['nota_minima' => $min, 'nota_maxima' => $max, 'descricao' => $descricao, 'activo' => true]);
            if (! $escala->intervalos()->exists()) {
                foreach ($intervalos as $i => [$a, $b, $rotulo, $sigla, $ref]) {
                    $escala->intervalos()->create(['minimo' => $a, 'maximo' => $b, 'rotulo' => $rotulo, 'sigla' => $sigla, 'valor_referencia' => $ref, 'ordem' => $i + 1]);
                }
                HistoricoConfiguracao::registar($escala->fresh(), 'escala', 'criacao', self::MOTIVO);
            }
            $this->escalas[$chave] = $escala->id;
        }
    }

    private function modelos(): void
    {
        $lista = [
            ['A', 'MACT', 'Média das Avaliações Contínuas (MAC)', 'MEDIA(AC)', 'trimestral', 'MAC = (x1 + x2 + … + xN) / N', 'Todas as classes'],
            ['B', 'MT', 'Média Trimestral (classes de transição)', '(MACT + NPT) / 2', 'trimestral', 'MT = (MACr + NPT) / 2', 'Classes de transição; 1.º e 2.º trimestres das classes de Exame Nacional'],
            ['C', 'MFD', 'Média Final da Disciplina (classes de transição)', '(MT1 + MT2 + MT3) / 3', 'anual', 'MFD = (MT1 + MT2 + MT3) / 3', 'Classes de transição'],
            ['D', 'MACA', 'Média anual das avaliações contínuas — MAC(A)', '(MACT1 + MACT2 + MACT3) / 3', 'anual', 'MAC(A) = (MACT1 + MACT2 + MACT3) / 3', 'Disciplinas com exame combinado'],
            ['E1', 'MEC', 'Média do Exame Combinado (escrito e oral)', '(NEE + NEO) / 2', 'anual', 'MEC = (NEE + NEO) / 2', 'Disciplinas com exame escrito e oral'],
            ['E2', 'MEC', 'Média do Exame Combinado (oral e prático)', '(NEO + NEP) / 2', 'anual', 'MEC = (NEO + NEP) / 2', 'Disciplinas com exame oral e prático'],
            ['F', 'MFD', 'Média Final com exame combinado', '0,6 * MACA + 0,4 * MEC', 'anual', 'MFD = 0,6 × MAC(A) + 0,4 × MEC', 'Disciplinas com exame combinado'],
            ['G', 'MT3', 'Média do 3.º trimestre (classes de Exame Nacional)', '(MT1 + MT2 + MACT3) / 3', 'anual', 'MT3 = (MT1 + MT2 + MACT3) / 3', '6.ª, 9.ª e 12.ª classes'],
            ['H1', 'MFED', 'Média Final com Exame Nacional (6.ª e 9.ª)', '0,6 * MT3 + 0,4 * EN', 'anual', 'MFED = 0,6 × MT3 + 0,4 × EN', '6.ª e 9.ª classes'],
            ['H2', 'MFED', 'Média Final com Exame Nacional (12.ª)', '0,5 * MT3 + 0,5 * EN', 'anual', 'MFED = 0,5 × MT3 + 0,5 × EN', '12.ª classe'],
        ];
        foreach ($lista as [$ref, $codigo, $nome, $expr, $nivel, $descricao, $aplicacao]) {
            $m = ModeloFormula::firstOrNew(['referencia' => $ref]);
            if (! $m->exists) {
                $m->fill(['codigo' => $codigo, 'nome' => $nome, 'expressao' => $expr, 'nivel' => $nivel,
                    'descricao' => 'Anexo III — '.$descricao, 'aplicacao' => $aplicacao, 'versao' => 1, 'activo' => true])->save();
                HistoricoConfiguracao::registar($m, 'modelo_formula', 'criacao', self::MOTIVO);
            }
            $this->modelos[$ref] = $m->id;
        }
    }

    /** Definições das configurações por nível / regime. */
    private function definicoes(array $s): array
    {
        $nivel = NivelEnsino::pluck('id', 'codigo');
        $classes = fn (string $niv, array $chaves) => Classe::where('nivel_ensino_id', $nivel[$niv])
            ->where(fn ($q) => $q->whereIn('numero', array_filter($chaves, 'is_int'))->orWhereIn('nome', array_filter($chaves, 'is_string')))
            ->pluck('id')->all();

        $ac = fn (float $max) => ['codigo' => 'AC', 'nome' => 'Avaliações contínuas', 'tipo' => 'lista', 'momento' => 'trimestral', 'trimestres' => null, 'itens_previstos' => 3, 'nota_minima' => 0, 'nota_maxima' => $max];
        $npt = fn (float $max, ?string $t = null) => ['codigo' => 'NPT', 'nome' => 'Prova trimestral', 'tipo' => 'valor', 'momento' => 'trimestral', 'trimestres' => $t, 'itens_previstos' => 1, 'nota_minima' => 0, 'nota_maxima' => $max];
        $en = fn (float $max) => ['codigo' => 'EN', 'nome' => 'Exame Nacional', 'tipo' => 'valor', 'momento' => 'exame', 'trimestres' => null, 'itens_previstos' => 1, 'nota_minima' => 0, 'nota_maxima' => $max];
        $f = fn (string $ref, bool $arred, ?string $trim = null, ?string $codigo = null) => (function () use ($ref, $arred, $trim, $codigo) {
            $m = ModeloFormula::find($this->modelos[$ref]);

            return ['codigo' => $codigo ?? $m->codigo, 'nome' => $m->nome, 'expressao' => $m->expressao, 'nivel' => $m->nivel,
                'trimestres' => $trim, 'arredondar' => $arred, 'modelo_formula_id' => $m->id];
        })();
        $regra = fn (string $tipo, array $p = [], bool $activo = true) => ['tipo' => $tipo, 'parametros' => $p, 'activo' => $activo];

        $transicao = fn (float $max) => [$f('A', false), $f('B', true), $f('C', true), $f('D', false)];
        $exame = fn (string $h) => [$f('A', false), $f('B', true, '1,2'), $f('G', true), $f($h, true)];
        $freq = $regra('frequencia_minima', ['percentagem' => 66.67]);
        $base = fn (string $niv, string $sis, string $escala, string $tipo, float $min, string $final) => [
            'sistema_avaliacao_id' => $s[$sis]->id, 'nivel_ensino_id' => $nivel[$niv], 'escala_id' => $this->escalas[$escala],
            'tipo_classificacao' => $tipo, 'nota_minima_aprovacao' => $min, 'arredondamento' => 'unidade',
            'variavel_trimestral' => 'MT', 'variavel_final' => $final, 'estado' => 'activa',
        ];
        $ciclo = fn (string $nome) => \App\Models\Ciclo::where('nome', $nome)->value('id');

        return [
            array_merge($base('PRE', 'PRE', 'PRE', 'qualitativa', 5, 'MFD'), ['variavel_trimestral' => 'MACT']) + [
                'nome' => 'Pré-Escolar — Iniciação', 'descricao' => 'Avaliação qualitativa, sem retenção.',
                'classes' => $classes('PRE', ['Iniciação']),
                'componentes' => [$ac(10)], 'formulas' => [$f('A', true), $f('D', true, null, 'MFD')],
                'regras' => [$regra('progressao_automatica')],
            ],
            $base('PRIM', 'PRIM', 'PRIM', 'qualitativa', 5, 'MFD') + [
                'nome' => 'Primário — 1.º e 2.º ciclos (1.ª a 4.ª classe)', 'descricao' => 'Avaliação exclusivamente qualitativa; lançamento por níveis de desempenho.',
                'classes' => $classes('PRIM', [1, 2, 3, 4]),
                'componentes' => [$ac(10), $npt(10)], 'formulas' => $transicao(10),
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 5])],
            ],
            $base('PRIM', 'PRIM', 'PRIM', 'mista', 5, 'MFD') + [
                'nome' => 'Primário — 3.º ciclo, 5.ª classe (transição)', 'ciclo_id' => $ciclo('3.º Ciclo do Ensino Primário'),
                'classes' => $classes('PRIM', [5]),
                'componentes' => [$ac(10), $npt(10)], 'formulas' => $transicao(10),
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 5])],
            ],
            $base('PRIM', 'PRIM', 'PRIM', 'mista', 5, 'MFED') + [
                'nome' => 'Primário — 6.ª classe (Exame Nacional)', 'ciclo_id' => $ciclo('3.º Ciclo do Ensino Primário'),
                'classes' => $classes('PRIM', [6]),
                'componentes' => [$ac(10), $npt(10, '1,2'), $en(10)], 'formulas' => $exame('H1'),
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 5]),
                    $regra('exame_recurso', ['max_disciplinas' => 2, 'nota_minima' => 3, 'regimes' => ['exame_nacional']])],
            ],
            $base('ESG1', 'ESG', 'ESG', 'quantitativa', 10, 'MFD') + [
                'nome' => 'I Ciclo — 7.ª e 8.ª classes (transição)',
                'classes' => $classes('ESG1', [7, 8]),
                'componentes' => [$ac(20), $npt(20)], 'formulas' => $transicao(20),
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 10]),
                    $regra('transicao_condicional', ['max_disciplinas' => 2, 'nota_minima' => 7, 'nota_maxima' => 9, 'excluir_nucleares' => true, 'excluir_especificas' => true, 'regimes' => ['transicao']])],
            ],
            $base('ESG1', 'ESG', 'ESG', 'quantitativa', 10, 'MFED') + [
                'nome' => 'I Ciclo — 9.ª classe (Exame Nacional)',
                'classes' => $classes('ESG1', [9]),
                'componentes' => [$ac(20), $npt(20, '1,2'), $en(20)], 'formulas' => $exame('H1'),
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 10]),
                    $regra('exame_recurso', ['max_disciplinas' => 2, 'nota_minima' => 7, 'regimes' => ['exame_nacional']]),
                    $regra('exame_extraordinario', ['max_disciplinas' => 3, 'regimes' => ['exame_nacional']])],
            ],
            $base('ESG2', 'ESG', 'ESG', 'quantitativa', 10, 'MFD') + [
                'nome' => 'II Ciclo — 10.ª e 11.ª classes (transição)',
                'classes' => $classes('ESG2', [10, 11]),
                'componentes' => [$ac(20), $npt(20)], 'formulas' => $transicao(20),
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 10]),
                    $regra('transicao_condicional', ['max_disciplinas' => 2, 'nota_minima' => 7, 'nota_maxima' => 9, 'excluir_nucleares' => true, 'excluir_especificas' => true, 'regimes' => ['transicao']])],
            ],
            $base('ESG2', 'ESG', 'ESG', 'quantitativa', 10, 'MFED') + [
                'nome' => 'II Ciclo — 12.ª classe (Exame Nacional)',
                'classes' => $classes('ESG2', [12]),
                'componentes' => [$ac(20), $npt(20, '1,2'), $en(20)], 'formulas' => $exame('H2'),
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 10]),
                    $regra('exame_recurso', ['max_disciplinas' => 2, 'nota_minima' => 7, 'regimes' => ['exame_nacional']]),
                    $regra('exame_extraordinario', ['max_disciplinas' => 3, 'regimes' => ['exame_nacional']]),
                    $regra('melhoria_nota', ['nota_maxima' => 13, 'regimes' => ['exame_nacional']])],
            ],
            array_merge($base('ESG2', 'ESG', 'ESG', 'quantitativa', 10, 'MFD'), ['estado' => 'rascunho']) + [
                'nome' => 'II Ciclo — exame combinado escrito e oral (exemplo)',
                'descricao' => 'EXEMPLO em rascunho: disciplina com exame combinado (modelos D, E e F do Anexo III). Confirmar no regulamento as disciplinas abrangidas antes de activar.',
                'disciplina_id' => Disciplina::whereNull('escola_id')->where('sigla', 'ING')->value('id'),
                'classes' => $classes('ESG2', [10, 11]),
                'componentes' => [$ac(20), $npt(20),
                    ['codigo' => 'NEE', 'nome' => 'Exame escrito', 'tipo' => 'valor', 'momento' => 'exame', 'trimestres' => null, 'itens_previstos' => 1, 'nota_minima' => 0, 'nota_maxima' => 20],
                    ['codigo' => 'NEO', 'nome' => 'Exame oral', 'tipo' => 'valor', 'momento' => 'exame', 'trimestres' => null, 'itens_previstos' => 1, 'nota_minima' => 0, 'nota_maxima' => 20]],
                'formulas' => [$f('A', false), $f('B', true), $f('D', false), $f('E1', false), $f('F', true)],
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 10])],
            ],
            $base('ETP', 'ETP', 'ESG', 'quantitativa', 10, 'MFD') + [
                'nome' => 'Técnico-Profissional — 10.ª a 13.ª classes',
                'descricao' => 'Configuração própria (a validar com as normas do Ensino Técnico-Profissional).',
                'classes' => $classes('ETP', [10, 11, 12, 13]),
                'componentes' => [$ac(20), $npt(20)], 'formulas' => $transicao(20),
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 10]),
                    $regra('transicao_condicional', ['max_disciplinas' => 2, 'nota_minima' => 7, 'nota_maxima' => 9, 'excluir_nucleares' => true, 'excluir_especificas' => true, 'regimes' => ['transicao']]),
                    $regra('exame_recurso', ['max_disciplinas' => 2, 'nota_minima' => 7, 'regimes' => ['terminal']])],
            ],
            $base('ADUL', 'ADUL', 'PRIM', 'mista', 5, 'MFD') + [
                'nome' => 'Educação de Adultos — Alfabetização e Pós-Alfabetização',
                'classes' => $classes('ADUL', ['Alfabetização — Módulo 1', 'Alfabetização — Módulo 2', 'Pós-Alfabetização']),
                'componentes' => [$ac(10), $npt(10)], 'formulas' => $transicao(10),
                'regras' => [$freq, $regra('nota_minima_disciplina', ['nota' => 5])],
            ],
        ];
    }
}
