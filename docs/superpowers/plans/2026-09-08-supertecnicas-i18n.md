# Idioma en supertécnicas — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Traducir de verdad las supertécnicas en la web pública (texto libre venga del idioma que venga) y traducir el chrome de `supertecnicas/index.php` (login + plantilla del presidente) a los 10 idiomas del sitio, con detección automática por navegador y banderas para cambiar a mano.

**Architecture:** Bloque 1 (web pública, JS): un fix de dos líneas en la auto-traducción genérica ya existente (`sl=es`→`sl=auto`) más un diccionario curado nuevo para `tipo`, igual que ya existe para `afinidad`. Bloque 2 (`supertecnicas/`, PHP puro sin JS): un fichero `i18n.php` nuevo con diccionario + resolución de idioma (`?lang=` → sesión → `Accept-Language` → español), consumido desde `index.php`.

**Tech Stack:** JavaScript vanilla (`_fuente/i18n.js`, `_fuente/app.js`, sin build), PHP 8 puro (`supertecnicas/`), sin dependencias nuevas.

## Global Constraints

- `supertecnicas/admin.php` no se toca — se queda en español (confirmado con el usuario).
- El esquema de `datos_oficiales.json` no cambia. `tipo`/`afinidad` siguen guardándose siempre en su valor canónico español (`tiro`, `fuego`...); solo cambia la etiqueta visible según idioma.
- `supertecnicas/guardar.php` no cambia: sigue validando contra `ST_TIPOS`/`ST_AFINIDADES` de `lib.php`, idénticos de siempre.
- 10 idiomas soportados, mismos códigos y mismo mapeo a bandera que la web pública: `es→es, en→gb, pt→pt, it→it, fr→fr, ja→jp, ko→kr, pl→pl, bg→bg, sr→rs` (`SF_LANGS`, `_fuente/i18n.js:8`).
- Todo texto de usuario en `supertecnicas/` se escapa con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (función `stEsc()` ya existente en `supertecnicas/lib.php`).
- Nombres de función/variable en español, sin clases, mismo estilo que el resto de `supertecnicas/` y de `_fuente/*.js`.

---

### Task 1: Bloque 1 — auto-traducción correcta en la web pública

**Files:**
- Modify: `_fuente/i18n.js:213` (`_sfATRequest`)
- Modify: `_fuente/i18n.js:406` (`sfATBatch`)
- Modify: `_fuente/i18n.js` (nuevo bloque `SF_TIPO_MAP`/`sfTipoLabel`, entre las líneas 264 y 266 actuales — después de `sfAfinidadLabel()`, antes del comentario "TRADUCCIÓN DE PÁGINA COMPLETA")
- Modify: `_fuente/app.js:71-72` (nuevo `tipoName()`, junto a `afName()`)
- Modify: `_fuente/app.js:852-854` (`openPlayer()`, usa `tipoName()` en vez de `t.tipo` en crudo)

**Interfaces:**
- Produces: `window.sfTipoLabel(tipo: string): string` — análogo a `window.sfAfinidadLabel`, ya expuesto en `_fuente/i18n.js`. Devuelve `''` si `tipo` está vacío o no coincide con ninguna clave conocida (a diferencia de `sfAfinidadLabel`, que cae a "Neutro" — `tipo` sí puede estar legítimamente vacío).
- Produces: `tipoName(t: string): string` en `_fuente/app.js`, mismo patrón que `afName(a)` (línea 71): usa `window.sfTipoLabel` si existe, si no cae a un mapa local `TIPO_LABEL`.

- [ ] **Step 1: Cambiar el idioma de origen a autodetectado**

En `_fuente/i18n.js`, línea 213 (dentro de `_sfATRequest`), cambiar:

```javascript
                var url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=es&tl=' + lang + '&dt=t&q=' + encodeURIComponent(text);
```

por:

```javascript
                var url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=' + lang + '&dt=t&q=' + encodeURIComponent(text);
```

Y en la línea 406 (dentro de `sfATBatch`), cambiar:

```javascript
                var url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=es&tl='
                    + lang + '&dt=t&q=' + encodeURIComponent(joined);
```

