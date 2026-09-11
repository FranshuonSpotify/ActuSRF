// dashboard/tests/test_pegado_ocr.js
// Self-check de dashboard/js/pegado_ocr.js: la lógica de "líneas reconocidas
// por Tesseract -> filas Nombre;POS;TIER" del reconocimiento de capturas de
// pegado.php. Sin framework, sin npm — igual que _fuente/test-intl-equipos.js
// y compañía:
//   node dashboard/tests/test_pegado_ocr.js

const assert = require('assert');
const { normalizarPosicion, normalizarTier, esRuidoNumerico, esRuidoConocido, filasDesdeLineas } = require('../js/pegado_ocr.js');

let fallos = 0;
function verificar(descripcion, condicion) {
    if (condicion) {
        console.log('OK   ' + descripcion);
    } else {
        console.log('FAIL ' + descripcion);
        fallos++;
    }
}

// Una línea de Tesseract se construye como { words: [{text:'..'}, ...] }.
const linea = (...palabras) => ({ words: palabras.map((t) => ({ text: t })) });

// -- normalizarPosicion: canónicas y sinónimos de los diez idiomas --------
verificar('POR ya es canónico', normalizarPosicion('POR') === 'POR');
verificar('minúsculas y espacios no importan', normalizarPosicion(' por ') === 'POR');
verificar('GK (inglés/japonés/coreano) normaliza a POR', normalizarPosicion('GK') === 'POR');
verificar('BR (polaco) normaliza a POR', normalizarPosicion('BR') === 'POR');
verificar('ВР (búlgaro) normaliza a POR', normalizarPosicion('ВР') === 'POR');
verificar('MF (inglés) normaliza a MED', normalizarPosicion('MF') === 'MED');
verificar('ATT (italiano/francés) normaliza a ATA', normalizarPosicion('ATT') === 'ATA');
verificar('una palabra que no es ninguna posición no normaliza a nada', normalizarPosicion('XYZ') === null);
verificar('un nombre de jugador tampoco cuela como posición', normalizarPosicion('Endou') === null);

// -- palabras completas en español: la hoja de cálculo real que motivó esto -
verificar('Portero normaliza a POR', normalizarPosicion('Portero') === 'POR');
verificar('Guardameta también', normalizarPosicion('Guardameta') === 'POR');
verificar('Defensa normaliza a DEF', normalizarPosicion('Defensa') === 'DEF');
verificar('Central también (defensa central)', normalizarPosicion('Central') === 'DEF');
verificar('Mediocentro normaliza a MED', normalizarPosicion('Mediocentro') === 'MED');
verificar('Delantero normaliza a ATA', normalizarPosicion('Delantero') === 'ATA');
verificar('un acento no rompe la coincidencia (Líbero)', normalizarPosicion('Líbero') === 'DEF');
verificar('mayúsculas o minúsculas da igual (PORTERO)', normalizarPosicion('PORTERO') === 'POR');

verificar('en inglés, Goalkeeper también normaliza', normalizarPosicion('Goalkeeper') === 'POR');
verificar('y Forward', normalizarPosicion('Forward') === 'ATA');

// -- normalizarTier: los diez tiers exactos, nada más --------------------
for (const t of ['S++', 'S+', 'S', 'A+', 'A', 'A-', 'B+', 'B', 'B-', 'C']) {
    verificar(`el tier ${t} se reconoce`, normalizarTier(t) === t);
}
verificar('minúsculas también', normalizarTier('s++') === 'S++');
verificar('un tier inventado no normaliza', normalizarTier('D') === null);
verificar('"S" no es lo mismo que "S+": no hay coincidencia parcial', normalizarTier('S ') === 'S' && normalizarTier('S+') === 'S+');

// -- esRuidoNumerico: dorsal, salario... columnas a ignorar ---------------
verificar('un dorsal (número solo) es ruido', esRuidoNumerico('7'));
verificar('un salario con M es ruido', esRuidoNumerico('75M'));
verificar('un nombre no es ruido', !esRuidoNumerico('Endou'));
verificar('un tier no es ruido numérico (así no se pierde el propio tier)', !esRuidoNumerico('A+'));

