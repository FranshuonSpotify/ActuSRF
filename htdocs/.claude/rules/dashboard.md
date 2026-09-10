---
description: Convenciones del subproyecto dashboard/ — prefijo, escapado, CSRF, fases y datos
paths:
  - "dashboard/**"
---

# Convenciones de `dashboard/`

## Nombres

- **Toda función lleva prefijo `pl`**: `plCargarJson`, `plEsc`, `plTotalSalarios`,
  `plValidarClausulas`. Es el equivalente del prefijo `st` de `supertecnicas/`.
- Sin clases, sin namespaces, sin `use`. PHP procedural, como el resto del repo.
- Nombres de función, variable, clave JSON y clase CSS **en español**.
- Comentarios en español y explicando **el porqué**, no el qué.
- Cuidado: `'pl'` es también el código de idioma **polaco** en `PL_IDIOMAS`.
  No confundas el prefijo de funciones con una clave de idioma.

## Seguridad

- `plEsc($v)` = `htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8')`, y se usa en
  **todo** valor interpolado en HTML. Sin excepciones.
- Todo POST valida `plCsrfValido()` antes de hacer nada; si falla, `plCortar(403, …)`.
  Un POST con éxito termina en `plRedirigir(…)`. Nunca `header()`+`exit` a pelo. El token es `bin2hex(random_bytes(32))` en `$_SESSION`, comparado con
  `hash_equals()`.
- Tras un login correcto: `session_regenerate_id(true)`.
- Contraseñas de presidente: `password_hash($clave, PASSWORD_DEFAULT)` y
  `password_verify()`. Nunca un hash propio, nunca texto plano.
- El admin **no** es una fila de `usuarios.json`: es HTTP Basic vía
  `require_once __DIR__.'/../config/admin_auth.php'` +
  `requerirAdminBasicAuthConClaves('admin_dashboard_user', 'admin_dashboard_pass_hash', 'Dashboard Admin')`.

## PHP 8.1+

- Suelo **8.1.0**, fijado por `array_is_list()`. `lib.php` lo comprueba con
  `version_compare` y muere con mensaje claro si no se cumple.
- **`?? ''` antes de pasar cualquier valor de `$_GET`/`$_POST`/JSON decodificado a
  una función interna** (`trim`, `str_contains`, `mb_substr`, `htmlspecialchars`).
  Desde 8.1 pasar `null` a un parámetro no nullable está deprecado.
- Tipos nullable explícitos en las firmas: `?string $x = null`, nunca
  `string $x = null` (deprecado en 8.4).
- Toda pantalla arranca con
  `if (session_status() === PHP_SESSION_NONE && !headers_sent()) { session_start(); }`.
  El `!headers_sent()` no es decorativo: no se puede abrir una sesion despues de
  enviar cabeceras, y el arnes de tests renderiza la pantalla cuando ya ha impreso.
  En una peticion real la pantalla es el punto de entrada y la sesion se abre igual.

## Datos

- Escritura **siempre** por `plGuardarJsonAtomico()`: `flock(LOCK_EX)` sobre
  `"$ruta.lock"` → `tempnam()` → `file_put_contents` → `chmod(0644)` →
  `rename()`. Nunca `file_put_contents` directo sobre el fichero final.
- `json_encode` siempre con `JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT`.
- **Dentro de una temporada, el salario se lee de `ajustes.tiers` del fichero de esa
  temporada, jamás de `tiers.json`.** `tiers.json` solo alimenta temporadas nuevas.
- Todo guardado de la plantilla o de las cláusulas de un equipo pasa por
  `plGuardarEquipoTemporada(string $temporadaId, string $equipoId, array $datos, int $revEsperado): array`
  y se rechaza con `error.rev_desfasado` si el `rev` del disco no coincide.
- `dashboard/data/` no se versiona y lleva `.htaccess` con `Require all denied`.

## Reglas de negocio — se revalidan en servidor, siempre

- Máximo 20 jugadores por equipo.
- Salary cap 250M sobre la suma de salarios.
- Cláusulas: nunca **más** de 650M; por debajo se guarda como borrador y el equipo
  queda `INCOMPLETO`; exactamente 650M es `Presupuesto completo`.
- Posiciones: exactamente `POR`, `DEF`, `MED`, `ATA`. (La web pública usa `DEL`;
  aquí no, y los jugadores de este subproyecto no se enlazan con los de ella.)
- Fases: `ROSTER` (plantilla editable) · `CLAUSULAS` (cláusulas editables) ·
  `MERCADO` (solo lectura + marcar clausulado) · `CERRADA` (todo solo lectura).
- En `MERCADO` un presidente solo puede marcar jugadores **de otro equipo** como
  clausulados **por su propio equipo**, y no puede revertirlo. El admin sí.
- Las validaciones de rango también se aplican al admin.
- Toda clausulación y toda corrección escribe un evento en `registro.json`, que es
  **append-only**: nunca se edita ni se borra una entrada.

## Prohibido

- Escribir en `datos_oficiales.json` — se lee y punto.
- Tocar cualquier fichero fuera de `dashboard/` salvo `.gitignore` y
  `config/secrets.example.php`, y solo añadiendo líneas.
- Añadir Composer, npm, un framework, un ORM o un paso de build.
- Crear códigos de tier fuera de los diez de `tiers.json`.
