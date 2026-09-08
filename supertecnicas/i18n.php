<?php
// supertecnicas/i18n.php
// Diccionario de idioma para el chrome de supertecnicas/index.php (login +
// plantilla del presidente). admin.php se queda en español, sin usar esto.
//
// $ST_TIPOS_I18N y $ST_AFINIDADES_I18N son copias en PHP de
// SF_TIPO_MAP/SF_AFINIDADES_MAP de _fuente/i18n.js (no se puede compartir
// el mismo fichero JS desde PHP sin añadir un paso de build) — si se
// retoca una traducción ahí, hay que retocarla aquí también.

require_once __DIR__ . '/lib.php'; // stEsc(), usada por stRenderSelectorIdioma()

const ST_IDIOMAS = ['es', 'en', 'pt', 'it', 'fr', 'ja', 'ko', 'pl', 'bg', 'sr'];

// código de idioma => código de país para la bandera (flagcdn.com), mismos
// pares que SF_LANGS en _fuente/i18n.js:8.
const ST_BANDERAS = [
    'es' => 'es', 'en' => 'gb', 'pt' => 'pt', 'it' => 'it', 'fr' => 'fr',
    'ja' => 'jp', 'ko' => 'kr', 'pl' => 'pl', 'bg' => 'bg', 'sr' => 'rs',
];

