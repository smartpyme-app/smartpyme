/** Oculta SQL y detalles técnicos en mensajes mostrados al usuario. */
export function sanitizeApiErrorMessage(raw: unknown): string {
  const msg = typeof raw === 'string' ? raw.trim() : '';
  if (!msg) {
    return 'Ocurrió un error inesperado. Intente de nuevo o contacte a soporte.';
  }
  if (looksLikeTechnicalLeak(msg)) {
    if (/Out of range value/i.test(msg)) {
      return 'Un monto o cantidad supera el límite permitido. Verifique los valores o contacte a soporte.';
    }
    return 'No se pudo completar la operación. Intente de nuevo o contacte a soporte.';
  }
  return msg;
}

function looksLikeTechnicalLeak(message: string): boolean {
  const needles = [
    'SQLSTATE',
    'Connection:',
    'SQL:',
    'insert into `',
    'update `',
    'delete from `',
    'Integrity constraint violation',
    'PDOException',
    'Stack trace',
  ];
  return needles.some((n) => message.includes(n));
}
