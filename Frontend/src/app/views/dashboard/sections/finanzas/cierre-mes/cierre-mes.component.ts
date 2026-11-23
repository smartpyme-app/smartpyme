import { ChangeDetectorRef, Component, EventEmitter, OnInit, Output, TemplateRef, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { finalize, take } from 'rxjs/operators';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SharedDataService } from '@services/shared-data.service';
import { CrearProveedorComponent } from '@shared/modals/crear-proveedor/crear-proveedor.component';
import { formatEmpresaCurrency, getEmpresaCurrencySymbol } from '@helpers/currency-format.helper';
import {
  CierreChecklistGrupoItem,
  CierreGastoFueraRangoFila,
  CierreMesDetalle,
  CierreMesItem,
  CierrePresupuestoFila,
  CierreResumenVar,
  CierreTopVendedorItem,
  CierreVarTono,
} from './cierre-mes.model';
import {
  CIERRE_MES_VACIA,
  FinanzasCierreMesDataService,
} from '../../../services/finanzas-cierre-mes-data.service';

const ACCION_CHECKLIST: Record<string, string> = {
  registrar_gasto: 'Registrar gasto →',
  revisar_dia: 'Revisar día →',
  asignar_costos: 'Asignar costos →',
  clasificar: 'Clasificar →',
};

const TIPOS_GASTO_CIERRE = [
  'Alquiler',
  'Combustible',
  'Costo de venta',
  'Gastos varios',
  'Insumos',
  'Impuestos',
  'Gastos Administrativos',
  'Mantenimiento',
  'Marketing',
  'Materia Prima',
  'Servicios',
  'Pago comisión',
  'Planilla',
  'Préstamos',
  'Publicidad',
];

interface RegistrarGastoForm {
  fecha: string;
  tipo: string;
  concepto: string;
  id_proveedor: number | null;
  id_sucursal: number | null;
  tipo_documento: string;
  forma_pago: string;
  estado: string;
  sub_total: number | null;
  aplica_iva: boolean;
  iva: number;
  total: number;
}

@Component({
  selector: 'app-cierre-mes',
  templateUrl: './cierre-mes.component.html',
  styleUrls: ['./cierre-mes.component.css'],
  standalone: true,
  imports: [CommonModule, FormsModule, NgSelectModule, CrearProveedorComponent],
})
export class CierreMesComponent implements OnInit {
  private readonly api = inject(ApiService);
  private readonly shared = inject(SharedDataService);
  private readonly alert = inject(AlertService);
  private readonly modalService = inject(BsModalService);
  private readonly router = inject(Router);
  private readonly cierreData = inject(FinanzasCierreMesDataService);
  private readonly cdr = inject(ChangeDetectorRef);

  @Output() irATab = new EventEmitter<'modelado' | 'pl' | 'rentabilidad' | 'flujo' | 'alertas'>();
  @ViewChild('mRegistrarGasto') tplRegistrarGasto?: TemplateRef<unknown>;

  readonly anio = new Date().getFullYear();
  readonly tiposGasto = TIPOS_GASTO_CIERRE;
  data = { ...CIERRE_MES_VACIA, anio: this.anio };
  mesSeleccionadoId: number | null = null;
  cargando = false;
  certificando = false;
  guardandoGasto = false;
  descartandoCategoria: string | null = null;
  contenidoListo = false;
  errorMensaje: string | null = null;
  modalGastoRef?: BsModalRef;
  proveedores: { id: number; etiqueta: string }[] = [];
  sucursales: { id: number; nombre: string }[] = [];
  formasPago: { id: number; nombre: string }[] = [];
  gastoForm: RegistrarGastoForm = this.nuevoGastoForm();
  private catalogosCargados = false;
  private loadSeq = 0;

  ngOnInit(): void {
    this.cargar();
  }

  seleccionarMes(mes: CierreMesItem): void {
    if (mes.estado === 'pendiente' || mes.id === this.mesSeleccionadoId) {
      return;
    }
    this.mesSeleccionadoId = mes.id;
    this.cargar(mes.id + 1);
  }