$ST_I18N = [
    'es' => [
        'login.titulo' => 'Supertécnicas',
        'login.subtitulo' => 'Entra con el código y el PIN de tu equipo.',
        'login.campo_codigo' => 'Código de equipo',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Entrar',
        'login.error' => 'Código o PIN incorrectos.',
        'roster.subtitulo' => 'Asigna hasta 4 supertécnicas por jugador. Los cambios se publican en la web al guardar.',
        'roster.ventana_abierta' => 'Ventana abierta',
        'roster.ventana_cerrada' => 'Ventana cerrada',
        'roster.cerrar_sesion' => 'Cerrar sesión',
        'roster.guardado_ok' => 'Cambios guardados correctamente.',
        'roster.ventana_cerrada_aviso' => 'La ventana de supertécnicas está cerrada. Puedes ver lo asignado, pero no editarlo.',
        'roster.sin_asignar' => 'Sin asignar',
        'roster.supertecnica' => 'Supertécnica',
        'campo.nombre' => 'Nombre',
        'campo.tipo' => 'Tipo',
        'campo.afinidad' => 'Afinidad',
        'campo.especial' => 'Especial',
        'campo.descripcion' => 'Descripción',
        'placeholder.nombre' => 'Sin usar',
        'placeholder.especial' => 'miximax, tótem…',
        'placeholder.descripcion' => 'Efecto de la supertécnica…',
        'roster.guardar' => 'Guardar cambios',
    ],
    'en' => [
        'login.titulo' => 'Supertechniques',
        'login.subtitulo' => "Enter your team's code and PIN.",
        'login.campo_codigo' => 'Team code',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Enter',
        'login.error' => 'Incorrect code or PIN.',
        'roster.subtitulo' => 'Assign up to 4 supertechniques per player. Changes go live on the site once saved.',
        'roster.ventana_abierta' => 'Window open',
        'roster.ventana_cerrada' => 'Window closed',
        'roster.cerrar_sesion' => 'Log out',
        'roster.guardado_ok' => 'Changes saved successfully.',
        'roster.ventana_cerrada_aviso' => "The supertechniques window is closed. You can view what's assigned, but not edit it.",
        'roster.sin_asignar' => 'Unassigned',
        'roster.supertecnica' => 'Supertechnique',
        'campo.nombre' => 'Name',
        'campo.tipo' => 'Type',
        'campo.afinidad' => 'Affinity',
        'campo.especial' => 'Special',
        'campo.descripcion' => 'Description',
        'placeholder.nombre' => 'Unused',
        'placeholder.especial' => 'miximax, totem…',
        'placeholder.descripcion' => 'What the supertechnique does…',
        'roster.guardar' => 'Save changes',
    ],
    'pt' => [
        'login.titulo' => 'Supertécnicas',
        'login.subtitulo' => 'Entra com o código e o PIN da tua equipa.',
        'login.campo_codigo' => 'Código da equipa',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Entrar',
        'login.error' => 'Código ou PIN incorretos.',
        'roster.subtitulo' => 'Atribui até 4 supertécnicas por jogador. As alterações são publicadas no site ao guardar.',
        'roster.ventana_abierta' => 'Janela aberta',
        'roster.ventana_cerrada' => 'Janela fechada',
        'roster.cerrar_sesion' => 'Terminar sessão',
        'roster.guardado_ok' => 'Alterações guardadas com sucesso.',
        'roster.ventana_cerrada_aviso' => 'A janela de supertécnicas está fechada. Podes ver o que está atribuído, mas não editar.',
        'roster.sin_asignar' => 'Sem atribuir',
        'roster.supertecnica' => 'Supertécnica',
        'campo.nombre' => 'Nome',
        'campo.tipo' => 'Tipo',
        'campo.afinidad' => 'Afinidade',
        'campo.especial' => 'Especial',
        'campo.descripcion' => 'Descrição',
        'placeholder.nombre' => 'Sem usar',
        'placeholder.especial' => 'miximax, tótem…',
        'placeholder.descripcion' => 'Efeito da supertécnica…',
        'roster.guardar' => 'Guardar alterações',
    ],
    'it' => [
        'login.titulo' => 'Supertecniche',
        'login.subtitulo' => 'Entra con il codice e il PIN della tua squadra.',
        'login.campo_codigo' => 'Codice squadra',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Entra',
        'login.error' => 'Codice o PIN errati.',
        'roster.subtitulo' => 'Assegna fino a 4 supertecniche per giocatore. Le modifiche vengono pubblicate sul sito al salvataggio.',
        'roster.ventana_abierta' => 'Finestra aperta',
        'roster.ventana_cerrada' => 'Finestra chiusa',
        'roster.cerrar_sesion' => 'Esci',
        'roster.guardado_ok' => 'Modifiche salvate correttamente.',
        'roster.ventana_cerrada_aviso' => 'La finestra delle supertecniche è chiusa. Puoi vedere quanto assegnato, ma non modificarlo.',
        'roster.sin_asignar' => 'Non assegnata',
        'roster.supertecnica' => 'Supertecnica',
        'campo.nombre' => 'Nome',
        'campo.tipo' => 'Tipo',
        'campo.afinidad' => 'Affinità',
        'campo.especial' => 'Speciale',
        'campo.descripcion' => 'Descrizione',
        'placeholder.nombre' => 'Non usata',
        'placeholder.especial' => 'miximax, totem…',
        'placeholder.descripcion' => 'Effetto della supertecnica…',
        'roster.guardar' => 'Salva modifiche',
    ],
    'fr' => [
        'login.titulo' => 'Supertechniques',
        'login.subtitulo' => 'Entre avec le code et le PIN de ton équipe.',
        'login.campo_codigo' => "Code d'équipe",
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Entrer',
        'login.error' => 'Code ou PIN incorrects.',
        'roster.subtitulo' => "Attribue jusqu'à 4 supertechniques par joueur. Les changements sont publiés sur le site à l'enregistrement.",
        'roster.ventana_abierta' => 'Fenêtre ouverte',
        'roster.ventana_cerrada' => 'Fenêtre fermée',
        'roster.cerrar_sesion' => 'Se déconnecter',
        'roster.guardado_ok' => 'Modifications enregistrées avec succès.',
        'roster.ventana_cerrada_aviso' => 'La fenêtre des supertechniques est fermée. Tu peux voir ce qui est attribué, mais pas le modifier.',
        'roster.sin_asignar' => 'Non attribuée',
        'roster.supertecnica' => 'Supertechnique',
        'campo.nombre' => 'Nom',
        'campo.tipo' => 'Type',
        'campo.afinidad' => 'Affinité',
        'campo.especial' => 'Spécial',
        'campo.descripcion' => 'Description',
        'placeholder.nombre' => 'Non utilisé',
        'placeholder.especial' => 'miximax, totem…',
        'placeholder.descripcion' => 'Effet de la supertechnique…',
        'roster.guardar' => 'Enregistrer les modifications',
    ],
    'ja' => [
        'login.titulo' => 'スーパーテクニック',
        'login.subtitulo' => 'チームのコードとPINを入力してください。',
        'login.campo_codigo' => 'チームコード',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'ログイン',
        'login.error' => 'コードまたはPINが正しくありません。',
        'roster.subtitulo' => '選手ごとに最大4つのスーパーテクニックを設定できます。保存すると公式サイトに反映されます。',
        'roster.ventana_abierta' => '受付中',
        'roster.ventana_cerrada' => '受付終了',
        'roster.cerrar_sesion' => 'ログアウト',
        'roster.guardado_ok' => '変更を保存しました。',
        'roster.ventana_cerrada_aviso' => 'スーパーテクニックの受付は終了しています。内容の確認はできますが、編集はできません。',
        'roster.sin_asignar' => '未設定',
        'roster.supertecnica' => 'スーパーテクニック',
        'campo.nombre' => '名前',
        'campo.tipo' => 'タイプ',
        'campo.afinidad' => '属性',
        'campo.especial' => '特殊',
        'campo.descripcion' => '説明',
        'placeholder.nombre' => '未使用',
        'placeholder.especial' => 'ミキシマックス、トーテムなど…',
        'placeholder.descripcion' => 'スーパーテクニックの効果…',
        'roster.guardar' => '変更を保存',
    ],
    'ko' => [
        'login.titulo' => '슈퍼테크닉',
        'login.subtitulo' => '팀 코드와 PIN을 입력하세요.',
        'login.campo_codigo' => '팀 코드',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => '입장',
        'login.error' => '코드 또는 PIN이 올바르지 않습니다.',
        'roster.subtitulo' => '선수당 최대 4개의 슈퍼테크닉을 지정할 수 있습니다. 저장하면 웹사이트에 바로 반영됩니다.',
        'roster.ventana_abierta' => '접수 중',
        'roster.ventana_cerrada' => '접수 마감',
        'roster.cerrar_sesion' => '로그아웃',
        'roster.guardado_ok' => '변경 사항이 저장되었습니다.',
        'roster.ventana_cerrada_aviso' => '슈퍼테크닉 접수 기간이 아닙니다. 지정된 내용은 볼 수 있지만 수정할 수는 없습니다.',
        'roster.sin_asignar' => '미지정',
        'roster.supertecnica' => '슈퍼테크닉',
        'campo.nombre' => '이름',
        'campo.tipo' => '유형',
        'campo.afinidad' => '속성',
        'campo.especial' => '특수',
        'campo.descripcion' => '설명',
        'placeholder.nombre' => '미사용',
        'placeholder.especial' => '믹시맥스, 토템 등…',
        'placeholder.descripcion' => '슈퍼테크닉 효과…',
        'roster.guardar' => '변경 사항 저장',
    ],
    'pl' => [
        'login.titulo' => 'Supertechniki',
        'login.subtitulo' => 'Zaloguj się kodem i PIN-em swojej drużyny.',
        'login.campo_codigo' => 'Kod drużyny',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Wejdź',
        'login.error' => 'Nieprawidłowy kod lub PIN.',
        'roster.subtitulo' => 'Przypisz do 4 supertechnik na zawodnika. Zmiany trafiają na stronę po zapisaniu.',
        'roster.ventana_abierta' => 'Okno otwarte',
        'roster.ventana_cerrada' => 'Okno zamknięte',
        'roster.cerrar_sesion' => 'Wyloguj się',
        'roster.guardado_ok' => 'Zmiany zapisane pomyślnie.',
        'roster.ventana_cerrada_aviso' => 'Okno supertechnik jest zamknięte. Możesz zobaczyć przypisane techniki, ale nie edytować ich.',
        'roster.sin_asignar' => 'Nieprzypisana',
        'roster.supertecnica' => 'Supertechnika',
        'campo.nombre' => 'Nazwa',
        'campo.tipo' => 'Typ',
        'campo.afinidad' => 'Żywioł',
        'campo.especial' => 'Specjalna',
        'campo.descripcion' => 'Opis',
        'placeholder.nombre' => 'Nieużywana',
        'placeholder.especial' => 'miximax, totem…',
        'placeholder.descripcion' => 'Efekt supertechniki…',
        'roster.guardar' => 'Zapisz zmiany',
    ],
    'bg' => [
        'login.titulo' => 'Суперумения',
        'login.subtitulo' => 'Влез с кода и ПИН кода на отбора си.',
        'login.campo_codigo' => 'Код на отбора',
        'login.campo_pin' => 'ПИН',
        'login.boton_entrar' => 'Вход',
        'login.error' => 'Грешен код или ПИН.',
        'roster.subtitulo' => 'Задай до 4 суперумения на играч. Промените се публикуват на сайта при запазване.',
        'roster.ventana_abierta' => 'Прозорецът е отворен',
        'roster.ventana_cerrada' => 'Прозорецът е затворен',
        'roster.cerrar_sesion' => 'Изход',
        'roster.guardado_ok' => 'Промените са запазени успешно.',
        'roster.ventana_cerrada_aviso' => 'Прозорецът за суперумения е затворен. Можеш да видиш зададеното, но не и да го редактираш.',
        'roster.sin_asignar' => 'Незададено',
        'roster.supertecnica' => 'Суперумение',
        'campo.nombre' => 'Име',
        'campo.tipo' => 'Вид',
        'campo.afinidad' => 'Стихия',
        'campo.especial' => 'Специално',
        'campo.descripcion' => 'Описание',
        'placeholder.nombre' => 'Неизползвано',
        'placeholder.especial' => 'миксимакс, тотем…',
        'placeholder.descripcion' => 'Ефект на суперумението…',
        'roster.guardar' => 'Запази промените',
    ],
    'sr' => [
        'login.titulo' => 'Супертехнике',
        'login.subtitulo' => 'Улогуј се кодом и ПИН-ом свог тима.',
        'login.campo_codigo' => 'Код тима',
        'login.campo_pin' => 'ПИН',
        'login.boton_entrar' => 'Улаз',
        'login.error' => 'Погрешан код или ПИН.',
        'roster.subtitulo' => 'Додели до 4 супертехнике по играчу. Измене се објављују на сајту чим се сачувају.',
        'roster.ventana_abierta' => 'Прозор отворен',
        'roster.ventana_cerrada' => 'Прозор затворен',
        'roster.cerrar_sesion' => 'Одјава',
        'roster.guardado_ok' => 'Измене су успешно сачуване.',
        'roster.ventana_cerrada_aviso' => 'Прозор за супертехнике је затворен. Можеш видети додељено, али не и мењати.',
        'roster.sin_asignar' => 'Није додељено',
        'roster.supertecnica' => 'Супертехника',
        'campo.nombre' => 'Име',
        'campo.tipo' => 'Тип',
        'campo.afinidad' => 'Афинитет',
        'campo.especial' => 'Специјално',
        'campo.descripcion' => 'Опис',
        'placeholder.nombre' => 'Неискоришћено',
        'placeholder.especial' => 'миксимакс, тотем…',
        'placeholder.descripcion' => 'Ефекат супертехнике…',
        'roster.guardar' => 'Сачувај измене',
    ],
];

