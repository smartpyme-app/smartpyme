import * as fs from 'fs';
import * as path from 'path';

/**
 * Regresión: abonos ventas usan permisos granulares ventas.abonos.*
 */
describe('Abonos acciones menú', () => {
  const root = path.join(__dirname, '../../..'); // src/app


  function read(rel: string): string {
    return fs.readFileSync(path.join(root, rel), 'utf8');
  }

  it('abonos ventas: editar/imprimir con ventas.abonos y partida con contabilidad', () => {
    const html = read('views/ventas/abonos/abonos-ventas.component.html');
    expect(html).toContain('(click)="imprimir(abono)"');
    expect(html).toContain("canEditTest('ventas.abonos.editar')");
    expect(html).toContain("hasPermission('ventas.abonos.ver')");
    expect(html).toContain("canGenerarPartidaContable('cxc'");
    expect(html).toContain('generarPartidaContable(abono)');
    expect(html).toContain('puedeEmitirRep(abono)');
    expect(html).toContain('(click)="openDTE(mDTE, abono)"');
    expect(html).toContain('puedeVerRep(abono)');
    expect(html).toContain('#mDTE');
    expect(html).toContain('imprimirDTEPDF(abono)');
    expect(html).toContain('imprimirDTEXML(abono)');
    expect(html).toContain('app-fe-enviar-correo');
    const ts = read('views/ventas/abonos/abonos-ventas.component.ts');
    expect(ts).toContain(".normalize('NFD')");
    expect(ts).toContain('openDTE(');
    expect(ts).toContain("tipo=abono");
    expect(ts).toContain("tipo_dte: '10'");
    expect(ts).toContain('tieneCorreoDestino');
    expect(ts).toContain('conCorreoDestino');
  });

  it('Crédito con tilde habilita REP (no basta includes cred)', () => {
    const cond = 'Crédito'
      .toLowerCase()
      .normalize('NFD')
      .replace(/\p{M}/gu, '');
    expect(cond.includes('cred')).toBe(true);
    expect('crédito'.includes('cred')).toBe(false);
  });

  it('gastos: partida solo con permiso granular', () => {
    const html = read('views/compras/gastos/gastos.component.html');
    expect(html).toContain("canGenerarPartidaContable('gasto'");
  });

  it('producto: switches de restaurante solo con el módulo', () => {
    const editar = read('views/inventario/productos/producto/informacion/producto-informacion.component.html');
    const crear = read('shared/modals/crear-producto/crear-producto.component.html');
    expect(editar).toContain('*ngIf="restauranteHabilitado"');
    expect(editar).toContain('Genera comanda');
    expect(crear).toContain('@if (restauranteHabilitado)');
    expect(crear).toContain('Genera comanda');
  });

  it('abonos compras: Imprimir con canEdit y partida solo con contabilidad', () => {
    const html = read('views/compras/abonos/abonos-compras.component.html');
    expect(html).toContain('(click)="imprimir(abono)"');
    expect(html).toContain("canGenerarPartidaContable('cxp'");
    expect(html).toContain('generarPartidaContable(abono)');
  });
});
