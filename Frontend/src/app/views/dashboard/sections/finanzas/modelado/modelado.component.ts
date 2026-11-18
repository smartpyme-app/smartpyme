import { ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ApiService } from '@services/api.service';
import { SharedDataService } from '@services/shared-data.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { finalize, switchMap, take } from 'rxjs/operators';
import { of } from 'rxjs';
import {
  MODELADO_VACIA,
  FinanzasModeladoDataService,
} from '../../../services/finanzas-modelado-data.service';
import {
  ModeladoCalendarioCostoFila,
  ModeladoGastoFila,
  ModeladoGastoTipo,
  ModeladoPresupuestoFila,
  ModeladoPresupuestoReparto,
  ModeladoPresupuestoSucursales,
  ModeladoPresupuestoVista,
  ModeladoVista,
} from './modelado.model';

const PRESUPUESTO_RUBROS = ['ventas', 'costo', 'personal', 'local', 'marketing', 'admin', 'financieros'];

const FRECUENCIA: Record<string, string> = {
  mensual: 'Mensual',
  quincenal: 'Quincenal',
  anual: 'Anual',
};

const AREAS_GASTO = [{ id: 'empresa', label: 'Empresa (consolidado)' }];

interface ModeladoGastoNuevoForm {
  nombre: string;
  proveedor: string;
  sucursal: string;
  rubro: string;
  tipo: ModeladoGastoTipo;
  montoMensual: number | null;
  pctVentas: number | null;
  diaPago: string;
  llevaIva: boolean;
  estimado?: boolean;
}

const RUBROS_GASTO: { id: string; label: string }[] = [
  { id: 'personal', label: 'Personal' },
  { id: 'local', label: 'Local y servicios' },
  { id: 'ventas_marketing', label: 'Ventas y marketing' },
  { id: 'admin', label: 'Administrativos' },
  { id: 'financieros', label: 'Financieros' },
  { id: 'costo', label: 'Costo de ventas' },
  { id: 'otros', label: 'Gastos varios' },
];

const RUBROS_COSTO: { id: string; label: string }[] = [
  { id: 'costo', label: 'Costo de ventas' },
  { id: 'financieros', label: 'Financieros' },
  { id: 'otros', label: 'Otros' },
];

@Component({
  selector: 'app-modelado',
  templateUrl: './modelado.component.html',
  styleUrls: ['./modelado.component.css'],
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, NgSelectModule, TooltipModule],
})
export class ModeladoComponent implements OnInit {
  private readonly api = inject(ApiService);
  private readonly shared = inject(SharedDataService);
  private readonly modeladoData = inject(FinanzasModeladoDataService);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly anio = new Date().getFullYear();
  data: ModeladoVista = MODELADO_VACIA;
  gastos: ModeladoGastoFila[] = [];
  calendarioFilas: ModeladoCalendarioCostoFila[] = [];
  calendarioPendiente = MODELADO_VACIA.calendarioPendiente;
  presupuestoFilas: ModeladoPresupuestoFila[] = [];
  presupuestoSucursales: ModeladoPresupuestoSucursales | null = null;
  presupuestoReparto: ModeladoPresupuestoReparto = 'igual';
  metaVentasAnual = 0;
  pronostico = { ...MODELADO_VACIA.parametrosPronostico };
  costoNuevo: ModeladoGastoNuevoForm | null = null;
  gastoNuevo: ModeladoGastoNuevoForm | null = null;
  proveedores: { id: number; etiqueta: string }[] = [];
  sucursales: { id: number; nombre: string }[] = [];
  readonly rubrosGasto = RUBROS_GASTO;
  readonly rubrosCosto = RUBROS_COSTO;
  readonly areasGasto = AREAS_GASTO;
  cargando = false;
  contenidoListo = false;
  errorMensaje: string | null = null;

  readonly presupuestoVistas: { id: ModeladoPresupuestoVista; label: string; activo: boolean }[] = [
    { id: 'empresa', label: 'Empresa', activo: true },
    { id: 'sucursales', label: 'Sucursales', activo: true },
    { id: 'areas', label: 'Áreas', activo: false },
  ];
  presupuestoVista: ModeladoPresupuestoVista = 'empresa';

  private loadSeq = 0;

  ngOnInit(): void {
    this.cargar();
    this.cargarCatalogos();
  }