// Copia en PHP de SF_TIPO_MAP (_fuente/i18n.js) — mismos 4 valores
// canónicos que ST_TIPOS de lib.php ('' se maneja aparte, no tiene
// traducción: la plantilla imprime '—' directamente).
$ST_TIPOS_I18N = [
    'tiro' => ['es'=>'Tiro','en'=>'Shot','pt'=>'Chute','it'=>'Tiro','fr'=>'Tir','ja'=>'シュート','ko'=>'슛','pl'=>'Strzał','bg'=>'Удар','sr'=>'Шут'],
    'regate' => ['es'=>'Regate','en'=>'Dribble','pt'=>'Drible','it'=>'Dribbling','fr'=>'Dribble','ja'=>'ドリブル','ko'=>'드리블','pl'=>'Drybling','bg'=>'Дрибъл','sr'=>'Дриблинг'],
    'bloqueo' => ['es'=>'Bloqueo','en'=>'Block','pt'=>'Bloqueio','it'=>'Blocco','fr'=>'Blocage','ja'=>'ブロック','ko'=>'블록','pl'=>'Blok','bg'=>'Блок','sr'=>'Блок'],
    'parada' => ['es'=>'Parada','en'=>'Save','pt'=>'Defesa','it'=>'Parata','fr'=>'Arrêt','ja'=>'セーブ','ko'=>'세이브','pl'=>'Obrona','bg'=>'Спасяване','sr'=>'Одбрана'],
];

