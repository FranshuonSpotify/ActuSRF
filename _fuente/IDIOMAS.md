# Idiomas de la web

30 idiomas, ordenados por importancia para quien visita desde España. La lista
vive en **`idiomas.json`** (código, endónimo, bandera, `og:locale`, `dir`); de
ahí salen los `{código}.html`, el bloque `hreflang`/`og:locale` del `<head>`, el
`sitemap.xml` y las reglas de `.htaccess` (`node _fuente/build.js`).

## Cómo funciona

- **Diccionarios.** Los 10 heredados (es en pt it fr ja ko pl bg sr) viven en
  `dict.js`, `faq-dict.js` e `i18n.js` y viajan en todas las páginas. Los 20
  añadidos (gl de ar ro nl uk ru zh tr el cs hu sv id vi th hi bn ms tl) tienen
  un fichero por idioma en `lang/<código>.js` y **solo se inlinan en su propia
  página**: así la portada en español no paga 600 KB por idiomas que quizá nadie
  use (~234 KB comprimidos por página).
- **Cambiar de idioma** en una página que no trae ese diccionario lleva a la
  página del idioma (`/?lang=xx`, que `.htaccess` sirve desde `xx.html`),
  ya pre-renderizada y traducida. En `terminos.html` y `404.html`
  (`data-sf-meta="off"`) no se redirige: ahí manda el traductor automático.
- **Detección.** Primera visita sin `?lang=` ni idioma guardado: se lee
  `navigator.languages` (no la geolocalización). Catalán/euskera/aragonés →
  español; idioma sin versión → inglés; rastreadores (Googlebot dice `en-US`) y
  jsdom → siempre español, que es el HTML canónico.
- **Árabe** es RTL: `dir="rtl"` lo pone `sfApplyLang`; el CSS usa propiedades
  lógicas (`inline-start/end`) y un bloque `html[dir="rtl"]` al final de
  `styles.css` para degradados, el cajón lateral y las cifras.
- **Banderas:** `flagcdn.com`, salvo las que no tiene (la gallega, en
  `assets/flags/gl.svg`; basta poner una ruta que empiece por `/` en `flag`).

## Añadir un idioma

1. `idiomas.json`: una línea (la posición manda el orden del selector).
2. `i18n.js`: la misma línea en `SF_LANGS` (el build falla si no coinciden;
   `node _fuente/build.js` ya imprime la diferencia).
3. `lang/<código>.js`: el diccionario completo (copia otro como plantilla; las
   claves son las de `lang/gl.js`) + una entrada en `lang/_meta.js` (título
   ≤ 60 y descripción ≤ 160 caracteres) y en `lang/zz_seo.js` (`<h1>`) y
   `lang/zz_claves.js`.
4. `node _fuente/build.js && node _fuente/test-idiomas.js && node _fuente/test-seo.js`.

Los nombres propios (clubes, jugadores) no se traducen; las noticias y textos
libres que escriben los presidentes los traduce en vivo el traductor automático
(`translate.googleapis.com`, ver la CSP del `.htaccess`).
