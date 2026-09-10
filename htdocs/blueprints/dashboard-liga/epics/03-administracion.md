# Epic 03: Administración, idiomas y entrega

Este epic cierra el producto. Da al admin las cuatro pantallas que le faltan —equipos, presidentes,
tiers y la vista global con las correcciones—, traduce la superficie del presidente a los diez
idiomas del sitio, y deja el subproyecto desplegable en IONOS.

**Va al final por dos razones distintas.** Las pantallas de admin dependen de que la superficie del
presidente exista, porque corrigen lo que esa superficie produce. Y el i18n va al final a propósito:
traducir pantallas cuyos textos todavía van a cambiar es trabajo que se tira dos veces.

Tareas: **E3-T1 … E3-T8** (pasos 11-18 de `blueprint.md` §9). Depende de los epics 01 y 02.

## Stack

PHP 8 procedural · sin framework · sin Composer · sin npm · sin paso de build · almacén en ficheros
JSON con `flock` + `rename` atómico · admin por HTTP Basic reutilizando `config/admin_auth.php` ·
sesión PHP con `password_hash` para presidentes · Apache en IONOS.

Suelo de versión: **PHP 8.1.0**. Máquina de desarrollo: 8.2.12. En el panel de IONOS conviene
elegir **8.3 u 8.4**: el soporte de seguridad de 8.2 termina el 31-12-2026.

PHP se invoca siempre como `/c/xampp/php/php.exe`, **desde la raíz del proyecto** (`htdocs/`).

Estilo: `../_fuente/styles.css` (Design System v3: negro real, 95 % monocromo, naranja `#FF5100`
como acento raro, Inter/Teko/JetBrains Mono) más `css/dashboard.css`, que se escribe aquí.

## Directory subtree

```
htdocs/
├── datos_oficiales.json         ← SE LEE en E3-T1. NUNCA SE ESCRIBE
└── dashboard/
    ├── lib.php  dominio.php  almacen.php  chrome.php   ← de epics anteriores
    ├── index.php  plantilla.php  chrome.php          ← se TRADUCEN en E3-T6
    ├── pegado.php  clausulas.php  mercado.php        ← se TRADUCEN en E3-T7
    ├── admin.php                ← del epic 01, se le añaden enlaces
    ├── admin_equipos.php        E3-T1
    ├── admin_presidentes.php    E3-T2
    ├── admin_tiers.php          E3-T3
    ├── admin_plantillas.php     E3-T4
    ├── i18n.php                 E3-T5 · motor + los 9 idiomas restantes
    ├── DESPLIEGUE.md            E3-T8
    ├── css/dashboard.css       E3-T8
    └── tests/
        ├── test_equipos.php           E3-T1
        ├── test_presidentes.php       E3-T2
        ├── test_tiers.php             E3-T3
        ├── test_admin_plantillas.php  E3-T4
        ├── test_i18n.php                    E3-T5
        ├── test_traduccion.php               E3-T6
        ├── test_traduccion_mercado.php       E3-T7
        └── test_css.php                     E3-T8
```

## Data model touched here

| Fichero | Tarea | Qué se escribe |
|---|---|---|
| `equipos.json` | E3-T1 | Alta, edición, `activo`, e importación desde `datos_oficiales.json` |
| `usuarios.json` | E3-T2 | Alta con `hash`, edición, `activo`, `equipoId` |
| `tiers.json` | E3-T3 | Los diez salarios. **No afecta a temporadas ya creadas** |
| `temporada-XXXX-YY.json` | E3-T4 | Correcciones del admin, con las mismas validaciones de rango |
| `registro.json` | E3-T4 | Eventos `CORRECCION` |
| `datos_oficiales.json` | E3-T1 | **Solo lectura.** Hay un `Verify` que lo comprueba |

Las formas completas están en `blueprint.md` §4.

## Contracts

- **El admin no vive en `usuarios.json`.** Se autentica por HTTP Basic contra `config/secrets.php`.
  No hay campo `rol` que manipular, así que no existe ninguna ruta por la que una cuenta de
  presidente escale a admin.
- **`ajustes.tiers` está congelado por temporada.** `admin_tiers.php` escribe `tiers.json` y eso solo
  afecta a las temporadas que se creen **a partir de ese momento**. Es lo que hace imposible que
  subir S++ de 75 a 90 reviente retroactivamente el cap de treinta equipos ya inscritos.