// Copia en PHP de SF_AFINIDADES_MAP (_fuente/i18n.js:247-251) — mismos 5
// valores canónicos que ST_AFINIDADES de lib.php.
$ST_AFINIDADES_I18N = [
    'fuego' => ['es'=>'Fuego','en'=>'Fire','pt'=>'Fogo','it'=>'Fuoco','fr'=>'Feu','ja'=>'炎','ko'=>'화염','pl'=>'Ogień','bg'=>'Огън','sr'=>'Ватра'],
    'aire' => ['es'=>'Aire','en'=>'Wind','pt'=>'Ar','it'=>'Aria','fr'=>'Air','ja'=>'風','ko'=>'바람','pl'=>'Wiatr','bg'=>'Въздух','sr'=>'Ветар'],
    'bosque' => ['es'=>'Bosque','en'=>'Forest','pt'=>'Floresta','it'=>'Foresta','fr'=>'Forêt','ja'=>'森','ko'=>'숲','pl'=>'Las','bg'=>'Гора','sr'=>'Шума'],
    'montaña' => ['es'=>'Montaña','en'=>'Mountain','pt'=>'Montanha','it'=>'Montagna','fr'=>'Montagne','ja'=>'山','ko'=>'산','pl'=>'Góra','bg'=>'Планина','sr'=>'Планина'],
    'neutro' => ['es'=>'Neutro','en'=>'Void','pt'=>'Vazio','it'=>'Vuoto','fr'=>'Vide','ja'=>'無','ko'=>'무','pl'=>'Pustka','bg'=>'Празнота','sr'=>'Празнина'],
];