// -- filasDesdeLineas: el caso completo -----------------------------------
const filas = filasDesdeLineas([
    linea('Jugador', 'Posición', 'Tier'),                 // cabecera: ni posición ni tier reconocibles -> se descarta
    linea('Endou', 'Mamoru', 'POR', 'S++'),                // nombre de dos palabras, orden conservado
    linea('7', 'Kazemaru', 'Ichirouta', 'DEF', 'S', '60M'),// dorsal y salario alrededor: se ignoran, no ensucian el nombre
    linea('Goenji', 'Shuuya', 'ATA'),                      // sin tier -> línea incompleta, se descarta entera
    linea('Kabeyama'),                                     // una sola palabra: ni siquiera se analiza
    linea('Kidou', 'Yuuto', 'GK', 'A+'),                   // posición en inglés (GK) -> normaliza a POR igualmente
]);

verificar('la cabecera se descarta (ni posición ni tier)', !filas.some((f) => f.startsWith('Jugador')));
verificar('Endou Mamoru entra con su nombre completo y en orden', filas.includes('Endou Mamoru;POR;S++'));
verificar('el dorsal y el salario no ensucian el nombre de Kazemaru', filas.includes('Kazemaru Ichirouta;DEF;S'));
verificar('Goenji se descarta entero por no traer tier', !filas.some((f) => f.startsWith('Goenji')));
verificar('Kabeyama (una sola palabra) no genera ninguna fila', !filas.some((f) => f.startsWith('Kabeyama')));
verificar('Kidou entra con GK normalizado a POR', filas.includes('Kidou Yuuto;POR;A+'));
verificar('solo las tres líneas válidas producen fila: exactamente 3', filas.length === 3);

// -- caso real: hoja de cálculo con posición completa + columna de afinidad -
// La captura que motivó esto: #, Nombre, Posición (palabra completa),
// Afinidad (columna de más), Tier, Salario. "Afinidad"/"Justicia"/etc. no
// son ni posición ni tier: tienen que ignorarse, no colarse en el nombre.
verificar('"Justicia" (columna de afinidad) es ruido conocido', esRuidoConocido('Justicia'));
verificar('"Contraataque" también', esRuidoConocido('Contraataque'));
verificar('pero un nombre normal no lo es', !esRuidoConocido('Beluga'));

const filasReales = filasDesdeLineas([
    linea('1', 'Beluga', 'Portero', 'Afinidad', 'B+', '8,00', 'M€'),
    linea('2', 'Tinkel', 'Urmia', 'Defensa', 'Afinidad', 'B', '6,00', 'M€'),
    linea('7', 'Harry', 'Dwind', 'Delantero', 'Centro', 'Juego', 'Sucio', 'A-', '14,00', 'M€'),
    linea('18', 'Cary', 'Lancaster', '2,00', 'M€'),   // sin posición asignada aún: se descarta
]);
verificar('Beluga entra como POR, sin que "Afinidad" ensucie el nombre', filasReales.includes('Beluga;POR;B+'));
verificar('Tinkel Urmia entra como DEF con nombre de dos palabras', filasReales.includes('Tinkel Urmia;DEF;B'));
verificar('"Delantero Centro" (dos palabras) normaliza junto a ATA', filasReales.includes('Harry Dwind;ATA;A-'));
verificar('Cary Lancaster, sin posición todavía en la hoja, se descarta en vez de colarse mal',
    !filasReales.some((f) => f.startsWith('Cary')));
verificar('solo tres de las cuatro líneas producen fila', filasReales.length === 3);

// -- el módulo funciona igual cargado con require() que con <script> ------
verificar('exporta las cuatro funciones esperadas',
    typeof normalizarPosicion === 'function' && typeof normalizarTier === 'function'
    && typeof esRuidoNumerico === 'function' && typeof filasDesdeLineas === 'function');

console.log('');
if (fallos > 0) {
    console.log(fallos + ' comprobacion(es) fallida(s).');
    process.exit(1);
}
console.log('Todas las comprobaciones pasan.');
