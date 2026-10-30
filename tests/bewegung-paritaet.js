'use strict';
// Paritätsprüfung (tests/bewegung.test.php, BW04): lässt den JS-Prüfer aus lp/bewegung.js über dieselben Fälle laufen wie der PHP-Prüfer.
// Aufruf: node tests/bewegung-paritaet.js <fälle.json>   Ausgabe: {"Name":"Kennung"|null, …}
const fs = require('fs');
const path = require('path');
const { pruefen } = require(path.join(__dirname, '..', 'lp', 'bewegung.js'));

const aus = {};
for (const f of JSON.parse(fs.readFileSync(process.argv[2], 'utf8'))) {
  let d;
  try {
    d = JSON.parse(f.text);
  } catch (e) {
    aus[f.name] = 'json';
    continue;
  }
  aus[f.name] = pruefen(d, f.bytes);
}
process.stdout.write(JSON.stringify(aus));
