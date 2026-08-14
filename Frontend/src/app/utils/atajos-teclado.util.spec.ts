import { debeDispararAtajoTcla } from './atajos-teclado.util';

describe('atajos-teclado.util', () => {
  function el(tag: string, attrs: Record<string, string> = {}): HTMLElement {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
    return node;
  }

  it('no dispara Delete (limpiar venta) mientras el foco está en un input', () => {
    expect(debeDispararAtajoTcla('Delete', el('input', { id: 'monto_pago' }))).toBeFalse();
  });

  it('no dispara Delete en textarea ni select', () => {
    expect(debeDispararAtajoTcla('Delete', el('textarea'))).toBeFalse();
    expect(debeDispararAtajoTcla('Delete', el('select'))).toBeFalse();
  });

  it('sí dispara Delete fuera de campos editables (atajo para limpiar)', () => {
    expect(debeDispararAtajoTcla('Delete', el('button'))).toBeTrue();
    expect(debeDispararAtajoTcla('Delete', el('body'))).toBeTrue();
    expect(debeDispararAtajoTcla('Delete', null)).toBeTrue();
  });

  it('mantiene atajos F1–F12 aunque el foco esté en un input', () => {
    const input = el('input', { id: 'monto_pago' });
    expect(debeDispararAtajoTcla('F8', input)).toBeTrue();
    expect(debeDispararAtajoTcla('F5', input)).toBeTrue();
  });

  it('no dispara dígitos u otras teclas de edición dentro de un input', () => {
    const input = el('input', { name: 'monto_pago' });
    expect(debeDispararAtajoTcla('5', input)).toBeFalse();
    expect(debeDispararAtajoTcla('Backspace', input)).toBeFalse();
  });
});