- **El admin puede saltarse el orden de las fases; no la aritmética.** Sus operaciones en
  `almacen.php` llaman a `plValidarAltaJugador()` y `plValidarClausulas()` igual que las pantallas
  del presidente, y las pantallas de admin solo llaman a esas operaciones. Un `grep` lo verifica.
- **El importador es idempotente.** Ejecutarlo dos veces deja el mismo número de equipos.
- **`plT()` cae a español** y devuelve `[clave]` si no existe en ningún idioma, para que una clave
  sin traducir se vea a simple vista en vez de romper la página.

## Conventions that bite in this area

- **El panel de admin se queda en español y NO usa `plT()`.** Es una decisión tomada, no un
  descuido: lo usa una sola persona hispanohablante, igual que `supertecnicas/admin.php`. Hay un
  `Verify` en E3-T7 que falla si `plT(` aparece en cualquier `admin*.php`.
- **`'pl'` en `PL_IDIOMAS` es el código de polaco**, no el prefijo de funciones. Es la confusión más
  fácil de cometer en E3-T5 y E3-T6.
- **`plEsc()` también en el admin.** El Basic Auth autentica; no escapa nada.
- **CSRF también en el admin.** Todos los POST de `admin*.php` lo validan.
- **Nada escribe en `datos_oficiales.json`.** Es el fichero de producción de la web pública. Hay un
  `grep` recursivo en el `Verify` de E3-T1, y una regla en `.claude/rules/fuera-de-alcance.md`.
- **`dashboard.css` no inventa tokens.** Solo `.chip-ata`, `.fase-*`, `.barra*` y los componentes de
  formulario replicados de `supertecnicas/css/supertecnicas.css`.

## Tasks

### E3-T1 — Admin de equipos e importador desde la web

Resuelve el problema que dio forma al diseño: hay equipos nuevos que aún no están en la web pública
—se crean a mano, con `equipoId: null`— y equipos de la web que aquí hay que archivar. Archivar es
**local** y no toca `datos_oficiales.json`.

El importador compara por `equipoId` y salta los ya importados, así que ejecutarlo dos veces no
duplica nada. Arrastra `nombre`, `nombre_en`, `abreviatura`, `abreviatura_en`, `escudo` y `color1`:
el `nombre_en` es lo que permite que el mercado en inglés muestre «Mount Olympus» y no
«Monte Olimpo», y lo consume la tarea E3-T7.

El importador trae **todos los equipos no archivados** de `datos_oficiales.json` y ninguno más. El
test los cuenta al ejecutarse en vez de fijar un número: la liga cambia (el 10-09-2026 eran 20;
al día siguiente, 16), y una cifra escrita aquí caducaría en cuanto alguien archive un equipo.

**Acceptance**

1. CUANDO se ejecute el importador EL SISTEMA DEBERÁ crear una entrada por cada equipo no archivado de datos_oficiales.json que no exista ya, con su equipoId apuntando al id de origen.
2. CUANDO el importador se ejecute por segunda vez EL SISTEMA DEBERÁ dejar el número de equipos exactamente igual que tras la primera.
3. CUANDO el importador termine EL SISTEMA DEBERÁ dejar datos_oficiales.json byte a byte idéntico.
4. CUANDO se archive un equipo EL SISTEMA DEBERÁ ponerlo activo:false en equipos.json sin modificar datos_oficiales.json.
5. CUANDO se cree un equipo a mano EL SISTEMA DEBERÁ guardarlo con equipoId null y permitir usarlo en la temporada igual que a uno importado.

**Files**

- `dashboard/admin_equipos.php`
- `dashboard/almacen.php`
- `dashboard/chrome.php`
- `dashboard/admin.php`
- `dashboard/tests/test_equipos.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin_equipos.php
/c/xampp/php/php.exe dashboard/tests/test_equipos.php
test -f dashboard/admin_equipos.php && ! grep -rnE "plGuardarJsonAtomico\([^)]*datos_oficiales|file_put_contents\([^)]*datos_oficiales" dashboard/
/c/xampp/php/php.exe -r "echo (int) is_readable('datos_oficiales.json');" | grep -qF "1"
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E3-T1: Admin de equipos e importador idempotente desde la web"
git tag step-11-admin-equipos
```

