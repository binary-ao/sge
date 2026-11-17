<?php

/*
|--------------------------------------------------------------------------
| Menu lateral
|--------------------------------------------------------------------------
| Cada item é mostrado apenas se o utilizador tiver uma das permissões
| indicadas (verificação também feita nas rotas). Itens com
| "em_desenvolvimento" são identificados claramente e não simulam funcionamento.
*/

return [
    [
        'titulo' => 'Principal',
        'itens' => [
            ['rotulo' => 'Início', 'icone' => 'house-door', 'rota' => 'inicio', 'activo' => 'inicio'],
            ['rotulo' => 'Painel Provincial', 'icone' => 'bar-chart-line', 'rota' => 'paineis.provincial', 'activo' => 'paineis.provincial', 'permissao' => 'painel.provincial'],
            ['rotulo' => 'Painel Municipal', 'icone' => 'pie-chart', 'rota' => 'paineis.municipal', 'activo' => 'paineis.municipal', 'permissao' => 'painel.municipal'],
            ['rotulo' => 'Painel da Escola', 'icone' => 'speedometer2', 'rota' => 'paineis.escola', 'activo' => 'paineis.escola', 'permissao' => 'painel.escola'],
            ['rotulo' => 'Mapa da Rede', 'icone' => 'geo-alt', 'rota' => 'mapa', 'activo' => 'mapa', 'permissao' => 'mapa.ver'],
        ],
    ],
    [
        'titulo' => 'Gestão Académica',
        'itens' => [
            ['rotulo' => 'Alunos', 'icone' => 'people', 'rota' => 'alunos.index', 'activo' => 'alunos.*', 'permissao' => 'alunos.ver'],
            ['rotulo' => 'Matrículas', 'icone' => 'journal-check', 'rota' => 'matriculas.index', 'activo' => 'matriculas.*', 'permissao' => 'matriculas.gerir|alunos.ver'],
            ['rotulo' => 'Turmas', 'icone' => 'grid-3x3-gap', 'rota' => 'turmas.index', 'activo' => 'turmas.*', 'permissao' => 'turmas.ver'],
            ['rotulo' => 'Professores', 'icone' => 'person-badge', 'rota' => 'professores.index', 'activo' => 'professores.*', 'permissao' => 'professores.ver'],
            ['rotulo' => 'Configuração Académica','icone' => 'sliders', 'rota' => 'academico.index', 'activo' => 'academico.*', 'permissao' => 'academico.configurar|academico.global'],
        ],
    ],
    [
        'titulo' => 'Avaliação',
        'itens' => [
            ['rotulo' => 'Lançamento de Notas', 'icone' => 'pencil-square', 'rota' => 'avaliacao.lancamento.index', 'activo' => 'avaliacao.lancamento.*', 'permissao' => 'notas.lancar|notas.ver'],
            ['rotulo' => 'Pautas', 'icone' => 'table', 'rota' => 'avaliacao.pautas.index', 'activo' => 'avaliacao.pautas.*', 'permissao' => 'pautas.ver'],
            ['rotulo' => 'Fecho de Períodos', 'icone' => 'lock', 'rota' => 'avaliacao.periodos.index', 'activo' => 'avaliacao.periodos.*', 'permissao' => 'pautas.fechar'],
            ['rotulo' => 'Configuração de Avaliações', 'icone' => 'calculator', 'rota' => 'avaliacao.configuracoes.index', 'activo' => 'avaliacao.configuracoes.*', 'permissao' => 'avaliacao.configurar'],
            ['rotulo' => 'Escalas e Fórmulas', 'icone' => 'rulers', 'rota' => 'avaliacao.escalas.index', 'activo' => 'avaliacao.escalas.*', 'permissao' => 'avaliacao.configurar'],
        ],
    ],
    [
        'titulo' => 'Administrativo',
        'itens' => [
            ['rotulo' => 'Faltas', 'icone' => 'calendar-x', 'rota' => 'faltas.index', 'activo' => 'faltas.*', 'permissao' => 'faltas.ver|faltas.registar'],
            ['rotulo' => 'Pagamentos', 'icone' => 'cash-coin', 'rota' => 'pagamentos.index', 'activo' => 'pagamentos.*', 'permissao' => 'pagamentos.ver'],
            ['rotulo' => 'Serviços e Preços', 'icone' => 'tags', 'rota' => 'servicos.index', 'activo' => 'servicos.*', 'permissao' => 'servicos.gerir'],
            ['rotulo' => 'Declarações', 'icone' => 'file-earmark-text', 'rota' => 'declaracoes.index', 'activo' => 'declaracoes.*', 'permissao' => 'declaracoes.emitir'],
            ['rotulo' => 'Relatórios', 'icone' => 'file-earmark-bar-graph', 'rota' => 'relatorios.index', 'activo' => 'relatorios.*', 'permissao' => 'relatorios.provinciais|relatorios.municipais|relatorios.escolares'],
        ],
    ],
    [
        // Dados estruturais do sistema (escrita sujeita a permissão e jurisdição no servidor).
        'titulo' => 'Cadastros e Configurações',
        'itens' => [
            ['rotulo' => 'Municípios', 'icone' => 'map', 'rota' => 'municipios.index', 'activo' => 'municipios.*', 'permissao' => 'municipios.ver'],
            ['rotulo' => 'Escolas', 'icone' => 'building', 'rota' => 'escolas.index', 'activo' => 'escolas.*|tipologias.*', 'permissao' => 'escolas.ver'],
            ['rotulo' => 'Disciplinas', 'icone' => 'book', 'rota' => 'cadastros.disciplinas.index', 'activo' => 'cadastros.disciplinas.*', 'permissao' => 'cadastros.ver|academico.configurar|academico.global'],
            ['rotulo' => 'Níveis de Ensino', 'icone' => 'layers', 'rota' => 'cadastros.niveis.index', 'activo' => 'cadastros.niveis.*', 'permissao' => 'cadastros.ver|rede.catalogos'],
            ['rotulo' => 'Classes e Ciclos', 'icone' => 'diagram-2', 'rota' => 'cadastros.classes.index', 'activo' => 'cadastros.classes.*', 'permissao' => 'cadastros.ver|academico.global'],
            ['rotulo' => 'Anos Lectivos', 'icone' => 'calendar3', 'rota' => 'cadastros.anos.index', 'activo' => 'cadastros.anos.*', 'permissao' => 'cadastros.ver|academico.global'],
            ['rotulo' => 'Períodos de Avaliação', 'icone' => 'calendar-range', 'rota' => 'cadastros.periodos.index', 'activo' => 'cadastros.periodos.*', 'permissao' => 'cadastros.ver|academico.global'],
        ],
    ],
    [
        'titulo' => 'Sistema',
        'itens' => [
            ['rotulo' => 'Utilizadores', 'icone' => 'person-gear', 'rota' => 'utilizadores.index', 'activo' => 'utilizadores.*', 'permissao' => 'utilizadores.gerir'],
            ['rotulo' => 'Perfis e Permissões', 'icone' => 'shield-lock', 'rota' => 'perfis.index', 'activo' => 'perfis.*', 'permissao' => 'perfis.gerir'],
            ['rotulo' => 'Histórico de Actividades', 'icone' => 'clock-history', 'rota' => 'logs.index', 'activo' => 'logs.*', 'permissao' => 'logs.ver'],
            ['rotulo' => 'Cópias de Segurança', 'icone' => 'database-down', 'rota' => 'copias.index', 'activo' => 'copias.*', 'permissao' => 'sistema.backups'],
        ],
    ],
];
