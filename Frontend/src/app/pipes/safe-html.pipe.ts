import { Pipe, PipeTransform, inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import * as DOMPurify from 'dompurify';

// DOMPurify se publica como CommonJS (`export =`). Sin `esModuleInterop`, el
// namespace import expone el módulo exportado; se accede a `sanitize` de forma
// tipada y segura sin alterar la configuración global del proyecto.
const dompurify = DOMPurify as unknown as {
  sanitize?: (dirty: string) => string;
  default?: { sanitize: (dirty: string) => string };
};
const sanitizeHtml = (dirty: string): string =>
  (dompurify.sanitize ?? dompurify.default!.sanitize)(dirty);

/**
 * Sanitiza HTML antes de marcarlo como seguro para uso con [innerHTML].
 *
 * Usa DOMPurify para eliminar scripts, handlers de eventos (onclick, onerror,
 * etc.) y cualquier HTML peligroso que pudiera provenir del servidor (Lucas).
 * Esto previene ataques XSS en el chat.
 */
@Pipe({
  name: 'safeHtml',
  standalone: true,
  pure: true,
})
export class SafeHtmlPipe implements PipeTransform {
  private readonly sanitizer = inject(DomSanitizer);
  private readonly platformId = inject(PLATFORM_ID);

  private lastValue: string | null = null;
  private lastResult: SafeHtml | null = null;

  transform(value: string): SafeHtml {
    if (!value) {
      return this.sanitizer.bypassSecurityTrustHtml('');
    }

    // Cache por valor: evita re-sanitizar (y re-crear el SafeHtml) cuando el
    // contenido no cambió entre ciclos de detección de cambios.
    if (this.lastValue === value && this.lastResult !== null) {
      return this.lastResult;
    }
    this.lastValue = value;

    // En el navegador, DOMPurify limpia el HTML. En SSR (sin DOM) simplemente
    // se confía en el sanitizer de Angular (no ejecuta scripts al no haber DOM).
    const clean = isPlatformBrowser(this.platformId)
      ? sanitizeHtml(value)
      : value;

    this.lastResult = this.sanitizer.bypassSecurityTrustHtml(clean);
    return this.lastResult;
  }
}
