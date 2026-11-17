<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tarefas agendadas. No Windows (XAMPP), criar no Agendador de Tarefas uma tarefa que execute
| a cada minuto:  C:\xampp\php\php.exe C:\xampp\htdocs\sg_escolar\artisan schedule:run
| Em Linux: * * * * * php /caminho/artisan schedule:run
*/

// Cópia de segurança diária da base de dados e dos documentos.
Schedule::command('sige:backup')->dailyAt(config('sige.backups.hora', '01:00'))->withoutOverlapping();