  private cargarCatalogos(): void {
    this.shared.getProveedores().pipe(take(1)).subscribe((rows) => {
      this.proveedores = (rows ?? []).map((p) => ({
        id: p.id,
        etiqueta: this.etiquetaProveedor(p),
      })).filter((p) => p.etiqueta);
      this.cdr.markForCheck();
    });
    this.api.getAll('sucursales/list').pipe(take(1)).subscribe((rows: { id: number; nombre: string }[]) => {
      this.sucursales = rows ?? [];
      this.cdr.markForCheck();
    });
  }

  private etiquetaProveedor(p: { tipo?: string; nombre_empresa?: string; nombre_completo?: string; nombre?: string; nombre_comercial?: string }): string {
    const legal = p.tipo === 'Empresa' ? p.nombre_empresa : (p.nombre_completo || p.nombre);
    const base = (legal || '').trim();
    if (!base) {
      return '';
    }
    return p.nombre_comercial ? `${base} (${p.nombre_comercial})` : base;
  }

  formatMonto(value: number | null): string {
    return formatEmpresaCurrency(value ?? 0, this.api.auth_user()?.empresa);
  }

  pct(value: number | null | undefined): string {
    return value == null ? '—' : `${value}%`;
  }

  cuenta(n: number, uno: string, varios: string): string {
    return `${n} ${n === 1 ? uno : varios}`;
  }

  tasa(value: number | null | undefined): string {
    if (value == null || Number.isNaN(value)) {
      return '—';
    }
    const t = Number.isInteger(value) ? String(value) : String(Math.round(value * 100) / 100);
    return `${t}%`;
  }

  gastoSubtitulo(gasto: ModeladoGastoFila): string {
    const partes = [gasto.categoria];
    if (gasto.proveedor) {
      partes.push(gasto.proveedor);
    }
    if (gasto.sucursal) {
      partes.push(gasto.sucursal);
    }
    if (gasto.tipo === 'variable' && gasto.pctVentas != null) {
      partes.push(`${gasto.pctVentas}% ventas`);
    }
    partes.push(`${this.formatMonto(gasto.montoMensual)}/mes`);
    return partes.join(' · ');
  }

  comportamientoTexto(tipo: ModeladoGastoTipo): string {
    return tipo === 'variable' ? 'Cambia de un mes a otro.' : 'Se paga lo mismo cada mes, venda o no venda.';
  }

  puedeAgregarGasto(n: ModeladoGastoNuevoForm): boolean {
    return !!n.nombre.trim() && n.montoMensual != null && Number(n.montoMensual) >= 0;
  }

  ventasPromedioMes(): number {
    const v = this.data.ventasPromedio3Meses;
    return v > 0 ? v / 3 : 0;
  }

  montoVariableDe(n: ModeladoGastoNuevoForm | null): number {
    if (!n || n.tipo !== 'variable' || n.pctVentas == null) {
      return 0;
    }
    return (this.ventasPromedioMes() * Number(n.pctVentas)) / 100;
  }

  nuevoGastoMontoVariable(): number {
    return this.montoVariableDe(this.gastoNuevo);
  }

  nuevoCostoMontoVariable(): number {
    return this.montoVariableDe(this.costoNuevo);
  }

  frecuenciaTexto(codigo: string): string {
    return FRECUENCIA[codigo] ?? codigo;
  }

  elegirTipo(gasto: ModeladoGastoFila, tipo: ModeladoGastoTipo, ev: Event): void {
    ev.preventDefault();
    if (gasto.origen || gasto.tipo === tipo) {
      return;
    }
    const previo = gasto.tipo;
    gasto.tipo = tipo;
    this.cdr.markForCheck();
    this.persistirGasto(gasto, () => {
      gasto.tipo = previo;
      this.cdr.markForCheck();
    });
  }

  guardarMonto(gasto: ModeladoGastoFila, ev: Event): void {
    const crudo = (ev.target as HTMLInputElement).value.replace(/,/g, '').trim();
    if (!crudo) {
      return;
    }
    const monto = Number(crudo);
    if (!Number.isFinite(monto) || monto < 0) {
      return;
    }
    gasto.montoMensual = monto;
    this.persistirGasto(gasto);
  }

  mostrarGastoNuevo(): void {
    this.gastoNuevo = {
      nombre: '',
      proveedor: '',
      sucursal: '',
      rubro: 'otros',
      tipo: 'variable',
      montoMensual: null,
      pctVentas: null,
      diaPago: '5',
      llevaIva: false,
      estimado: true,
    };
  }

  cancelarGastoNuevo(): void {
    this.gastoNuevo = null;
  }

  guardarGastoNuevo(): void {
    this.persistirItemNuevo(this.gastoNuevo, () => {
      this.gastoNuevo = null;
    }, 'El gasto necesita nombre.', 'No se guardó el gasto.');
  }

