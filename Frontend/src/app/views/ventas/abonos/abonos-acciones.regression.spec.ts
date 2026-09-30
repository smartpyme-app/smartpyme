import * as fs from 'fs';
import * as path from 'path';

/**
 * Regresión: acciones de abonos deben alinear Imprimir con canEdit()
 * y ocultar "Generar partida contable" sin funcionalidad contabilidad.
 */
describe('Abonos acciones menú', () => {
  const root = path.join(__dirname, '../../..'); // src/app


  function read(rel: string): string {
    return fs.readFileSync(path.join(root, rel), 'utf8');
  }

  it('abonos ventas: Imprimir con canEdit y partida solo con contabilidad', () => {
    const html = read('views/ventas/abonos/abonos-ventas.component.html');
    expect(html).toContain('(click)="imprimir(abono)"');
    expect(html).not.toContain("canEditTest('ventas.abonos.editar')");
    expect(html).toContain('@if (contabilidadHabilitada)');
    expect(html).toContain('generarPartidaContable(abono)');
  });

  it('gastos: partida solo con contabilidad', () => {
    const html = read('views/compras/gastos/gastos.component.html');
    expect(html).toContain('*ngIf="contabilidadHabilitada" (click)="generarPartidaContable(gasto)"');
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
    expect(html).toContain('@if (contabilidadHabilitada)');
    expect(html).toContain('generarPartidaContable(abono)');
  });
});