por:

```javascript
                var url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl='
                    + lang + '&dt=t&q=' + encodeURIComponent(joined);
```

- [ ] **Step 2: Verificar que el fichero sigue siendo JS válido**

Run: `node --check _fuente/i18n.js`
Expected: sin salida (sin errores de sintaxis).

- [ ] **Step 3: Añadir el diccionario curado de `tipo`**

En `_fuente/i18n.js`, insertar el siguiente bloque entre el final de `sfAfinidadLabel()` (línea 264, `}`) y el comentario `/* ══... TRADUCCIÓN DE PÁGINA COMPLETA` (línea 266):

```javascript

        /* Los cuatro tipos de supertécnica (tiro/regate/bloqueo/parada) son
           términos de fútbol/Inazuma Eleven, igual que las afinidades:
           terminología del juego, no traducción literal palabra por
           palabra. Mismo patrón que SF_AFINIDADES_MAP de arriba. */
        var SF_TIPO_TIRO    = {es:'Tiro',en:'Shot',pt:'Chute',it:'Tiro',fr:'Tir',ja:'シュート',ko:'슛',pl:'Strzał',bg:'Удар',sr:'Шут'};
        var SF_TIPO_REGATE  = {es:'Regate',en:'Dribble',pt:'Drible',it:'Dribbling',fr:'Dribble',ja:'ドリブル',ko:'드리블',pl:'Drybling',bg:'Дрибъл',sr:'Дриблинг'};
        var SF_TIPO_BLOQUEO = {es:'Bloqueo',en:'Block',pt:'Bloqueio',it:'Blocco',fr:'Blocage',ja:'ブロック',ko:'블록',pl:'Blok',bg:'Блок',sr:'Блок'};
        var SF_TIPO_PARADA  = {es:'Parada',en:'Save',pt:'Defesa',it:'Parata',fr:'Arrêt',ja:'セーブ',ko:'세이브',pl:'Obrona',bg:'Спасяване',sr:'Одбрана'};
        var SF_TIPO_MAP = {
            'tiro': SF_TIPO_TIRO, 'shot': SF_TIPO_TIRO,
            'regate': SF_TIPO_REGATE, 'dribble': SF_TIPO_REGATE,
            'bloqueo': SF_TIPO_BLOQUEO, 'block': SF_TIPO_BLOQUEO,
            'parada': SF_TIPO_PARADA, 'save': SF_TIPO_PARADA
        };
        function sfTipoLabel(tipo) {
            var lang = sfGetLang();
            var clave = String(tipo || '').toLowerCase().trim();
            if (!clave) return '';
            var entry = SF_TIPO_MAP[clave];
            if (!entry) return '';
            return entry[lang] || entry.es;
        }
```

Y exponerla junto a `window.sfAfinidadLabel` (línea 739 actual):

```javascript
        window.sfAfinidadLabel = sfAfinidadLabel;
        window.sfGetLang = sfGetLang;
        window.sfTipoLabel = sfTipoLabel;
```

(la línea `window.sfGetLang = sfGetLang;` ya existe; solo se añade la línea de `sfTipoLabel` justo debajo).

- [ ] **Step 4: Verificar de nuevo la sintaxis**

Run: `node --check _fuente/i18n.js`
Expected: sin salida.

- [ ] **Step 5: Añadir `tipoName()` en `app.js`, junto a `afName()`**

En `_fuente/app.js`, la línea 68 ya define `AF_LABEL`. Justo después de la línea 71 (`function afName(a){ return window.sfAfinidadLabel ? sfAfinidadLabel(a) : AF_LABEL[afKey(a)]; }`) y antes de la línea 72 (`function afTag...`), insertar:

```javascript
var TIPO_LABEL={tiro:'Tiro',regate:'Regate',bloqueo:'Bloqueo',parada:'Parada'};
function tipoName(t){
  var k=String(t||'').toLowerCase().trim();
  if(!k) return '';
  return window.sfTipoLabel ? (sfTipoLabel(k)||TIPO_LABEL[k]||t) : (TIPO_LABEL[k]||t);
}
```

