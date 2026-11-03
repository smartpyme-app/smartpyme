/** Run: node Frontend/src/app/shared/utils/permisos-modulos-empresa.check.mjs */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const source = fs.readFileSync(path.join(dir, 'permisos-modulos-empresa.ts'), 'utf8');

for (const key of [
  'productos.paquetes',
  'gastos.categorias',
  'finanzas.bancos',
  'clinica.profesionales',
  'clinica.pacientes',
  'contabilidad.activos',
]) {
  assert.match(source, new RegExp(`'${key.replace('.', '\\.')}'`), `falta regla ${key}`);
}

function empresaFlag(empresa, key) {
  const v = empresa?.[key];
  return v === true || v === 1 || v === '1';
}

function moduloContabilidad(slugs) {
  return slugs.has('contabilidad');
}

assert.equal(empresaFlag({ modulo_citas: 1 }, 'modulo_citas'), true);
assert.equal(empresaFlag({ modulo_paquetes: 0 }, 'modulo_paquetes'), false);
assert.equal(moduloContabilidad(new Set()), false);
assert.equal(moduloContabilidad(new Set(['contabilidad'])), true);

console.log('permisos-modulos-empresa.check.mjs OK');
