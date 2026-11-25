import { ChangeDetectorRef, Component, HostListener, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ApiService } from '@services/api.service';
import { SharedDataService } from '@services/shared-data.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { finalize, switchMap, take } from 'rxjs/operators';
import { forkJoin, of } from 'rxjs';
import { CrearDepartamentoComponent } from '@shared/modals/crear-departamento-empresa/crear-departamento-empresa.component';
import { CrearAreaEmpresaComponent } from '@shared/modals/crear-area-empresa/crear-area-empresa.component';
import {
  MODELADO_VACIA,
  FinanzasModeladoDataService,
} from '../../../services/finanzas-modelado-data.service';
import {
  ModeladoCalendarioCostoFila,
  ModeladoGastoFila,
  ModeladoGastoTipo,
  ModeladoPresupuestoEntidad,
  ModeladoPresupuestoFila,
  ModeladoPresupuestoReparto,
  ModeladoPresupuestoSucursales,
  ModeladoPresupuestoVista,
  ModeladoVista,
} from './modelado.model';

const PRESUPUESTO_DETALLE: { id: string; categoria: string }[] = [
  { id: 'ventas', categoria: 'Ventas netas' },
  { id: 'costo_de_venta', categoria: 'Costo de venta' },
  { id: 'materia_prima', categoria: 'Materia prima' },
  { id: 'insumos', categoria: 'Insumos' },
  { id: 'alquiler', categoria: 'Alquiler' },
  { id: 'servicios', categoria: 'Servicios' },
  { id: 'mantenimiento', categoria: 'Mantenimiento' },
  { id: 'combustible', categoria: 'Combustible' },
  { id: 'planilla', categoria: 'Planilla' },
  { id: 'marketing', categoria: 'Marketing' },
  { id: 'publicidad', categoria: 'Publicidad' },
  { id: 'comisiones', categoria: 'Comisiones' },
  { id: 'gastos_administrativos', categoria: 'Gastos Administrativos' },
  { id: 'varios', categoria: 'Gastos varios' },
  { id: 'impuestos', categoria: 'Impuestos' },
  { id: 'prestamos', categoria: 'Préstamos' },
];

const PRESUPUESTO_RUBROS = PRESUPUESTO_DETALLE.map((r) => r.id);

