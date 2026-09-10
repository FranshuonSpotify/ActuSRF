# Epic 02: Superficie del presidente — plantilla, cláusulas y mercado

Este epic construye las cuatro pantallas que usan las treinta personas para las que existe el
producto: Dashboard, Mi plantilla, Cláusulas y Mercado. Al terminarlo, un presidente puede recorrer
la temporada entera —inscribir, repartir y registrar clausulaciones— sin que el admin toque nada
salvo las transiciones de fase.

**Es el corte vertical que valida todo el diseño.** El epic 01 dejó la aritmética probada en
aislamiento; aquí se comprueba que esa aritmética, la máquina de fases y el control de concurrencia
se sostienen cuando los toca una persona a través de un formulario.

Tareas: **E2-T1 … E2-T5** (pasos 06-10 de `blueprint.md` §9). Depende del epic 01 completo.

## Stack

PHP 8 procedural · sin framework · sin Composer · sin npm · sin paso de build · almacén en ficheros
JSON con `flock` + `rename` atómico · sesión PHP con `password_hash` · CSRF por token de
sincronizador · Apache en IONOS.

Suelo de versión: **PHP 8.1.0**. Máquina de desarrollo: 8.2.12.

PHP se invoca siempre como `/c/xampp/php/php.exe`, **desde la raíz del proyecto** (`htdocs/`).

Estilo: `../_fuente/styles.css` (Design System v3, se enlaza y no se edita) más
`css/dashboard.css`, que se escribe en el epic 03. Durante este epic las pantallas se construyen
con las clases ya existentes —`.wrap`, `.card`, `.btn`, `.badge`, `.chip`, `table.tbl`— y se ven
razonablemente bien sin la hoja propia.

## Directory subtree

```
htdocs/
└── dashboard/
    ├── lib.php  dominio.php  almacen.php  chrome.php  i18n.php   ← del epic 01, se USAN
    ├── index.php            E2-T1 · se completa con el dashboard
    ├── plantilla.php        E2-T2
    ├── pegado.php           E2-T3
    ├── clausulas.php        E2-T4
    ├── mercado.php          E2-T5
    └── tests/
        ├── arnes.php                del workspace/
        ├── test_dashboard.php       E2-T1
        ├── test_plantilla.php       E2-T2
        ├── test_pegado.php          E2-T3
        ├── test_clausulas.php       E2-T4
        └── test_mercado.php         E2-T5
```

Nada fuera de `dashboard/` se modifica en este epic. Ni una línea.

## Data model touched here

Solo `temporada-XXXX-YY.json` y `registro.json`. Las formas completas están en `blueprint.md` §4.
Lo que se toca:

| Campo | Quién lo escribe | Regla |
|---|---|---|
| `equipos[id].jugadores[]` | E2-T2, E2-T3 | Máximo 20. Salario derivado del tier |
| `equipos[id].jugadores[].clausula` | E2-T4 | Suma ≤ 650M; = 650M para estar completo |
| `equipos[id].jugadores[].estado` / `clausuladoPor` / `clausuladoEn` | E2-T5 | Solo en fase `MERCADO` |
| `equipos[id].rev` | E2-T2, E2-T3, E2-T4, E2-T5 | +1 en cada guardado del equipo |
| `eventos[]` de `registro.json` | E2-T5 | Append-only, tipo `CLAUSULACION` |

`equipos.json`, `usuarios.json` y `tiers.json` se **leen** y no se escriben aquí.

## Contracts

- **Todo guardado de plantilla o cláusulas pasa por
  `plGuardarEquipoTemporada($temporadaId, $equipoId, $datos, $revEsperado)`.** Ninguna pantalla llama
  a `plGuardarJsonAtomico()`.
- **Toda validación viene de `dominio.php`** y devuelve `['ok'=>bool,'error'=>?clave]`. Ninguna
  pantalla reimplementa la aritmética del cap ni de los 650M.
- **Los mensajes de error son claves de i18n**, resueltas con `plT()` y con los marcadores
  `{total}`, `{cap}`, `{disponible}` sustituidos después de traducir.
- **Salario derivado, jamás recibido.** El `salario` que llegue en un POST se ignora; se calcula con
  `plSalarioDeTier($tier, $ajustes['tiers'])`.
- **POST/Redirect/GET en todo guardado con éxito**, para que recargar no reenvíe el formulario.

## Conventions that bite in this area

