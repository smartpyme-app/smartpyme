export type ComisionOrigen = 'venta' | 'manual' | 'canje_tarjeta';

export interface Comision {
  id?: number;
  id_vendedor: number;
  id_empresa?: number;
  origen: ComisionOrigen;
  correlativo_referencia?: string | null;
  id_venta?: number | null;
  categoria?: string | null;
  base_calculo: number;
  tasa_comision: number;
  monto_comision?: number;
  fecha: string;
  notas?: string | null;
  vendedor?: {
    id: number;
    name?: string;
    username?: string;
  };
}

export interface ComisionCreatePayload {
  id_vendedor: number;
  origen: ComisionOrigen;
  correlativo_referencia?: string | null;
  categoria?: string | null;
  base_calculo: number;
  tasa_comision: number;
  fecha: string;
  notas?: string | null;
}

export interface ComisionSummaryVendedor {
  id_vendedor: number;
  cantidad: number;
  total_comisiones: number;
  vendedor?: Comision['vendedor'];
}

export interface ComisionSummary {
  cantidad: number;
  total_comisiones: number;
  por_vendedor: ComisionSummaryVendedor[];
}

export interface ComisionFiltros {
  id_vendedor?: number | null;
  fecha_inicio?: string;
  fecha_fin?: string;
  correlativo_referencia?: string;
  origen?: ComisionOrigen | '';
  paginate?: number;
}

export interface ComisionSaveResponse {
  comision: Comision;
  venta_encontrada: boolean;
  advertencia?: string | null;
}
