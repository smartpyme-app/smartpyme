import { Injectable, inject, Injector } from '@angular/core';
import { Observable, Subject, of } from 'rxjs';
import { map, switchMap, tap } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { CountryI18nService } from '@services/country-i18n.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { ConstantsService } from '@services/constants.service';
import { SharedDataService } from '@services/shared-data.service';

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

export type EstadoDocumentoContador = 'vigente' | 'por_vencer' | 'sin_cargar';
export type EstadoCalendarioContador = 'pendiente' | 'presentado';

export interface ContadorCumplimientoResponse {
  empresa: {
    id: number;
    nombre: string;
    logo: string | null;
    giro?: string | null;
    nit?: string | null;
  };
  periodo: { mes: number; anio: number; label: string };
  kpis: {
    documentos: {
      al_dia: number;
      total: number;
      por_vencer: number;
      sin_cargar: number;
      proximo_vencimiento: { fecha: string; label: string; dias_restantes: number } | null;
    };
    fiscal: { iva_a_pagar: number; total_f14: number };
  };
  calendario_tributario: {
    codigo: string;
    titulo: string;
    fecha: string;
    fecha_corta: string;
    monto: number;
    estado: EstadoCalendarioContador;
  }[];
  renovaciones: {
    slug: string;
    nombre: string;
    fecha_limite: string;
    monto_estimado: number | null;
    nota: string | null;
    estado: string;
  }[];
  permisos?: string[];
  documentos: {
    id: number | null;
    slug: string | null;
    nombre: string;
    nombre_catalogo?: string | null;
    subible?: boolean;
    estado: EstadoDocumentoContador;
    detalle: string | null;
    archivo_url: string | null;
    vence_en?: string | null;
    es_anexo?: boolean;
    es_otro?: boolean;
  }[];
  capacidades: {
    subir_archivos: boolean;
    marcar_presentado: boolean;
    recordatorios_email: boolean;
    logo_empresa: boolean;
  };
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

export interface ContadorLibrosIvaResponse {
  empresa: { id: number; nombre: string; logo: string | null; giro?: string | null; pais?: string | null };
  modulo_pais?: 'sv' | 'cr' | 'hd' | 'general';
  informe_default?: string;
  periodo: { mes: number; anio: number; label: string };
  resumen: {
    debito_fiscal: number;
    credito_fiscal: number;
    retenciones: number;
    iva_a_pagar: number;
  };
  grupos: {
    titulo: string;
    informes: {
      clave: string;
      titulo: string;
      descripcion: string;
      total_documentos: number;
      soporta_pdf: boolean;
      soporta_excel: boolean;
      soporta_csv: boolean;
    }[];
  }[];
  actualizado_hoy: boolean;
}

export interface ContadorLibrosIvaInforme {
  clave: string;
  titulo: string;
  descripcion: string;
  columnas: { key: string; label: string; numeric?: boolean }[];
  filas: Record<string, unknown>[];
  total: number;
  mostrando: number;
  totales: Record<string, number>;
  nota?: string;
}

const KEY_EMPRESA_ACTIVA = 'SP_contador_empresa_activa';
const KEY_RECIENTES = 'SP_contador_empresas_recientes';
const KEY_CONTEXTO = 'SP_contador_contexto_empresa';

@Injectable({ providedIn: 'root' })
export class ContadoresPortalService {
  private readonly injector = inject(Injector);
  /** Emite cuando cambia la empresa cliente activa (header o portafolio). */
  readonly empresaActiva$ = new Subject<number>();
  /** Sesión local ya aplicada (user, permisos, i18n): recargar vistas que cachean empresa/sucursal. */
  readonly contextoSesionActualizado$ = new Subject<void>();

  constructor(private api: ApiService) {}

  listarEmpresas(): Observable<{ empresas: ContadorEmpresaPortafolio[] }> {
    return this.api.get('contadores/empresas');
  }

  cumplimiento(
    idEmpresa: number,
    params?: { mes?: number; anio?: number },
  ): Observable<ContadorCumplimientoResponse> {
    const q = new URLSearchParams();
    q.set('id_empresa', String(idEmpresa));
    if (params?.mes != null) {
      q.set('mes', String(params.mes));
    }
    if (params?.anio != null) {
      q.set('anio', String(params.anio));
    }
    return this.api.get(`contadores/cumplimiento?${q.toString()}`);
  }