- [ ] **Step 6: Usar `tipoName()` en la ficha de jugador**

En `_fuente/app.js`, línea 852-854 (dentro de `openPlayer()`), cambiar:

```javascript
  var techs=(j.supertecnicas||[]).map(function(t){
    return '<div class="tech"><div class="tech-top"><b>'+esc(t.nombre)+'</b>'+(t.tipo?'<span class="badge">'+esc(t.tipo)+'</span>':'')+'</div>'+(t.descripcion?'<p>'+esc(t.descripcion)+'</p>':'')+'</div>';
  }).join('');
```

por:

```javascript
  var techs=(j.supertecnicas||[]).map(function(t){
    return '<div class="tech"><div class="tech-top"><b>'+esc(t.nombre)+'</b>'+(t.tipo?'<span class="badge">'+esc(tipoName(t.tipo))+'</span>':'')+'</div>'+(t.descripcion?'<p>'+esc(t.descripcion)+'</p>':'')+'</div>';
  }).join('');
```

(único cambio: `esc(t.tipo)` → `esc(tipoName(t.tipo))`).

- [ ] **Step 7: Verificar sintaxis de `app.js`**

Run: `node --check _fuente/app.js`
Expected: sin salida.

- [ ] **Step 8: Verificación manual en navegador**

No hay framework de test en este proyecto (`package.json` → `"test": "echo \"Error: no test specified\""`, sin JS de test en ningún sitio del repo) — la verificación de este bloque es manual, con el sitio servido en local (Apache/XAMPP, o cualquier servidor estático que sirva `htdocs/`):

1. Abrir la ficha de un jugador que ya tenga alguna `supertecnica` con `tipo` relleno (revisar `datos_oficiales.json` para encontrar uno, o añadir una de prueba temporalmente con `supertecnicas/` — revertir después si se hace).
2. Con el idioma del sitio en español, confirmar que el tipo se ve igual que antes (p. ej. "Tiro").
3. Cambiar el idioma a inglés desde el selector de la cabecera y volver a abrir la ficha: el badge de tipo debe decir "Shot" (no "Tiro" en crudo).
4. Si hay alguna supertécnica con `nombre`/`descripcion` en un idioma no español (o se añade una de prueba en francés), confirmar que con el idioma del sitio en español se traduce razonablemente al español (no se queda en francés, ni sale una traducción absurda por asumir origen español) — esto ejercita el fix de `sl=auto`.

- [ ] **Step 9: Commit**

```bash
git add _fuente/i18n.js _fuente/app.js
git commit -m "Corrige sl=auto en la auto-traducción y traduce el tipo de supertécnica"
```

---

### Task 2: `supertecnicas/i18n.php` — diccionario y resolución de idioma

**Files:**
- Create: `supertecnicas/i18n.php`
- Test: `supertecnicas/tests/test_i18n.php`

**Interfaces:**
- Consumes: `stEsc()` de `supertecnicas/lib.php` (ya existente, de un plan anterior) — `i18n.php` lo carga él mismo con `require_once`, no depende de que quien lo incluya lo haya cargado antes.
- Produces (usadas por la Task 3):
  - `const ST_IDIOMAS` (array de 10 strings: `['es','en','pt','it','fr','ja','ko','pl','bg','sr']`)
  - `const ST_BANDERAS` (array asociativo idioma => código de país para `flagcdn.com`)
  - `stResolverIdioma(): string` — determina el idioma de la petición actual (no lo guarda en variable global; lo hace `stEstablecerIdioma`)
  - `stEstablecerIdioma(string $idioma): void` — fija el idioma que usarán `stT()`/`stTipoLabel()`/`stAfinidadLabel()`/`stRenderSelectorIdioma()` en el resto de la petición
  - `stT(string $clave): string`
  - `stTipoLabel(string $tipo): string`
  - `stAfinidadLabel(string $afinidad): string`
  - `stRenderSelectorIdioma(): void` — imprime la fila de banderas

- [ ] **Step 1: Crear el test (fallará porque `i18n.php` no existe todavía)**

