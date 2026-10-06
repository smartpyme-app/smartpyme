/** Mes en cierre contable habitual: mes calendario anterior (El Salvador). */
export function periodoCierrePorDefecto(fecha = new Date()): { mes: number; anio: number } {
  const d = new Date(fecha.getFullYear(), fecha.getMonth() - 1, 1);
  return { mes: d.getMonth() + 1, anio: d.getFullYear() };
}

export function etiquetaMesAnio(mes: number, anio: number): string {
  const raw = new Date(anio, mes - 1, 1).toLocaleDateString('es-SV', { month: 'long', year: 'numeric' });
  return raw.charAt(0).toUpperCase() + raw.slice(1);
}

/** Declaración de IVA: vencimiento día 14 del mes siguiente al periodo (convención SV en mockup). */
export function vencimientoIvaSv(mes: number, anio: number): Date {
  return new Date(anio, mes, 14);
}

export function diasHasta(fecha: Date, hoy = new Date()): number {
  const a = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate()).getTime();
  const b = new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate()).getTime();
  return Math.round((b - a) / 86400000);
}

export function textoVencimientoIva(mes: number, anio: number, hoy = new Date()): string {
  const mesNombre = new Date(anio, mes - 1, 1).toLocaleDateString('es-SV', { month: 'long' });
  const vence = vencimientoIvaSv(mes, anio);
  const dias = diasHasta(vence, hoy);
  const venceStr = vence.toLocaleDateString('es-SV', { day: 'numeric', month: 'long' });
  if (dias > 1) {
    return `Las declaraciones de IVA y pago a cuenta de ${mesNombre} vencen el ${venceStr}. Faltan ${dias} días.`;
  }
  if (dias === 1) {
    return `Las declaraciones de IVA y pago a cuenta de ${mesNombre} vencen mañana (${venceStr}).`;
  }
  if (dias === 0) {
    return `Hoy vence la declaración de IVA y pago a cuenta de ${mesNombre}.`;
  }
  return `El vencimiento de IVA de ${mesNombre} fue el ${venceStr}.`;
}

export function periodoEnCierre(mes: number, anio: number, hayPendientes: boolean, hoy = new Date()): boolean {
  const def = periodoCierrePorDefecto(hoy);
  if (mes !== def.mes || anio !== def.anio) {
    return hayPendientes;
  }
  const vence = vencimientoIvaSv(mes, anio);
  return diasHasta(vence, hoy) >= 0 || hayPendientes;
}

export function opcionesPeriodo(cantidad = 24, hoy = new Date()): { mes: number; anio: number; label: string }[] {
  const out: { mes: number; anio: number; label: string }[] = [];
  let mes = hoy.getMonth() + 1;
  let anio = hoy.getFullYear();
  for (let i = 0; i < cantidad; i++) {
    out.push({ mes, anio, label: etiquetaMesAnio(mes, anio) });
    mes -= 1;
    if (mes < 1) {
      mes = 12;
      anio -= 1;
    }
  }
  return out;
}
