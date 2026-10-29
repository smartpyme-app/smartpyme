import { ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { finalize, take } from 'rxjs/operators';
import {
  MODELADO_VACIA,
  FinanzasModeladoDataService,
} from '../../../services/finanzas-modelado-data.service';
import {
  ModeladoCalendarioCostoFila,
  ModeladoCostoNuevo,
  ModeladoGastoFila,
  ModeladoGastoTipo,
  ModeladoPresupuestoFila,
  ModeladoPresupuestoReparto,
  ModeladoPresupuestoVista,
  ModeladoVista,
} from './modelado.model';

const FRECUENCIA: Record<string, string> = {
  mensual: 'Mensual',
  quincenal: 'Quincenal',
  anual: 'Anual',
};

@Component({
  selector: 'app-modelado',
  templateUrl: './modelado.component.html',
  styleUrls: ['./modelado.component.css'],
  standalone: true,
  imports: [CommonModule, FormsModule],
})
export class ModeladoComponent implements OnInit {
  private readonly api = inject(ApiService);
  private readonly modeladoData = inject(FinanzasModeladoDataService);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly anio = new Date().getFullYear();
  data: ModeladoVista = MODELADO_VACIA;
  gastos: ModeladoGastoFila[] = [];
  calendarioFilas: ModeladoCalendarioCostoFila[] = [];
  calendarioPendiente = MODELADO_VACIA.calendarioPendiente;
  presupuestoFilas: ModeladoPresupuestoFila[] = [];
  presupuestoReparto: ModeladoPresupuestoReparto = 'igual';
  metaVentasAnual = 0;
  pronostico = { ...MODELADO_VACIA.parametrosPronostico };
  costoNuevo: ModeladoCostoNuevo | null = null;
  cargando = false;
  contenidoListo = false;
  errorMensaje: string | null = null;

  readonly presupuestoVistas: { id: ModeladoPresupuestoVista; label: string; activo: boolean }[] = [
    { id: 'empresa', label: 'Empresa', activo: true },
    { id: 'sucursales', label: 'Sucursales', activo: false },
    { id: 'areas', label: 'Áreas', activo: false },
  ];
  presupuestoVista: ModeladoPresupuestoVista = 'empresa';

  private loadSeq = 0;

  ngOnInit(): void {
    this.cargar();
  }

  formatMonto(value: number | null): string {
    return formatEmpresaCurrency(value ?? 0, this.api.auth_user()?.empresa);
  }

  pct(value: number | null | undefined): string {
    return value == null ? '—' : `${value}%`;
  }

  gastoSubtitulo(gasto: ModeladoGastoFila): string {
    return `${gasto.categoria} · ${this.formatMonto(gasto.montoMensual)}/mes`;
  }

  frecuenciaTexto(codigo: string): string {
    return FRECUENCIA[codigo] ?? codigo;
  }

  seleccionarTipo(gasto: ModeladoGastoFila, tipo: ModeladoGastoTipo): void {
    const previo = gasto.tipo;
    gasto.tipo = tipo;
    this.modeladoData
      .guardarClasificacion(gasto, tipo)
      .pipe(take(1))
      .subscribe({
        next: () => this.cargar(),
        error: () => {
          gasto.tipo = previo;
          this.errorMensaje = 'No se guardó la clasificación.';
          this.cdr.markForCheck();
        },
      });
  }

  sinClasificarTotal(): number {
    return this.gastos
      .filter((g) => g.tipo === null)
      .reduce((sum, g) => sum + g.montoMensual, 0);
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

  efectivoDiasTexto(): string {
    const dias = this.data.configuradoDetalle.efectivoDias;
    return dias == null ? 'sin fecha' : `${dias} días`;
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
    this.costoNuevo = { nombre: '', monto: null, dia: '1' };
  }

  guardarCostoNuevo(): void {
    const c = this.costoNuevo;
    if (!c?.nombre.trim() || c.monto == null) {
      this.errorMensaje = 'El costo fijo necesita nombre y monto.';
      return;
    }
    this.modeladoData
      .crearCalendario({
        nombre: c.nombre.trim(),
        monto: Number(c.monto),
        frecuencia: 'mensual',
        dia: c.dia || '1',
      })
      .pipe(take(1))
      .subscribe({
        next: () => {
          this.costoNuevo = null;
          this.cargar();
        },
        error: () => this.falloGuardar('No se guardó el costo fijo.'),
      });
  }

  guardarCalendario(fila: ModeladoCalendarioCostoFila): void {
    if (!fila.editableMonto && !fila.editableDia) {
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
      )
      .pipe(take(1))
      .subscribe({
        next: () => this.cargar(),
        error: () => this.falloGuardar('No se guardó el presupuesto.'),
      });
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
    this.presupuestoReparto = payload.presupuesto.reparto;
    this.metaVentasAnual = payload.presupuesto.metaVentas.montoAnual;
    this.pronostico = {
      ...payload.parametrosPronostico,
      historial: { ...payload.parametrosPronostico.historial },
    };
  }
}