```php
<?php
// supertecnicas/tests/test_i18n.php
// Self-check sin framework, mismo patrón que tests/test_lib.php.
// Ejecutar con: php supertecnicas/tests/test_i18n.php

require_once __DIR__ . '/../i18n.php';

$fallos = 0;

function verificar($descripcion, $condicion) {
    global $fallos;
    if ($condicion) {
        echo "OK   $descripcion\n";
    } else {
        echo "FAIL $descripcion\n";
        $fallos++;
    }
}

// -- ST_IDIOMAS / ST_BANDERAS ------------------------------------------
verificar('ST_IDIOMAS tiene 10 idiomas', count(ST_IDIOMAS) === 10);
verificar('ST_BANDERAS tiene una bandera por idioma', count(ST_BANDERAS) === 10);
foreach (ST_IDIOMAS as $idioma) {
    verificar("ST_BANDERAS tiene entrada para '$idioma'", isset(ST_BANDERAS[$idioma]));
}

// -- stDetectarIdiomaNavegador ------------------------------------------
verificar(
    'detecta inglés como preferido cuando tiene mayor q',
    stDetectarIdiomaNavegador('fr;q=0.5,en;q=0.9,es;q=0.3') === 'en'
);
verificar(
    'usa el primero sin q explícito (q=1.0 implícito)',
    stDetectarIdiomaNavegador('pt-BR,es;q=0.8') === 'pt'
);
verificar(
    'ignora idiomas no soportados y cae al primero soportado',
    stDetectarIdiomaNavegador('de-DE,fr;q=0.9,es;q=0.5') === 'fr'
);
verificar(
    'cabecera vacía cae a español',
    stDetectarIdiomaNavegador('') === 'es'
);
verificar(
    'ningún idioma soportado en la cabecera cae a español',
    stDetectarIdiomaNavegador('de-DE,nl-NL;q=0.9') === 'es'
);

// -- stT / stEstablecerIdioma --------------------------------------------
stEstablecerIdioma('en');
verificar('stT devuelve el texto en inglés', stT('login.boton_entrar') === 'Enter');

stEstablecerIdioma('es');
verificar('stT devuelve el texto en español', stT('login.boton_entrar') === 'Entrar');

verificar(
    'stT cae a español si la clave falta en el idioma actual',
    (function () {
        stEstablecerIdioma('fr');
        global $ST_I18N;
        $original = $ST_I18N['fr']['login.boton_entrar'];
        unset($ST_I18N['fr']['login.boton_entrar']);
        $resultado = stT('login.boton_entrar') === 'Entrar';
        $ST_I18N['fr']['login.boton_entrar'] = $original;
        stEstablecerIdioma('es');
        return $resultado;
    })()
);

verificar(
    'stT devuelve la clave entre corchetes si no existe en ningún idioma',
    stT('clave.que.no.existe') === '[clave.que.no.existe]'
);

// -- stTipoLabel / stAfinidadLabel ---------------------------------------
stEstablecerIdioma('en');
verificar('stTipoLabel traduce "tiro" a inglés', stTipoLabel('tiro') === 'Shot');
verificar('stTipoLabel es insensible a mayúsculas', stTipoLabel('TIRO') === 'Shot');
verificar('stTipoLabel devuelve vacío si no hay tipo', stTipoLabel('') === '');
verificar('stTipoLabel devuelve vacío si el tipo no existe', stTipoLabel('inventado') === '');

verificar('stAfinidadLabel traduce "fuego" a inglés', stAfinidadLabel('fuego') === 'Fire');
verificar('stAfinidadLabel traduce "montaña" (con ñ) a inglés', stAfinidadLabel('montaña') === 'Mountain');
stEstablecerIdioma('es');

// -- stRenderSelectorIdioma ------------------------------------------------
verificar(
    'stRenderSelectorIdioma imprime un enlace por idioma',
    (function () {
        ob_start();
        stRenderSelectorIdioma();
        $html = ob_get_clean();
        return substr_count($html, '<a href="?lang=') === 10;
    })()
);

echo "\n";
if ($fallos > 0) {
    echo "$fallos comprobación(es) fallida(s).\n";
    exit(1);
}
echo "Todas las comprobaciones pasan.\n";
exit(0);
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php supertecnicas/tests/test_i18n.php`
Expected: error fatal `Failed opening required '.../supertecnicas/i18n.php'` (el fichero todavía no existe).

