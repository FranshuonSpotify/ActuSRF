Sistema de Lesiones v5.2

Corregido en esta versión:
- La ruleta principal y el texto usan exactamente el mismo resultado.
- La ruleta secundaria vuelve a mostrarse y gira solo cuando hay evento.
- Las lesiones sí se guardan en historial y activas.
- Los equipos archivados no reaparecen en tiradas, historial ni activas.
- Sin blur en ruletas ni selector.
- Se limpia estado semanal heredado de equipos archivados al cargar datos.

Despliegue:
1. Sube TODO el contenido del zip dentro de /lesiones_superliga/
2. Mantén datos_oficiales.json en /lesiones_superliga/ o en /htdocs/
3. Si vienes de una versión rota, ejecuta una vez api/reset_semana.php