  private persistirGasto(gasto: ModeladoGastoFila, alFallar?: () => void): void {
    this.modeladoData
      .guardarClasificacion(gasto)
      .pipe(take(1))
      .subscribe({
        next: () => this.cargar(),
        error: () => {
          alFallar?.();
          this.falloGuardar('No se guardó la clasificación.');
        },
      });
  }

  ejecutadoPresupuestoTexto(fila: ModeladoPresupuestoFila): string {
    return `${this.formatMonto(fila.ejecutadoMonto)} de ${this.formatMonto(fila.ejecutadoPresupuesto)}`;
  }

  vsPresupuestoLabel(pct: number | null): string {
    if (pct == null) {
      return '—';
    }
    const sign = pct > 0 ? '+' : '';
    return `${sign}${pct}%`;
  }

  presupuestoSucursalesActivo(): boolean {
    return (this.presupuestoSucursales?.sucursales.length ?? 0) > 0;
  }

  presupuestoSucursalCelda(fila: { porSucursal: Record<string, number> }, idSucursal: number): number {
    return Number(fila.porSucursal[String(idSucursal)]) || 0;
  }

  presupuestoSucursalSetCelda(
    fila: { porSucursal: Record<string, number> },
    idSucursal: number,
    value: number,
  ): void {
    fila.porSucursal[String(idSucursal)] = value;
  }

  presupuestoSumaFila(fila: { porSucursal: Record<string, number> }): number {
    if (!this.presupuestoSucursales) {
      return 0;
    }
    return this.presupuestoSucursales.sucursales.reduce(
      (sum, s) => sum + (Number(fila.porSucursal[String(s.id)]) || 0),
      0,
    );
  }

  presupuestoContribSucursal(idSucursal: number): number {
    if (!this.presupuestoSucursales) {
      return 0;
    }
    let total = 0;
    for (const fila of this.presupuestoSucursales.filas) {
      const v = Number(fila.porSucursal[String(idSucursal)]) || 0;
      total += fila.id === 'ventas' ? v : -v;
    }
    return total;
  }

  presupuestoContribSuma(): number {
    if (!this.presupuestoSucursales) {
      return 0;
    }
    return this.presupuestoSucursales.sucursales.reduce((sum, s) => sum + this.presupuestoContribSucursal(s.id), 0);
  }

  presupuestoContribClase(valor: number): string {
    if (valor > 0) {
      return 'modelado-pres-suc-contrib--pos';
    }
    if (valor < 0) {
      return 'modelado-pres-suc-contrib--neg';
    }
    return '';
  }

  varPctLabel(pct: number | null): string {
    if (pct == null) {
      return '—';
    }
    const sign = pct > 0 ? '+' : '';
    return `${sign}${pct}%`;
  }

  efectivoDiasTexto(): string {
    const dias = this.data.configuradoDetalle.efectivoDias;
    return dias == null ? 'sin fecha' : `${dias} días`;
  }

  costoCapitalMes(): number | null {
    const efectivo = this.pronostico.efectivoHoy;
    const pct = this.pronostico.costoCapitalPctMes;
    if (efectivo == null || pct == null) {
      return null;
    }
    return (Number(efectivo) * Number(pct)) / 100;
  }

  escenarioVentas(pct: number): number | null {
    const base = Number(this.metaVentasAnual) || this.data.presupuesto.metaVentas.montoAnual;
    if (!base) {
      return null;
    }
    return base * (1 + Number(pct) / 100);
  }

  private montoCaja(monto: number, llevaIva: boolean): number {
    const iva = this.data.perfilFiscal.ivaPct ?? 0;
    if (!llevaIva || iva <= 0) {
      return monto;
    }
    return Math.round(monto * (1 + iva / 100) * 100) / 100;
  }

  agregarCalendarioPendiente(): void {
    const p = this.calendarioPendiente;
    if (!p) {
      return;
    }
    this.modeladoData
      .crearCalendario({
        clave: p.clave || null,
        nombre: p.nombre,
        monto: p.monto,
        frecuencia: 'mensual',
        dia: p.dia || '1',
      })
      .pipe(take(1))
      .subscribe({
        next: () => this.cargar(),
        error: () => this.falloGuardar('No se agregó la regla de calendario.'),
      });
  }

  mostrarCostoNuevo(): void {
    this.costoNuevo = {
      nombre: '',
      proveedor: '',
      sucursal: '',
      rubro: 'costo',
      tipo: 'fijo',
      montoMensual: null,
      pctVentas: null,
      diaPago: '5',
      llevaIva: true,
    };
  }