- [ ] **Step 3: Crear `supertecnicas/i18n.php`**

```php
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
    $clave = mb_strtolower(trim($tipo), 'UTF-8');
    if ($clave === '' || !isset($ST_TIPOS_I18N[$clave])) return '';
    return $ST_TIPOS_I18N[$clave][$idioma] ?? $ST_TIPOS_I18N[$clave]['es'];
}

function stAfinidadLabel(string $afinidad): string {
    global $ST_AFINIDADES_I18N;
    $idioma = $GLOBALS['ST_IDIOMA_ACTUAL'];
    $clave = mb_strtolower(trim($afinidad), 'UTF-8');
    if ($clave === '' || !isset($ST_AFINIDADES_I18N[$clave])) return '';
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
```

- [ ] **Step 4: Ejecutar el test y confirmar que pasa**

Run: `php supertecnicas/tests/test_i18n.php`
Expected: todas las líneas empiezan por `OK`, termina con `Todas las comprobaciones pasan.`, código de salida `0`.

- [ ] **Step 5: `php -l` y commit**

Run: `php -l supertecnicas/i18n.php && php -l supertecnicas/tests/test_i18n.php`
Expected: `No syntax errors detected` en ambos.

```bash
git add supertecnicas/i18n.php supertecnicas/tests/test_i18n.php
git commit -m "Añade el diccionario de idioma de supertecnicas (i18n.php)"
```

---

### Task 3: Integrar el idioma en `index.php`

**Files:**
- Modify: `supertecnicas/index.php` (reescritura completa — cambia casi cada línea de texto)
- Modify: `supertecnicas/css/supertecnicas.css` (añade `.st-idiomas`)

**Interfaces:**
- Consumes: todo lo de `supertecnicas/i18n.php` (Task 2): `stResolverIdioma()`, `stEstablecerIdioma()`, `stT()`, `stTipoLabel()`, `stAfinidadLabel()`, `stRenderSelectorIdioma()`, `ST_IDIOMAS`.
- No añade ni cambia ninguna interfaz nueva para tareas futuras — es la última tarea del plan.

- [ ] **Step 1: Sustituir `supertecnicas/index.php` completo**

El fichero actual (post-rediseño visual) tiene 215 líneas; la única lógica que
cambia es (a) cargar `i18n.php` y resolver el idioma justo después de
`lib.php`, y (b) sustituir cada cadena de texto en español por `stT('clave')`,
y las etiquetas de los `<option>` de tipo/afinidad por
`stTipoLabel()`/`stAfinidadLabel()`. El `value` de esos `<option>` sigue
siendo `$t`/`$a` (el término canónico español), sin cambios — solo cambia lo
que se ve.

Reemplazar el contenido completo de `supertecnicas/index.php` por:

