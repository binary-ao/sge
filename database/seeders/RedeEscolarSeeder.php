<?php

namespace Database\Seeders;

use App\Models\Escola;
use App\Models\Municipio;
use App\Models\NivelEnsino;
use App\Models\Provincia;
use App\Models\TipologiaEscola;
use Illuminate\Database\Seeder;

/**
 * Província do Bengo, municípios, catálogos da rede escolar e escolas.
 *
 * ATENÇÃO: as escolas, coordenadas e restantes dados aqui criados são
 * DEMONSTRATIVOS e fictícios. Não representam estatísticas oficiais.
 */
class RedeEscolarSeeder extends Seeder
{
    public function run(): void
    {
        $bengo = Provincia::updateOrCreate(['codigo' => 'BGO'], ['nome' => 'Bengo', 'capital' => 'Caxito', 'activo' => true]);

        // Coordenadas aproximadas das sedes municipais (demonstrativas).
        $municipios = [
            ['codigo' => 'BGO-DAN', 'nome' => 'Dande', 'sede' => 'Caxito', 'latitude' => -8.5785, 'longitude' => 13.6643],
            ['codigo' => 'BGO-AMB', 'nome' => 'Ambriz', 'sede' => 'Ambriz', 'latitude' => -7.8550, 'longitude' => 13.1270],
            ['codigo' => 'BGO-NAM', 'nome' => 'Nambuangongo', 'sede' => 'Muxaluando', 'latitude' => -8.0190, 'longitude' => 14.1990],
            ['codigo' => 'BGO-DEM', 'nome' => 'Dembos', 'sede' => 'Quibaxe', 'latitude' => -8.4950, 'longitude' => 14.5880],
            ['codigo' => 'BGO-PAL', 'nome' => 'Pango Aluquém', 'sede' => 'Pango Aluquém', 'latitude' => -8.6830, 'longitude' => 14.5350],
            ['codigo' => 'BGO-BAT', 'nome' => 'Bula Atumba', 'sede' => 'Bula Atumba', 'latitude' => -8.4170, 'longitude' => 14.7330],
        ];
        $m = [];
        foreach ($municipios as $dados) {
            $m[$dados['nome']] = Municipio::updateOrCreate(['codigo' => $dados['codigo']], $dados + ['provincia_id' => $bengo->id, 'activo' => true]);
        }

        $tipologias = [
            ['Escola do Ensino Primário', 'EP'],
            ['Complexo Escolar', 'CE'],
            ['Escola do I Ciclo do Ensino Secundário', 'ESIC'],
            ['Liceu / Escola do II Ciclo do Ensino Secundário', 'LIC'],
            ['Escola Técnico-Profissional', 'ETP'],
            ['Instituto Médio Técnico', 'IMT'],
            ['Escola de Formação de Professores', 'EFP'],
            ['Escola de Formação Especial', 'EFE'],
            ['Centro de Educação de Adultos', 'CEA'],
            ['Outra tipologia', 'OUT'],
        ];
        $t = [];
        foreach ($tipologias as $i => [$nome, $sigla]) {
            $t[$sigla] = TipologiaEscola::updateOrCreate(['nome' => $nome], ['sigla' => $sigla, 'ordem' => $i + 1, 'activo' => true]);
        }

        $niveis = [
            ['PRE', 'Educação Pré-Escolar / Iniciação'],
            ['PRIM', 'Ensino Primário'],
            ['ESG1', 'I Ciclo do Ensino Secundário'],
            ['ESG2', 'II Ciclo do Ensino Secundário Geral'],
            ['ETP', 'Ensino Técnico-Profissional'],
            ['ADUL', 'Educação de Adultos'],
            ['ESP', 'Educação Especial'],
        ];
        $n = [];
        foreach ($niveis as $i => [$codigo, $nome]) {
            $n[$codigo] = NivelEnsino::updateOrCreate(['codigo' => $codigo], ['nome' => $nome, 'ordem' => $i + 1, 'activo' => true]);
        }

        // Escolas demonstrativas (nomes e dados fictícios).
        $escolas = [
            ['BGO-DAN-0101', 'Escola Primária n.º 101 — Caxito', 'Dande', 'EP', 'publica', ['PRE', 'PRIM'], 'Caxito', 'Bairro Kicabo', -8.5741, 13.6578, 1985],
            ['BGO-DAN-0205', 'Complexo Escolar n.º 205 — Mabubas', 'Dande', 'CE', 'publica', ['PRIM', 'ESG1', 'ESG2'], 'Mabubas', 'Centro', -8.5402, 13.7495, 2008],
            ['BGO-DAN-0310', 'Liceu n.º 310 — Caxito', 'Dande', 'LIC', 'publica', ['ESG2'], 'Caxito', 'Bairro Açucareira', -8.5838, 13.6721, 1998],
            ['BGO-DAN-0412', 'Instituto Médio Técnico do Dande', 'Dande', 'IMT', 'publica', ['ETP'], 'Caxito', 'Zona Industrial', -8.5689, 13.6810, 2012],
            ['BGO-AMB-0120', 'Escola do I Ciclo n.º 120 — Ambriz', 'Ambriz', 'ESIC', 'publica', ['ESG1'], 'Ambriz', 'Bairro da Praia', -7.8601, 13.1188, 2003],
            ['BGO-AMB-0501', 'Colégio Privado Horizonte — Ambriz', 'Ambriz', 'CE', 'privada', ['PRE', 'PRIM', 'ESG1'], 'Ambriz', 'Centro', -7.8492, 13.1335, 2015],
            ['BGO-NAM-0045', 'Escola Primária n.º 45 — Muxaluando', 'Nambuangongo', 'EP', 'publica', ['PRIM', 'ADUL'], 'Muxaluando', 'Sede', -8.0215, 14.2042, 1979],
            ['BGO-DEM-0230', 'Complexo Escolar Comparticipado São José — Quibaxe', 'Dembos', 'CE', 'publico_privada', ['PRIM', 'ESG1', 'ESG2'], 'Quibaxe', 'Missão', -8.4918, 14.5921, 1960],
            ['BGO-DEM-0601', 'Escola de Formação de Professores — Quibaxe', 'Dembos', 'EFP', 'publica', ['ETP'], 'Quibaxe', 'Sede', -8.4987, 14.5833, 2010],
            ['BGO-BAT-0033', 'Centro de Educação de Adultos n.º 33 — Bula Atumba', 'Bula Atumba', 'CEA', 'publica', ['ADUL'], 'Bula Atumba', 'Sede', -8.4192, 14.7301, 2006],
        ];

        foreach ($escolas as [$codigo, $nome, $mun, $tip, $natureza, $nivs, $comuna, $bairro, $lat, $lng, $ano]) {
            $escola = Escola::updateOrCreate(['codigo' => $codigo], [
                'nome' => $nome,
                'municipio_id' => $m[$mun]->id,
                'tipologia_id' => $t[$tip]->id,
                'natureza' => $natureza,
                'comuna' => $comuna,
                'bairro' => $bairro,
                'endereco' => "{$bairro}, {$comuna}, {$mun}",
                'latitude' => $lat,
                'longitude' => $lng,
                'coordenadas_demonstrativas' => true,
                'telefone' => '+244 9'.substr(preg_replace('/\D/', '', $codigo), -2).' 000 '.substr(preg_replace('/\D/', '', $codigo), -3),
                'email' => strtolower(str_replace('-', '.', $codigo)).'@escolas.demo.ao',
                'director_nome' => 'Director(a) — dado demonstrativo',
                'estado_funcionamento' => 'em_funcionamento',
                'ano_inicio_actividade' => $ano,
                'numero_salas' => count($nivs) * 6,
                'dados_adicionais' => ['demonstrativo' => true],
            ]);
            $escola->niveisEnsino()->sync(collect($nivs)->map(fn ($c) => $n[$c]->id));
        }
    }
}
