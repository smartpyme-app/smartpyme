import { Injectable, inject, Injector } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

/** Locales con archivo en assets/i18n/es-{cod}.json */
const SUPPORTED_LOCALES = new Set(['SV', 'CR', 'GT', 'HN']);

@Injectable({ providedIn: 'root' })
export class CountryI18nService {
  private injector = inject(Injector);
  static readonly FALLBACK_LOCALE = 'es-SV';

  private translate(): TranslateService {
    return this.injector.get(TranslateService);
  }

  /** es-SV, es-CR, es-GT, es-HN; fallback es-SV si el país no tiene traducciones. */
  localeFromEmpresa(
    empresa?: { cod_pais?: string | null; pais?: string | null } | null
  ): string {
    const cod = resolveCodigoPaisFe(empresa);
    return SUPPORTED_LOCALES.has(cod) ? `es-${cod}` : CountryI18nService.FALLBACK_LOCALE;
  }

  applyForEmpresa(
    empresa?: { cod_pais?: string | null; pais?: string | null } | null
  ): void {
    this.translate().use(this.localeFromEmpresa(empresa));
  }

  /** Atajo tipado para mensajes en TypeScript. */
  t(key: string): string {
    return this.translate().instant(key);
  }

  /** Alias legible en componentes: `this.countryI18n.k('country.identity.name')` */
  k(key: string): string {
    return this.t(key);
  }

  /** Mensajes FE: `fe('emitSuccessTitle')` → clave `country.tax.fe.*` */
  fe(key: string, params?: Record<string, unknown>): string {
    return this.translate().instant(`country.tax.fe.${key}`, params);
  }

  tax(key: string): string {
    return this.t(`country.tax.${key}`);
  }

  /** Libros fiscales / IVA: `libroIva('menuLabel')` → `country.tax.libroIva.*` */
  libroIva(key: string): string {
    return this.t(`country.tax.libroIva.${key}`);
  }
}
