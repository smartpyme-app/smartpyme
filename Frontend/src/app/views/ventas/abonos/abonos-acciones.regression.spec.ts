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

  it('abonos compras: Imprimir con canEdit y partida solo con contabilidad', () => {
    const html = read('views/compras/abonos/abonos-compras.component.html');
    expect(html).toContain('(click)="imprimir(abono)"');
    expect(html).toContain('@if (contabilidadHabilitada)');
    expect(html).toContain('generarPartidaContable(abono)');
  });
});
