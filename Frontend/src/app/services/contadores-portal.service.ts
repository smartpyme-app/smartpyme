import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { ApiService } from '@services/api.service';

export interface ContadorEmpresaPortafolio {
  acceso_id: number;
  id: number;
  nombre: string;
  logo: string | null;
  giro?: string | null;
  permisos: string[];
}

export interface ContadorEmpresaReciente {
  id: number;
  nombre: string;
  logo: string | null;
  accedidoEn: number;
}

export type EstadoCarteraContador = 'lista' | 'casi' | 'proceso' | 'atrasada';

export interface ContadorMetricasEmpresa {
  registradas: number;
  con_partida: number;
  por_contabilizar: number;
  avance: number;
  estado: EstadoCarteraContador;
  iva_pagar: number;
}

export interface ContadorDesgloseFila {
  registradas: number;
  por_correo: number;
  con_partida: number;
}

export interface ContadorCarteraDetalle {
  desglose: {
    ventas: ContadorDesgloseFila;
    compras: ContadorDesgloseFila;
    gastos: ContadorDesgloseFila;
  };
  impuestos: {
    f07: {
      debito_fiscal: number;
      credito_fiscal: number;
      retenciones: number;
      iva_a_pagar: number;
    };
    f14: {
      pago_cuenta_isr: number;
      renta_retenida: number;
      total: number;
    };
  };
  totales: {
    registradas: number;
    con_partida: number;
    por_correo: number;
  };
}

export interface ContadorCarteraResponse {
  periodo: {
    mes: number;
    anio: number;
    label: string;
    en_cierre?: boolean;
    vencimiento_iva?: { fecha: string; dias_restantes: number };
  };
  metricas: Record<string, ContadorMetricasEmpresa>;
}

export interface ContadorContextoEmpresa {
  acceso_id: number;
  id: number;
  nombre: string;
  logo: string | null;
  giro?: string | null;
  permisos: string[];
}

const KEY_EMPRESA_ACTIVA = 'SP_contador_empresa_activa';
const KEY_RECIENTES = 'SP_contador_empresas_recientes';
const KEY_CONTEXTO = 'SP_contador_contexto_empresa';

@Injectable({ providedIn: 'root' })
export class ContadoresPortalService {
  constructor(private api: ApiService) {}

  listarEmpresas(): Observable<{ empresas: ContadorEmpresaPortafolio[] }> {
    return this.api.get('contadores/empresas');
  }

  carteraDetalleEmpresa(
    idEmpresa: number,
    params?: { mes?: number; anio?: number },
  ): Observable<{ id_empresa: number; detalle: ContadorCarteraDetalle }> {
    const q = new URLSearchParams();
    if (params?.mes != null) {
      q.set('mes', String(params.mes));
    }
    if (params?.anio != null) {
      q.set('anio', String(params.anio));
    }
    const suffix = q.toString() ? `?${q.toString()}` : '';
    return this.api.get(`contadores/cartera/empresa/${idEmpresa}${suffix}`);
  }

  carteraMetricas(params?: { mes?: number; anio?: number }): Observable<ContadorCarteraResponse> {
    const q = new URLSearchParams();
    if (params?.mes != null) {
      q.set('mes', String(params.mes));
    }
    if (params?.anio != null) {
      q.set('anio', String(params.anio));
    }
    const suffix = q.toString() ? `?${q.toString()}` : '';
    return this.api.get(`contadores/cartera${suffix}`);
  }

  guardarEmpresaActiva(idEmpresa: number): void {
    localStorage.setItem(KEY_EMPRESA_ACTIVA, String(idEmpresa));
  }

  leerEmpresaActivaId(): number | null {
    const raw = localStorage.getItem(KEY_EMPRESA_ACTIVA);
    if (!raw) {
      return null;
    }
    const id = Number(raw);
    return Number.isFinite(id) ? id : null;
  }

  leerRecientes(): ContadorEmpresaReciente[] {
    try {
      const raw = localStorage.getItem(KEY_RECIENTES);
      return raw ? (JSON.parse(raw) as ContadorEmpresaReciente[]) : [];
    } catch {
      return [];
    }
  }

  establecerContextoEmpresa(idEmpresa: number): Observable<{ contexto: ContadorContextoEmpresa }> {
    return this.api.store(`contadores/empresas/${idEmpresa}/contexto`, {}).pipe(
      tap((res) => {
        if (res?.contexto) {
          localStorage.setItem(KEY_CONTEXTO, JSON.stringify(res.contexto));
          this.guardarEmpresaActiva(idEmpresa);
        }
      }),
    );
  }

  leerContextoEmpresa(): ContadorContextoEmpresa | null {
    try {
      const raw = localStorage.getItem(KEY_CONTEXTO);
      return raw ? (JSON.parse(raw) as ContadorContextoEmpresa) : null;
    } catch {
      return null;
    }
  }

  registrarAccesoReciente(empresa: ContadorEmpresaPortafolio): void {
    const prev = this.leerRecientes();
    const next: ContadorEmpresaReciente[] = [
      {
        id: empresa.id,
        nombre: empresa.nombre,
        logo: empresa.logo,
        accedidoEn: Date.now(),
      },
      ...prev.filter((r) => r.id !== empresa.id),
    ].slice(0, 3);
    localStorage.setItem(KEY_RECIENTES, JSON.stringify(next));
  }
}
