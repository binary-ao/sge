<?php

/*
|--------------------------------------------------------------------------
| Catálogo de permissões e atribuição inicial por perfil
|--------------------------------------------------------------------------
| Usado pelo seeder. Depois da instalação, a matriz perfil × permissão pode
| ser alterada pelo superutilizador em "Perfis e Permissões".
*/

$consultaRede = [
    'cadastros.ver', 'escolas.ver', 'municipios.ver', 'mapa.ver',
    'alunos.ver', 'professores.ver', 'turmas.ver',
    'notas.ver', 'pautas.ver', 'faltas.ver',
];

return [
    /*
    | Permissões de escrita sobre registos operacionais das escolas.
    | Nunca são concedidas a perfis de âmbito provincial ou municipal,
    | mesmo que sejam marcadas na matriz de permissões.
    */
    'escrita_operacional' => [
        'academico.configurar', 'professores.gerir', 'alunos.gerir', 'matriculas.gerir', 'turmas.gerir',
        'notas.lancar', 'pautas.fechar', 'faltas.registar', 'faltas.justificar',
        'pagamentos.registar', 'pagamentos.anular', 'servicos.gerir', 'declaracoes.emitir',
    ],

    'catalogo' => [
        'Painéis' => [
            'painel.provincial' => 'Consultar painel provincial',
            'painel.municipal' => 'Consultar painel municipal',
            'painel.escola' => 'Consultar painel da escola',
            'mapa.ver' => 'Consultar mapa da rede escolar',
        ],
        'Cadastros e Configurações' => [
            'cadastros.ver' => 'Consultar cadastros e configurações (disciplinas, níveis, classes, anos lectivos e períodos)',
            'municipios.ver' => 'Consultar municípios',
            'municipios.gerir' => 'Gerir municípios',
            'escolas.ver' => 'Consultar escolas',
            'escolas.gerir' => 'Cadastrar, editar, activar e desactivar escolas (Direcção Municipal: só do seu município)',
            'rede.catalogos' => 'Gerir tipologias institucionais e níveis de ensino',
            'academico.global' => 'Gerir configurações provinciais (disciplinas de referência, classes, ciclos, anos lectivos, períodos, turnos)',
        ],
        'Gestão Académica' => [
            'academico.configurar' => 'Configuração académica da escola (cursos, disciplinas, planos curriculares)',
            'professores.ver' => 'Consultar professores',
            'professores.gerir' => 'Gerir professores',
            'alunos.ver' => 'Consultar alunos',
            'alunos.gerir' => 'Cadastrar e editar alunos',
            'matriculas.gerir' => 'Efectuar matrículas',
            'turmas.ver' => 'Consultar turmas',
            'turmas.gerir' => 'Gerir turmas',
        ],
        'Avaliação' => [
            'avaliacao.configurar' => 'Configurar sistemas de avaliação',
            'notas.ver' => 'Consultar notas e resultados',
            'notas.lancar' => 'Lançar avaliações',
            'pautas.ver' => 'Consultar pautas',
            'pautas.fechar' => 'Fechar e reabrir trimestres',
        ],
        'Faltas' => [
            'faltas.ver' => 'Consultar faltas',
            'faltas.registar' => 'Registar faltas',
            'faltas.justificar' => 'Analisar justificativos',
        ],
        'Financeiro' => [
            'pagamentos.ver' => 'Consultar pagamentos',
            'pagamentos.registar' => 'Registar pagamentos e emitir recibos',
            'pagamentos.anular' => 'Anular recibos',
            'servicos.gerir' => 'Gerir serviços e preços',
        ],
        'Documentos e Relatórios' => [
            'declaracoes.emitir' => 'Emitir declarações',
            'relatorios.provinciais' => 'Relatórios provinciais',
            'relatorios.municipais' => 'Relatórios municipais',
            'relatorios.escolares' => 'Relatórios escolares',
        ],
        'Sistema' => [
            'utilizadores.gerir' => 'Gerir utilizadores',
            'perfis.gerir' => 'Gerir perfis e permissões',
            'logs.ver' => 'Consultar histórico de actividades',
            'sistema.backups' => 'Gerir cópias de segurança',
        ],
    ],

    'perfis' => [
        'superutilizador' => [
            'nome' => 'Superutilizador',
            'ambito' => 'global',
            'descricao' => 'Acesso global a todos os módulos e configurações.',
            'permissoes' => '*',
        ],
        'direccao_provincial' => [
            'nome' => 'Direcção Provincial',
            'ambito' => 'provincia',
            'descricao' => 'Toda a província: indicadores, relatórios, gestão da rede escolar e das configurações provinciais. Sem alteração de registos operacionais das escolas.',
            'permissoes' => array_merge($consultaRede, [
                'municipios.gerir', 'escolas.gerir', 'rede.catalogos', 'academico.global',
                'painel.provincial', 'painel.municipal', 'painel.escola',
                'relatorios.provinciais', 'relatorios.municipais', 'relatorios.escolares',
            ]),
        ],
        'direccao_municipal' => [
            'nome' => 'Direcção Municipal',
            'ambito' => 'municipio',
            'descricao' => 'Escolas do respectivo município: consulta e gestão do cadastro institucional. Sem alteração de registos operacionais.',
            'permissoes' => array_merge($consultaRede, [
                'escolas.gerir',
                'painel.municipal', 'painel.escola',
                'relatorios.municipais', 'relatorios.escolares',
            ]),
        ],
        'director_escola' => [
            'nome' => 'Director / Administrativo',
            'ambito' => 'escola',
            'descricao' => 'Gestão integral da respectiva escola.',
            'permissoes' => [
                'painel.escola', 'escolas.ver', 'cadastros.ver',
                'academico.configurar', 'professores.ver', 'professores.gerir',
                'alunos.ver', 'alunos.gerir', 'matriculas.gerir', 'turmas.ver', 'turmas.gerir',
                'notas.ver', 'notas.lancar', 'pautas.ver', 'pautas.fechar',
                'faltas.ver', 'faltas.registar', 'faltas.justificar',
                'pagamentos.ver', 'pagamentos.registar', 'pagamentos.anular', 'servicos.gerir',
                'declaracoes.emitir', 'relatorios.escolares',
            ],
        ],
        'professor' => [
            'nome' => 'Professor',
            'ambito' => 'escola',
            'descricao' => 'Turmas e disciplinas atribuídas: avaliações, faltas e pautas.',
            'permissoes' => [
                'painel.escola', 'turmas.ver', 'alunos.ver', 'cadastros.ver',
                'notas.ver', 'notas.lancar', 'pautas.ver',
                'faltas.ver', 'faltas.registar',
            ],
        ],
        'secretaria' => [
            'nome' => 'Secretaria / Tesouraria',
            'ambito' => 'escola',
            'descricao' => 'Cadastro de alunos, matrículas, pagamentos e recibos.',
            'permissoes' => [
                'painel.escola', 'alunos.ver', 'alunos.gerir', 'matriculas.gerir', 'turmas.ver', 'cadastros.ver',
                'pagamentos.ver', 'pagamentos.registar', 'declaracoes.emitir',
            ],
        ],
    ],
];
