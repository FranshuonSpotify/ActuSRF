---
name: anadir-pantalla
description: Usar al crear una pantalla PHP nueva dentro de dashboard/ (por ejemplo plantilla.php, clausulas.php, mercado.php o una vista de admin). Cubre el orden obligatorio de sesión, guardia de acceso, fase, CSRF, i18n, escapado y test. Dispara con "nueva pantalla", "añadir vista", "crear página en plantillas".
---

# Añadir una pantalla a `dashboard/`

## Cuándo usarla

Al crear cualquier `.php` de nivel superior dentro de `dashboard/` que
imprima HTML: pantallas de presidente y vistas del panel de admin.

## Pasos

1. **Cabecera.** Comentario en español de una o dos líneas diciendo qué
   pantalla es y por qué existe. Luego:

   ```php
   if (session_status() === PHP_SESSION_NONE) { session_start(); }
   require_once __DIR__ . '/almacen.php';   // arrastra lib.php y dominio.php
   require_once __DIR__ . '/i18n.php';
   ```

2. **Guardia de acceso.** Pantalla de presidente:

   ```php
   $usuario = plUsuarioActual();
   if ($usuario === null) { header('Location: index.php'); exit; }
   ```

   Vista de admin, en su lugar:

   ```php
   require_once __DIR__ . '/../config/admin_auth.php';
   requerirAdminBasicAuthConClaves('admin_dashboard_user', 'admin_dashboard_pass_hash', 'Dashboard Admin');
   ```

3. **Fase.** Lee la fase activa y decide **editable o solo lectura** antes de
   pintar nada: `$fase = plFaseActiva(); $editable = ($fase === 'ROSTER');`
   (o `'CLAUSULAS'`, o `'MERCADO'`, según la pantalla). El admin ignora el
   bloqueo de fase pero **no** los rangos.

4. **POST.** Antes de tocar datos:

   ```php
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       if (!plCsrfValido()) { http_response_code(403); exit('CSRF invalido'); }
       // revalidar SIEMPRE fase + reglas de dominio.php aquí, no en el cliente
   }
   ```

   El formulario lleva `<input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">`
   y, si guarda plantilla o cláusulas, también
   `<input type="hidden" name="rev" value="<?= plEsc($rev) ?>">`.

5. **Textos.** Pantalla de presidente: cada cadena visible es `plT('clave')`,
   con la clave dada de alta en el diccionario `es` de `i18n.php` y en los
   otros nueve idiomas. El panel de admin va en español, sin `plT()`.

6. **HTML.** `plCabecera($titulo)` y `plPie()` de `chrome.php`. Todo valor
   interpolado con `plEsc()`. Un solo `<h1>`, `<main>` con landmark, tablas
   anchas dentro de `<div class="tabla-scroll">`, y todo estado con texto
   además de color.

7. **Test.** Crea `dashboard/tests/test_<pantalla>.php` con el patrón del
   resto: `require_once __DIR__ . '/arnes.php';`, `plVerificar(...)` por cada
   comprobación y `plSalirConResultado();` al final. Comprueba al menos: el
   render con sesión válida contiene lo que debe, un POST sin CSRF no escribe
   nada, y la pantalla respeta el bloqueo de fase.

## Verificar

```bash
for f in dashboard/*.php dashboard/tests/*.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done   # expect: exit 0
for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" || exit 1; done                  # expect: exit 0, ninguna linea FAIL
```

## No hagas

- No renderices el camino de redirección desde el arnés: un `exit` dentro de
  un `include` mata el proceso del test. Comprueba la **función de guardia**
  (`plUsuarioActual()` devuelve `null`), no la pantalla.
- No escribas JSON sin pasar por `almacen.php`.
- No valides solo en el cliente.
- No inventes una clave de i18n sin darla de alta en los diez idiomas.
- No copies el marcado de otra pantalla con variaciones: si falta un
  componente, se añade a `css/dashboard.css`.