```php
<?php
session_start();
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/i18n.php';

stEstablecerIdioma(stResolverIdioma());

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'login') {
    $codigoIntento = stNormalizarTexto($_POST['codigo'] ?? '');
    $pinIntento = stNormalizarTexto($_POST['pin'] ?? '');
    $codigos = stCargarCodigos();

    $equipoEncontrado = null;
    if ($codigoIntento !== '') {
        foreach ($codigos as $id => $c) {
            $codigoGuardado = stNormalizarTexto($c['codigo'] ?? '');
            $pinGuardado = stNormalizarTexto($c['pin'] ?? '');
            if (hash_equals($codigoGuardado, $codigoIntento) && hash_equals($pinGuardado, $pinIntento)) {
                $equipoEncontrado = $id;
                break;
            }
        }
    }

    if ($equipoEncontrado !== null) {
        $_SESSION['st_equipo_id'] = $equipoEncontrado;
        session_regenerate_id(true);
        header('Location: index.php');
        exit;
    }
    $error = stT('login.error');
}

$equipoId = $_SESSION['st_equipo_id'] ?? null;
$equipo = null;

if ($equipoId !== null) {
    $data = stCargarDatosOficiales();
    $idx = stBuscarEquipoPorId($data, $equipoId);
    if ($idx === null || !empty($data['equipos'][$idx]['archivado'])) {
        unset($_SESSION['st_equipo_id']);
        $equipoId = null;
    } else {
        $equipo = $data['equipos'][$idx];
    }
}

$config = stCargarConfig();
$ventanaAbierta = $config['ventana_abierta'];
$guardado = isset($_GET['guardado']);

// Iniciales para el avatar del jugador ("Fran Dictador" -> "FD").
function stIniciales($nombre) {
    $partes = preg_split('/\s+/', trim((string) $nombre));
    $partes = array_filter($partes);
    if (!$partes) return '?';
    $ini = mb_substr(reset($partes), 0, 1, 'UTF-8');
    if (count($partes) > 1) $ini .= mb_substr(end($partes), 0, 1, 'UTF-8');
    return mb_strtoupper($ini, 'UTF-8');
}
?>
<!doctype html>
<html lang="<?= stEsc($GLOBALS['ST_IDIOMA_ACTUAL']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= stEsc(stT('login.titulo')) ?> — Superliga Frontier</title>
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/supertecnicas.css">
</head>
<body>
<?php if ($equipoId === null): ?>
  <main class="st-login">
    <div class="st-marca">
      <span class="pip"></span>
      <span>Superliga Frontier</span>
    </div>
    <?php stRenderSelectorIdioma(); ?>
    <div class="card st-login-card">
      <h1><?= stEsc(stT('login.titulo')) ?></h1>
      <p class="ayuda"><?= stEsc(stT('login.subtitulo')) ?></p>
      <?php if ($error !== ''): ?>
        <p class="mal">
          <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16.5" x2="12.01" y2="16.5"/></svg>
          <?= stEsc($error) ?>
        </p>
      <?php endif; ?>
      <form method="post" action="index.php" class="st-form">
        <input type="hidden" name="accion" value="login">
        <label class="campo"><span><?= stEsc(stT('login.campo_codigo')) ?></span>
          <input class="inp" type="text" name="codigo" required autofocus autocomplete="off">
        </label>
        <label class="campo"><span><?= stEsc(stT('login.campo_pin')) ?></span>
          <input class="inp inp-mono" type="text" name="pin" required autocomplete="off">
        </label>
        <button class="btn btn-accent btn-lg btn-icon-txt" type="submit">
          <?= stEsc(stT('login.boton_entrar')) ?>
          <svg class="icon" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
      </form>
    </div>
  </main>
<?php else: ?>
  <main class="st-shell">
    <?php stRenderSelectorIdioma(); ?>
    <header class="st-cabecera">
      <div>
        <h1><?= stEsc($equipo['nombre'] ?? '') ?></h1>
        <p class="ayuda"><?= stEsc(stT('roster.subtitulo')) ?></p>
      </div>
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <span class="st-estado" data-abierta="<?= $ventanaAbierta ? '1' : '0' ?>">
          <span class="punto"></span>
          <?= $ventanaAbierta ? stEsc(stT('roster.ventana_abierta')) : stEsc(stT('roster.ventana_cerrada')) ?>
        </span>
        <a class="btn btn-secondary btn-icon-txt" href="logout.php">
          <svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <?= stEsc(stT('roster.cerrar_sesion')) ?>
        </a>
      </div>
    </header>

    <?php if ($guardado): ?>
      <div class="st-banda st-banda-ok">
        <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?= stEsc(stT('roster.guardado_ok')) ?>
      </div>
    <?php endif; ?>

    <?php if (!$ventanaAbierta): ?>
      <div class="st-banda st-banda-aviso">
        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <?= stEsc(stT('roster.ventana_cerrada_aviso')) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="guardar.php">
      <input type="hidden" name="csrf" value="<?= stEsc(stTokenCsrf()) ?>">
      <div class="st-lista">
        <?php foreach (($equipo['jugadores'] ?? []) as $i => $j): ?>
          <?php
            $slots = $j['supertecnicas'] ?? [];
            $asignadas = array_values(array_filter($slots, function ($x) { return trim((string) ($x['nombre'] ?? '')) !== ''; }));
          ?>
          <details class="jugador-card">
            <summary class="jugador-resumen">
              <span class="jugador-avatar"><?= stEsc(stIniciales($j['nombre'] ?? '')) ?></span>
              <span class="jugador-info">
                <span class="jugador-nombre"><?= stEsc($j['nombre'] ?? '') ?></span>
                <span class="jugador-meta">#<?= stEsc($j['dorsal'] ?? '') ?> · <?= stEsc($j['posicion'] ?? '') ?></span>
              </span>
              <span class="jugador-chips">
                <?php if (!$asignadas): ?>
                  <span class="chip chip-vacio"><?= stEsc(stT('roster.sin_asignar')) ?></span>
                <?php else: ?>
                  <?php foreach (array_slice($asignadas, 0, 2) as $x): ?>
                    <span class="chip"><?= stEsc($x['nombre']) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($asignadas) > 2): ?>
                    <span class="chip chip-mas">+<?= count($asignadas) - 2 ?></span>
                  <?php endif; ?>
                <?php endif; ?>
              </span>
              <svg class="icon icon-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </summary>
            <fieldset class="jugador-editor" <?= $ventanaAbierta ? '' : 'disabled' ?>>
              <input type="hidden" name="jugadores[<?= (int) $i ?>][nombre_check]" value="<?= stEsc($j['nombre'] ?? '') ?>">
              <?php for ($s = 0; $s < ST_MAX_SUPERTECNICAS; $s++):
                $st = $slots[$s] ?? ['nombre' => '', 'tipo' => '', 'afinidad' => '', 'especial' => '', 'descripcion' => ''];
              ?>
                <div class="st-slot">
                  <span class="st-slot-num"><?= stEsc(stT('roster.supertecnica')) ?> <?= $s + 1 ?></span>
                  <div class="rejilla rejilla-4">
                    <label class="campo"><span><?= stEsc(stT('campo.nombre')) ?></span>
                      <input class="inp inp-sm" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][nombre]" value="<?= stEsc($st['nombre'] ?? '') ?>" placeholder="<?= stEsc(stT('placeholder.nombre')) ?>">
                    </label>
                    <label class="campo"><span><?= stEsc(stT('campo.tipo')) ?></span>
                      <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][tipo]">
                        <?php foreach (ST_TIPOS as $t): ?>
                          <option value="<?= stEsc($t) ?>" <?= ($st['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t === '' ? '—' : stEsc(stTipoLabel($t)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="campo"><span><?= stEsc(stT('campo.afinidad')) ?></span>
                      <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][afinidad]">
                        <?php foreach (ST_AFINIDADES as $a): ?>
                          <option value="<?= stEsc($a) ?>" <?= ($st['afinidad'] ?? '') === $a ? 'selected' : '' ?>><?= $a === '' ? '—' : stEsc(stAfinidadLabel($a)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="campo"><span><?= stEsc(stT('campo.especial')) ?></span>
                      <input class="inp inp-sm inp-mono" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][especial]" value="<?= stEsc($st['especial'] ?? '') ?>" placeholder="<?= stEsc(stT('placeholder.especial')) ?>">
                    </label>
                  </div>
                  <label class="campo"><span><?= stEsc(stT('campo.descripcion')) ?></span>
                    <textarea class="inp" maxlength="300" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][descripcion]" placeholder="<?= stEsc(stT('placeholder.descripcion')) ?>"><?= stEsc($st['descripcion'] ?? '') ?></textarea>
                  </label>
                </div>
              <?php endfor; ?>
            </fieldset>
          </details>
        <?php endforeach; ?>
      </div>

      <?php if ($ventanaAbierta): ?>
        <div class="st-acciones">
          <button class="btn btn-accent btn-lg btn-icon-txt" type="submit">
            <svg class="icon" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?= stEsc(stT('roster.guardar')) ?>
          </button>
        </div>
      <?php endif; ?>
    </form>
  </main>
<?php endif; ?>
</body>
</html>
```