  mesesCerradosConteo(): number {
    return this.data.mesesCerrados;
  }

  detalleActivo(): CierreMesDetalle | null {
    return this.data.detalle;
  }

  pendientesChecklist(detalle: CierreMesDetalle): number {
    if (detalle.checklistGrupos) {
      const g = detalle.checklistGrupos;
      return (
        g.fijos.items.filter((i) => i.estado !== 'ok').length +
        g.variables.items.filter((i) => i.estado !== 'ok').length +
        g.otros.filter((i) => i.estado !== 'ok').length
      );
    }
    return detalle.checklist.filter((i) => i.estado !== 'ok').length;
  }

  ejecutarAccionChecklist(item: {
    accion?: string;
    tipoGasto?: string;
    concepto?: string;
    titulo?: string;
    montoSugerido?: number | null;
  }): void {
    switch (item.accion) {
      case 'registrar_gasto':
        this.abrirRegistrarGasto(item);
        return;
      case 'clasificar':
        this.irATab.emit('modelado');
        return;
      case 'asignar_costos':
        this.router.navigate(['/compras']);
        return;
      case 'revisar_dia':
        this.router.navigate(['/ventas']);
        return;
    }
  }

  abrirRegistrarGasto(item?: {
    tipoGasto?: string;
    concepto?: string;
    titulo?: string;
    montoSugerido?: number | null;
  }): void {
    this.cargarCatalogosGasto();
    this.gastoForm = this.nuevoGastoForm(item);
    this.recalcularTotalesGasto();
    if (this.tplRegistrarGasto) {
      this.modalGastoRef = this.modalService.show(this.tplRegistrarGasto, {
        class: 'modal-md modal-ng-select-overflow',
        backdrop: 'static',
      });
    }
  }

  irAFormularioCompleto(): void {
    this.modalGastoRef?.hide();
    this.router.navigate(['/gasto/nuevo']);
  }

  revisarFueraRango(fila: CierreGastoFueraRangoFila): void {
    const ruta = fila.categoria.toLowerCase().startsWith('devoluciones')
      ? ['/devoluciones/ventas']
      : ['/gastos'];
    this.router.navigate(ruta);
  }

  esNormal(fila: CierreGastoFueraRangoFila): void {
    const mes = this.mesSeleccionadoId != null ? this.mesSeleccionadoId + 1 : null;
    if (!mes || this.descartandoCategoria) {
      return;
    }
    this.descartandoCategoria = fila.categoria;
    this.cierreData
      .descartarFueraRango({ anio: this.anio, mes, categoria: fila.categoria })
      .pipe(
        take(1),
        finalize(() => {
          this.descartandoCategoria = null;
          this.cdr.markForCheck();
        }),
      )
      .subscribe((ok) => {
        if (!ok) {
          this.alert.error('No se pudo marcar como normal.');
          return;
        }
        if (this.data.detalle) {
          this.data.detalle.gastosFueraRango.filas =
            this.data.detalle.gastosFueraRango.filas.filter((f) => f.categoria !== fila.categoria);
        }
        this.cdr.markForCheck();
      });
  }

  recalcularTotalesGasto(): void {
    const sub = Number(this.gastoForm.sub_total) || 0;
    const iva = this.gastoForm.aplica_iva ? Math.round(sub * 0.13 * 100) / 100 : 0;
    this.gastoForm.iva = iva;
    this.gastoForm.total = Math.round((sub + iva) * 100) / 100;
  }

  setProveedorGasto(prov: { id?: number; tipo?: string; nombre_empresa?: string; nombre_completo?: string; nombre?: string }): void {
    if (!prov?.id) {
      return;
    }
    const etiqueta = (
      prov.tipo === 'Empresa' ? prov.nombre_empresa : prov.nombre_completo || prov.nombre
    )?.trim() || `Proveedor #${prov.id}`;
    if (!this.proveedores.some((p) => p.id === prov.id)) {
      this.proveedores = [...this.proveedores, { id: prov.id, etiqueta }];
    }
    this.gastoForm.id_proveedor = prov.id;
    this.cdr.markForCheck();
  }

