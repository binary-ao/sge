<?php

/*
|--------------------------------------------------------------------------
| Parâmetros administrativos (faltas, pagamentos, documentos)
|--------------------------------------------------------------------------
*/

return [
    'faltas' => [
        // Semanas lectivas por trimestre usadas para estimar as aulas previstas (carga horária × semanas).
        'semanas_por_trimestre' => 12,
        // As faltas justificadas (justificativo aprovado) não contam para o cálculo da frequência.
        'contar_justificadas' => false,
        // Frequência abaixo da qual o aluno é assinalado em risco (a retenção usa a regra da configuração de avaliação).
        'alerta_frequencia' => 75,
    ],

    'pagamentos' => [
        'moeda' => 'Kz',
        // Dia do mês até ao qual a mensalidade deve ser paga; depois disso fica em atraso.
        'dia_vencimento' => 10,
        'formas' => ['numerario' => 'Numerário', 'transferencia' => 'Transferência bancária', 'tpa' => 'TPA (multicaixa)'],
        'tipos_servico' => ['matricula' => 'Matrícula', 'mensalidade' => 'Mensalidade', 'declaracao' => 'Declaração', 'outro' => 'Outro serviço'],
    ],

    'declaracoes' => [
        'tipos' => [
            'matricula' => 'Declaração de matrícula',
            'frequencia' => 'Declaração de frequência',
            'notas' => 'Declaração com notas',
        ],
    ],
];