  cancelarCostoNuevo(): void {
    this.costoNuevo = null;
  }

  guardarCostoNuevo(): void {
    this.persistirItemNuevo(this.costoNuevo, () => {
      this.costoNuevo = null;
    }, 'El costo necesita nombre.', 'No se guardó el costo.');
  }

  guardarCalendario(fila: ModeladoCalendarioCostoFila): void {
    if (fila.id < 1 || (!fila.editableMonto && !fila.editableDia)) {
      return;
    }
    this.modeladoData
      .actualizarCalendario(fila)
      .pipe(take(1))
      .subscribe({
        next: () => this.cargar(),
        error: () => this.falloGuardar('No se actualizó el calendario.'),
      });
  }

  puedeEliminarCalendario(fila: ModeladoCalendarioCostoFila): boolean {
    return fila.editableMonto && fila.id > 0 && !fila.origenModulo;
  }

  eliminarCalendario(fila: ModeladoCalendarioCostoFila): void {
    const id = Math.trunc(Number(fila.id));
    if (!this.puedeEliminarCalendario(fila) || id < 1) {
      return;
    }
    const ok = window.confirm(
      `¿Quitar "${fila.nombre}" del calendario?\n\nNo borra el gasto del P&L; solo la regla de cuándo sale de caja.`,
    );
    if (!ok) {
      return;
    }
    this.modeladoData
      .borrarCalendario(id)
      .pipe(take(1))
      .subscribe({
        next: () => {
          this.calendarioFilas = this.calendarioFilas.filter((f) => f.id !== id);
          this.cdr.markForCheck();
          this.cargar();
        },
        error: (err: HttpErrorResponse) => {
          if (err.status === 404) {
            this.calendarioFilas = this.calendarioFilas.filter((f) => f.id !== id);
            this.cargar();
            return;
          }
          this.falloGuardar('No se eliminó la regla del calendario.');
        },
      });
  }

  elegirReparto(reparto: ModeladoPresupuestoReparto): void {
    this.presupuestoReparto = reparto;
    this.guardarPresupuesto();
  }

  alEditarAnual(fila: ModeladoPresupuestoFila): void {
    if (fila.id === 'ventas') {
      this.metaVentasAnual = Number(fila.anual) || 0;
    }
  }

  alEditarMeta(): void {
    const ventas = this.presupuestoFilas.find((f) => f.id === 'ventas');
    if (ventas) {
      ventas.anual = Number(this.metaVentasAnual) || 0;
    }
  }

  guardarPresupuesto(): void {
    this.alEditarMeta();
    this.modeladoData
      .guardarPresupuesto(
        this.data.anio,
        this.presupuestoReparto,
        this.presupuestoFilas,
        this.data.presupuesto.estacionalidadPesos,
        this.payloadPresupuestoSucursales(),
      )
      .pipe(take(1))
      .subscribe({
        next: () => this.cargar(),
        error: () => this.falloGuardar('No se guardó el presupuesto.'),
      });
  }

  guardarPresupuestoSucursales(): void {
    this.guardarPresupuesto();
  }

  private payloadPresupuestoSucursales():
    | { idSucursal: number; filas: { rubro: string; anual: number }[] }[]
    | undefined {
    const bloque = this.presupuestoSucursales;
    if (!bloque?.sucursales.length) {
      return undefined;
    }
    return bloque.sucursales.map((s) => ({
      idSucursal: s.id,
      filas: PRESUPUESTO_RUBROS.map((rubro) => {
        const fila = bloque.filas.find((f) => f.id === rubro);
        return {
          rubro,
          anual: Number(fila?.porSucursal[String(s.id)]) || 0,
        };
      }),
    }));
  }

  guardarPronostico(): void {
    this.modeladoData
      .guardarPronostico(this.pronostico)
      .pipe(take(1))
      .subscribe({
        next: () => this.cargar(),
        error: () => this.falloGuardar('No se guardó el pronóstico.'),
      });
  }

  private falloGuardar(mensaje: string): void {
    this.errorMensaje = mensaje;
    this.cdr.markForCheck();
  }

