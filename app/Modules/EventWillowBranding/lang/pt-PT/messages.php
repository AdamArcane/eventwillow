<?php

// Share EventWillow's Portuguese wording, with European Portuguese for the most visible labels.
// Upstream's pt-PT catalog still supplies every key not customized by EventWillow.
return array_replace(require __DIR__.'/../pt/messages.php', [
    'schedule_name' => 'Nome do Canal',
    'create_schedule' => 'Criar Canal',
    'create_your_own_event_schedule' => 'Crie o seu próprio canal de eventos!',
    'schedule_settings' => 'Definições do canal',
    'schedule_style' => 'Estilo do canal',
    'share_your_event_schedule_link' => 'Partilhe o link ou código QR do seu canal para conseguir mais seguidores',
    'note_all_schedules_are_publicly_listed' => 'O seu canal será visível publicamente',
    'created_schedule' => 'Canal criado com sucesso',
    'updated_schedule' => 'Canal atualizado com sucesso',
    'edit_schedule' => 'Editar Canal',
    'talent_schedules' => 'Canais de organizadores',
    'talent' => 'Organizador',
    'talent_role_not_found' => 'Canal do organizador não encontrado.',
]);