  guardarGastoModal(): void {
    const f = this.gastoForm;
    if (!f.concepto.trim() || !f.tipo || !f.fecha || !f.id_proveedor || !f.id_sucursal || (Number(f.sub_total) || 0) <= 0) {
      this.alert.error('Completa fecha, tipo, concepto, proveedor, sucursal y monto.');
      return;
    }
    const user = this.api.auth_user();
    this.guardandoGasto = true;
    const payload = {
      id_empresa: user?.id_empresa,
      id_usuario: user?.id,
      id_sucursal: Number(f.id_sucursal),
      id_proveedor: Number(f.id_proveedor),
      fecha: f.fecha,
      concepto: f.concepto.trim(),
      tipo: f.tipo,
      tipo_clasificacion: 'Gasto',
      tipo_operacion: f.aplica_iva ? 'Gravada' : 'No Gravada',
      tipo_documento: f.tipo_documento || 'Factura',
      forma_pago: f.forma_pago || 'Efectivo',
      estado: f.estado || 'Pagado',
      impuesto: f.aplica_iva,
      sub_total: Number(f.sub_total) || 0,
      iva: f.iva,
      renta_retenida: 0,
      iva_retenido: 0,
      iva_percibido: 0,
      total: f.total,
      varios_items: false,
    };
    this.api
      .store('gasto', payload)
      .pipe(
        take(1),
        finalize(() => {
          this.guardandoGasto = false;
          this.cdr.markForCheck();
        }),
      )
      .subscribe({
        next: () => {
          this.modalGastoRef?.hide();
          this.alert.success('Gasto registrado', 'El gasto se guardó correctamente.');
          const mes = this.mesSeleccionadoId != null ? this.mesSeleccionadoId + 1 : undefined;
          this.cargar(mes);
        },
        error: (err) => {
          this.alert.error(err);
        },
      });
  }

  private cargarCatalogosGasto(): void {
    if (this.catalogosCargados) {
      return;
    }
    this.catalogosCargados = true;
    this.shared
      .getProveedores()
      .pipe(take(1))
      .subscribe((rows) => {
        this.proveedores = (rows ?? [])
          .map((p: { id: number; tipo?: string; nombre_empresa?: string; nombre_completo?: string; nombre?: string }) => ({
            id: p.id,
            etiqueta: ((p.tipo === 'Empresa' ? p.nombre_empresa : p.nombre_completo || p.nombre) || '').trim(),
          }))
          .filter((p) => p.id && p.etiqueta);
        if (!this.gastoForm.id_proveedor && this.proveedores.length) {
          this.gastoForm.id_proveedor = this.proveedores[0].id;
        }
        this.cdr.markForCheck();
      });
    this.shared
      .getSucursales()
      .pipe(take(1))
      .subscribe((rows) => {
        this.sucursales = rows ?? [];
        if (!this.gastoForm.id_sucursal && this.sucursales.length) {
          this.gastoForm.id_sucursal = this.sucursales[0].id;
        }
        this.cdr.markForCheck();
      });
    this.shared
      .getFormasDePago()
      .pipe(take(1))
      .subscribe((rows) => {
        this.formasPago = rows ?? [];
        if (this.formasPago.length && !this.formasPago.some((fp) => fp.nombre === this.gastoForm.forma_pago)) {
          this.gastoForm.forma_pago = this.formasPago[0].nombre;
        }
        this.cdr.markForCheck();
      });
  }