- **La fase se valida en el servidor, siempre.** Deshabilitar el formulario es presentación; un POST
  reenviado a mano tiene que ser rechazado igual. Cada pantalla comprueba
  `plPuedeEditarPlantilla()` / `plPuedeEditarClausulas()` / `plPuedeMarcarClausulado()` en el camino
  del POST, no solo al pintar.
- **CSRF antes que nada.** `plCsrfValido()` es la primera comprobación del manejador de POST, antes
  de mirar fase, permisos o datos. 403 si falla.
- **`plEsc()` en todo lo interpolado.** Los nombres de jugador los escriben los presidentes: son
  entrada de usuario, y van a acabar en la pantalla de los otros 29 equipos.
- **`?? ''` antes de toda función interna de PHP** sobre valores de `$_POST`/`$_GET`.
- **Los enlaces de navegación no se ocultan según la fase.** La pantalla se sirve deshabilitada o en
  solo lectura, con su aviso. Ocultar el enlace haría creer que la sección no existe.
- **Comprobar el `false` de los guardados.** Un guardado que falla en silencio es el peor fallo
  posible aquí: el presidente cree que ha guardado su reparto de 650M y no lo ha hecho.

## Tasks

### E2-T1 — Dashboard del presidente

Las cuatro tarjetas y la tabla de solo lectura. Todas las cifras se **calculan** al pintar; ninguna
se almacena. Un total de salarios guardado en el fichero es un total que se desincroniza.

El estado del mercado se deriva de la fase (`MERCADO` ⇒ abierto), no de un campo aparte. Dos fuentes
para el mismo hecho es una fuente de más.

**Acceptance**

1. CUANDO la fase sea MERCADO EL SISTEMA DEBERÁ mostrar el mercado como ABIERTO; en ROSTER, CLAUSULAS y CERRADA DEBERÁ mostrarlo CERRADO.
2. CUANDO el equipo tenga 18 jugadores EL SISTEMA DEBERÁ mostrar 18 / 20 y nunca un número de plazas negativo.
3. CUANDO los salarios sumen 225M con cap de 250M EL SISTEMA DEBERÁ mostrar los 25M disponibles calculados, no un valor almacenado.
4. CUANDO un jugador esté CLAUSULADO EL SISTEMA DEBERÁ mostrarlo con el texto 'Clausulado por {equipo}', nunca solo con un color.
5. CUANDO el equipo no tenga jugadores EL SISTEMA DEBERÁ mostrar un estado vacío con la acción propia de la fase actual.

**Files**

- `dashboard/index.php`
- `dashboard/tests/test_dashboard.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/index.php
/c/xampp/php/php.exe dashboard/tests/test_dashboard.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E2-T1: Dashboard del presidente con cap, cláusulas y fase"
git tag step-06-dashboard
```

### E2-T2 — Mi plantilla

El salario se muestra como **texto calculado**, nunca como `<input>`. Su `Verify` lo comprueba con un
`grep` negativo de `name="salario"`, precedido de un `test -f` para que la guarda no pase vacíamente
si el fichero no llegó a crearse.

El `rev` viaja en un `hidden` del formulario y vuelve en el POST. Si no coincide, se rechaza con el
aviso de copresidente y **no se escribe nada**. Este es el caso que `flock` no cubre: dos
copresidentes editando a la vez.

Fuera de `ROSTER`, el aviso literal es *«No puedes modificar tu plantilla. La fase de inscripción de
plantillas ya ha finalizado.»* — y el POST se rechaza en el servidor igualmente.

**Acceptance**

1. CUANDO la fase no sea ROSTER EL SISTEMA DEBERÁ rechazar todo POST de plantilla en el servidor, aunque el formulario se reenvíe a mano.
2. CUANDO se intente añadir un jugador que dejaría el total en 260M con cap de 250M EL SISTEMA DEBERÁ rechazarlo mostrando el total resultante y los millones disponibles, sin escribir nada.
3. CUANDO se intente añadir el jugador número 21 EL SISTEMA DEBERÁ rechazarlo indicando el límite de 20.
4. CUANDO se cambie el tier EL SISTEMA DEBERÁ recalcular el salario desde ajustes.tiers y rechazar el cambio si el nuevo total supera el cap.
5. CUANDO el formulario se envíe con un rev distinto al del disco EL SISTEMA DEBERÁ rechazar el guardado con el aviso de copresidente y dejar el fichero sin modificar.
6. CUANDO se envíe un salario manipulado en el POST EL SISTEMA DEBERÁ ignorarlo y usar el derivado del tier.

