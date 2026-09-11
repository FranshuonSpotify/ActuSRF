# Despliegue del dashboard en IONOS

URL final: **https://superligafrontier.es/dashboard/**. El despliegue es una subida de ficheros
por FTP/SFTP, igual que el resto de `htdocs/`: no hay build, ni CI, ni comando que ejecutar en el
servidor.

## Qué se sube

| Se sube | No se sube |
|---|---|
| Todo `dashboard/` **menos** `data/*.json` | `dashboard/data/*.json`: el estado vive en el servidor y se quedaría pisado |
| `dashboard/data/.htaccess` | `dashboard/tests/`: no hacen falta en producción, aunque no estorban |
| `config/secrets.example.php` | `config/secrets.php`: se edita **en el servidor** |
| | `blueprints/`: es documentación de construcción |

## Checklist

1. **Elegir PHP 8.3 o 8.4 en el panel de IONOS.** No 8.2: su soporte de seguridad termina el
   31-12-2026. El código funciona desde 8.1.0 (`lib.php` se niega a arrancar por debajo), pero
   quedarse en la versión que caduca antes es buscarse trabajo para dentro de unos meses.

2. **Rellenar `config/secrets.php` en el servidor** con las dos claves del admin:
   `admin_dashboard_user` (el usuario) y `admin_dashboard_pass_hash` (el hash de la contraseña).
   El hash se genera así, y la contraseña en claro **no** se escribe en ningún fichero:

   ```bash
   php -r "echo password_hash('tu_clave', PASSWORD_DEFAULT);"
   ```

3. **Dar permiso de escritura en `dashboard/data/`** al usuario con el que corre PHP. Sin él la
   app carga pero no guarda nada, y el síntoma (un "No se ha podido guardar" en cada formulario)
   parece un fallo de la aplicación cuando es de permisos.

4. **Comprobar que `dashboard/data/` no es accesible por URL.** Abrir en el navegador
   `https://superligafrontier.es/dashboard/data/equipos.json`: **tiene que responder 403**.
   Si se ve el JSON, o no se ha subido `dashboard/data/.htaccess` o el hosting no tiene
   `AllowOverride` activo. En ese caso los hashes de contraseña de los presidentes están
   publicados: **no se crea ninguna cuenta hasta que esto dé 403.**

5. **Prueba de humo.** Entrar en `/dashboard/admin.php` con el usuario del punto 2, crear un
   presidente de prueba, entrar con él en `/dashboard/` y comprobar que ve su equipo y que la fase
   que se muestra es la misma que la del panel de admin. Después, desactivar ese presidente.

## Comprobaciones manuales en el navegador

No hay navegador automatizado en este proyecto, así que esto se mira a mano tras el despliegue:

- A **375 px** de ancho (móvil), ninguna pantalla hace scroll horizontal de página. Las tablas
  anchas se desplazan dentro de su propia caja.
- Recorriendo con el **tabulador**, el foco se ve siempre (contorno naranja o halo en los campos)
  y nunca queda tapado.
- Con el selector de banderas, las pantallas de presidente cambian de idioma y lo recuerdan.

## Copias de seguridad

Todo el estado está en `dashboard/data/*.json`. Descargar esa carpeta por FTP es la copia de
seguridad completa; conviene hacerlo antes de cada cambio de fase.
