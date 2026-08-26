' Arranca "node _fuente/watch.js" sin ventana visible, para no tener que
' dejar una terminal abierta mientras trabajas con el gestor.
'
' Uso:
'   - Doble clic en este archivo -> arranca el vigilante en segundo plano.
'     A partir de ahí, cada vez que el gestor guarde datos_oficiales.json,
'     index.html y los {idioma}.html se regeneran solos.
'   - Para pararlo: Administrador de tareas -> buscar "Node.js JavaScript
'     Runtime" -> Finalizar tarea. (No hay ventana que cerrar porque
'     precisamente no se abre ninguna.)
'   - Si quieres que arranque solo al encender el PC: copia un acceso
'     directo a este .vbs en la carpeta de inicio de Windows
'     (Win+R -> shell:startup).
Set fso = CreateObject("Scripting.FileSystemObject")
carpeta = fso.GetParentFolderName(WScript.ScriptFullName)
Set shell = CreateObject("WScript.Shell")
shell.CurrentDirectory = carpeta
shell.Run "node ""watch.js""", 0, False