### E3-T2 — Admin de presidentes

`password_hash($clave, PASSWORD_DEFAULT)` al crear y al cambiar contraseña, **dentro de `almacen.php`** y
no en la pantalla: es el único punto por el que pasa toda alta o edición. **Editar los datos sin
escribir contraseña nueva conserva el hash anterior** — el error clásico aquí es rehashear una
cadena vacía y dejar al presidente sin poder entrar.

**Los copresidentes son un caso normal**, no una excepción: varios usuarios con el mismo `equipoId`,
sin restricción de unicidad. La pantalla muestra cuántos presidentes tiene cada equipo para que el
admin vea de un vistazo dónde hay dos, porque son justo los equipos donde el `rev` del epic 02 va a
entrar en juego.

**Acceptance**

1. CUANDO se cree un presidente EL SISTEMA DEBERÁ guardar únicamente el hash de la contraseña y nunca el texto en claro.
2. CUANDO se editen sus datos sin escribir contraseña nueva EL SISTEMA DEBERÁ conservar el hash anterior.
3. CUANDO se asigne a dos usuarios el mismo equipoId EL SISTEMA DEBERÁ aceptarlo y mostrar que ese equipo tiene dos presidentes.
4. CUANDO se desactive a un presidente EL SISTEMA DEBERÁ impedirle iniciar sesión y cerrarle la sesión abierta en su siguiente petición.
5. CUANDO se intente crear un presidente con un email ya existente EL SISTEMA DEBERÁ rechazarlo.

**Files**

- `dashboard/admin_presidentes.php`
- `dashboard/almacen.php`
- `dashboard/tests/test_presidentes.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin_presidentes.php
/c/xampp/php/php.exe dashboard/tests/test_presidentes.php
grep -q "password_hash" dashboard/almacen.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E3-T2: Admin de presidentes con hash, copresidentes y reasignación"
git tag step-12-admin-presidentes
```

### E3-T3 — Admin de tiers

Los diez códigos son **fijos**: no se crean, no se renombran, no se borran, y `C+`, `C-` y `D` no
existen. Solo se editan los salarios.

El aviso de que los salarios se congelan al crear una temporada va **visible y permanente** en la
pantalla, no en una nota al pie. Es la explicación de por qué esta pantalla no puede romper nada, y
quien la usa necesita entenderlo antes de tocar un número.

**Acceptance**

1. CUANDO se guarde un salario nuevo EL SISTEMA DEBERÁ escribirlo en tiers.json y NO DEBERÁ modificar ajustes.tiers de ninguna temporada existente.
2. CUANDO se cree una temporada después del cambio EL SISTEMA DEBERÁ congelar en ella los salarios nuevos.
3. CUANDO se envíe un POST con un código de tier que no sea uno de los diez EL SISTEMA DEBERÁ ignorarlo.
4. CUANDO se envíe un salario negativo o no entero EL SISTEMA DEBERÁ rechazar el guardado completo.
5. CUANDO se pinte la pantalla EL SISTEMA DEBERÁ mostrar los diez tiers en el orden de tiers.json.

**Files**

- `dashboard/admin_tiers.php`
- `dashboard/almacen.php`
- `dashboard/tests/test_tiers.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin_tiers.php
/c/xampp/php/php.exe dashboard/tests/test_tiers.php
test -f dashboard/admin_tiers.php && ! grep -nE "'C\+'|'C-'|'D'" dashboard/admin_tiers.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E3-T3: Admin de tiers, congelados por temporada"
git tag step-13-admin-tiers
```

### E3-T4 — Vista global, correcciones y aviso de duplicados

La pantalla de arbitraje. Tres piezas:

1. **Tabla global** de los equipos de la temporada activa con `X/20`, cap y cláusulas.
2. **Corrección de clausulaciones** —estado y comprador—, que escribe un evento `CORRECCION`. Es la
   vía de respaldo de §23 de la especificación, y con la vista del `registro.json` es lo que cierra
   el riesgo R4: una disputa se arbitra con datos, no de memoria.