  subirDocumentoCumplimiento(
    idEmpresa: number,
    file: File,
    opts: {
      idDocumento?: number | null;
      slug?: string | null;
      venceEn?: string | null;
      titulo?: string | null;
    },
  ): Observable<{ ok: boolean; id?: number }> {
    const fd = new FormData();
    fd.append('id_empresa', String(idEmpresa));
    fd.append('archivo', file);
    if (opts.idDocumento) {
      fd.append('id_documento', String(opts.idDocumento));
    }
    const slug = (opts.slug ?? 'libre').trim() || 'libre';
    fd.append('slug', slug);
    const venceEn = opts.venceEn?.trim();
    if (venceEn) {
      fd.append('vence_en', venceEn);
    }
    fd.append('titulo', (opts.titulo ?? '').trim());
    return this.api.upload('contadores/cumplimiento/documentos', fd);
  }

  marcarObligacionPresentada(
    idEmpresa: number,
    codigo: string,
    mes: number,
    anio: number,
  ): Observable<{ ok: boolean }> {
    return this.api.store('contadores/cumplimiento/presentado', {
      id_empresa: idEmpresa,
      codigo,
      mes,
      anio,
    });
  }

  librosIva(
    idEmpresa: number,
    params?: { mes?: number; anio?: number },
  ): Observable<ContadorLibrosIvaResponse> {
    const q = new URLSearchParams();
    q.set('id_empresa', String(idEmpresa));
    if (params?.mes != null) {
      q.set('mes', String(params.mes));
    }
    if (params?.anio != null) {
      q.set('anio', String(params.anio));
    }
    return this.api.get(`contadores/libros-iva?${q.toString()}`);
  }

  librosIvaInforme(
    idEmpresa: number,
    informe: string,
    params?: { mes?: number; anio?: number; limit?: number },
  ): Observable<ContadorLibrosIvaInforme> {
    const q = new URLSearchParams();
    q.set('id_empresa', String(idEmpresa));
    q.set('informe', informe);
    if (params?.mes != null) {
      q.set('mes', String(params.mes));
    }
    if (params?.anio != null) {
      q.set('anio', String(params.anio));
    }
    if (params?.limit != null) {
      q.set('limit', String(params.limit));
    }
    return this.api.get(`contadores/libros-iva/informe?${q.toString()}`);
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

  establecerContextoEmpresa(idEmpresa: number): Observable<ContadorContextoEmpresa | null> {
    return this.api.store(`contadores/empresas/${idEmpresa}/contexto`, {}).pipe(
      switchMap((res) => this.aplicarRespuestaContexto(res, idEmpresa)),
    );
  }

  restablecerContextoDespacho(): Observable<void> {
    return this.api.store('contadores/contexto/despacho', {}).pipe(
      switchMap((res) =>
        this.aplicarRespuestaContexto(res, null).pipe(
          tap(() => {
            localStorage.removeItem(KEY_CONTEXTO);
            localStorage.removeItem(KEY_EMPRESA_ACTIVA);
          }),
          map(() => undefined),
        ),
      ),
    );
  }

  cambiarEmpresaActiva(idEmpresa: number): Observable<ContadorContextoEmpresa | null> {
    const sesionId = this.api.auth_user()?.id_empresa;
    if (sesionId === idEmpresa) {
      this.guardarEmpresaActiva(idEmpresa);
      this.empresaActiva$.next(idEmpresa);
      return of(this.leerContextoEmpresa());
    }
    return this.establecerContextoEmpresa(idEmpresa).pipe(
      tap((ctx) => {
        if (ctx?.id) {
          this.empresaActiva$.next(ctx.id);
        }
      }),
    );
  }

  private aplicarRespuestaContexto(
    res: { contexto?: ContadorContextoEmpresa; user?: unknown },
    idEmpresaFallback: number | null,
  ): Observable<ContadorContextoEmpresa | null> {
    if (res?.user) {
      localStorage.setItem('SP_auth_user', JSON.stringify(res.user));
    }
    const ctx = res?.contexto ?? null;
    if (ctx) {
      localStorage.setItem(KEY_CONTEXTO, JSON.stringify(ctx));
      this.guardarEmpresaActiva(ctx.id);
    } else if (idEmpresaFallback != null) {
      this.guardarEmpresaActiva(idEmpresaFallback);
    }

    const userId = this.api.auth_user()?.id;
    if (!userId) {
      return of(ctx);
    }

    this.api.loadUserPermissions(userId);
    this.injector.get(FuncionalidadesService).limpiarCache();

    const empresa = (res?.user as { empresa?: unknown } | undefined)?.empresa ?? null;
    this.injector.get(ConstantsService).loadConstants().subscribe({
      error: () => {},
    });

    return this.injector
      .get(CountryI18nService)
      .applyForEmpresa(empresa)
      .pipe(
        tap(() => {
          this.injector.get(SharedDataService).invalidateOperationalListCaches();
          this.api.loadData();
          this.contextoSesionActualizado$.next();
        }),
        map(() => ctx),
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
