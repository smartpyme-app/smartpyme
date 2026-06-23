import { Injectable } from '@angular/core';
import { ApiService } from '@services/api.service';
import {
  EmpresaCurrencySource,
  FormatEmpresaCurrencyOptions,
  formatEmpresaCurrency,
  formatEmpresaCurrencyInt,
  getEmpresaCurrencyCode,
  getEmpresaCurrencySymbol,
} from '../helpers/currency-format.helper';

@Injectable({ providedIn: 'root' })
export class CurrencyFormatService {
  constructor(private apiService: ApiService) {}

  getEmpresa(): EmpresaCurrencySource | null | undefined {
    return this.apiService.auth_user()?.empresa;
  }

  getCode(empresa?: EmpresaCurrencySource | null): string {
    return getEmpresaCurrencyCode(empresa ?? this.getEmpresa());
  }

  getSymbol(empresa?: EmpresaCurrencySource | null): string {
    return getEmpresaCurrencySymbol(empresa ?? this.getEmpresa());
  }

  format(
    value: number | null | undefined,
    empresa?: EmpresaCurrencySource | null,
    options?: FormatEmpresaCurrencyOptions
  ): string {
    return formatEmpresaCurrency(value, empresa ?? this.getEmpresa(), options);
  }

  formatInt(value: number | null | undefined, empresa?: EmpresaCurrencySource | null): string {
    return formatEmpresaCurrencyInt(value, empresa ?? this.getEmpresa());
  }
}