  private nuevoGastoForm(item?: {
    tipoGasto?: string;
    concepto?: string;
    titulo?: string;
    montoSugerido?: number | null;
  }): RegistrarGastoForm {
    const user = this.api.auth_user();
    const mes = (this.mesSeleccionadoId ?? new Date().getMonth()) + 1;
    const hoy = new Date();
    const fechaDef =
      hoy.getFullYear() === this.anio && hoy.getMonth() + 1 === mes
        ? hoy.toISOString().slice(0, 10)
        : new Date(Date.UTC(this.anio, mes, 0)).toISOString().slice(0, 10);
    const tipoMatch = this.tiposGasto.find(
      (t) => t.toLowerCase() === (item?.tipoGasto ?? '').trim().toLowerCase(),
    );
    return {
      fecha: fechaDef,
      tipo: tipoMatch ?? (item?.tipoGasto || 'Gastos varios'),
      concepto: item?.concepto || item?.titulo || '',
      id_proveedor: this.proveedores?.[0]?.id ?? null,
      id_sucursal: user?.id_sucursal ?? this.sucursales?.[0]?.id ?? null,
      tipo_documento: 'Factura',
      forma_pago: this.formasPago?.[0]?.nombre ?? 'Efectivo',
      estado: 'Pagado',
      sub_total: item?.montoSugerido ?? null,
      aplica_iva: user?.empresa?.cobra_iva === 'Si',
      iva: 0,
      total: 0,
    };
  }

  trackChecklistGrupoItem(_idx: number, item: CierreChecklistGrupoItem): string {
    return `${item.titulo}-${item.estado}`;
  }

  puedeCertificar(detalle: CierreMesDetalle): boolean {
    return detalle.puedeCertificar && this.pendientesChecklist(detalle) === 0 && !this.certificando;
  }

  certificar(detalle: CierreMesDetalle): void {
    if (!this.puedeCertificar(detalle) || this.mesSeleccionadoId == null) {
      return;
    }
    this.certificando = true;
    this.errorMensaje = null;
    const mes = this.mesSeleccionadoId + 1;
    this.cierreData
      .certificar({ anio: this.anio, mes })
      .pipe(
        take(1),
        finalize(() => {
          this.certificando = false;
          this.cdr.markForCheck();
        }),
      )
      .subscribe((ok) => {
        if (!ok) {
          this.errorMensaje = 'No se pudo bloquear el mes.';
          return;
        }
        this.cargar(mes);
      });
  }

  etiquetaAccion(codigo: string): string {
    return ACCION_CHECKLIST[codigo] ?? codigo;
  }

  etiquetaMes(): string {
    const mes = this.data.meses.find((m) => m.id === this.mesSeleccionadoId);
    return (mes?.etiqueta ?? this.data.detalle?.mesEtiquetaCorto ?? '').toLowerCase();
  }

  formatMonto(value: number): string {
    return formatEmpresaCurrency(value, this.api.auth_user()?.empresa);
  }

  formatMontoCard(value: number, conSigno = false): string {
    const sym = getEmpresaCurrencySymbol(this.api.auth_user()?.empresa);
    const num = new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(Math.round(Math.abs(value)));
    if (value < 0) {
      return `-${sym}${num}`;
    }
    if (conSigno && value > 0) {
      return `+${sym}${num}`;
    }
    return `${sym}${num}`;
  }

  formatDelta(valor: number | null, unidad: '%' | ' pts'): string {
    if (valor == null) {
      return '—';
    }
    const sign = valor > 0 ? '+' : '';
    return `${sign}${valor.toFixed(1)}${unidad}`;
  }

  deltaTono(valor: number | null, inverso = false): 'pos' | 'neg' | 'neutral' {
    if (valor == null || valor === 0) {
      return 'neutral';
    }
    const bueno = inverso ? valor < 0 : valor > 0;
    return bueno ? 'pos' : 'neg';
  }

  venceImpuestosTexto(vence: string): string {
    if (!vence) {
      return '';
    }
    const texto = vence.startsWith('vence ') ? vence : `vence ${vence}`;
    return texto.replace(/·\s+(\d)/, '· en $1');
  }

  resumenComparaciones(card: {
    vsMesAnt: CierreResumenVar;
    vsAnioAnt: CierreResumenVar;
    vsPresupuesto: CierreResumenVar;
  }): CierreResumenVar[] {
    return [card.vsMesAnt, card.vsAnioAnt, card.vsPresupuesto];
  }

  formatVarMes(pct: number | null): string {
    if (pct === null) {
      return '—';
    }
    const r = Math.round(pct);
    if (pct > 0) {
      return `+${r}%`;
    }
    if (pct < 0 && r === 0) {
      return '-0%';
    }
    return `${r}%`;
  }

