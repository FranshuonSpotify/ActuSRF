<?php
require_once __DIR__ . '/../config/admin_auth.php';

define('DATA_JSON', __DIR__ . '/../datos_oficiales.json');
define('DISCORD_SHARED_TOKEN', cargarSecretos()['discord_shared_token']);