Nota: `ST_TIPOS`/`ST_AFINIDADES` (las constantes de `lib.php` con los valores
canónicos españoles, incluida la cadena vacía `''`) no cambian — el bucle
`foreach` sigue recorriendo exactamente los mismos 5/6 valores de siempre.
Solo cambia qué texto se imprime dentro de cada `<option>`.

- [ ] **Step 2: Añadir el estilo del selector de idioma**

En `supertecnicas/css/supertecnicas.css`, añadir al final:

```css
.st-idiomas{ display:flex; gap:.4rem; justify-content:center; margin-bottom:1.25rem; flex-wrap:wrap; }
.st-shell .st-idiomas{ justify-content:flex-end; margin-bottom:1rem; }
.st-idiomas a{
  display:inline-flex; padding:.3rem; border-radius:var(--r-sm);
  border:1px solid transparent; opacity:.55; transition:opacity var(--t1) var(--ease), border-color var(--t1) var(--ease);
}
.st-idiomas a:hover{ opacity:.85; }
.st-idiomas a.activo{ opacity:1; border-color:var(--line-2); background:var(--surface-2); }
.st-idiomas img{ display:block; border-radius:2px; }
```

- [ ] **Step 3: `php -l`**

Run: `php -l supertecnicas/index.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Prueba manual con el servidor embebido de PHP**

Con `php -S localhost:8000` corriendo desde `htdocs/` (necesita
`supertecnicas/data/codigos_equipos.json` sembrado con un equipo real de
prueba y `config.json` con `ventana_abierta: true`, igual que en tareas
anteriores del plan original — sembrar, probar, revertir al final):

1. `curl -i http://localhost:8000/supertecnicas/` sin cabeceras extra →
   200, contenido en español (`Entra con el código y el PIN de tu equipo.`).
