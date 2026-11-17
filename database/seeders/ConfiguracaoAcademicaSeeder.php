<?php

namespace Database\Seeders;

use App\Models\AnoLectivo;
use App\Models\Ciclo;
use App\Models\Classe;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\NivelEnsino;
use App\Models\PlanoCurricular;
use App\Models\Turno;
use Illuminate\Database\Seeder;

/**
 * Configuração académica de referência (provincial).
 *
 * Os planos curriculares aqui criados são uma REFERÊNCIA SIMPLIFICADA para a
 * demonstração. Devem ser validados e ajustados aos planos oficiais em vigor,
 * o que pode ser feito na interface sem alterar código.
 */
class ConfiguracaoAcademicaSeeder extends Seeder
{
    public function run(): void
    {
        $this->anosLectivos();
        $this->turnos();
        $classes = $this->estruturaEnsino();
        $cursos = $this->cursos();
        $disc = $this->disciplinas();
        $this->planos($classes, $cursos, $disc);
        $this->niveisDasDisciplinas();
    }

    /**
     * Níveis de ensino de cada disciplina: deduzidos dos planos curriculares de referência
     * e, para as disciplinas que não constam de nenhum plano, indicados explicitamente.
     */
    private function niveisDasDisciplinas(): void
    {
        \Illuminate\Support\Facades\DB::statement('INSERT IGNORE INTO disciplina_nivel_ensino (disciplina_id, nivel_ensino_id)
            SELECT DISTINCT pcd.disciplina_id, c.nivel_ensino_id
            FROM plano_curricular_disciplina pcd
            JOIN planos_curriculares p ON p.id = pcd.plano_curricular_id
            JOIN classes c ON c.id = p.classe_id');

        $semPlano = ['DES' => ['ESG2', 'ETP']];   // Desenho: II Ciclo e Técnico-Profissional
        foreach ($semPlano as $sigla => $niveis) {
            $disciplina = Disciplina::whereNull('escola_id')->where('sigla', $sigla)->first();
            $disciplina?->niveisEnsino()->syncWithoutDetaching(NivelEnsino::whereIn('codigo', $niveis)->pluck('id'));
        }
    }

    private function anosLectivos(): void
    {
        $anos = [
            ['2025/2026', '2025-09-01', '2026-07-31', 'encerrado', false],
            ['2026/2027', '2026-09-01', '2027-07-31', 'em_curso', true],
        ];
        foreach ($anos as [$designacao, $inicio, $fim, $estado, $actual]) {
            $ano = AnoLectivo::updateOrCreate(['designacao' => $designacao],
                ['data_inicio' => $inicio, 'data_fim' => $fim, 'estado' => $estado, 'actual' => $actual]);
            $a = (int) substr($inicio, 0, 4);
            $trimestres = [
                1 => ["{$a}-09-01", "{$a}-12-15"],
                2 => [($a + 1).'-01-05', ($a + 1).'-04-05'],
                3 => [($a + 1).'-04-15', ($a + 1).'-07-15'],
            ];
            foreach ($trimestres as $n => [$i, $f]) {
                $ano->trimestres()->updateOrCreate(['numero' => $n],
                    ['designacao' => ['', 'I', 'II', 'III'][$n].' Trimestre', 'data_inicio' => $i, 'data_fim' => $f]);
            }
        }
    }

    private function turnos(): void
    {
        foreach ([['Manhã', '07:00', '12:30'], ['Tarde', '13:00', '18:30'], ['Noite', '18:30', '22:00']] as $i => [$nome, $ini, $fim]) {
            Turno::updateOrCreate(['nome' => $nome], ['hora_inicio' => $ini, 'hora_fim' => $fim, 'ordem' => $i + 1, 'activo' => true]);
        }
    }

    /** @return array<string, Classe> chave "NIVEL:numero" ou "NIVEL:nome" */
    private function estruturaEnsino(): array
    {
        $n = NivelEnsino::pluck('id', 'codigo');
        NivelEnsino::whereIn('codigo', ['ESG2', 'ETP'])->update(['exige_curso' => true]);

        $ciclo = fn (string $nivel, string $nome, int $ordem) => Ciclo::updateOrCreate(
            ['nivel_ensino_id' => $n[$nivel], 'nome' => $nome], ['ordem' => $ordem, 'activo' => true])->id;

        $estrutura = [
            // [nível, ciclo, [classes: numero => regime]]
            ['PRE', null, ['Iniciação' => [null, 'sem_retencao']]],
            ['PRIM', '1.º Ciclo do Ensino Primário', [1 => 'transicao', 2 => 'transicao']],
            ['PRIM', '2.º Ciclo do Ensino Primário', [3 => 'transicao', 4 => 'transicao']],
            ['PRIM', '3.º Ciclo do Ensino Primário', [5 => 'transicao', 6 => 'exame_nacional']],
            ['ESG1', 'I Ciclo do Ensino Secundário Geral', [7 => 'transicao', 8 => 'transicao', 9 => 'exame_nacional']],
            ['ESG2', 'II Ciclo do Ensino Secundário Geral', [10 => 'transicao', 11 => 'transicao', 12 => 'exame_nacional']],
            ['ETP', 'II Ciclo do Ensino Técnico-Profissional', [10 => 'transicao', 11 => 'transicao', 12 => 'transicao', 13 => 'terminal']],
            ['ADUL', 'Alfabetização', ['Alfabetização — Módulo 1' => [null, 'transicao'], 'Alfabetização — Módulo 2' => [null, 'transicao']]],
            ['ADUL', 'Pós-Alfabetização', ['Pós-Alfabetização' => [null, 'transicao']]],
        ];

        $classes = [];
        $ordemCiclo = [];
        foreach ($estrutura as [$nivel, $nomeCiclo, $lista]) {
            $ordemCiclo[$nivel] = ($ordemCiclo[$nivel] ?? 0) + 1;
            $cicloId = $nomeCiclo ? $ciclo($nivel, $nomeCiclo, $ordemCiclo[$nivel]) : null;
            foreach ($lista as $chave => $valor) {
                [$numero, $nome, $regime] = is_array($valor)
                    ? [$valor[0], $chave, $valor[1]]
                    : [$chave, $chave.'.ª Classe', $valor];
                $classe = Classe::updateOrCreate(['nivel_ensino_id' => $n[$nivel], 'nome' => $nome], [
                    'ciclo_id' => $cicloId, 'numero' => $numero, 'regime' => $regime,
                    'ordem' => $numero ?? (count($classes) + 1), 'activo' => true,
                ]);
                $classes[$nivel.':'.($numero ?? $nome)] = $classe;
            }
        }

        return $classes;
    }

    /** @return array<string, Curso> */
    private function cursos(): array
    {
        $n = NivelEnsino::pluck('id', 'codigo');
        $lista = [
            ['ESG2', 'CFB', 'Ciências Físicas e Biológicas'],
            ['ESG2', 'CEJ', 'Ciências Económicas e Jurídicas'],
            ['ESG2', 'CH', 'Ciências Humanas'],
            ['ETP', 'INF', 'Técnico de Informática'],
            ['ETP', 'CG', 'Técnico de Contabilidade e Gestão'],
            ['ETP', 'FPEP', 'Formação de Professores do Ensino Primário'],
        ];
        $cursos = [];
        foreach ($lista as [$nivel, $codigo, $nome]) {
            $cursos[$codigo] = Curso::updateOrCreate(['escola_id' => null, 'codigo' => $codigo],
                ['nivel_ensino_id' => $n[$nivel], 'nome' => $nome, 'activo' => true]);
        }

        return $cursos;
    }

    /** @return array<string, Disciplina> por sigla */
    private function disciplinas(): array
    {
        $lista = [
            // sigla, nome, nuclear, componente
            ['LP', 'Língua Portuguesa', true, 'geral'],
            ['MAT', 'Matemática', true, 'geral'],
            ['EMEIO', 'Estudo do Meio', false, 'geral'],
            ['CN', 'Ciências da Natureza', false, 'geral'],
            ['HIST', 'História', false, 'geral'],
            ['GEO', 'Geografia', false, 'geral'],
            ['EMC', 'Educação Moral e Cívica', false, 'geral'],
            ['EMP', 'Educação Manual e Plástica', false, 'complementar'],
            ['EMUS', 'Educação Musical', false, 'complementar'],
            ['EF', 'Educação Física', false, 'complementar'],
            ['ING', 'Língua Inglesa', false, 'geral'],
            ['BIO', 'Biologia', false, 'geral'],
            ['FIS', 'Física', false, 'geral'],
            ['QUI', 'Química', false, 'geral'],
            ['EVP', 'Educação Visual e Plástica', false, 'complementar'],
            ['EL', 'Educação Laboral', false, 'complementar'],
            ['FIL', 'Filosofia', false, 'geral'],
            ['INFO', 'Informática', false, 'geral'],
            ['GEOL', 'Geologia', false, 'especifica'],
            ['DES', 'Desenho', false, 'especifica'],
            ['ECON', 'Introdução à Economia', false, 'especifica'],
            ['DIR', 'Introdução ao Direito', false, 'especifica'],
            ['CONT', 'Contabilidade e Gestão', false, 'especifica'],
            ['SOC', 'Sociologia', false, 'especifica'],
            ['LIT', 'Literatura', false, 'especifica'],
            ['EMPR', 'Empreendedorismo', false, 'complementar'],
            ['TLP', 'Técnicas de Linguagens de Programação', false, 'tecnica'],
            ['SEAC', 'Sistemas de Exploração e Arquitectura de Computadores', false, 'tecnica'],
            ['TREI', 'Tecnologias de Redes e Instalações Informáticas', false, 'tecnica'],
            ['CGER', 'Contabilidade Geral', false, 'tecnica'],
            ['FISC', 'Fiscalidade', false, 'tecnica'],
            ['GEST', 'Gestão Empresarial', false, 'tecnica'],
            ['PSI', 'Psicologia do Desenvolvimento e da Aprendizagem', false, 'tecnica'],
            ['PED', 'Pedagogia Geral', false, 'tecnica'],
            ['DLP', 'Didáctica da Língua Portuguesa', false, 'tecnica'],
            ['DMAT', 'Didáctica da Matemática', false, 'tecnica'],
            ['CLING', 'Comunicação Linguística', false, 'geral'],
            ['RMAT', 'Representação Matemática', false, 'geral'],
            ['MFS', 'Meio Físico e Social', false, 'geral'],
            ['EXPM', 'Expressão Motora', false, 'complementar'],
            ['EXPP', 'Expressão Plástica e Musical', false, 'complementar'],
        ];
        $disc = [];
        foreach ($lista as [$sigla, $nome, $nuclear, $componente]) {
            $disc[$sigla] = Disciplina::updateOrCreate(['escola_id' => null, 'sigla' => $sigla],
                ['nome' => $nome, 'nuclear' => $nuclear, 'componente' => $componente, 'activo' => true]);
        }

        return $disc;
    }

    private function planos(array $classes, array $cursos, array $disc): void
    {
        $prim14 = ['LP', 'MAT', 'EMEIO', 'EMC', 'EMP', 'EMUS', 'EF'];
        $prim56 = ['LP', 'MAT', 'CN', 'HIST', 'GEO', 'EMC', 'EMP', 'EMUS', 'EF'];
        $esg1 = ['LP', 'ING', 'MAT', 'BIO', 'FIS', 'QUI', 'GEO', 'HIST', 'EMC', 'EVP', 'EL', 'EF'];
        $troncoEsg2 = ['LP', 'ING', 'MAT', 'FIL', 'INFO', 'EF'];
        $esg2 = [
            'CFB' => [$troncoEsg2, ['FIS', 'QUI', 'BIO', 'GEOL']],
            'CEJ' => [$troncoEsg2, ['ECON', 'DIR', 'CONT', 'GEO']],
            'CH' => [$troncoEsg2, ['HIST', 'GEO', 'SOC', 'LIT']],
        ];
        $troncoEtp = ['LP', 'ING', 'MAT', 'FIS', 'EMPR', 'EF'];
        $etp = [
            'INF' => [$troncoEtp, ['TLP', 'SEAC', 'TREI']],
            'CG' => [$troncoEtp, ['CGER', 'FISC', 'GEST']],
            'FPEP' => [['LP', 'MAT', 'ING', 'HIST', 'EF'], ['PSI', 'PED', 'DLP', 'DMAT']],
        ];

        $criar = function (Classe $classe, ?Curso $curso, array $gerais, array $especificas = []) use ($disc) {
            $nome = 'Plano de referência — '.$classe->nome.($curso ? ' — '.$curso->nome : '');
            $plano = PlanoCurricular::updateOrCreate(
                ['escola_id' => null, 'ano_lectivo_id' => null, 'classe_id' => $classe->id, 'curso_id' => $curso?->id],
                ['nome' => $nome, 'activo' => true]
            );
            $sync = [];
            $ordem = 0;
            foreach ($gerais as $s) {
                $sync[$disc[$s]->id] = ['carga_horaria' => in_array($s, ['LP', 'MAT'], true) ? 5 : 3, 'especifica' => false, 'ordem' => ++$ordem];
            }
            foreach ($especificas as $s) {
                $sync[$disc[$s]->id] = ['carga_horaria' => 4, 'especifica' => true, 'ordem' => ++$ordem];
            }
            $plano->disciplinas()->sync($sync);
        };

        $criar($classes['PRE:Iniciação'], null, ['CLING', 'RMAT', 'MFS', 'EXPM', 'EXPP']);
        foreach ([1, 2, 3, 4] as $c) {
            $criar($classes["PRIM:$c"], null, $prim14);
        }
        foreach ([5, 6] as $c) {
            $criar($classes["PRIM:$c"], null, $prim56);
        }
        foreach ([7, 8, 9] as $c) {
            $criar($classes["ESG1:$c"], null, $esg1);
        }
        foreach ([10, 11, 12] as $c) {
            foreach ($esg2 as $codigo => [$gerais, $esp]) {
                $criar($classes["ESG2:$c"], $cursos[$codigo], $gerais, $esp);
            }
        }
        foreach ([10, 11, 12, 13] as $c) {
            foreach ($etp as $codigo => [$gerais, $esp]) {
                $criar($classes["ETP:$c"], $cursos[$codigo], $gerais, $esp);
            }
        }
        foreach (['Alfabetização — Módulo 1', 'Alfabetização — Módulo 2', 'Pós-Alfabetização'] as $nome) {
            $criar($classes["ADUL:$nome"], null, ['LP', 'MAT', 'CN']);
        }
    }
}
