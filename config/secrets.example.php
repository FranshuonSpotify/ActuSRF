<?php
// Plantilla. Copia este fichero a config/secrets.php (NO se sube a git)
// y rellena los valores reales de tu entorno.

return [
    'db_host' => '',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',

    'discord_shared_token' => '',

    // Genera el hash con: php -r "echo password_hash('tu_clave', PASSWORD_DEFAULT);"
    'admin_lesiones_user' => 'admin_lesiones',
    'admin_lesiones_pass_hash' => '',

    // Token para invocar cron/reconstruir.php por URL. No hace falta si el
    // cron de IONOS ejecuta el script por CLI (php cron/reconstruir.php).
    'cron_token' => '',
];
