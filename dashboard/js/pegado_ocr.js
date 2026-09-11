// dashboard/js/pegado_ocr.js
// Lógica pura de "líneas reconocidas por Tesseract -> filas Nombre;POS;TIER"
// para pegado.php. Vive en su propio fichero, sin nada de DOM ni de
// Tesseract.js, precisamente para poder probarla con un script de Node
// normal y corriente (dashboard/tests/test_pegado_ocr.js) — sin framework,
// sin npm, igual que _fuente/test-intl-equipos.js prueba lógica de la web
// pública. El fichero funciona igual cargado por <script> en el navegador
// (cuelga de window.PLPegadoOcr) que con require() en Node.

(function (definicion) {
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = definicion();
    } else {
        window.PLPegadoOcr = definicion();
    }
})(function () {
    'use strict';

    // Quita acentos y pasa a mayúsculas: "Líbero"/"Médio" tienen que casar
    // igual que "LIBERO"/"MEDIO". normalize('NFD') separa la tilde como
    // marca combinante aparte, y el regex la quita.
    function normalizar(texto) {
        return String(texto).trim().toUpperCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    // Sinónimos de posición: los códigos canónicos y sus abreviaturas en los
    // diez idiomas (ver dashboard/i18n.php) MÁS palabras completas, porque
    // una hoja de cálculo real —como la que motivó esto— suele traer
    // "Portero"/"Defensa"/"Mediocentro" en vez de POR/DEF/MED. Cada entrada
    // es una frase de 1 o 2 palabras (["DELANTERO","CENTRO"] para que un
    // término de dos palabras también case), ya ordenadas por longitud de
    // frase para que el escaneo pruebe primero las de dos palabras.
    //
    // Cubierto a fondo en español, más las abreviaturas ya construidas para
    // el resto de idiomas. Ampliar con más idiomas completos es solo añadir
    // filas aquí — no hace falta tocar nada más.
    // ponytail: no cubre palabras completas en los nueve idiomas restantes,
    // solo sus abreviaturas — se amplía si hace falta cuando aparezca el caso real.
    var POSICION_FRASES = [
        // dos palabras primero
        ['DELANTERO', 'CENTRO'], ['CENTRO', 'DELANTERO'],
        ['MEDIO', 'CENTRO'], ['MEDIO', 'CAMPISTA'],
        ['GARDIEN', 'DE'],   // "Gardien de but" (fr) — se completa por prefijo más abajo
        // una palabra: canónicos y abreviaturas de los diez idiomas
        ['POR'], ['GK'], ['GR'], ['GB'], ['BR'], ['ВР'], ['ГОЛ'],
        ['DEF'], ['DF'], ['DIF'], ['OBR'], ['ЗАЩ'], ['ОДБ'],
        ['MED'], ['MF'], ['CEN'], ['MIL'], ['POM'], ['ПОЛ'], ['ВЕЗ'],
        ['ATA'], ['FW'], ['AV'], ['ATT'], ['NAP'], ['НАП'],
        // palabras completas en español, la lengua real de la liga
        ['PORTERO'], ['PORTERA'], ['GUARDAMETA'], ['ARQUERO'], ['CANCERBERO'],
        ['DEFENSA'], ['DEFENSOR'], ['CENTRAL'], ['LATERAL'], ['LIBERO'], ['ZAGUERO'],
        ['MEDIOCENTRO'], ['MEDIOCAMPISTA'], ['CENTROCAMPISTA'], ['VOLANTE'], ['MEDIO'],
        ['DELANTERO'], ['ATACANTE'], ['ARIETE'], ['EXTREMO'],
        // palabras completas en inglés, por si la captura viene de fuera
        ['GOALKEEPER'], ['DEFENDER'], ['MIDFIELDER'], ['FORWARD'], ['STRIKER']
    ].map(function (frase) { return { frase: frase, codigo: codigoDeFrase(frase) }; })
     .sort(function (a, b) { return b.frase.length - a.frase.length; });

    // Deduce el código canónico a partir de la primera palabra de la frase:
    // se define UNA vez qué código le corresponde a cada palabra, en el
    // array de abajo, y POSICION_FRASES tira de aquí en vez de repetir el
    // código en cada línea (fuente única, para no desincronizarlos).
    function codigoDeFrase(frase) {
        var mapa = {
            POR: 'POR', GK: 'POR', GR: 'POR', GB: 'POR', BR: 'POR', 'ВР': 'POR', 'ГОЛ': 'POR',
            PORTERO: 'POR', PORTERA: 'POR', GUARDAMETA: 'POR', ARQUERO: 'POR', CANCERBERO: 'POR', GOALKEEPER: 'POR',
            GARDIEN: 'POR',
            DEF: 'DEF', DF: 'DEF', DIF: 'DEF', OBR: 'DEF', 'ЗАЩ': 'DEF', 'ОДБ': 'DEF',
            DEFENSA: 'DEF', DEFENSOR: 'DEF', CENTRAL: 'DEF', LATERAL: 'DEF', LIBERO: 'DEF', ZAGUERO: 'DEF', DEFENDER: 'DEF',
            MED: 'MED', MF: 'MED', CEN: 'MED', MIL: 'MED', POM: 'MED', 'ПОЛ': 'MED', 'ВЕЗ': 'MED',
            MEDIOCENTRO: 'MED', MEDIOCAMPISTA: 'MED', CENTROCAMPISTA: 'MED', VOLANTE: 'MED', MEDIO: 'MED', MIDFIELDER: 'MED',
            ATA: 'ATA', FW: 'ATA', AV: 'ATA', ATT: 'ATA', NAP: 'ATA', 'НАП': 'ATA',
            DELANTERO: 'ATA', ATACANTE: 'ATA', ARIETE: 'ATA', EXTREMO: 'ATA', FORWARD: 'ATA', STRIKER: 'ATA',
            CENTRO: 'ATA'   // solo se usa dentro de ["MEDIO","CENTRO"], nunca sola
        };
        return mapa[frase[0]] || null;
    }

    // Los diez tiers reales (dashboard/dominio.php, PL_TIERS_SEMILLA). Esto
    // es solo una ayuda para trocear el texto reconocido: la verdad la
    // decide igualmente plValidarAltaJugador() en el servidor al
    // previsualizar.
    var TIERS = ['S++', 'S+', 'S', 'A+', 'A', 'A-', 'B+', 'B', 'B-', 'C'];

    // Columnas de más que suelen aparecer en una hoja de cálculo real junto
    // a nombre/posición/tier —afinidad, estado, etiquetas de la propia
    // plantilla— y que hay que ignorar sin que ensucien el nombre. No es una
    // lista cerrada: lo que no esté aquí y tampoco sea posición/tier/ruido
    // numérico simplemente se cuela en el nombre, que es el mismo riesgo que
    // ya existía antes.
    var RUIDO_CONOCIDO = [
        'AFINIDAD', 'JUSTICIA', 'CONTRAATAQUE', 'JUEGO', 'SUCIO', 'TENSION', 'BRECHA',
        'FUEGO', 'AIRE', 'BOSQUE', 'MONTANA', 'NEUTRO'
    ];

    function normalizarPosicion(palabra) {
        var limpia = normalizar(palabra);
        for (var i = 0; i < POSICION_FRASES.length; i++) {
            if (POSICION_FRASES[i].frase.length === 1 && POSICION_FRASES[i].frase[0] === limpia) {
                return POSICION_FRASES[i].codigo;
            }
        }
        return null;
    }

    function normalizarTier(palabra) {
        var limpia = String(palabra).trim().toUpperCase().replace(/\s+/g, '');
        return TIERS.indexOf(limpia) !== -1 ? limpia : null;
    }

    // Dorsal, salario, edad... una columna numérica de más no es ni
    // posición ni tier: se descarta en vez de colarse dentro del nombre.
    // Cubre tanto "75M"/"75€" pegado como "75,00" y "M€" sueltos —una hoja
    // de cálculo real parte el número del símbolo de moneda en columnas o
    // celdas distintas, y Tesseract los da como palabras separadas.
    var UNIDADES_SUELTAS = ['M€', '€', '$', '%', 'M'];
    function esRuidoNumerico(palabra) {
        var limpia = String(palabra).trim();
        if (/^[0-9]+([.,][0-9]+)?\s*(M€|€|\$|%|M)?$/i.test(limpia)) { return true; }
        return UNIDADES_SUELTAS.indexOf(limpia.toUpperCase()) !== -1;
    }

    function esRuidoConocido(palabra) {
        return RUIDO_CONOCIDO.indexOf(normalizar(palabra)) !== -1;
    }

    // Prueba si, empezando en palabras[i], hay una frase de posición
    // (1 o 2 palabras) reconocible. POSICION_FRASES ya viene ordenada con
    // las de dos palabras primero, así que el primer acierto es el más
    // largo posible.
    function intentarPosicion(palabras, i) {
        for (var f = 0; f < POSICION_FRASES.length; f++) {
            var frase = POSICION_FRASES[f].frase;
            if (i + frase.length > palabras.length) { continue; }
            var ok = true;
            for (var k = 0; k < frase.length; k++) {
                if (normalizar(palabras[i + k]) !== frase[k]) { ok = false; break; }
            }
            if (ok) { return { codigo: POSICION_FRASES[f].codigo, consumidas: frase.length }; }
        }
        return null;
    }

    // Convierte las líneas que ya detecta Tesseract (agrupa palabras por su
    // propio análisis de maquetación de la imagen) en filas
    // "Nombre;POS;TIER". Una línea que no trae a la vez UNA posición Y UN
    // tier reconocibles se descarta entera: es la cabecera de la tabla, o
    // una columna que no interesa aquí — justo lo pedido: ignorar lo que no
    // sea nombre/posición/tier, no intentar adivinarlo.
    //
    // $lineas: [{ words: [{ text: 'Endou' }, { text: 'Mamoru' }, ...] }, ...]
    // — la misma forma que trae resultado.data.lines de Tesseract.js.
    function filasDesdeLineas(lineas) {
        var filas = [];
        for (var i = 0; i < lineas.length; i++) {
            var palabras = (lineas[i].words || [])
                .map(function (w) { return w.text; })
                .filter(function (t) { return String(t).trim() !== ''; });
            if (palabras.length < 2) { continue; }

            var tier = null, posicion = null, nombre = [];
            var j = 0;
            while (j < palabras.length) {
                var p = palabras[j];
                if (esRuidoNumerico(p) || esRuidoConocido(p)) { j++; continue; }
                if (!tier) {
                    var t = normalizarTier(p);
                    if (t) { tier = t; j++; continue; }
                }
                if (!posicion) {
                    var m = intentarPosicion(palabras, j);
                    if (m) { posicion = m.codigo; j += m.consumidas; continue; }
                }
                nombre.push(p);
                j++;
            }
            var nombreTexto = nombre.join(' ').trim();
            if (nombreTexto !== '' && posicion && tier) {
                filas.push(nombreTexto + ';' + posicion + ';' + tier);
            }
        }
        return filas;
    }

    return {
        normalizarPosicion: normalizarPosicion,
        normalizarTier: normalizarTier,
        esRuidoNumerico: esRuidoNumerico,
        esRuidoConocido: esRuidoConocido,
        filasDesdeLineas: filasDesdeLineas
    };
});
