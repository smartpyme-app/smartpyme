export function esCampoEditableAtajo(target: EventTarget | null | undefined): boolean {
  if (!target || typeof (target as HTMLElement).tagName !== 'string') {
    return false;
  }
  const el = target as HTMLElement;
  const tag = el.tagName;
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
    return true;
  }
  return !!el.isContentEditable;
}

export function esTeclaFuncionAtajo(key: string | undefined): boolean {
  return !!key && /^F\d{1,2}$/.test(key);
}

/**
 * Atajos tcla-* (p. ej. Delete para limpiar la venta) no deben dispararse
 * mientras se escribe en un campo. F1–F12 sí, incluso con foco en un input.
 */
export function debeDispararAtajoTcla(
  key: string | undefined,
  target: EventTarget | null | undefined
): boolean {
  if (esCampoEditableAtajo(target) && !esTeclaFuncionAtajo(key)) {
    return false;
  }
  return true;
}