const FRECUENCIA: Record<string, string> = {
  mensual: 'Mensual',
  quincenal: 'Quincenal',
  anual: 'Anual',
};

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
  editandoClave?: string | null;
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
  imports: [CommonModule, FormsModule, RouterLink, NgSelectModule, TooltipModule, CrearDepartamentoComponent, CrearAreaEmpresaComponent],
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
  presupuestoDepartamentos: ModeladoPresupuestoEntidad | null = null;
  presupuestoAreas: ModeladoPresupuestoEntidad | null = null;
  presupuestoReparto: ModeladoPresupuestoReparto = 'igual';
  metaVentasAnual = 0;
  pronostico = { ...MODELADO_VACIA.parametrosPronostico };
  costoNuevo: ModeladoGastoNuevoForm | null = null;
  gastoNuevo: ModeladoGastoNuevoForm | null = null;
  menuGastoId: string | null = null;
  proveedores: { id: number; etiqueta: string }[] = [];
  sucursales: { id: number; nombre: string }[] = [];
  departamentosEmpresa: { id: number; nombre: string }[] = [];
  areasEmpresa: { id: number; nombre: string; id_departamento?: number; departamento?: { id?: number; nombre?: string } }[] = [];
  readonly rubrosGasto = RUBROS_GASTO;
  readonly rubrosCosto = RUBROS_COSTO;
  cargando = false;
  contenidoListo = false;
  errorMensaje: string | null = null;

  readonly presupuestoVistas: { id: ModeladoPresupuestoVista; label: string; activo: boolean }[] = [
    { id: 'empresa', label: 'Empresa', activo: true },
    { id: 'sucursales', label: 'Sucursales', activo: true },
    { id: 'departamentos', label: 'Departamentos', activo: true },
    { id: 'areas', label: 'Áreas', activo: true },
  ];
  presupuestoVista: ModeladoPresupuestoVista = 'empresa';
  filtroDepartamentoId: number | null = null;

  private loadSeq = 0;

  @HostListener('document:click')
  cerrarMenuGasto(): void {
    this.menuGastoId = null;
  }

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
    this.cargarDepartamentosYAreas();
  }

  cargarDepartamentosYAreas(): void {
    forkJoin({
      deps: this.api.getAll('area-empresa/list_departamentos'),
      areas: this.api.getAll('area-empresa/list'),
    })
      .pipe(take(1))
      .subscribe({
        next: ({ deps, areas }) => {
          this.departamentosEmpresa = deps ?? [];
          this.areasEmpresa = areas ?? [];
          this.sincronizarPresupuestoEntidades();
          this.cdr.markForCheck();
        },
        error: () => {
          this.cdr.markForCheck();
        },
      });
  }

  onDepartamentoCreado(dept?: { id?: number; nombre?: string }): void {
    if (dept?.id != null && dept?.nombre) {
      const id = Number(dept.id);
      if (!this.departamentosEmpresa.some((d) => Number(d.id) === id)) {
        this.departamentosEmpresa = [...this.departamentosEmpresa, { id, nombre: dept.nombre }];
      }
      this.sincronizarPresupuestoEntidades();
      this.cdr.markForCheck();
    }
    this.cargarDepartamentosYAreas();
  }

  onAreaCreada(area?: {
    id?: number;
    nombre?: string;
    id_departamento?: number;
    departamento?: { id?: number; nombre?: string };
  }): void {
    if (area?.id != null && area?.nombre) {
      const id = Number(area.id);
      if (!this.areasEmpresa.some((a) => Number(a.id) === id)) {
        this.areasEmpresa = [
          ...this.areasEmpresa,
          {
            id,
            nombre: area.nombre,
            id_departamento: area.id_departamento,
            departamento: area.departamento,
          },
        ];
      }
      this.sincronizarPresupuestoEntidades();
      this.cdr.markForCheck();
    }
    this.cargarDepartamentosYAreas();
  }

  private nombreAreaConDepartamento(area: {
    nombre: string;
    id_departamento?: number;
    departamento?: { id?: number; nombre?: string };
  }): string {
    const deptNombre =
      area.departamento?.nombre ??
      this.departamentosEmpresa.find((d) => Number(d.id) === Number(area.id_departamento))?.nombre;
    return deptNombre ? `${area.nombre} · ${deptNombre}` : area.nombre;
  }

  private sincronizarPresupuestoEntidades(): void {
    this.presupuestoDepartamentos = this.sincronizarMatrizEntidad(
      this.presupuestoDepartamentos,
      this.data.presupuesto.departamentos,
      this.departamentosEmpresa.map((d) => ({ id: Number(d.id), nombre: d.nombre })),
    );
    this.presupuestoAreas = this.sincronizarMatrizEntidad(
      this.presupuestoAreas,
      this.data.presupuesto.areas,
      this.areasEmpresa.map((a) => ({ id: Number(a.id), nombre: this.nombreAreaConDepartamento(a) })),
    );
  }

  private sincronizarMatrizEntidad(
    actualMatriz: ModeladoPresupuestoEntidad | null,
    backendMatriz: ModeladoPresupuestoEntidad | null,
    catalogoCols: { id: number; nombre: string }[],
  ): ModeladoPresupuestoEntidad | null {
    const colsMap = new Map<number, { id: number; nombre: string }>();
    for (const col of backendMatriz?.cols ?? []) {
      colsMap.set(Number(col.id), { id: Number(col.id), nombre: col.nombre });
    }
    for (const item of catalogoCols) {
      if (item.id > 0 && item.nombre && !colsMap.has(item.id)) {
        colsMap.set(item.id, { id: item.id, nombre: item.nombre });
      }
    }
    const cols = Array.from(colsMap.values());
    if (!cols.length) {
      return null;
    }

    const actualFilas = new Map((actualMatriz?.filas ?? []).map((f) => [f.id, f]));
    const backendFilas = new Map((backendMatriz?.filas ?? []).map((f) => [f.id, f]));
    const empresaById = new Map(this.presupuestoFilas.map((f) => [f.id, f]));
    const baseIds = this.presupuestoFilas.length
      ? this.presupuestoFilas.map((f) => ({ id: f.id, categoria: f.categoria }))
      : backendMatriz?.filas?.length
        ? backendMatriz.filas.map((f) => ({ id: f.id, categoria: f.categoria }))
        : PRESUPUESTO_DETALLE;

    const filas = baseIds.map(({ id, categoria }) => {
      const actual = actualFilas.get(id);
      const back = backendFilas.get(id);
      const porCol: Record<string, number> = {};
      let suma = 0;
      for (const col of cols) {
        const key = String(col.id);
        const val = Number(actual?.porCol?.[key] ?? back?.porCol?.[key] ?? 0);
        porCol[key] = val;
        suma += val;
      }
      const empresa = Number(empresaById.get(id)?.anual ?? back?.empresa ?? actual?.empresa ?? 0);
      return { id, categoria, porCol, suma, empresa };
    });

    const contribPorCol: Record<string, number> = {};
    let contribSuma = 0;
    for (const col of cols) {
      const key = String(col.id);
      const ventas = filas.find((f) => f.id === 'ventas')?.porCol[key] ?? 0;
      const gastos = filas.filter((f) => f.id !== 'ventas').reduce((s, f) => s + (f.porCol[key] ?? 0), 0);
      contribPorCol[key] = ventas - gastos;
      contribSuma += contribPorCol[key];
    }
    const ventasEmp = filas.find((f) => f.id === 'ventas')?.empresa ?? 0;
    const gastosEmp = filas.filter((f) => f.id !== 'ventas').reduce((s, f) => s + f.empresa, 0);

    return {
      cols,
      filas,
      contribucion: {
        porCol: contribPorCol,
        suma: contribSuma,
        empresa: backendMatriz?.contribucion.empresa ?? ventasEmp - gastosEmp,
      },
    };
  }

  private clonarEntidad(ent: ModeladoPresupuestoEntidad | null): ModeladoPresupuestoEntidad | null {
    if (!ent) {
      return null;
    }
    return {
      cols: ent.cols.map((c) => ({ ...c })),
      filas: ent.filas.map((f) => ({ ...f, porCol: { ...f.porCol } })),
      contribucion: {
        porCol: { ...ent.contribucion.porCol },
        suma: ent.contribucion.suma,
        empresa: ent.contribucion.empresa,
      },
    };
  }

  resumenDepartamentos(): string {
    return this.departamentosEmpresa.map((d) => d.nombre).filter(Boolean).join(', ');
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
    if (gasto.tipo === 'fijo' && gasto.diaPago) {
      const dia = gasto.diaPago.trim();
      partes.push(/^d[ií]a\b/i.test(dia) ? dia : `día ${dia}`);
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

  frecuenciaTexto(codigo: string): string {
    return FRECUENCIA[codigo] ?? codigo;
  }

  elegirTipo(gasto: ModeladoGastoFila, tipo: ModeladoGastoTipo, ev: Event): void {
    ev.preventDefault();
    if (gasto.origen || gasto.suspendido || gasto.tipo === tipo) {
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

  toggleMenuGasto(id: string, ev: Event): void {
    ev.stopPropagation();
    this.menuGastoId = this.menuGastoId === id ? null : id;
  }

  editarGasto(gasto: ModeladoGastoFila): void {
    this.menuGastoId = null;
    if (gasto.origen) {
      return;
    }
    this.gastoNuevo = {
      nombre: gasto.nombre,
      proveedor: gasto.proveedor ?? '',
      sucursal: gasto.sucursal ?? '',
      rubro: this.rubrosGasto.some((r) => r.id === gasto.rubro) ? gasto.rubro : 'otros',
      tipo: gasto.tipo ?? 'fijo',
      montoMensual: gasto.montoMensual ?? null,
      pctVentas: gasto.pctVentas ?? null,
      diaPago: gasto.diaPago ?? '5',
      llevaIva: false,
      estimado: true,
      editandoClave: gasto.id,
    };
  }

  alternarSuspensionGasto(gasto: ModeladoGastoFila): void {
    this.menuGastoId = null;
    if (gasto.origen) {
      return;
    }
    const suspendido = !gasto.suspendido;
    const actualizado: ModeladoGastoFila = {
      ...gasto,
      tipo: gasto.tipo ?? 'fijo',
      suspendido,
    };
    this.modeladoData
      .guardarClasificacion(actualizado, { suspendido })
      .pipe(
        switchMap(() => {
          if (!suspendido && actualizado.tipo === 'fijo' && actualizado.diaPago) {
            const yaEnCal = this.calendarioFilas.some((c) => c.clave === actualizado.id);
            if (!yaEnCal) {
              const dia = actualizado.diaPago.trim();
              return this.modeladoData.crearCalendario({
                clave: actualizado.id,
                nombre: actualizado.nombre,
                monto: actualizado.montoMensual,
                frecuencia: this.frecuenciaDeDia(dia),
                dia,
              });
            }
          }
          return of(null);
        }),
        take(1),
      )
      .subscribe({
        next: () => this.cargar(),
        error: () => this.falloGuardar(suspendido ? 'No se suspendió el gasto.' : 'No se reactivó el gasto.'),
      });
  }

  eliminarGasto(gasto: ModeladoGastoFila): void {
    this.menuGastoId = null;
    if (gasto.origen) {
      return;
    }
    const esManual = gasto.id.startsWith('m:') || gasto.montoMensual <= 0;
    const mensaje = esManual
      ? `¿Eliminar "${gasto.nombre}"?\n\nSe quitará del Paso 1 y del Calendario de costos.`
      : `"${gasto.nombre}" proviene de facturas registradas en contabilidad.\n\nAl eliminar su regla se quitará del Calendario y volverá a quedar sin clasificar. Si prefieres ocultarlo del modelado, usa "Suspender".\n\n¿Eliminar regla de todas formas?`;
    if (!window.confirm(mensaje)) {
      return;
    }
    this.modeladoData
      .borrarClasificacion(gasto.id)
      .pipe(take(1))
      .subscribe({
        next: () => {
          if (this.gastoNuevo?.editandoClave === gasto.id) {
            this.gastoNuevo = null;
          }
          this.cargar();
        },
        error: () => this.falloGuardar('No se eliminó el gasto.'),
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

  private crearFormItemNuevo(rubro: string, tipo: ModeladoGastoTipo): ModeladoGastoNuevoForm {
    return {
      nombre: '',
      proveedor: '',
      sucursal: '',
      rubro,
      tipo,
      montoMensual: null,
      pctVentas: null,
      diaPago: '5',
      llevaIva: false,
      estimado: true,
    };
  }

  mostrarGastoNuevo(): void {
    this.gastoNuevo = this.crearFormItemNuevo('otros', 'variable');
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
      .pipe(
        switchMap(() => {
          if (gasto.tipo === 'variable') {
            const calExistente = this.calendarioFilas.find((c) => c.clave === gasto.id && c.id > 0 && !c.origenModulo);
            if (calExistente) {
              return this.modeladoData.borrarCalendario(calExistente.id);
            }
          }
          return of(null);
        }),
        take(1),
      )
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

  vsPresupuestoClase(fila: ModeladoPresupuestoFila): string {
    const pct = fila.vsPresupuestoPct;
    if (pct == null) {
      return '';
    }
    if (fila.id === 'ventas') {
      if (pct < -5) {
        return 'modelado-pres-vs--alerta';
      }
      if (pct > 2) {
        return 'modelado-pres-vs--ok';
      }
      return '';
    }
    if (pct > 5) {
      return 'modelado-pres-vs--alerta';
    }
    if (pct < -2) {
      return 'modelado-pres-vs--ok';
    }
    return '';
  }

  presupuestoSucursalesActivo(): boolean {
    return (this.presupuestoSucursales?.sucursales.length ?? 0) > 0;
  }

  presupuestoEntidadCelda(mapa: Record<string, number>, idEntidad: number): number {
    return Number(mapa[String(idEntidad)]) || 0;
  }

  presupuestoEntidadSetCelda(mapa: Record<string, number>, idEntidad: number, value: number): void {
    mapa[String(idEntidad)] = value;
  }

  presupuestoSumaEntidadFila(mapa: Record<string, number>, cols: { id: number }[]): number {
    return cols.reduce((sum, c) => sum + (Number(mapa[String(c.id)]) || 0), 0);
  }

  private contribEntidad<T extends { id: string }>(
    filas: T[] | undefined,
    leerMapa: (fila: T) => Record<string, number>,
    idEntidad: number,
  ): number {
    if (!filas) {
      return 0;
    }
    let total = 0;
    for (const fila of filas) {
      const v = Number(leerMapa(fila)[String(idEntidad)]) || 0;
      total += fila.id === 'ventas' ? v : -v;
    }
    return total;
  }

  presupuestoSucursalCelda(fila: { porSucursal: Record<string, number> }, idSucursal: number): number {
    return this.presupuestoEntidadCelda(fila.porSucursal, idSucursal);
  }

  presupuestoSucursalSetCelda(
    fila: { porSucursal: Record<string, number> },
    idSucursal: number,
    value: number,
  ): void {
    this.presupuestoEntidadSetCelda(fila.porSucursal, idSucursal, value);
  }

  presupuestoSumaFila(fila: { porSucursal: Record<string, number> }): number {
    return this.presupuestoSumaEntidadFila(fila.porSucursal, this.presupuestoSucursales?.sucursales ?? []);
  }

  presupuestoContribSucursal(idSucursal: number): number {
    return this.contribEntidad(this.presupuestoSucursales?.filas, (f) => f.porSucursal, idSucursal);
  }

  presupuestoContribSuma(): number {
    return (this.presupuestoSucursales?.sucursales ?? []).reduce(
      (sum, s) => sum + this.presupuestoContribSucursal(s.id),
      0,
    );
  }

  presupuestoMatrizActiva(): ModeladoPresupuestoEntidad | null {
    if (this.presupuestoVista === 'departamentos') {
      return this.presupuestoDepartamentos;
    }
    if (this.presupuestoVista === 'areas') {
      return this.presupuestoAreas;
    }
    return null;
  }

  presupuestoColsVisibles(matriz: ModeladoPresupuestoEntidad): { id: number; nombre: string }[] {
    if (this.filtroDepartamentoId == null) {
      return matriz.cols;
    }
    const deptId = Number(this.filtroDepartamentoId);
    if (this.presupuestoVista === 'departamentos') {
      return matriz.cols.filter((c) => Number(c.id) === deptId);
    }
    const deptPorArea = new Map(
      this.areasEmpresa.map((a) => [Number(a.id), Number(a.id_departamento ?? a.departamento?.id ?? 0)]),
    );
    return matriz.cols.filter((c) => deptPorArea.get(Number(c.id)) === deptId);
  }

  presupuestoContribMatriz(matriz: ModeladoPresupuestoEntidad, idCol: number): number {
    return this.contribEntidad(matriz.filas, (f) => f.porCol, idCol);
  }

  presupuestoContribMatrizSuma(
    matriz: ModeladoPresupuestoEntidad,
    cols: { id: number }[] = matriz.cols,
  ): number {
    return cols.reduce((sum, c) => sum + this.presupuestoContribMatriz(matriz, c.id), 0);
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

  private frecuenciaDeDia(dia: string): 'mensual' | 'quincenal' {
    return (dia.match(/\d+/g)?.length ?? 0) >= 2 ? 'quincenal' : 'mensual';
  }

  agregarCalendarioPendiente(): void {
    const p = this.calendarioPendiente;
    if (!p) {
      return;
    }
    const dia = String(p.dia ?? '').trim() || '1';
    this.modeladoData
      .crearCalendario({
        clave: p.clave || null,
        nombre: p.nombre,
        monto: p.monto,
        frecuencia: this.frecuenciaDeDia(dia),
        dia,
      })
      .pipe(take(1))
      .subscribe({
        next: () => this.cargar(),
        error: () => this.falloGuardar('No se agregó la regla de calendario.'),
      });
  }

  mostrarCostoNuevo(): void {
    this.costoNuevo = this.crearFormItemNuevo('costo', 'fijo');
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
        this.payloadPresupuestoAreas(),
        this.payloadPresupuestoDepartamentos(),
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

  private payloadPresupuestoPorEntidad<T extends { id: string }>(
    cols: { id: number }[] | undefined,
    filas: T[] | undefined,
    leerMapa: (fila: T) => Record<string, number>,
  ): { id: number; filas: { rubro: string; anual: number }[] }[] | undefined {
    if (!cols?.length || !filas) {
      return undefined;
    }
    return cols.map((col) => ({
      id: col.id,
      filas: PRESUPUESTO_RUBROS.map((rubro) => {
        const fila = filas.find((f) => f.id === rubro);
        return {
          rubro,
          anual: fila ? Number(leerMapa(fila)[String(col.id)]) || 0 : 0,
        };
      }),
    }));
  }

  private payloadPresupuestoSucursales():
    | { idSucursal: number; filas: { rubro: string; anual: number }[] }[]
    | undefined {
    return this.payloadPresupuestoPorEntidad(
      this.presupuestoSucursales?.sucursales,
      this.presupuestoSucursales?.filas,
      (f) => f.porSucursal,
    )?.map(({ id, filas }) => ({ idSucursal: id, filas }));
  }

  private payloadPresupuestoAreas():
    | { idArea: number; filas: { rubro: string; anual: number }[] }[]
    | undefined {
    return this.payloadPresupuestoPorEntidad(
      this.presupuestoAreas?.cols,
      this.presupuestoAreas?.filas,
      (f) => f.porCol,
    )?.map(({ id, filas }) => ({ idArea: id, filas }));
  }

  private payloadPresupuestoDepartamentos():
    | { idDepartamento: number; filas: { rubro: string; anual: number }[] }[]
    | undefined {
    return this.payloadPresupuestoPorEntidad(
      this.presupuestoDepartamentos?.cols,
      this.presupuestoDepartamentos?.filas,
      (f) => f.porCol,
    )?.map(({ id, filas }) => ({ idDepartamento: id, filas }));
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
    const esEdicion = !!n.editandoClave;
    const clave = n.editandoClave || claveManual(n.nombre);
    const prev = esEdicion ? this.gastos.find((g) => g.id === clave) : undefined;
    const proveedor = String(n.proveedor ?? '').trim();
    const sucursal = String(n.sucursal ?? '').trim();
    const diaTexto = String(n.diaPago ?? '').trim();
    const dia = n.tipo === 'fijo' && diaTexto ? diaTexto : null;
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
      suspendido: prev?.suspendido,
    };
    this.modeladoData
      .guardarClasificacion(gasto, { pctVentas, diaPago: dia, proveedor: proveedor || null, sucursal: sucursal || null })
      .pipe(
        switchMap(() => {
          const calExistente = this.calendarioFilas.find((c) => c.clave === clave && c.id > 0 && !c.origenModulo);
          if (!gasto.suspendido && n.tipo === 'fijo' && dia) {
            const bodyCal = {
              clave,
              nombre: gasto.nombre,
              monto: this.montoCaja(monto, n.llevaIva),
              frecuencia: this.frecuenciaDeDia(dia),
              dia,
            };
            return calExistente
              ? this.modeladoData.actualizarCalendario({ ...calExistente, ...bodyCal })
              : this.modeladoData.crearCalendario(bodyCal);
          }
          if (esEdicion && calExistente) {
            return this.modeladoData.borrarCalendario(calExistente.id);
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
    this.presupuestoFilas = normalizarFilasPresupuesto(payload.presupuesto.filas);
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
    this.presupuestoDepartamentos = this.clonarEntidad(payload.presupuesto.departamentos);
    this.presupuestoAreas = this.clonarEntidad(payload.presupuesto.areas);
    this.sincronizarPresupuestoEntidades();
    this.presupuestoReparto = 'igual';
    this.metaVentasAnual = payload.presupuesto.metaVentas.montoAnual;
    this.pronostico = {
      ...payload.parametrosPronostico,
      historial: { ...payload.parametrosPronostico.historial },
    };
  }
}

function normalizarFilasPresupuesto(filas: ModeladoPresupuestoFila[]): ModeladoPresupuestoFila[] {
  if (!filas.length || filas.some((f) => f.id === 'costo_de_venta')) {
    return filas.map((f) => ({ ...f }));
  }
  const alias: Record<string, string> = {
    costo_de_venta: 'costo',
    alquiler: 'local',
    planilla: 'personal',
    gastos_administrativos: 'admin',
    prestamos: 'financieros',
  };
  const porId = new Map(filas.map((f) => [f.id, f]));
  return PRESUPUESTO_DETALLE.map(({ id, categoria }) => {
    const src = porId.get(id) ?? porId.get(alias[id] ?? '');
    return src
      ? { ...src, id, categoria }
      : { id, categoria, anual: 0, mesProyectado: 0, ejecutadoMonto: 0, ejecutadoPresupuesto: 0, vsPresupuestoPct: null };
  });
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