  formatVarAcum(fila: CierrePresupuestoFila): string {
    if (fila.varAcumMonto === null && fila.varAcumPct === null) {
      return '—';
    }
    if (fila.categoria === 'Ventas netas') {
      return this.formatVarMes(fila.varAcumPct);
    }
    const monto = fila.varAcumMonto ?? 0;
    return `${this.formatMontoCard(monto, true)} (${this.formatVarMes(fila.varAcumPct)})`;
  }

  tonoAcum(fila: CierrePresupuestoFila): CierreVarTono {
    return this.tonoPorPct(fila.categoria, fila.varAcumPct);
  }

  claseVarTono(tono: CierreVarTono, valorNull = false): string {
    if (valorNull) {
      return 'cierre-text-muted';
    }
    switch (tono) {
      case 'pos':
        return 'cierre-text-pos';
      case 'neg':
        return 'cierre-text-neg';
      case 'warn':
        return 'cierre-text-warn';
      default:
        return 'cierre-text-dark';
    }
  }

  private tonoPorPct(categoria: string, pct: number | null): CierreVarTono {
    if (pct === null || pct === 0) {
      return 'neutral';
    }
    if (categoria === 'Ventas netas') {
      return pct < 0 ? 'neg' : 'pos';
    }
    if (pct >= 10) {
      return 'warn';
    }
    return pct > 0 ? 'neg' : 'pos';
  }

  topVentasCards(detalle: CierreMesDetalle): {
    titulo: string;
    mesLabel: string;
    emptyText: string;
    items: { nombre: string; subtitulo: string; monto: number; pctDelMes: number; pctBarra: number }[];
  }[] {
    const tv = detalle.topVentas;
    const mesLabel = tv?.mesLabel || detalle.mesEtiquetaCorto;
    const mesAnt = tv?.mesAnteriorLabel ?? '';
    return [
      {
        titulo: 'Top ventas por vendedor',
        mesLabel,
        emptyText: 'Sin ventas por vendedor registradas en este mes.',
        items: (tv?.vendedores ?? []).map((v) => ({
          nombre: v.nombre,
          subtitulo: this.subtituloTopVendedor(v, mesAnt),
          monto: v.monto,
          pctDelMes: v.pctDelMes,
          pctBarra: v.pctBarra,
        })),
      },
      {
        titulo: 'Top ventas por producto',
        mesLabel,
        emptyText: 'Sin ventas por producto registradas en este mes.',
        items: (tv?.productos ?? []).map((p) => ({
          nombre: p.nombre,
          subtitulo: `${p.categoria} · margen ${Math.round(p.margenPct)}%`,
          monto: p.monto,
          pctDelMes: p.pctDelMes,
          pctBarra: p.pctBarra,
        })),
      },
    ];
  }

  formatPctEntero(pct: number): string {
    return `${Math.round(pct)}%`;
  }

  private subtituloTopVendedor(v: CierreTopVendedorItem, mesAnt: string): string {
    const partes: string[] = [];
    if (v.sucursal) {
      partes.push(v.sucursal);
    }
    if (v.deltaPct !== null && mesAnt) {
      const r = Math.round(v.deltaPct);
      const signo = r > 0 ? '+' : r < 0 ? '−' : '';
      partes.push(`${signo}${Math.abs(r)}% vs. ${mesAnt}`);
    }
    return partes.join(' · ') || 'Sin comparación mes anterior';
  }

  private cargar(mes?: number): void {
    const seq = ++this.loadSeq;
    this.cargando = true;
    this.errorMensaje = null;
    this.cdr.markForCheck();
    this.cierreData
      .obtener({ anio: this.anio, mes })
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
            this.data = { ...CIERRE_MES_VACIA, anio: this.anio };
          }
          this.errorMensaje = 'No se pudo cargar el cierre de mes. Revisa la conexión con analytics.';
          return;
        }
        this.data = payload;
        this.contenidoListo = true;
        this.errorMensaje = null;
        if (mes == null) {
          this.mesSeleccionadoId = payload.mesActivoId;
        }
      });
  }
}