2. `curl -i -H "Accept-Language: en-US,en;q=0.9,es;q=0.5" http://localhost:8000/supertecnicas/` → 200, contenido en inglés
   (`Enter your team's code and PIN.`), sin haber pasado `?lang=`.
3. `curl -i "http://localhost:8000/supertecnicas/?lang=fr"` → 200, contenido
   en francés, y la cookie de sesión queda con `st_lang=fr` para peticiones
   siguientes con esa misma cookie.
4. Repetir la petición anterior reutilizando la cookie de sesión pero sin
   `?lang=` → sigue en francés (confirma que `$_SESSION['st_lang']`
   persiste).
5. Login con un equipo de prueba, entrar en la plantilla, comprobar que los
   `<option>` de Tipo/Afinidad muestran las etiquetas en el idioma activo
   (ver el HTML devuelto, buscar `<option value="tiro"` y confirmar que el
   texto visible coincide con el idioma probado) y que el `value` sigue
   siendo el término español.
6. Revertir cualquier dato de prueba sembrado en `supertecnicas/data/` y
   confirmar `git status --short` limpio en `datos_oficiales.json` si se
   llegó a guardar algo.

- [ ] **Step 5: Commit**

```bash
git add supertecnicas/index.php supertecnicas/css/supertecnicas.css
git commit -m "Traduce el login y la plantilla del presidente en supertecnicas"
```

---

## Autorrevisión

- **Cobertura de la spec:** Bloque 1 (fix `sl=auto` + `SF_TIPO_MAP` +
  `tipoName()`) → Task 1. Bloque 2 (`i18n.php`, resolución de idioma,
  selector de banderas, integración en `index.php`, `tipo`/`afinidad`
  traducidos con `value` canónico intacto) → Tasks 2-3. `admin.php` fuera de
  alcance, confirmado. Nada de la spec queda sin tarea.
- **Placeholders:** ninguno — todo el código y las ~230 cadenas traducidas
  están escritas literalmente, no hay "TBD" ni "añadir traducción aquí".
- **Consistencia de tipos/nombres:** `stTipoLabel`/`stAfinidadLabel` reciben
  siempre `string` y devuelven `string` en las tres tareas; `stT()` idéntica
  en Task 2 y Task 3; `ST_TIPOS`/`ST_AFINIDADES` (de `lib.php`, ya
  existentes) no se tocan, solo se leen.