$GLOBALS['ST_IDIOMA_ACTUAL'] = 'es';

function stEstablecerIdioma(string $idioma): void {
    $GLOBALS['ST_IDIOMA_ACTUAL'] = in_array($idioma, ST_IDIOMAS, true) ? $idioma : 'es';
}

// Parsea una cabecera Accept-Language ("es-ES,es;q=0.9,en;q=0.8") y
// devuelve el primer subtag principal soportado, ordenado por calidad (q)
// descendente. Formato de cabecera HTTP estándar, sin librerías.
function stDetectarIdiomaNavegador(string $cabecera): string {
    if (trim($cabecera) === '') return 'es';

    $candidatos = [];
    foreach (explode(',', $cabecera) as $parte) {
        $parte = trim($parte);
        if ($parte === '') continue;
        $q = 1.0;
        if (preg_match('/;\s*q=([0-9.]+)/', $parte, $m)) {
            $q = (float) $m[1];
        }
        $subtag = strtolower(substr(preg_replace('/;.*/', '', $parte), 0, 2));
        $candidatos[] = ['subtag' => $subtag, 'q' => $q];
    }

    usort($candidatos, function ($a, $b) { return $b['q'] <=> $a['q']; });

    foreach ($candidatos as $c) {
        if (in_array($c['subtag'], ST_IDIOMAS, true)) return $c['subtag'];
    }
    return 'es';
}

// Orden de resolución: ?lang= en la URL (y se guarda en sesión) -> idioma
// ya guardado en sesión -> cabecera Accept-Language -> español.
function stResolverIdioma(): string {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ST_IDIOMAS, true)) {
        $_SESSION['st_lang'] = $_GET['lang'];
        return $_GET['lang'];
    }

    if (isset($_SESSION['st_lang']) && in_array($_SESSION['st_lang'], ST_IDIOMAS, true)) {
        return $_SESSION['st_lang'];
    }

    $detectado = stDetectarIdiomaNavegador($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $_SESSION['st_lang'] = $detectado;
    return $detectado;
}

function stT(string $clave): string {
    global $ST_I18N;
    $idioma = $GLOBALS['ST_IDIOMA_ACTUAL'];
    if (isset($ST_I18N[$idioma][$clave])) return $ST_I18N[$idioma][$clave];
    if (isset($ST_I18N['es'][$clave])) return $ST_I18N['es'][$clave];
    return '[' . $clave . ']';
}

function stTipoLabel(string $tipo): string {
    global $ST_TIPOS_I18N;
    $idioma = $GLOBALS['ST_IDIOMA_ACTUAL'];
    $original = trim($tipo);
    if ($original === '') return '';
    $clave = mb_strtolower($original, 'UTF-8');
    if (!isset($ST_TIPOS_I18N[$clave])) return $original;
    return $ST_TIPOS_I18N[$clave][$idioma] ?? $ST_TIPOS_I18N[$clave]['es'];
}

function stAfinidadLabel(string $afinidad): string {
    global $ST_AFINIDADES_I18N;
    $idioma = $GLOBALS['ST_IDIOMA_ACTUAL'];
    $original = trim($afinidad);
    if ($original === '') return '';
    $clave = mb_strtolower($original, 'UTF-8');
    if (!isset($ST_AFINIDADES_I18N[$clave])) return $original;
    return $ST_AFINIDADES_I18N[$clave][$idioma] ?? $ST_AFINIDADES_I18N[$clave]['es'];
}

// Fila de banderas: un enlace <a href="?lang=xx"> por idioma, sin JS.
// stEsc() viene de supertecnicas/lib.php (ya cargado por index.php antes
// de incluir este fichero).
function stRenderSelectorIdioma(): void {
    echo '<nav class="st-idiomas" aria-label="Idioma">';
    foreach (ST_BANDERAS as $codigo => $pais) {
        $activo = $GLOBALS['ST_IDIOMA_ACTUAL'] === $codigo;
        echo '<a href="?lang=' . stEsc($codigo) . '"'
            . ($activo ? ' class="activo" aria-current="true"' : '')
            . '><img src="https://flagcdn.com/16x12/' . stEsc($pais) . '.png" alt="' . stEsc($codigo) . '" width="16" height="12" loading="lazy"></a>';
    }
    echo '</nav>';
}