3. **Aviso de nombres duplicados** (R2): `plPosiblesDuplicados()` de `dominio.php` señala el mismo
   nombre normalizado («Kidou Yuuto» / «KIDOU  yuuto») y los nombres a una sola letra («Endou» /
   «Endo»), que la normalización sola no ve. **Avisa, no bloquea**: pueden ser dos personas
   distintas de verdad, y bloquear obligaría al admin a resolver algo que quizá no es un problema.

**Acceptance**

1. CUANDO el admin corrija una clausulación EL SISTEMA DEBERÁ actualizar estado y clausuladoPor y añadir exactamente un evento CORRECCION a registro.json.
2. CUANDO el admin edite una plantilla en fase MERCADO EL SISTEMA DEBERÁ permitirlo pero rechazar el cambio si deja el equipo por encima de 250M o de 20 jugadores.
3. CUANDO el admin edite cláusulas EL SISTEMA DEBERÁ rechazar cualquier reparto que supere 650M.
4. CUANDO dos jugadores de equipos distintos tengan nombres que normalicen igual EL SISTEMA DEBERÁ listarlos como posible duplicado y permitir continuar sin corregirlos.
5. CUANDO se abra la vista del registro EL SISTEMA DEBERÁ mostrar los eventos del más reciente al más antiguo, con actor y sello de tiempo.

**Files**

- `dashboard/admin_plantillas.php`
- `dashboard/almacen.php`
- `dashboard/dominio.php`
- `dashboard/i18n.php`
- `dashboard/tests/test_admin_plantillas.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin_plantillas.php
/c/xampp/php/php.exe dashboard/tests/test_admin_plantillas.php
grep -q "plValidarAltaJugador" dashboard/almacen.php && grep -q "plValidarClausulas" dashboard/almacen.php && grep -q "plAdminAltaJugador" dashboard/admin_plantillas.php && grep -q "plAdminGuardarClausulas" dashboard/admin_plantillas.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E3-T4: Vista global del admin, correcciones y aviso de duplicados"
git tag step-14-admin-plantillas
```

### E3-T5 — Motor de i18n con los diez idiomas

Este paso construye **el motor y el diccionario completo**; aplicarlo a las pantallas son las tareas
E3-T6 y E3-T7. Están separados porque traducir diez idiomas y recorrer seis pantallas son dos
trabajos distintos, y juntos no caben en una sesión.

El selector de banderas replica la forma de `supertecnicas/i18n.php:375`: `<a href="?lang=xx">` con
`https://flagcdn.com/16x12/{pais}.png`, `width="16" height="12" loading="lazy"` y `aria-current` en
el activo.

**Ojo:** `'pl'` en `PL_IDIOMAS` es el código de polaco, no el prefijo de las funciones. Es la
confusión más fácil de cometer en toda la tarea.

**Acceptance**

1. CUANDO se pida ?lang=en EL SISTEMA DEBERÁ resolver el idioma a inglés y recordarlo en la sesión para las siguientes peticiones.
2. CUANDO se pida un lang que no esté en PL_IDIOMAS EL SISTEMA DEBERÁ ignorarlo y conservar el idioma anterior.
3. CUANDO no haya lang ni idioma en sesión EL SISTEMA DEBERÁ deducirlo de HTTP_ACCEPT_LANGUAGE y caer a español si no hay coincidencia.
4. CUANDO falte una clave en el idioma activo EL SISTEMA DEBERÁ devolver la versión española de esa clave, y [clave] si tampoco existe en español.
5. CUANDO se pinten los diez idiomas EL SISTEMA DEBERÁ tener el mismo juego de claves en todos, sin que a ninguno le falte una que los demás tengan.
6. CUANDO se ejecute test_i18n.php EL SISTEMA DEBERÁ salir 0.

**Files**

- `dashboard/i18n.php`
- `dashboard/tests/test_i18n.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/i18n.php
/c/xampp/php/php.exe dashboard/tests/test_i18n.php
test "$(/c/xampp/php/php.exe -r "require 'dashboard/i18n.php'; echo count(PL_IDIOMAS);")" = "10"
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E3-T5: Motor de i18n con los diez idiomas"
git tag step-15-i18n
```

### E3-T6 — Traducir el envoltorio, el login y la plantilla

El envoltorio primero, porque el resto lo hereda: `chrome.php` es donde viven el selector de
banderas y `<html lang="…">`, que es lo que hace que un lector de pantalla pronuncie el búlgaro
como búlgaro.