**Files**

- `dashboard/plantilla.php`
- `dashboard/tests/test_plantilla.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/plantilla.php
/c/xampp/php/php.exe dashboard/tests/test_plantilla.php
test -f dashboard/plantilla.php && ! grep -nE "name=[\"']salario[\"']" dashboard/plantilla.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E2-T2: Mi plantilla: alta, edición y borrado con cap y rev"
git tag step-07-plantilla
```

### E2-T3 — Pegado masivo `Nombre;POS;TIER`

**Esta tarea es la mitigación del riesgo R1 y por eso está en la v1.** Teclear veinte jugadores por
equipo en formularios es más lento que la hoja de cálculo que esta app sustituye, y una herramienta
más lenta que lo que reemplaza no se adopta.

El lote es **atómico**: o entra entero o no entra nada. Importar la mitad y dejar al presidente
adivinando cuáles faltaron es peor que rechazarlo. La previsualización señala el número de línea y
el motivo de cada error, y el botón de confirmar solo aparece si todo el lote es válido y no supera
ni el cap ni los 20.

**Acceptance**

1. CUANDO se pegue un lote con una línea inválida EL SISTEMA DEBERÁ señalar el número de línea y su motivo, y NO DEBERÁ ofrecer el botón de confirmar.
2. CUANDO un lote hiciera superar el cap de 250M EL SISTEMA DEBERÁ rechazarlo entero indicando el total resultante.
3. CUANDO un lote hiciera pasar de 20 jugadores EL SISTEMA DEBERÁ rechazarlo entero indicando cuántas plazas quedan.
4. CUANDO se confirme un lote válido de 20 líneas EL SISTEMA DEBERÁ escribir los 20 jugadores en una sola operación e incrementar rev exactamente en 1.
5. CUANDO el pegado incluya líneas en blanco o espacios sobrantes EL SISTEMA DEBERÁ ignorarlas y recortarlos sin considerarlo un error.
6. CUANDO la fase no sea ROSTER EL SISTEMA DEBERÁ rechazar el pegado en el servidor.

**Files**

- `dashboard/pegado.php`
- `dashboard/tests/test_pegado.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/pegado.php
/c/xampp/php/php.exe dashboard/tests/test_pegado.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E2-T3: Pegado masivo Nombre;POS;TIER con previsualización atómica"
git tag step-08-pegado
```

### E2-T4 — Cláusulas

La regla, exactamente: **nunca superar 650M**; guardar por debajo **sí se permite** y deja el equipo
`INCOMPLETO`; el ✓ `Presupuesto completo` aparece solo en 650M exactos. Ese guardado por debajo es
el borrador, y existe para que nadie pierda media hora de reparto por no haber cuadrado todavía.

El contador en vivo es JavaScript inline y **el servidor revalida el total íntegro**. Con JavaScript
desactivado la pantalla sigue guardando y sigue validando: el contador es una comodidad, no la
validación.

Se permite cláusula **0** en un jugador. Si eso debe prohibirlo la liga, es una regla de liga, no de
la aplicación.

**Acceptance**

1. CUANDO el total supere 650M EL SISTEMA DEBERÁ rechazar el guardado indicando los millones disponibles y no escribir nada.
2. CUANDO el total sea inferior a 650M EL SISTEMA DEBERÁ guardar el reparto y marcar el equipo INCOMPLETO.
3. CUANDO el total sea exactamente 650M EL SISTEMA DEBERÁ guardar y mostrar 'Presupuesto completo'.
4. CUANDO una cláusula sea 0 EL SISTEMA DEBERÁ aceptarla, porque no hay mínimo por jugador.
5. CUANDO se desactive JavaScript EL SISTEMA DEBERÁ seguir permitiendo guardar y seguir validando el total en el servidor.
6. CUANDO la fase sea ROSTER EL SISTEMA DEBERÁ mostrar el aviso de terminar antes la inscripción y rechazar todo POST.

**Files**

- `dashboard/clausulas.php`
- `dashboard/tests/test_clausulas.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/clausulas.php
/c/xampp/php/php.exe dashboard/tests/test_clausulas.php
grep -q "plValidarClausulas" dashboard/clausulas.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E2-T4: Cláusulas: reparto con borrador y 650M exactos"
git tag step-09-clausulas
```

### E2-T5 — Mercado y registro de clausulaciones

Consulta libre en **cualquier** fase. El botón de marcar solo aparece en `MERCADO`, solo sobre
jugadores de otros equipos y solo si están `DISPONIBLE`.

