/* Un texto ya escrito en el idioma de la web no se traduce: _sfATTextos
   devuelve el original cuando el idioma detectado coincide con el destino.
   Sin red: se prueba la función aislada con respuestas reales del endpoint.
   Uso:  node _fuente/test-idioma-origen.js   (desde htdocs/) */
'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert');

const src = fs.readFileSync(path.join(__dirname, 'i18n.js'), 'utf8');
const fn = src.match(/function _sfATTextos\([\s\S]*?\n        \}/)[0];
const _sfATTextos = new Function(fn + '; return _sfATTextos;')();

// Web en francés: la técnica española se traduce, la francesa queda intacta.
assert.deepStrictEqual(
  _sfATTextos([['tornade de feu', 'es'], ['Tornade De Feu', 'fr']], ['Tornado de fuego', 'Tornade de Feu'], 'fr'),
  ['tornade de feu', 'Tornade de Feu']);
// Web en español: la española queda intacta, la francesa se traduce.
assert.deepStrictEqual(
  _sfATTextos([['Tornado De Fuego', 'es'], ['Tornado de fuego', 'fr']], ['Tornado de fuego', 'Tornade de Feu'], 'es'),
  ['Tornado de fuego', 'Tornado de fuego']);
// Código con región ("zh-CN") se compara por prefijo; reparto que no cuadra → null.
assert.deepStrictEqual(_sfATTextos([['x', 'ja-JP']], ['必殺'], 'ja'), ['必殺']);
assert.strictEqual(_sfATTextos([['a', 'es']], ['a', 'b'], 'fr'), null);
console.log('OK  idioma de origen respetado');
