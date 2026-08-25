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
];
