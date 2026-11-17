<?php

return [
    'nome_curto' => 'SIGE Bengo',
    'nome_completo' => 'Sistema Integrado de Gestão Escolar',
    'entidade' => 'Direcção Provincial da Educação do Bengo',
    'desenvolvimento' => 'Binary',
    'versao' => '1.1.0',

    // Cópias de segurança (comando sige:backup, agendado diariamente em routes/console.php).
    'backups' => [
        'mysqldump' => env('SIGE_MYSQLDUMP', file_exists('C:\xampp\mysql\bin\mysqldump.exe') ? 'C:\xampp\mysql\bin\mysqldump.exe' : 'mysqldump'),
        'manter' => (int) env('SIGE_BACKUPS_MANTER', 14),
        'hora' => env('SIGE_BACKUP_HORA', '01:00'),
    ],

    // Mostra a faixa "Ambiente de demonstração — dados fictícios".
    'modo_demonstracao' => env('SIGE_MODO_DEMONSTRACAO', true),
];