`plPuedeClausular()` comprueba las cuatro condiciones y se llama **en el camino del POST**, no solo
al decidir si se pinta el botón. Un presidente que reenvíe el formulario a mano con el `equipoId` de
otro club tiene que ser rechazado.

Cada clausulación escribe un evento en `registro.json`. Ese registro es lo único que permitirá
arbitrar una disputa (R4): sin él se arbitra de memoria.

**Acceptance**

1. CUANDO la fase no sea MERCADO EL SISTEMA DEBERÁ mostrar el listado completo en solo lectura y rechazar en el servidor todo intento de marcar clausulado.
2. CUANDO un presidente marque a un jugador de otro equipo en MERCADO EL SISTEMA DEBERÁ ponerlo CLAUSULADO con clausuladoPor igual a su propio equipoId y añadir exactamente un evento CLAUSULACION.
3. CUANDO un presidente intente marcar a un jugador de su propio equipo EL SISTEMA DEBERÁ rechazarlo en el servidor.
4. CUANDO el POST indique como comprador un equipo distinto al del presidente EL SISTEMA DEBERÁ rechazarlo aunque el valor venga manipulado.
5. CUANDO un jugador ya esté CLAUSULADO EL SISTEMA DEBERÁ ocultar el botón a los presidentes y rechazar el POST.
6. CUANDO se registre una clausulación EL SISTEMA DEBERÁ dejar intactas las entradas anteriores de registro.json.

**Files**

- `dashboard/mercado.php`
- `dashboard/tests/test_mercado.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/mercado.php
/c/xampp/php/php.exe dashboard/tests/test_mercado.php
grep -q "plPuedeMarcarClausulado" dashboard/mercado.php && grep -q "plPuedeClausular" dashboard/mercado.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E2-T5: Mercado consultable y registro append-only de clausulaciones"
git tag step-10-mercado
```

## Epic acceptance

```bash
for f in dashboard/*.php dashboard/tests/*.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" || exit 1; done
! grep -rnE "plGuardarJsonAtomico([^)]*datos_oficiales|file_put_contents([^)]*datos_oficiales" dashboard/
```

Los tres salen 0, y existen las etiquetas `step-06-dashboard` … `step-10-mercado`.

**Recorrido manual que cierra el epic:** entrar como presidente en fase `ROSTER`, inscribir 20
jugadores (pegando el lote), pasar a `CLAUSULAS` desde el panel de admin, repartir 650M exactos,
pasar a `MERCADO`, y marcar como clausulado a un jugador de otro equipo. Ese recorrido es el
producto entero.

## Pitfalls

- **Validar la fase solo al pintar.** Deshabilitar el formulario no impide un POST. La comprobación
  va también en el manejador, y su ausencia no la detecta ningún test que solo mire el HTML.
- **Aceptar el `salario` que llega en el POST.** Se ignora y se deriva del tier, siempre.
- **Leer el `rev` al pintar y no reenviarlo en el formulario.** Sin el `hidden` no hay control de
  concurrencia, y la pantalla parece funcionar perfectamente hasta que dos copresidentes coinciden.
- **Ignorar el `false` de `plGuardarEquipoTemporada()`.** El presidente ve la pantalla recargada y
  cree que guardó.
- **Poner la validación de los 650M solo en el JavaScript.** Es una comodidad, no una puerta.
- **Aplicar el pegado a medias cuando una línea falla.** Deja la plantilla en un estado que el
  presidente no puede reconstruir.
- **Pintar el estado del mercado con color y sin texto.** Rompe §15 y la revisión de accesibilidad.
- **Olvidar `overflow-x:auto` en la tabla del mercado.** Con 600 filas y siete columnas, a 375 px
  provoca scroll horizontal de página.

## Before moving on

- [ ] Los cinco tests de este epic pasan, y también los cinco del epic 01.
- [ ] Un presidente no puede ver ni tocar la plantilla de otro equipo por ninguna vía.
- [ ] Un POST reenviado a mano fuera de fase se rechaza.
- [ ] Guardar cláusulas por 580M funciona y marca `INCOMPLETO`; por 651M se rechaza.
- [ ] Marcar un jugador propio como clausulado se rechaza en el servidor.
- [ ] `registro.json` conserva todos los eventos anteriores tras una clausulación nueva.
- [ ] Existe la etiqueta `step-10-mercado`.

Luego: `epics/03-administracion.md`.