Los marcadores `{total}`, `{cap}` y `{disponible}` se sustituyen **después** de traducir, nunca
antes: al revés, se traduciría una cadena que ya lleva números dentro y el diccionario dejaría de
casar.

**Acceptance**

1. CUANDO se pida ?lang=en EL SISTEMA DEBERÁ servir el login y el dashboard en inglés, con sus etiquetas, botones y avisos traducidos.
2. CUANDO se pinte cualquiera de estas tres pantallas EL SISTEMA DEBERÁ emitir html lang con el idioma activo.
3. CUANDO se recorra su HTML EL SISTEMA NO DEBERÁ contener ninguna cadena [clave] sin traducir en ninguno de los diez idiomas.
4. CUANDO se pinte un error de cap o de límite de 20 EL SISTEMA DEBERÁ mostrarlo traducido y con sus marcadores sustituidos por las cifras reales.
5. CUANDO se pinte el selector de idioma EL SISTEMA DEBERÁ marcar el idioma activo con aria-current y dar alt a cada bandera.
6. CUANDO se ejecute test_traduccion.php EL SISTEMA DEBERÁ salir 0.

**Files**

- `dashboard/chrome.php`
- `dashboard/index.php`
- `dashboard/plantilla.php`
- `dashboard/tests/test_traduccion.php`

**Verify**

```bash
for f in dashboard/chrome.php dashboard/index.php dashboard/plantilla.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
/c/xampp/php/php.exe dashboard/tests/test_traduccion.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E3-T6: Traducir el envoltorio, el login y la plantilla"
git tag step-16-traduccion
```

### E3-T7 — Traducir pegado, cláusulas y mercado

Las tres pantallas con más texto del subproyecto: los errores por línea del pegado, los tres estados
del presupuesto de cláusulas, y los estados y el diálogo de confirmación del mercado.

En `mercado.php`, usar `nombre_en` del equipo cuando el idioma activo sea inglés — es el dato que el
importador de E3-T1 ya arrastra de `datos_oficiales.json`. Y cuidado con el equipo creado a mano,
que no tiene `nombre_en`: ahí se cae a `nombre`, nunca a una celda vacía.

**Acceptance**

1. CUANDO se pida ?lang=en EL SISTEMA DEBERÁ servir las tres pantallas en inglés, incluidos los mensajes de error por línea del pegado.
2. CUANDO el idioma activo sea inglés y el equipo tenga nombre_en EL SISTEMA DEBERÁ mostrar ese nombre en el mercado.
3. CUANDO el idioma activo sea inglés y el equipo NO tenga nombre_en EL SISTEMA DEBERÁ mostrar su nombre en español, sin dejar la celda vacía.
4. CUANDO se recorra el HTML de las tres pantallas EL SISTEMA NO DEBERÁ contener ninguna cadena [clave] sin traducir en ninguno de los diez idiomas.
5. CUANDO se lea cualquier admin*.php EL SISTEMA NO DEBERÁ encontrar ninguna llamada a plT().
6. CUANDO se ejecute test_traduccion_mercado.php EL SISTEMA DEBERÁ salir 0.

**Files**

- `dashboard/pegado.php`
- `dashboard/clausulas.php`
- `dashboard/mercado.php`
- `dashboard/tests/test_traduccion_mercado.php`

**Verify**

```bash
for f in dashboard/pegado.php dashboard/clausulas.php dashboard/mercado.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
/c/xampp/php/php.exe dashboard/tests/test_traduccion_mercado.php
test -f dashboard/admin.php && ! grep -l "plT(" dashboard/admin.php dashboard/admin_equipos.php dashboard/admin_presidentes.php dashboard/admin_tiers.php dashboard/admin_plantillas.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E3-T7: Traducir pegado, cláusulas y mercado"
git tag step-17-traduccion-mercado
```

### E3-T8 — CSS, responsive y despliegue

`dashboard.css` con **solo** lo que la hoja pública no da. Ni un token nuevo, ni un color fuera de
la paleta.

Responsive a 375 px: tablas anchas en un contenedor `overflow-x:auto`, tarjetas apiladas, objetivos
táctiles de acción primaria a 44 px.

`DESPLIEGUE.md` con los cinco puntos del checklist de `blueprint.md` §12. El cuarto es el que más
importa: **comprobar por URL que `dashboard/data/equipos.json` devuelve 403**. Si devuelve el JSON,
los hashes de contraseña de los presidentes están publicados en internet.

