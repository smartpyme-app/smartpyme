export interface EmpresaCurrencySource {
  moneda?: string | null;
  currency?: {
    currency_symbol?: string | null;
  } | null;
}

export interface FormatEmpresaCurrencyOptions {
  blankWhenZero?: boolean;
  blankWhenNull?: boolean;
}

const FALLBACK_SYMBOLS: Record<string, string> = {
  USD: '$',
  HNL: 'L',
  GTQ: 'Q',
  CRC: '₡',
  NIO: 'C$',
  PAB: 'B/.',
  BZD: 'BZ$',
  MXN: '$',
  EUR: '€',
};

export function getEmpresaCurrencyCode(empresa?: EmpresaCurrencySource | null): string {
  return empresa?.moneda || 'USD';
}

export function getEmpresaCurrencySymbol(empresa?: EmpresaCurrencySource | null): string {
  const symbol = empresa?.currency?.currency_symbol;
  if (symbol) {
    return symbol;
  }

  const code = getEmpresaCurrencyCode(empresa);
  return FALLBACK_SYMBOLS[code] || '$';
}

export function formatEmpresaCurrency(
  value: number | null | undefined,
  empresa?: EmpresaCurrencySource | null,
  options?: FormatEmpresaCurrencyOptions
): string {
  if (value === null || value === undefined) {
    if (options?.blankWhenNull) {
      return '';
    }
    value = 0;
  }

  if (value === 0 && options?.blankWhenZero) {
    return '';
  }

  const currencySymbol = getEmpresaCurrencySymbol(empresa);
  const formattedValue = new Intl.NumberFormat('en-US', {
    style: 'decimal',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(value));

  const result = `${currencySymbol}${formattedValue}`;
  return value < 0 ? `(${result})` : result;
}

export function formatEmpresaCurrencyInt(
  value: number | null | undefined,
  empresa?: EmpresaCurrencySource | null
): string {
  if (value === null || value === undefined) {
    value = 0;
  }

  const currencySymbol = getEmpresaCurrencySymbol(empresa);
  const formattedValue = new Intl.NumberFormat('en-US', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(Math.abs(value));

  const result = `${currencySymbol}${formattedValue}`;
  return value < 0 ? `(${result})` : result;
}
