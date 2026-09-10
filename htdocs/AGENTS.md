# superligafrontier.es (`htdocs/`) — instrucciones para agentes

Repositorio de la web pública en producción. El único trabajo abierto es el
subproyecto **`dashboard/`**: gestor de plantillas, cláusulas y mercado de la
Superliga Frontier. PHP procedural sin dependencias.

## Alcance

Solo se escribe dentro de `dashboard/`. Fuera de ahí, únicamente se **añaden
líneas** a `.gitignore` y a `config/secrets.example.php`. Todo lo demás
(`index.html`, `_fuente/`, `api/`, `supertecnicas/`, `admin/`, `cron/`,
`datos_oficiales.json`) es producción y no se toca.

## Comandos

PHP no está en PATH: se invoca como `/c/xampp/php/php.exe`. Todo se ejecuta
desde la raíz del proyecto.

| Tarea | Comando |
|---|---|
| Versión de PHP | `/c/xampp/php/php.exe -v` |
| Sintaxis de un fichero | `/c/xampp/php/php.exe -l dashboard/lib.php` |
| Sintaxis de todo | `for f in dashboard/*.php dashboard/tests/*.php; do /c/xampp/php/php.exe -l "$f" \|\| exit 1; done` |
| Un test | `/c/xampp/php/php.exe dashboard/tests/test_dominio.php` |
| Todos los tests | `for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" \|\| exit 1; done` |

No hay Composer, npm, build ni framework de tests.

## Innegociable

1. Nunca escribas en `datos_oficiales.json` ni fuera de `dashboard/`.
2. Nunca metas un valor en HTML sin `plEsc()`.
3. Nunca aceptes un POST sin validar CSRF y sin revalidar fase y reglas en servidor.
4. Nunca calcules un salario desde `tiers.json`: dentro de una temporada se lee `ajustes.tiers`.
5. Nunca versiones `dashboard/data/*.json` ni `config/secrets.php`.
6. Nunca des una tarea por terminada con el barrido de sintaxis o los tests en rojo.

Arquitectura completa, capas y tokens de diseño: `CLAUDE.md` en este mismo
directorio.