  private persistirItemNuevo(
    n: ModeladoGastoNuevoForm | null,
    alOk: () => void,
    msgSinNombre: string,
    msgError: string,
  ): void {
    if (!n?.nombre.trim()) {
      this.errorMensaje = msgSinNombre;
      this.cdr.markForCheck();
      return;
    }
    let monto = 0;
    let pctVentas: number | null = null;
    if (!n.estimado && n.tipo === 'variable') {
      if (n.pctVentas == null || Number(n.pctVentas) < 0) {
        this.errorMensaje = 'Indica el % de ventas.';
        this.cdr.markForCheck();
        return;
      }
      pctVentas = Number(n.pctVentas);
      monto = this.montoVariableDe(n);
    } else {
      if (n.montoMensual == null || Number(n.montoMensual) < 0) {
        this.errorMensaje = 'Indica el monto mensual.';
        this.cdr.markForCheck();
        return;
      }
      monto = Number(n.montoMensual);
    }
    const clave = claveManual(n.nombre);
    const proveedor = n.proveedor.trim();
    const sucursal = n.sucursal.trim();
    const dia = n.tipo === 'fijo' && n.diaPago.trim() ? n.diaPago.trim() : null;
    const gasto: ModeladoGastoFila = {
      id: clave,
      nombre: n.nombre.trim(),
      rubro: n.rubro,
      categoria: '',
      montoMensual: monto,
      tipo: n.tipo,
      pctVentas,
      proveedor: proveedor || undefined,
      sucursal: sucursal || undefined,
      diaPago: dia || undefined,
    };
    this.modeladoData
      .guardarClasificacion(gasto, { pctVentas, diaPago: dia, proveedor: proveedor || null, sucursal: sucursal || null })
      .pipe(
        switchMap(() => {
          if (n.tipo === 'fijo' && dia) {
            return this.modeladoData.crearCalendario({
              clave,
              nombre: gasto.nombre,
              monto: this.montoCaja(monto, n.llevaIva),
              frecuencia: 'mensual',
              dia,
            });
          }
          return of(null);
        }),
        take(1),
      )
      .subscribe({
        next: () => {
          alOk();
          this.cargar();
        },
        error: () => this.falloGuardar(msgError),
      });
  }

  private cargar(): void {
    const seq = ++this.loadSeq;
    this.cargando = true;
    this.errorMensaje = null;
    this.cdr.markForCheck();
    this.modeladoData
      .obtener(this.anio)
      .pipe(
        take(1),
        finalize(() => {
          if (seq === this.loadSeq) {
            this.cargando = false;
            this.cdr.markForCheck();
          }
        }),
      )
      .subscribe((payload) => {
        if (seq !== this.loadSeq) {
          return;
        }
        if (!payload) {
          if (!this.contenidoListo) {
            this.data = MODELADO_VACIA;
          }
          this.errorMensaje = 'No se pudo cargar el modelado. Revisa la conexión con analytics.';
          return;
        }
        this.aplicar(payload);
        this.contenidoListo = true;
        this.errorMensaje = null;
        this.cdr.markForCheck();
      });
  }

  private aplicar(payload: ModeladoVista): void {
    this.data = payload;
    this.gastos = payload.gastosFijosVariables.map((g) => ({ ...g }));
    this.calendarioFilas = payload.calendarioCostosFijos.map((f) => ({ ...f }));
    this.calendarioPendiente = payload.calendarioPendiente
      ? { ...payload.calendarioPendiente }
      : null;
    this.presupuestoFilas = payload.presupuesto.filas.map((f) => ({ ...f }));
    this.presupuestoSucursales = payload.presupuesto.sucursales
      ? {
          sucursales: payload.presupuesto.sucursales.sucursales.map((s) => ({ ...s })),
          filas: payload.presupuesto.sucursales.filas.map((f) => ({
            ...f,
            porSucursal: { ...f.porSucursal },
          })),
          contribucion: {
            porSucursal: { ...payload.presupuesto.sucursales.contribucion.porSucursal },
            suma: payload.presupuesto.sucursales.contribucion.suma,
            empresa: payload.presupuesto.sucursales.contribucion.empresa,
          },
          comparacionMes: {
            titulo: payload.presupuesto.sucursales.comparacionMes.titulo,
            filas: payload.presupuesto.sucursales.comparacionMes.filas.map((f) => ({ ...f })),
          },
        }
      : null;
    this.presupuestoVistas[1].activo = this.presupuestoSucursalesActivo();
    if (this.presupuestoVista === 'sucursales' && !this.presupuestoSucursalesActivo()) {
      this.presupuestoVista = 'empresa';
    }
    this.presupuestoReparto = payload.presupuesto.reparto;
    this.metaVentasAnual = payload.presupuesto.metaVentas.montoAnual;
    this.pronostico = {
      ...payload.parametrosPronostico,
      historial: { ...payload.parametrosPronostico.historial },
    };
  }
}

function claveManual(nombre: string): string {
  const slug = nombre
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 80);
  return `m:${slug || Date.now()}`;
}
