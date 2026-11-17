<?php

/*
|--------------------------------------------------------------------------
| Motor de avaliação — catálogo de regras e situações
|--------------------------------------------------------------------------
| Este ficheiro define os TIPOS de regra disponíveis e os seus parâmetros.
| Os VALORES dos parâmetros pertencem a cada configuração de avaliação e são
| editados na interface ("Configuração de Avaliações"), com histórico de versões.
|
| Os valores iniciais semeados devem ser validados face ao Decreto Executivo
| n.º 424/25 (Regulamento da Avaliação das Aprendizagens) e respectivos anexos.
*/

return [
    'regras' => [
        'progressao_automatica' => [
            'nome' => 'Progressão automática (sem retenção)',
            'descricao' => 'Todos os alunos progridem. Aplica-se à Educação Pré-Escolar, sem condições de não progressão.',
            'parametros' => [],
        ],
        'frequencia_minima' => [
            'nome' => 'Frequência mínima',
            'descricao' => 'Retém o aluno cuja frequência seja inferior à percentagem indicada (ex.: 2/3 das aulas = 66,67%).',
            'parametros' => [
                'percentagem' => ['rotulo' => 'Frequência mínima (%)', 'tipo' => 'numero', 'padrao' => 66.67],
            ],
        ],
        'nota_minima_disciplina' => [
            'nome' => 'Classificação mínima por disciplina',
            'descricao' => 'O aluno transita/aprova quando a classificação final em todas as disciplinas é igual ou superior ao mínimo.',
            'parametros' => [
                'nota' => ['rotulo' => 'Classificação mínima', 'tipo' => 'numero', 'padrao' => 10],
            ],
        ],
        'transicao_condicional' => [
            'nome' => 'Transição condicional',
            'descricao' => 'Permite transitar com um número limitado de disciplinas com classificação num intervalo, excluindo, se indicado, as disciplinas nucleares e as específicas da formação.',
            'parametros' => [
                'max_disciplinas' => ['rotulo' => 'Máximo de disciplinas', 'tipo' => 'inteiro', 'padrao' => 2],
                'nota_minima' => ['rotulo' => 'Classificação mínima admitida', 'tipo' => 'numero', 'padrao' => 7],
                'nota_maxima' => ['rotulo' => 'Classificação máxima do intervalo', 'tipo' => 'numero', 'padrao' => 9],
                'excluir_nucleares' => ['rotulo' => 'Não admitir em disciplinas nucleares (L. Portuguesa, Matemática)', 'tipo' => 'booleano', 'padrao' => true],
                'excluir_especificas' => ['rotulo' => 'Não admitir em disciplinas específicas da formação', 'tipo' => 'booleano', 'padrao' => true],
                'regimes' => ['rotulo' => 'Aplica-se a classes com regime', 'tipo' => 'regimes', 'padrao' => ['transicao']],
            ],
        ],
        'exame_recurso' => [
            'nome' => 'Acesso a exame de recurso',
            'descricao' => 'O aluno não aprovado é admitido a exame de recurso nas disciplinas com classificação negativa, até ao máximo indicado e acima da classificação mínima.',
            'parametros' => [
                'max_disciplinas' => ['rotulo' => 'Máximo de disciplinas em recurso', 'tipo' => 'inteiro', 'padrao' => 2],
                'nota_minima' => ['rotulo' => 'Classificação mínima para acesso', 'tipo' => 'numero', 'padrao' => 7],
                'regimes' => ['rotulo' => 'Aplica-se a classes com regime', 'tipo' => 'regimes', 'padrao' => ['exame_nacional', 'terminal']],
            ],
        ],
        'exame_extraordinario' => [
            'nome' => 'Acesso a exame extraordinário',
            'descricao' => 'Indica, na justificação, os alunos não aprovados que reúnem as condições para requerer exame extraordinário.',
            'parametros' => [
                'max_disciplinas' => ['rotulo' => 'Máximo de disciplinas em falta', 'tipo' => 'inteiro', 'padrao' => 3],
                'regimes' => ['rotulo' => 'Aplica-se a classes com regime', 'tipo' => 'regimes', 'padrao' => ['terminal', 'exame_nacional']],
            ],
        ],
        'melhoria_nota' => [
            'nome' => 'Exame de melhoria de nota',
            'descricao' => 'Indica, na justificação, as disciplinas em que o aluno aprovado pode requerer melhoria de nota.',
            'parametros' => [
                'nota_maxima' => ['rotulo' => 'Classificação máxima para requerer melhoria', 'tipo' => 'numero', 'padrao' => 13],
                'regimes' => ['rotulo' => 'Aplica-se a classes com regime', 'tipo' => 'regimes', 'padrao' => ['exame_nacional', 'terminal']],
            ],
        ],
        'exame_equivalencia' => [
            'nome' => 'Exame de equivalência',
            'descricao' => 'Regra informativa: os exames de equivalência destinam-se a candidatos externos e serão tratados no módulo de exames (fase futura).',
            'parametros' => [
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'texto', 'padrao' => ''],
            ],
        ],
    ],

    'situacoes' => [
        'aprovado' => ['rotulo' => 'Aprovado', 'cor' => 'success', 'positiva' => true],
        'transita' => ['rotulo' => 'Transita', 'cor' => 'success', 'positiva' => true],
        'progressao_automatica' => ['rotulo' => 'Transita (progressão automática)', 'cor' => 'success', 'positiva' => true],
        'transita_condicionalmente' => ['rotulo' => 'Transita condicionalmente', 'cor' => 'warning', 'positiva' => true],
        'admitido_recurso' => ['rotulo' => 'Admitido a exame de recurso', 'cor' => 'info', 'positiva' => false],
        'reprovado' => ['rotulo' => 'Reprovado', 'cor' => 'danger', 'positiva' => false],
        'retido' => ['rotulo' => 'Não transita', 'cor' => 'danger', 'positiva' => false],
        'retido_faltas' => ['rotulo' => 'Retido por faltas', 'cor' => 'danger', 'positiva' => false],
        'incompleto' => ['rotulo' => 'Resultado incompleto', 'cor' => 'secondary', 'positiva' => null],
    ],

    // Opções para futura adaptação aos modelos oficiais de pauta.
    'pautas' => [
        'mostrar_qualitativa' => true,
        'orientacao_pdf' => 'landscape',
        'assinaturas' => ['O(A) Director(a) de Turma', 'O(A) Subdirector(a) Pedagógico(a)', 'O(A) Director(a) da Escola'],
    ],
];