**Acceptance**

1. CUANDO se cargue cualquier pantalla a 375px de ancho EL SISTEMA NO DEBERÁ producir scroll horizontal de página; las tablas anchas DEBERÁN desplazarse dentro de su propio contenedor.
2. CUANDO se recorra la interfaz con el tabulador EL SISTEMA DEBERÁ mostrar el foco visible en todo elemento interactivo, sin quedar tapado.
3. CUANDO se ejecute el barrido de sintaxis sobre todo el subproyecto EL SISTEMA DEBERÁ salir 0 en todos los ficheros.
4. CUANDO se ejecute la batería completa de tests EL SISTEMA DEBERÁ salir 0 en los dieciocho.
5. CUANDO se lea DESPLIEGUE.md EL SISTEMA DEBERÁ nombrar los cinco puntos del checklist, incluida la comprobación de que data/ responde 403 por URL.

**Files**

- `dashboard/css/dashboard.css`
- `dashboard/DESPLIEGUE.md`
- `dashboard/tests/test_css.php`

**Verify**

```bash
test -f dashboard/css/dashboard.css && test -f dashboard/DESPLIEGUE.md
/c/xampp/php/php.exe dashboard/tests/test_css.php
for f in dashboard/*.php dashboard/tests/*.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" || exit 1; done
grep -q "403" dashboard/DESPLIEGUE.md
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E3-T8: CSS propio, responsive a 375px y checklist de despliegue"
git tag step-18-css-entrega
```

## Epic acceptance

La puerta de aceptación global de `blueprint.md` §20.1, entera:

```bash
for f in dashboard/*.php dashboard/tests/*.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" || exit 1; done
! grep -rnE "plGuardarJsonAtomico([^)]*datos_oficiales|file_put_contents([^)]*datos_oficiales" dashboard/
! git ls-files --error-unmatch dashboard/data/usuarios.json 2>/dev/null
```

Los cuatro salen 0, existen las ocho etiquetas de `step-11-admin-equipos` a `step-18-css-entrega`, y los
trece criterios de aceptación de §20.1 se han recorrido a mano en el navegador.

## Pitfalls

- **Rehashear una contraseña vacía al editar un presidente.** Lo deja sin poder entrar, y el síntoma
  aparece días después, cuando intenta acceder.
- **Impedir dos presidentes por equipo.** Los copresidentes son un requisito, no un error de datos.
- **Traducir el panel de admin.** No solo es trabajo de más: rompe el `Verify` de E3-T5.
- **Confundir `'pl'` de polaco con el prefijo `pl` de las funciones.**
- **Dejar que una corrección del admin salte las validaciones de rango.** El admin manda sobre el
  orden de las fases, no sobre la aritmética; saltárselas deja un equipo en un estado que la app
  luego no sabe representar.
- **Hacer que el aviso de duplicados bloquee.** Son homónimos posibles, no un error.
- **Que el importador escriba en `datos_oficiales.json`.** Es el fichero de producción de la web
  pública, con todos los equipos de la liga y sus plantillas reales.
- **Inventar tokens de color en `dashboard.css`.** La paleta está cerrada.
- **Dar por hecho el 403 de `data/` sin comprobarlo por URL.** Depende de que `AllowOverride` esté
  activo en el hosting, y es lo único de este checklist cuyo fallo publica contraseñas.

## Before moving on

- [ ] Los dieciocho tests pasan y el barrido de sintaxis sale 0.
- [ ] Ningún fichero de `dashboard/` escribe en `datos_oficiales.json` (lo comprueba el `grep` recursivo; `git status` no vale aquí: ese fichero cambia por el flujo de la web pública).
- [ ] Ningún `dashboard/data/*.json` está versionado.
- [ ] El importador ejecutado dos veces deja el mismo número de equipos.
- [ ] Los diez idiomas tienen el mismo juego de claves y ninguna pantalla muestra `[clave]`.
- [ ] A 375 px no hay scroll horizontal de página en ninguna pantalla.
- [ ] `dashboard/data/equipos.json` devuelve **403** pedido por URL en el servidor.
- [ ] Existe la etiqueta `step-18-css-entrega`.

El subproyecto está terminado.
