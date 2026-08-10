import { Component, OnInit, TemplateRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { PlanillaConstants } from '../../../constants/planilla.constants';
import { ConceptoPlanilla, ConfiguracionPlanillaService } from '@services/configuracion-planilla.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { of, Subject } from 'rxjs';
import { catchError, debounceTime, switchMap } from 'rxjs/operators';
import {
  FE_PAIS_CR,
  FE_PAIS_SV,
  resolveCodigoPaisFe,
} from '@services/facturacion-electronica/fe-pais.util';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-planilla-detalle',
    templateUrl: './planilla-detalle.component.html',
    styles: [
      `
        .text-alert-descuentos {
          font-size: 0.8rem;
        }
      `,
    ],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PopoverModule, TooltipModule, CurrencyPipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PlanillaDetalleComponent implements OnInit {
  public planilla: any = {};
  public detalles: any[] = [];
  public loading: boolean = false;
  public procesando: boolean = false;
  public saving: boolean = false;
  public ESTADOS_PLANILLA = PlanillaConstants.ESTADOS_PLANILLA;
  public filtros: any = {
    buscador: '',
    estado: '',
    id_departamento: '',
    id_cargo: '',
    direccion: 'asc',
    orden: 'empleado.nombres',
    paginate: 10,
  };
  public notValue = false;

  public departamentos: any[] = [];
  public cargos: any[] = [];
  public cargosFiltrados: any[] = [];
  public vistaActual: string = 'empleados';
  public descuentosPatronales: any = null;
  public mostrarTodos: boolean = false;
  public downloading: boolean = false;
  public configPlanilla: any = null;
  public round: any = Math.round;

  private roundMonetary(value: number): number {
    return Math.round((Number(value) || 0) * 100) / 100;
  }

  private getDiasReferenciaPlanilla(): number {
    if (!this.planilla) {
      return 30;
    }

    switch (this.planilla.tipo_planilla) {
      case 'quincenal':
        return 15;
      case 'semanal':
        return 7;
      default:
        return 30;
    }
  }
  public conceptosConfigurados: any = null;
  public conceptosDeduccion: [string, ConceptoPlanilla][] = [];
  public conceptos: { [codigo: string]: ConceptoPlanilla } = {};
  public mapeoCamposES: { [codigo: string]: string } = {
    isss_pat: 'isss_patronal',
    afp_pat: 'afp_patronal',
  };
  public contabilidadHabilitada: boolean = false;


  modalRef!: BsModalRef;
  detalleSeleccionado: any = null;
  prestamosActivosEmpleado: any[] = [];
  abonosPrestamosAsignados: { id_prestamo: number; monto: number }[] = [];
  /** Selector: préstamo y monto elegidos para agregar a la lista de abonos */
  prestamoSeleccionadoParaAbono: any = null;
  montoSeleccionadoParaAbono: number | null = null;
  /** Accordion: Préstamos en descuentos colapsado por defecto para no ensuciar la interfaz */
  prestamosDescuentoCollapsed = true;
  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);
  /** Cada edición pide al backend el cálculo del país; el frontend no replica fórmulas. */
  private recalculoDetalle$ = new Subject<void>();
  public calculandoDetalle = false;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private configPlanillaService: ConfiguracionPlanillaService,
    private funcionalidadesService: FuncionalidadesService,
    private cdr: ChangeDetectorRef
  ) { }

  get currencySymbol(): string {
    const empresa = this.apiService.auth_user()?.empresa;
    return empresa?.currency?.currency_symbol
      || (empresa?.moneda === 'HNL' ? 'L' : '$');
  }

  ngOnInit() {
    this.route.params.pipe(this.untilDestroyed()).subscribe((params) => {

      if (params['id']) {
        this.loadPlanillas(params['id']);
      }
    });

    this.conceptosDeduccion = Object.entries(this.conceptos || {}).filter(
      ([, concepto]) => concepto.es_deduccion && !concepto.es_patronal
    );

    this.recalculoDetalle$
      .pipe(
        debounceTime(350),
        switchMap(() =>
          this.detalleSeleccionado?.id
            ? this.apiService
                .store(`planillas/detalles/previsualizar/${this.detalleSeleccionado.id}`, this.construirDatosDetalle())
                .pipe(catchError(() => of(null)))
            : of(null)
        ),
        this.untilDestroyed()
      )
      .subscribe((response: any) => {
        this.calculandoDetalle = false;
        if (response?.detalle && this.detalleSeleccionado?.id === response.detalle.id) {
          this.aplicarCalculoDetalle(response.detalle);
        }
        this.cdr.markForCheck();
      });

    this.cargarCatalogos();
    this.verificarAccesoContabilidad();
  }

  /** Entradas editables del detalle; los importes los calcula el backend según el país. */
  private construirDatosDetalle(): any {
    const detalle = this.detalleSeleccionado;
    const sinHorasExtra = (detalle.empleado?.tipo_contrato || 1) === 4;

    const datos: any = {
      dias_laborados: detalle.dias_laborados || this.getDiasReferenciaPlanilla(),
      horas_extra: sinHorasExtra ? 0 : (detalle.horas_extra || 0),
      comisiones: detalle.comisiones || 0,
      bonificaciones: detalle.bonificaciones || 0,
      otros_ingresos: detalle.otros_ingresos || 0,
      viaticos: detalle.viaticos || 0,
      abonos: detalle.abonos || 0,
      abonos_sin_retencion: detalle.abonos_sin_retencion !== false,
      prestamos: detalle.prestamos || 0,
      anticipos: detalle.anticipos || 0,
      otros_descuentos: detalle.otros_descuentos || 0,
      descuentos_judiciales: detalle.descuentos_judiciales || 0,
      detalle_otras_deducciones: detalle.detalle_otras_deducciones || '',
      salario_base: detalle.salario_base || 0,
    };

    if (this.esElSalvador && !sinHorasExtra) {
      datos.detalle_horas_extra = this.getDetalleHorasExtraDesdeLista();
    }

    return datos;
  }

  /** Copia los importes calculados por el backend sin tocar lo que el usuario está escribiendo. */
  private aplicarCalculoDetalle(calculado: any): void {
    const campos = [
      'salario_devengado',
      'monto_horas_extra',
      'isss_empleado',
      'isss_patronal',
      'afp_empleado',
      'afp_patronal',
      'renta',
      'conceptos_personalizados',
      'pais_configuracion',
      'total_ingresos',
      'total_descuentos',
      'sueldo_neto',
    ];
    campos.forEach((campo) => (this.detalleSeleccionado[campo] = calculado[campo]));

    if (this.esElSalvador) {
      this.detalleSeleccionado.horas_extra = calculado.horas_extra;
      this.detalleSeleccionado.detalle_horas_extra = calculado.detalle_horas_extra;
    }
  }

  verificarAccesoContabilidad() {
    this.funcionalidadesService.verificarAcceso('contabilidad')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (acceso) => {
          this.contabilidadHabilitada = acceso;
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al verificar acceso a contabilidad:', error);
          this.contabilidadHabilitada = false;
          this.cdr.markForCheck();
        }
      });
  }

  public cargarCatalogos() {
    this.apiService.getAll('departamentosPlanilla/list').pipe(this.untilDestroyed()).subscribe({
      next: (departamentos) => {
        this.departamentos = departamentos;
        this.cdr.markForCheck();
      },
      error: (error) => { this.alertService.error(error); this.cdr.markForCheck(); },
    });

    this.apiService.getAll('cargos/list').pipe(this.untilDestroyed()).subscribe({
      next: (cargos) => {
        this.cargos = cargos;
        this.cdr.markForCheck();
      },
      error: (error) => { this.alertService.error(error); this.cdr.markForCheck(); },
    });
  }

  cargarConceptosDeduccion() {
    if (!this.esElSalvador && this.conceptosConfigurados) {
      this.conceptosDeduccion = Object.entries(this.conceptosConfigurados || {}).filter(
        ([, concepto]: any) => concepto.es_deduccion && !concepto.es_patronal
      ) as [string, ConceptoPlanilla][];

    }
  }

  public onDepartamentoChange() {
    if (this.filtros.id_departamento) {
      this.cargosFiltrados = this.cargos.filter(
        (cargo) => cargo.id_departamento == this.filtros.id_departamento
      );
    } else {
      this.cargosFiltrados = [];
      this.filtros.id_cargo = '';
    }
    this.cdr.markForCheck();
  }

  /*** Método para limpiar filtros y mostrar vista normal*/
  public limpiarFiltros() {
    this.vistaActual = 'empleados';
    this.mostrarTodos = false;
    this.filtros = {
      buscador: '',
      fecha: '',
      estado: '',
      id_departamento: '',
      id_cargo: '',
      paginate: 10,
    };
    this.cargosFiltrados = [];
    this.filtrarDetallePlanillas();
  }

  public loadPlanillas(id: number) {
    this.loading = true;
    const params = {
      ...this.filtros,
      id: id,
    };

    this.apiService.getAll('planillas/detalles', params).pipe(this.untilDestroyed()).subscribe({
      next: (response) => {
        this.planilla = response;
        this.detalles = response.detalles.data;
        this.calcularTotalesPlanilla();
        this.calcularTotalesPatronales();
        this.loading = false;
        this.cdr.markForCheck();

        this.loadConceptosConfigurados();

      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
        this.cdr.markForCheck();
      },
    });
  }

  // calcularConcepto(detalle: any, concepto: any): number {
  //   const base = detalle[concepto.base_calculo]; // ej: detalle['salario_devengado']
  //   const tipo = concepto.tipo;
  //   const valor = concepto.valor;

  //   if (!base || !tipo) return 0;

  //   if (tipo === 'porcentaje') {
  //     return (base * valor) / 100;
  //   }



  //   // Otros tipos (como tabla) los veremos después
  //   return 0;
  // }

  calcularConcepto(detalle: any, concepto: any, codigoKey?: string): number {
    const codigo = (concepto?.codigo || codigoKey || '').toString().toLowerCase();

    // 🎯 Si es El Salvador, usar campos fijos
    if (this.esElSalvador) {
      const campo = this.mapeoCamposES[codigo];
      if (campo && detalle.hasOwnProperty(campo)) {
        return Number(detalle[campo]) || 0;
      }
    }

    // Snapshot CR/otros: valores ya calculados en conceptos_personalizados
    const personalizados = detalle?.conceptos_personalizados;
    if (personalizados && typeof personalizados === 'object') {
      const porKey = codigoKey && personalizados[codigoKey];
      if (porKey && porKey.valor != null) {
        return Number(porKey.valor) || 0;
      }
      for (const item of Object.values(personalizados) as any[]) {
        if (item?.codigo?.toString().toLowerCase() === codigo && item.valor != null) {
          return Number(item.valor) || 0;
        }
      }
      if (codigo && personalizados[codigo]?.valor != null) {
        return Number(personalizados[codigo].valor) || 0;
      }
    }

    // Campo directo en el detalle (p. ej. ccss_empleado si existiera)
    if (codigoKey && detalle.hasOwnProperty(codigoKey)) {
      return Number(detalle[codigoKey]) || 0;
    }
    if (codigo && detalle.hasOwnProperty(codigo)) {
      return Number(detalle[codigo]) || 0;
    }

    if (concepto.tipo === 'porcentaje') {
      const base = detalle[concepto.base_calculo];
      const tope = concepto.tope_maximo || null;

      let monto = Number(base) || 0;
      if (tope && monto > tope) {
        monto = tope;
      }

      return (monto * concepto.valor) / 100;
    }

    if (concepto.tipo === 'fijo' || concepto.tipo === 'monto_fijo') {
      return Number(concepto.valor) || 0;
    }

    return 0;
  }




  /** País efectivo de la planilla (detalle > config > empresa.pais/cod_pais). */
  get codPaisPlanilla(): string {
    const desdeDetalle = this.detalles?.[0]?.pais_configuracion;
    if (typeof desdeDetalle === 'string' && desdeDetalle.trim()) {
      const cod = desdeDetalle.trim().toUpperCase();
      if (cod.length <= 3) {
        return cod;
      }
      if (cod.includes('COSTA RICA')) {
        return FE_PAIS_CR;
      }
      if (cod.includes('SALVADOR')) {
        return FE_PAIS_SV;
      }
    }

    const desdeConfig = this.configPlanilla?.cod_pais;
    if (typeof desdeConfig === 'string' && desdeConfig.trim()) {
      return desdeConfig.trim().toUpperCase();
    }

    return resolveCodigoPaisFe(
      this.planilla?.empresa ?? this.apiService.auth_user()?.empresa
    );
  }

  get esElSalvador(): boolean {
    return this.codPaisPlanilla === FE_PAIS_SV;
  }

  get esCostaRica(): boolean {
    return this.codPaisPlanilla === FE_PAIS_CR;
  }

  /** Texto de ayuda para deducciones que no afectan retenciones. */
  get textoRetencionesLaborales(): string {
    if (this.esCostaRica) {
      return 'CCSS ni Impuesto sobre la Renta';
    }
    if (this.esElSalvador) {
      return 'ISSS, AFP ni ISR';
    }
    return 'retenciones laborales';
  }

  get conceptosEmpleado() {
    return Object.entries(this.conceptosConfigurados || {}).filter(
      ([, c]: any) => !c.es_patronal
    );
  }

  get conceptosPatronales() {
    return Object.entries(this.conceptosConfigurados || {}).filter(
      ([, c]: any) => c.es_patronal
    );
  }

  getTotalConcepto(codigo: string): number {
    let total = 0;

    // Validación defensiva
    if (!this.conceptosConfigurados || !this.detalles) return 0;

    const concepto = this.conceptosConfigurados[codigo];
    if (!concepto) return 0;

    for (const detalle of this.detalles) {
      const valor = this.calcularConcepto(detalle, concepto, codigo);
      total += Number(valor) || 0;
    }

    return total;
  }

  getTotalCampoReal(campo: string): number {
    let total = 0;

    for (const detalle of this.detalles) {
      if (detalle[campo] !== undefined && detalle[campo] !== null) {
        total += Number(detalle[campo]) || 0;
      }
    }

    return total;
  }




  getTotalCampo(campo: string): number {
    let total = 0;

    for (const detalle of this.detalles) {
      const valor = Number(detalle[campo]) || 0;
      total += valor;
    }

    return total;
  }


  getTotalAportesPatronales(detalle: any): number {
    if (this.esElSalvador) {
      return (
        Number(detalle.isss_patronal || 0) +
        Number(detalle.afp_patronal || 0)
      );
    }

    return this.conceptosPatronales
      .map(([codigo, c]) => this.calcularConcepto(detalle, c, codigo))
      .reduce((a, b) => a + b, 0);
  }


  loadConceptosConfigurados() {
    this.configPlanillaService.obtenerConfiguracion().pipe(this.untilDestroyed()).subscribe({
      next: (response) => {
        // Guardar la configuración completa para acceder a pais_configuracion
        this.configPlanilla = response.configuracion;
        this.conceptosConfigurados = response.configuracion?.configuracion?.conceptos || null;
        this.cargarConceptosDeduccion();
        this.cdr.markForCheck();
      },
      error: () => {
        console.warn('⚠️ No hay configuración personalizada');
        this.cdr.markForCheck();
      }
    });
  }

  public filtrarPlanillas() {
    this.loadPlanillas(this.planilla.id);
    if (this.modalRef) {
      this.modalRef.hide();
    }
  }

  /** Tipo de hora extra El Salvador */
  public readonly TIPOS_HORAS_EXTRA_ES: { value: 'diurna' | 'nocturna' | 'dia_descanso' | 'dia_asueto'; label: string }[] = [
    { value: 'diurna', label: 'Diurna (100% recargo - Art. 169)' },
    { value: 'nocturna', label: 'Nocturna (100%+25% nocturnidad - Art. 168)' },
    { value: 'dia_descanso', label: 'Día de descanso (50% recargo + día compensatorio - Art. 175)' },
    { value: 'dia_asueto', label: 'Día de asueto (100% recargo - Art. 192)' },
  ];

  /** Lista dinámica de filas de horas extra (El Salvador). Se convierte a detalle_horas_extra al calcular/guardar. */
  public listaHorasExtraES: { tipo: 'diurna' | 'nocturna' | 'dia_descanso' | 'dia_asueto'; horas: number }[] = [];

  /** Estructura por defecto de detalle de horas extra (El Salvador) */
  public getDetalleHorasExtraDefault(): { diurna: number; nocturna: number; dia_descanso: number; dia_asueto: number } {
    return { diurna: 0, nocturna: 0, dia_descanso: 0, dia_asueto: 0 };
  }

  /** Convierte listaHorasExtraES a objeto { diurna, nocturna, dia_descanso, dia_asueto, dia_descanso_dias } (suma por tipo; dias = cantidad de filas tipo día descanso) */
  public getDetalleHorasExtraDesdeLista(): { diurna: number; nocturna: number; dia_descanso: number; dia_asueto: number; dia_descanso_dias?: number } {
    const out = this.getDetalleHorasExtraDefault();
    let diaDescansoDias = 0;
    (this.listaHorasExtraES || []).forEach((fila) => {
      const h = Number(fila.horas) || 0;
      if (fila.tipo === 'dia_descanso') diaDescansoDias += 1;
      if (fila.tipo in out) (out as Record<string, number>)[fila.tipo] += h;
    });
    return { ...out, dia_descanso_dias: diaDescansoDias };
  }

  /** Multiplicadores hora extra El Salvador: diurna 100%, nocturna 100%+25%, día asueto 100% (Art. 192). Día descanso: solo parte horaria 50% (Art. 175); el monto real usa getMontoFilaHoraExtra. */
  public readonly MULTIPLICADORES_HORAS_EXTRA_ES: Record<string, number> = {
    diurna: 2,
    nocturna: 2.25,
    dia_descanso: 1.5,
    dia_asueto: 2,
  };

  public agregarHoraExtraES(): void {
    this.listaHorasExtraES = this.listaHorasExtraES || [];
    this.listaHorasExtraES.push({ tipo: 'diurna', horas: 0 });
    this.calcularTotalesUnificado();
  }

  public eliminarHoraExtraES(index: number): void {
    this.listaHorasExtraES.splice(index, 1);
    this.calcularTotalesUnificado();
  }

  /** Monto mostrado por fila. Art. 175 día descanso: solo parte horaria (50% recargo); el día compensatorio se suma una vez al total. Art. 192 asueto: 100% recargo (2×). */
  public getMontoFilaHoraExtra(fila: { tipo: 'diurna' | 'nocturna' | 'dia_descanso' | 'dia_asueto'; horas: number }): number {
    const h = Number(fila.horas) || 0;
    const V = this.getValorHoraNormalES();
    if (fila.tipo === 'diurna') return Number((h * V * 2).toFixed(2));
    if (fila.tipo === 'nocturna') return Number((h * V * 2.25).toFixed(2));
    if (fila.tipo === 'dia_descanso') return Number((h * V * 1.5).toFixed(2)); // solo 50% por hora; día compensatorio se suma al total
    if (fila.tipo === 'dia_asueto') return Number((h * V * 2).toFixed(2)); // 100% recargo
    return 0;
  }

  public getNombreTipoHoraExtra(tipo: string): string {
    return this.TIPOS_HORAS_EXTRA_ES.find((t) => t.value === tipo)?.label || tipo;
  }

  /** Desglose del monto horas extra para presentación: horas trabajadas vs día compensatorio (Art. 175). */
  public getDesgloseMontoHorasExtraES(): { montoHoras: number; montoDiaCompensatorio: number } {
    const dhe = this.getDetalleHorasExtraDesdeLista();
    const dias = dhe.dia_descanso_dias ?? 0;
    const V = this.getValorHoraNormalES();
    let montoHoras = 0;
    (this.listaHorasExtraES || []).forEach((fila) => { montoHoras += this.getMontoFilaHoraExtra(fila); });
    const montoDiaCompensatorio = Number((dias * (8 * V)).toFixed(2));
    return { montoHoras: Number(montoHoras.toFixed(2)), montoDiaCompensatorio };
  }

  public editarDetalle(detalle: any) {
    if (this.planilla.estado !== 2) {
      this.alertService.warning(
        'Advertencia',
        'Solo se pueden editar planillas en estado borrador'
      );
      return;
    }
    this.detalleSeleccionado = { ...detalle };
    if (this.detalleSeleccionado.abonos === undefined || this.detalleSeleccionado.abonos === null) {
      this.detalleSeleccionado.abonos = 0;
    }
    if (this.detalleSeleccionado.abonos_sin_retencion === undefined || this.detalleSeleccionado.abonos_sin_retencion === null) {
      this.detalleSeleccionado.abonos_sin_retencion = true;
    }
    if (this.detalleSeleccionado.viaticos === undefined || this.detalleSeleccionado.viaticos === null) {
      this.detalleSeleccionado.viaticos = 0;
    }
    this.listaHorasExtraES = [];
    const tipoContratoEdicion = this.detalleSeleccionado.empleado?.tipo_contrato || 1;
    const sinHorasExtraPorContrato = tipoContratoEdicion === 4; // servicios profesionales: sin HE en planilla
    if (this.esElSalvador && !sinHorasExtraPorContrato) {
      const dhe = detalle.detalle_horas_extra;
      if (dhe && typeof dhe === 'object' && ['diurna', 'nocturna', 'dia_descanso', 'dia_asueto'].every((k) => k in dhe)) {
        const obj = dhe as Record<string, number>;
        this.detalleSeleccionado.detalle_horas_extra = {
          diurna: Number(obj['diurna']) || 0,
          nocturna: Number(obj['nocturna']) || 0,
          dia_descanso: Number(obj['dia_descanso']) || 0,
          dia_asueto: Number(obj['dia_asueto']) || 0,
        };
        (['diurna', 'nocturna', 'dia_asueto'] as const).forEach((tipo) => {
          const horas = Number(obj[tipo]) || 0;
          if (horas > 0) this.listaHorasExtraES.push({ tipo, horas });
        });
        const horasDiaDescanso = Number(obj['dia_descanso']) || 0;
        const diasDescanso = Math.max(1, Math.round(Number(obj['dia_descanso_dias']) || 0));
        if (horasDiaDescanso > 0) {
          const horasPorDia = horasDiaDescanso / diasDescanso;
          for (let i = 0; i < diasDescanso; i++) this.listaHorasExtraES.push({ tipo: 'dia_descanso', horas: Number(horasPorDia.toFixed(2)) });
        }
      } else {
        const def = this.getDetalleHorasExtraDefault();
        if (Number(detalle.horas_extra) > 0) def.diurna = Number(detalle.horas_extra) || 0;
        this.detalleSeleccionado.detalle_horas_extra = def;
        if (def.diurna > 0) this.listaHorasExtraES.push({ tipo: 'diurna', horas: def.diurna });
        if (def.nocturna > 0) this.listaHorasExtraES.push({ tipo: 'nocturna', horas: def.nocturna });
        if (def.dia_descanso > 0) this.listaHorasExtraES.push({ tipo: 'dia_descanso', horas: def.dia_descanso });
        if (def.dia_asueto > 0) this.listaHorasExtraES.push({ tipo: 'dia_asueto', horas: def.dia_asueto });
      }
    } else if (this.esElSalvador && sinHorasExtraPorContrato) {
      this.detalleSeleccionado.detalle_horas_extra = this.getDetalleHorasExtraDefault();
      this.detalleSeleccionado.horas_extra = 0;
      this.detalleSeleccionado.monto_horas_extra = 0;
    }
    this.calcularTotalesUnificado();
    this.cdr.markForCheck();

    const idEmpleado = detalle.id_empleado ?? detalle.empleado?.id;
    this.prestamosActivosEmpleado = [];
    this.prestamoSeleccionadoParaAbono = null;
    this.montoSeleccionadoParaAbono = null;
    // Cargar abonos ya asignados solo si el detalle tiene monto en préstamos (evita inconsistencia 0 vs abonos)
    const montoPrestamosDetalle = Number(detalle.prestamos) || 0;
    const abonosDelDetalle = montoPrestamosDetalle > 0
      ? (detalle.abonos_prestamo ?? detalle.abonosPrestamo ?? [])
      : [];
    this.abonosPrestamosAsignados = Array.isArray(abonosDelDetalle)
      ? abonosDelDetalle.map((m: any) => ({
          id_prestamo: m.id_prestamo,
          monto: Number(m.monto) || 0,
        }))
      : [];
    if (idEmpleado) {
      this.apiService
        .getAll(`planillas/prestamos/empleado/${idEmpleado}/prestamos-activos`, {})
        .subscribe({
          next: (data: any) => {
            const lista = Array.isArray(data) ? data : (data?.data ?? []);
            this.prestamosActivosEmpleado = lista;
          },
          error: () => {
            this.prestamosActivosEmpleado = [];
          },
        });
    }

    setTimeout(() => {
      const element = document.querySelector('.card-header');
      element?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
  }

  public cancelarEdicion() {
    this.detalleSeleccionado = null;
    this.calculandoDetalle = false;
    this.listaHorasExtraES = [];
    this.cdr.markForCheck();
    this.prestamosActivosEmpleado = [];
    this.abonosPrestamosAsignados = [];
    this.prestamoSeleccionadoParaAbono = null;
    this.montoSeleccionadoParaAbono = null;
  }

  /** Préstamos que aún no están en la lista de abonos (para el dropdown) */
  get prestamosDisponiblesParaSeleccionar(): any[] {
    const idsAsignados = this.abonosPrestamosAsignados.map((a) => a.id_prestamo);
    return (this.prestamosActivosEmpleado || []).filter((p) => !idsAsignados.includes(p.id));
  }

  getLabelPrestamo(idPrestamo: number): string {
    const p = (this.prestamosActivosEmpleado || []).find((x) => x.id === idPrestamo);
    if (!p) return `Préstamo #${idPrestamo}`;
    return `Préstamo #${p.numero_prestamo} — Saldo: ${p.saldo_actual ?? 0}`;
  }

  /** Al cambiar el monto a descontar: si queda en 0 o es menor que la suma actual, vaciar la lista y resetear el selector para que el desplegable vuelva a mostrar todos los préstamos. */
  onPrestamosMontoChange(): void {
    this.calcularTotalesUnificado();
    const monto = Number(this.detalleSeleccionado?.prestamos) || 0;
    const suma = this.sumaAbonosPrestamos();
    const debeLimpiar = this.abonosPrestamosAsignados.length > 0 && (monto <= 0 || monto < suma);
    if (debeLimpiar) {
      this.abonosPrestamosAsignados = [];
      this.prestamoSeleccionadoParaAbono = null;
      this.montoSeleccionadoParaAbono = null;
    }
  }

  agregarAbonoPrestamo(): void {
    if (!this.prestamoSeleccionadoParaAbono || (this.montoSeleccionadoParaAbono ?? 0) <= 0) return;
    const monto = Number(this.montoSeleccionadoParaAbono);
    const id = this.prestamoSeleccionadoParaAbono.id;
    const existente = this.abonosPrestamosAsignados.find((a) => a.id_prestamo === id);
    if (existente) {
      existente.monto = monto;
    } else {
      this.abonosPrestamosAsignados.push({ id_prestamo: id, monto });
    }
    this.prestamoSeleccionadoParaAbono = null;
    this.montoSeleccionadoParaAbono = null;
  }

  quitarAbonoPrestamo(idPrestamo: number): void {
    this.abonosPrestamosAsignados = this.abonosPrestamosAsignados.filter((a) => a.id_prestamo !== idPrestamo);
    if (this.detalleSeleccionado) {
      this.detalleSeleccionado.prestamos = this.sumaAbonosPrestamos();
      this.calcularTotalesUnificado();
    }
  }

  sumaAbonosPrestamos(): number {
    return this.abonosPrestamosAsignados.reduce((s, a) => s + (Number(a.monto) || 0), 0);
  }

  /** Si hay monto en préstamos y la suma de abonos no coincide, mostrar advertencia */
  mostrarAdvertenciaSumaAbonosPrestamos(): boolean {
    const montoPrestamos = Number(this.detalleSeleccionado?.prestamos) || 0;
    if (montoPrestamos <= 0) return false;
    return Math.abs(this.sumaAbonosPrestamos() - montoPrestamos) > 0.02;
  }

  getEstadoDetalle(estado: number) {
    let estadoObject;
    switch (estado) {
      case this.ESTADOS_PLANILLA.INACTIVA:
        estadoObject = {
          nombre: 'Inactiva',
          className: 'bg-dark',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.ACTIVA:
        estadoObject = {
          nombre: 'Activa',
          className: 'bg-success',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.BORRADOR:
        estadoObject = {
          nombre: 'Borrador',
          className: 'bg-secondary',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.APROBADA:
        estadoObject = {
          nombre: 'Aprobada',
          className: 'bg-primary',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.PAGADA:
        estadoObject = {
          nombre: 'Pagada',
          className: 'bg-success',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.ANULADA:
        estadoObject = {
          nombre: 'Anulada',
          className: 'bg-danger',
        };
        return estadoObject;
      case this.ESTADOS_PLANILLA.PENDIENTE:
        estadoObject = {
          nombre: 'Pendiente',
          className: 'bg-secondary',
        };
        return estadoObject;
      default:
        return {
          nombre: 'Desconocido',
          className: 'bg-secondary',
        };
    }
  }

  public guardarDetalle() {
    if (!this.detalleSeleccionado || !this.detalleSeleccionado.id) {
      this.alertService.error(
        'No se ha seleccionado ningún detalle para guardar'
      );
      return;
    }

    if (this.planilla.estado !== 2) {
      this.alertService.warning(
        'Advertencia',
        'Solo se pueden editar planillas en estado borrador'
      );
      return;
    }

    this.saving = true;

    const datosActualizados: any = this.construirDatosDetalle();

    const abonosConMonto = this.abonosPrestamosAsignados.filter((a) => (Number(a.monto) || 0) > 0);

    if (abonosConMonto.length > 0) {
      const sumaAbonos = this.sumaAbonosPrestamos();
      const montoPrestamos = Number(this.detalleSeleccionado.prestamos) || 0;
      if (Math.abs(sumaAbonos - montoPrestamos) > 0.02) {
        this.alertService.error(
          'La suma de los abonos asignados (' + sumaAbonos.toFixed(2) + ') debe coincidir con el monto en Préstamos (' + montoPrestamos.toFixed(2) + ').'
        );
        this.saving = false;
        return;
      }
      datosActualizados.abonos_prestamos = abonosConMonto.map((a) => ({
        id_prestamo: a.id_prestamo,
        monto: Number(a.monto),
      }));
    }

    this.apiService
      .store(
        `planillas/detalles/editar/${this.detalleSeleccionado.id}`,
        datosActualizados
      )
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          this.alertService.success(
            'Éxito',
            'Detalle actualizado correctamente'
          );

          const index = this.detalles.findIndex(
            (d) => d.id === this.detalleSeleccionado.id
          );
          if (index !== -1) {
            this.detalles[index] = {
              ...this.detalles[index],
              ...response.detalle,
              empleado: response.empleado || this.detalles[index].empleado
            };
          }

          if (response.planilla) {
            this.planilla = response.planilla;
          }

          this.calcularTotalesPlanilla();
          this.detalleSeleccionado = null;
          this.saving = false;
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.saving = false;
          this.cdr.markForCheck();
        },
      });
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion =
        this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
  }

  public getEstadoPlanilla(estado: number): string {
    switch (estado) {
      case 0:
        return 'Inactiva';
      case 1:
        return 'Activa';
      case 2:
        return 'Borrador';
      case 3:
        return 'Pendiente';
      case 4:
        return 'Aprobada';
      case 5:
        return 'Pagada';
      case 6:
        return 'Anulada';
      default:
        return 'Desconocido';
    }
  }

  public getEstadoClass(estado: number): string {
    switch (estado) {
      case 0:
        return 'bg-dark';
      case 1:
        return 'bg-success';
      case 2:
        return 'bg-secondary';
      case 3:
        return 'bg-info';
      case 4:
        return 'bg-success';
      case 5:
        return 'bg-primary';
      case 6:
        return 'bg-danger';
      default:
        return 'bg-secondary';
    }
  }
  public getTextEstadoClass(estado: number): string {
    switch (estado) {
      case 0:
        return 'text-white';
      case 1:
        return 'text-dark';
      case 2:
        return 'text-white';
      case 3:
        return 'text-dark';
      case 4:
        return 'text-dark';
      case 5:
        return 'text-white';
      case 6:
        return 'text-dark';
      default:
        return 'text-white';
    }
  }

  public openFilter(template: TemplateRef<any>) {
    this.modalRef = this.modalService.show(template);
  }

  public filtrarDetallePlanillas() {
    this.loadPlanillas(this.planilla.id);
    this.modalRef?.hide();
  }

  openEditarDetalleModal(detalle: any, template: TemplateRef<any>) {
    this.detalleSeleccionado = { ...detalle };
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }



  private parseNumber(value: any): number {
    if (value === null || value === undefined || value === '') {
      return 0;
    }
    const parsed = parseFloat(value.toString().replace(/[^\d.-]/g, ''));
    return isNaN(parsed) ? 0 : parsed;
  }

  getTotalRegistros(): string {
    return `${this.planilla?.detalles?.total ?? 0} registros`;
  }

  withdrawPayroll(detalle: any) {
    if (this.planilla.estado !== 2) {
      this.alertService.warning(
        'Advertencia',
        'Solo se pueden modificar planillas en estado borrador'
      );
      return;
    }
    Swal.fire({
      title: '¿Está seguro?',
      text: '¿Está seguro de retirar este empleado de la planilla?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, retirar',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      } else {
        this.saving = true;
        this.apiService
          .store(`planillas/detalles/retirar/${detalle.id}`, {})
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (response) => {
              this.alertService.success(
                'Éxito',
                'Empleado retirado de la planilla exitosamente'
              );
              // Actualizar el estado del detalle localmente
              detalle.estado = 0;
              this.loadPlanillas(this.planilla.id);
              // this.calcularTotalesPlanilla();
              this.saving = false;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(
                'Error al retirar el empleado de la planilla'
              );
              this.saving = false;
              this.cdr.markForCheck();
            },
          });
      }
    });
  }

  includePayroll(detalle: any) {
    if (this.planilla.estado !== 2) {
      this.alertService.warning(
        'Advertencia',
        'Solo se pueden modificar planillas en estado borrador'
      );
      return;
    }

    Swal.fire({
      title: '¿Está seguro?',
      text: '¿Está seguro de incluir este empleado en la planilla?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, incluir',
    }).then((result: any) => {
      if (!result.isConfirmed) {
        return;
      } else {
        this.saving = true;
        this.apiService
          .store(`planillas/detalles/incluir/${detalle.id}`, {})
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (response) => {
              this.alertService.success(
                'Éxito',
                'Empleado incluido en la planilla exitosamente'
              );
              // Actualizar el estado del detalle localmente
              detalle.estado = 1;
              this.loadPlanillas(this.planilla.id);
              // this.calcularTotalesPlanilla();
              this.saving = false;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(
                'Error al incluir el empleado en la planilla'
              );
              this.saving = false;
              this.cdr.markForCheck();
            },
          });
      }
    });
  }

  public downloadInvoice(detalle: any) {
    Swal.fire({
      title: 'Generando boleta',
      text: 'Por favor espere...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    this.apiService
      .download(`planillas/detalles/${detalle.id}/boleta`)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          // Crear blob y descargar
          const blob = new Blob([response], { type: 'application/pdf' });
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          const codigoPlanilla = this.planilla?.codigo ?? `planilla_${this.planilla?.id ?? 'x'}`;
          const codigoEmpleado = detalle.empleado?.codigo ?? detalle.codigo ?? detalle.id_empleado ?? detalle.id;
          link.download = `boleta_${codigoPlanilla}_${codigoEmpleado}.pdf`;
          link.click();
          window.URL.revokeObjectURL(url);

          Swal.fire({
            title: '¡Éxito!',
            text: 'Boleta generada correctamente',
            icon: 'success',
            timer: 1500,
          });
        },
        error: (error) => {
          Swal.fire({
            title: 'Error',
            text: 'Error al generar la boleta de pago',
            icon: 'error',
          });
          this.alertService.error(error);
        },
      });
  }

  /**
   * Método para mostrar todos los registros (sin paginación)
   */
  public mostrarTodosRegistros() {
    this.mostrarTodos = true;
    this.filtros.paginate = 1000; // Número alto para mostrar todos
    this.filtrarDetallePlanillas();
    this.cdr.markForCheck();
  }

  /**
   * Método para mostrar vista de empleados con paginación normal
   */
  public mostrarDetallesEmpleados() {
    this.vistaActual = 'empleados';
    this.mostrarTodos = false;
    this.filtros.paginate = 10; // Volver a paginación normal
    this.filtrarDetallePlanillas();
    this.cdr.markForCheck();
  }

  /**
   * Método para mostrar vista de descuentos patronales
   */
  public mostrarDescuentosPatronales() {
    this.vistaActual = 'descuentos_patronales';
    this.cargarDescuentosPatronales();
    this.cdr.markForCheck();
  }

  /**
   * Cargar datos de descuentos patronales
   */
  private cargarDescuentosPatronales() {
    this.loading = true;
    this.apiService.read('planillas/descuentos-patronales/', this.planilla.id).pipe(this.untilDestroyed()).subscribe({
      next: (response) => {
        this.descuentosPatronales = response;
        // console.log(this.descuentosPatronales);
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        this.alertService.error('Error al cargar los descuentos patronales');
        this.loading = false;
        this.cdr.markForCheck();
      }
    });
  }

  public getTotalDescuentosPatronales(): number {
    if (this.esElSalvador) {
      return (
        (this.descuentosPatronales?.resumen?.total_isss_patronal || 0) +
        (this.descuentosPatronales?.resumen?.total_afp_patronal || 0)
      );
    }

    return (this.detalles || []).reduce(
      (sum, detalle) => sum + this.getTotalAportesPatronales(detalle),
      0
    );
  }

  // public getTotalDescuentosPatronales(): number {
  //   if (!this.descuentosPatronales) return 0;
  //   return this.descuentosPatronales.detalles?.reduce((total: number, detalle: any) => {
  //     return total + (detalle.isss_patronal || 0) + (detalle.afp_patronal || 0);
  //   }, 0) || 0;
  // }


  /** Cualquier edición dispara el cálculo del país en el backend (SV, CR y los que se agreguen). */
  public calcularTotalesUnificado() {
    if (!this.detalleSeleccionado?.id) {
      return;
    }
    this.calculandoDetalle = true;
    this.recalculoDetalle$.next();
  }


  public getTipoContratoNombre(tipoContrato: number): string {
    switch (tipoContrato) {
      case 1: return 'Permanente';
      case 2: return 'Temporal';
      case 3: return 'Por obra';
      case 4: return 'Servicios Profesionales';
      default: return 'Desconocido';
    }
  }

  public esServiciosProfesionales(): boolean {
    return this.detalleSeleccionado?.empleado?.tipo_contrato === 4;
  }

  public esContratoSinPrestaciones(): boolean {
    const tipoContrato = this.detalleSeleccionado?.empleado?.tipo_contrato;
    return tipoContrato === 3 || tipoContrato === 4;
  }


  /**
   * Mostrar información detallada del cálculo de renta
   */
  public mostrarDetalleCalculoRenta(detalle: any) {
    if (!detalle) return;

    const salarioDevengado = Number(detalle.salario_devengado) || 0;
    const montoHorasExtra = Number(detalle.monto_horas_extra) || 0;
    const comisiones = Number(detalle.comisiones) || 0;
    const bonificaciones = Number(detalle.bonificaciones) || 0;
    const otrosIngresos = Number(detalle.otros_ingresos) || 0;

    const totalIngresos = salarioDevengado + montoHorasExtra + comisiones + bonificaciones + otrosIngresos;
    const isssEmpleado = PlanillaConstants.calcularDescuentoISSSEmpleado(totalIngresos, this.planilla.tipo_planilla);
    const afpEmpleado = PlanillaConstants.calcularDescuentoAFPEmpleado(totalIngresos);
    const salarioGravado = PlanillaConstants.calcularSalarioGravado(
      totalIngresos,
      isssEmpleado,
      afpEmpleado,
      this.planilla.tipo_planilla
    );

    const infoTramo = PlanillaConstants.obtenerInformacionTramo(salarioGravado, this.planilla.tipo_planilla);

    if (!infoTramo) {
      this.alertService.warning('Advertencia', 'No se pudo determinar el tramo de renta aplicable');
      return;
    }

    // Crear mensaje detallado
    const mensaje = `
      <div style="text-align: left;">
        <h4>📊 Detalle de Cálculo de Renta - ${detalle.empleado?.nombres} ${detalle.empleado?.apellidos}</h4>
        <hr>

        <h5>💰 Ingresos:</h5>
        <p>• Total Ingresos: <strong>$${totalIngresos.toFixed(2)}</strong></p>

        <h5>📉 Descuentos de Seguridad Social:</h5>
        <p>• ISSS Empleado (3%): <strong>$${isssEmpleado.toFixed(2)}</strong></p>
        <p>• AFP Empleado (7.25%): <strong>$${afpEmpleado.toFixed(2)}</strong></p>

        <h5>💵 Base Gravable:</h5>
        <p>• Salario Gravado: <strong>$${salarioGravado.toFixed(2)}</strong></p>

        <h5>📋 Tramo Aplicado (${this.planilla.tipo_planilla}):</h5>
        <p>• Tramo: <strong>${infoTramo.tramo_numero}</strong></p>
        <p>• Rango: <strong>$${infoTramo.desde} - $${infoTramo.hasta}</strong></p>
        <p>• Porcentaje: <strong>${infoTramo.porcentaje}%</strong></p>
        <p>• Sobre exceso de: <strong>$${infoTramo.sobre_exceso}</strong></p>
        <p>• Cuota fija: <strong>$${infoTramo.cuota_fija}</strong></p>
        <p>• Exceso: <strong>$${infoTramo.exceso.toFixed(2)}</strong></p>

        <h5>🎯 Resultado:</h5>
        <p>• <strong>Retención calculada: $${infoTramo.retencion_calculada.toFixed(2)}</strong></p>

        <hr>
        <small><em>Decreto No. 10 - Abril 2025</em></small>
      </div>
    `;

    // Usar SweetAlert2 para mejor presentación
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: '📊 Detalle de Cálculo de Renta',
        html: mensaje,
        icon: 'info',
        width: '600px',
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#3085d6'
      });
    } else {
      // Fallback si no hay SweetAlert2
      this.alertService.info('Detalle de Cálculo de Renta', mensaje);
    }
  }

  /**
   * Validar que los cálculos estén correctos
   */
  public validarCalculos(): boolean {
    if (!this.detalleSeleccionado) return false;

    const salarioDevengado = Number(this.detalleSeleccionado.salario_devengado) || 0;
    if (salarioDevengado <= 0) {
      this.alertService.warning('Advertencia', 'El salario devengado debe ser mayor a cero');
      return false;
    }

    const diasLaborados = Number(this.detalleSeleccionado.dias_laborados) || 0;
    const diasMaximos = this.planilla.tipo_planilla === 'quincenal' ? 15 :
      this.planilla.tipo_planilla === 'semanal' ? 7 : 30;

    if (diasLaborados > diasMaximos) {
      this.alertService.warning('Advertencia',
        `Los días laborados no pueden exceder ${diasMaximos} para planilla ${this.planilla.tipo_planilla}`);
      return false;
    }

    // Validar que las constantes estén cargadas
    if (!PlanillaConstants.isLoaded()) {
      this.alertService.error('Las constantes de planilla no están cargadas. Recargue la página.');
      return false;
    }

    return true;
  }

  /**
   * Método para aplicar recálculo de renta (junio/diciembre)
   */
  public aplicarRecalculoRenta() {
    const mesActual = new Date().getMonth() + 1;

    if (mesActual !== 6 && mesActual !== 12) {
      this.alertService.error('El recálculo de renta solo se aplica en junio y diciembre');
      return;
    }

    const tipoRecalculo = mesActual === 6 ? 'junio' : 'diciembre';

    Swal.fire({
      title: '¿Está seguro?',
      text: `¿Está seguro de aplicar el recálculo de renta para ${tipoRecalculo}? Esta acción modificará las retenciones de todos los empleados según las tablas de recálculo.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, aplicar recálculo',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.store(`planillas/recalculo-renta/${this.planilla.id}`, {
          tipo_recalculo: tipoRecalculo
        }).pipe(this.untilDestroyed()).subscribe({
          next: (response) => {
            Swal.fire({
              title: 'Éxito',
              text: 'Recálculo de renta aplicado correctamente',
              icon: 'success'
            });
            this.loadPlanillas(this.planilla.id); // Recargar datos
          },
          error: (error) => {
            Swal.fire({
              title: 'Error',
              text: 'Error al aplicar recálculo de renta',
              icon: 'error'
            });
          }
        });
      }
    });
  }

  getIngresosAdicionales(detalle: any): number {

    const bon = parseFloat(detalle.bonificaciones) || 0;
    const com = parseFloat(detalle.comisiones) || 0;
    const horas = parseFloat(detalle.monto_horas_extra) || 0;
    const otros = parseFloat(detalle.otros_ingresos) || 0;

    return bon + com + horas + otros;
  }

  /** Total a pagar = sueldo neto + viáticos (viáticos no afectan deducciones) */
  getTotalAPagar(detalle: any): number {
    const sueldoNeto = Number(detalle?.sueldo_neto) || 0;
    const viaticos = Number(detalle?.viaticos) || 0;
    return sueldoNeto + viaticos;
  }

  /** Total a pagar de toda la planilla */
  getTotalAPagarPlanilla(): number {
    if (!this.detalles?.length) return 0;
    return this.detalles.reduce((sum, d) => sum + this.getTotalAPagar(d), 0);
  }

  /** Viáticos no afectan cálculos; solo actualizar vista */
  onViaticosChange(): void {
    // No-op: viáticos no afectan sueldo_neto ni deducciones
  }

  calcularValoresDetalle() {
    if (!this.detalleSeleccionado) return;

    // Obtener valores de entrada
    const salarioDevengado = Number(this.detalleSeleccionado.salario_devengado) || 0;
    const montoHorasExtra = Number(this.detalleSeleccionado.monto_horas_extra) || 0;
    const comisiones = Number(this.detalleSeleccionado.comisiones) || 0;
    const bonificaciones = Number(this.detalleSeleccionado.bonificaciones) || 0;
    const otrosIngresos = Number(this.detalleSeleccionado.otros_ingresos) || 0;
    const prestamos = Number(this.detalleSeleccionado.prestamos) || 0;
    const anticipos = Number(this.detalleSeleccionado.anticipos) || 0;
    const otrosDescuentos = Number(this.detalleSeleccionado.otros_descuentos) || 0;
    const descuentosJudiciales = Number(this.detalleSeleccionado.descuentos_judiciales) || 0;

    // Calcular usando las nuevas constantes 2025
    const calculos = PlanillaConstants.calcularDescuentosEmpleado(
      salarioDevengado,
      montoHorasExtra,
      comisiones,
      bonificaciones,
      otrosIngresos,
      this.planilla.tipo_planilla
    );

    // Asignar valores calculados
    this.detalleSeleccionado.total_ingresos = calculos.totalIngresos;
    this.detalleSeleccionado.isss_empleado = calculos.isssEmpleado;
    this.detalleSeleccionado.isss_patronal = calculos.isssPatronal;
    this.detalleSeleccionado.afp_empleado = calculos.afpEmpleado;
    this.detalleSeleccionado.afp_patronal = calculos.afpPatronal;
    this.detalleSeleccionado.renta = calculos.renta;

    // Calcular total de descuentos incluyendo otros descuentos
    const totalDescuentos = calculos.isssEmpleado + calculos.afpEmpleado + calculos.renta +
      prestamos + anticipos + otrosDescuentos + descuentosJudiciales;

    this.detalleSeleccionado.total_descuentos = Math.round(totalDescuentos * 100) / 100;

    // Calcular sueldo neto
    this.detalleSeleccionado.sueldo_neto = Math.round((calculos.totalIngresos - totalDescuentos) * 100) / 100;
  }

  compararCalculos(detalle: any) {
    if (!detalle) return;

    const salarioDevengado = Number(detalle.salario_devengado) || 0;
    const montoHorasExtra = Number(detalle.monto_horas_extra) || 0;
    const comisiones = Number(detalle.comisiones) || 0;
    const bonificaciones = Number(detalle.bonificaciones) || 0;
    const otrosIngresos = Number(detalle.otros_ingresos) || 0;

    const totalIngresos = salarioDevengado + montoHorasExtra + comisiones + bonificaciones + otrosIngresos;
    const isssEmpleado = PlanillaConstants.calcularDescuentoISSSEmpleado(totalIngresos, this.planilla.tipo_planilla);
    const afpEmpleado = PlanillaConstants.calcularDescuentoAFPEmpleado(totalIngresos);

    // Cálculo con método legacy
    const rentaLegacy = PlanillaConstants.calcularRenta(totalIngresos - isssEmpleado - afpEmpleado);

    // Cálculo con nuevas tablas
    const salarioGravado = PlanillaConstants.calcularSalarioGravado(
      totalIngresos, isssEmpleado, afpEmpleado, this.planilla.tipo_planilla
    );
    const rentaNueva = PlanillaConstants.calcularRetencionRenta(salarioGravado, this.planilla.tipo_planilla);

    const diferencia = rentaNueva - rentaLegacy;

    return {
      rentaLegacy,
      rentaNueva,
      diferencia,
      salarioGravado
    };
  }

  public calcularValorHora(): number {
    if (!this.detalleSeleccionado) return 0;
    const valorHoraNormal = this.getValorHoraNormalES();
    return Number((valorHoraNormal * 1.25).toFixed(2));
  }

  /**
   * Valor hora ordinaria (ES y fallback otros países).
   * Por obra: salario_base ya es el total del período de planilla — no dividir como salario mensual.
   */
  public getValorHoraNormalES(): number {
    if (!this.detalleSeleccionado || !this.planilla) return 0;
    const salarioBase = Number(this.detalleSeleccionado.salario_base) || 0;
    const esPorObra = (this.detalleSeleccionado.empleado?.tipo_contrato || 1) === 3;

    let salarioBaseAjustado = salarioBase;
    let diasReferencia = 30;

    if (esPorObra) {
      if (this.planilla.tipo_planilla === 'quincenal') {
        diasReferencia = 15;
      } else if (this.planilla.tipo_planilla === 'semanal') {
        diasReferencia = 7;
      } else {
        diasReferencia = 30;
      }
    } else {
      if (this.planilla.tipo_planilla === 'quincenal') {
        salarioBaseAjustado = salarioBase / 2;
        diasReferencia = 15;
      } else if (this.planilla.tipo_planilla === 'semanal') {
        salarioBaseAjustado = salarioBase / 4.33;
        diasReferencia = 7;
      }
    }
    const valorHoraNormal = salarioBaseAjustado / diasReferencia / 8;
    return Number(valorHoraNormal);
  }

  /** Valor por hora extra según tipo (El Salvador): diurna, nocturna, dia_descanso, dia_asueto. */
  public getValorHoraExtraPorTipo(tipo: 'diurna' | 'nocturna' | 'dia_descanso' | 'dia_asueto'): number {
    const valorHora = this.getValorHoraNormalES();
    const mult = this.MULTIPLICADORES_HORAS_EXTRA_ES[tipo] ?? 2;
    return Number((valorHora * mult).toFixed(2));
  }

  /** Monto subtotal de un tipo de hora extra (horas * valor por tipo). */
  public getMontoHorasExtraPorTipo(tipo: 'diurna' | 'nocturna' | 'dia_descanso' | 'dia_asueto'): number {
    if (!this.detalleSeleccionado?.detalle_horas_extra) return 0;
    const horas = Number((this.detalleSeleccionado.detalle_horas_extra as any)[tipo]) || 0;
    return Number((horas * this.getValorHoraExtraPorTipo(tipo)).toFixed(2));
  }


  public exportarVistaActual() {
    this.downloading = true;

    const filtrosExport = {
      id_planilla: this.planilla.id,
      vista: this.vistaActual, // 'empleados' o 'descuentos_patronales'
      ...this.filtros // Incluir todos los filtros actuales
    };

    this.apiService.export('planillas/detalles/exportar', filtrosExport).pipe(this.untilDestroyed()).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;

        // Generar nombre del archivo basado en la vista actual
        const tipoExport = this.vistaActual === 'descuentos_patronales' ? 'patronales' : 'empleados';
        a.download = `planilla_${this.planilla.codigo}_${tipoExport}.xlsx`;

        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.downloading = false;
        this.cdr.markForCheck();
      }
    );
  }

  public calcularDescuentos(): void {
    if (!this.detalleSeleccionado || !this.planilla) {
      return;
    }

    // Preparar datos del empleado
    const datosEmpleado = {
      salario_base: Number(this.detalleSeleccionado.salario_base) || 0,
      salario_devengado: Number(this.detalleSeleccionado.salario_devengado) || 0,
      dias_laborados: Number(this.detalleSeleccionado.dias_laborados) || 30,
      horas_extra: Number(this.detalleSeleccionado.horas_extra) || 0,
      monto_horas_extra: Number(this.detalleSeleccionado.monto_horas_extra) || 0,
      comisiones: Number(this.detalleSeleccionado.comisiones) || 0,
      bonificaciones: Number(this.detalleSeleccionado.bonificaciones) || 0,
      otros_ingresos: Number(this.detalleSeleccionado.otros_ingresos) || 0,
      prestamos: Number(this.detalleSeleccionado.prestamos) || 0,
      anticipos: Number(this.detalleSeleccionado.anticipos) || 0,
      otros_descuentos: Number(this.detalleSeleccionado.otros_descuentos) || 0,
      descuentos_judiciales: Number(this.detalleSeleccionado.descuentos_judiciales) || 0,
      tipo_contrato: this.detalleSeleccionado.empleado?.tipo_contrato
    };

    // Intentar usar sistema configurable
    this.configPlanillaService.probarCalculo(datosEmpleado).pipe(this.untilDestroyed()).subscribe({
      next: (resultado) => {
        this.aplicarResultadosConfigurables(resultado);


      },
      error: (error) => {
        console.warn('Fallback al sistema legacy:', error);
        this.calcularDescuentosLegacy();
      }
    });
  }

  private aplicarResultadosConfigurables(resultado: any): void {
    const resultados = resultado.resultados;

    // Obtener configuración de descuentos del empleado
    const configDescuentos = this.detalleSeleccionado.empleado?.configuracion_descuentos || {};
    const aplicarAfp = configDescuentos.aplicar_afp !== false; // Por defecto true si no existe
    const aplicarIsss = configDescuentos.aplicar_isss !== false; // Por defecto true si no existe

    // Aplicar valores calculados respetando la configuración del empleado
    this.detalleSeleccionado.isss_empleado = aplicarIsss ? this.roundMonetary(resultados.isss_empleado || 0) : 0;
    this.detalleSeleccionado.isss_patronal = aplicarIsss ? this.roundMonetary(resultados.isss_patronal || 0) : 0;
    this.detalleSeleccionado.afp_empleado = aplicarAfp ? this.roundMonetary(resultados.afp_empleado || 0) : 0;
    this.detalleSeleccionado.afp_patronal = aplicarAfp ? this.roundMonetary(resultados.afp_patronal || 0) : 0;
    this.detalleSeleccionado.renta = this.roundMonetary(resultados.renta || 0);

    // Aplicar totales
    if (resultados.totales) {
      this.detalleSeleccionado.total_ingresos = this.roundMonetary(resultados.totales.total_ingresos || 0);
      this.detalleSeleccionado.total_descuentos = this.roundMonetary(resultados.totales.total_deducciones || 0);
      this.detalleSeleccionado.sueldo_neto = this.roundMonetary(resultados.totales.sueldo_neto || 0);
    }

    // Recalcular totales de planilla
    this.calcularTotalesPlanilla();
    this.cdr.markForCheck();

  }

  private calcularDescuentosLegacy(): void {
    const salarioDevengado = Number(this.detalleSeleccionado.salario_devengado) || 0;
    const montoHorasExtra = Number(this.detalleSeleccionado.monto_horas_extra) || 0;
    const comisiones = Number(this.detalleSeleccionado.comisiones) || 0;
    const bonificaciones = Number(this.detalleSeleccionado.bonificaciones) || 0;
    const otrosIngresos = Number(this.detalleSeleccionado.otros_ingresos) || 0;
    const prestamos = Number(this.detalleSeleccionado.prestamos) || 0;
    const anticipos = Number(this.detalleSeleccionado.anticipos) || 0;
    const otrosDescuentos = Number(this.detalleSeleccionado.otros_descuentos) || 0;
    const descuentosJudiciales = Number(this.detalleSeleccionado.descuentos_judiciales) || 0;

    // Obtener configuración de descuentos del empleado
    const configDescuentos = this.detalleSeleccionado.empleado?.configuracion_descuentos || {};
    const aplicarAfp = configDescuentos.aplicar_afp !== false; // Por defecto true si no existe
    const aplicarIsss = configDescuentos.aplicar_isss !== false; // Por defecto true si no existe

    // Usar tu lógica actual (PlanillaConstants)
    const calculos = PlanillaConstants.calcularDescuentosEmpleado(
      salarioDevengado,
      montoHorasExtra,
      comisiones,
      bonificaciones,
      otrosIngresos,
      this.planilla.tipo_planilla
    );

    // Asignar valores calculados respetando la configuración del empleado
    this.detalleSeleccionado.total_ingresos = calculos.totalIngresos;
    this.detalleSeleccionado.isss_empleado = aplicarIsss ? calculos.isssEmpleado : 0;
    this.detalleSeleccionado.isss_patronal = aplicarIsss ? calculos.isssPatronal : 0;
    this.detalleSeleccionado.afp_empleado = aplicarAfp ? calculos.afpEmpleado : 0;
    this.detalleSeleccionado.afp_patronal = aplicarAfp ? calculos.afpPatronal : 0;
    this.detalleSeleccionado.renta = calculos.renta;

    // Calcular total de descuentos
    const totalDescuentos = calculos.isssEmpleado + calculos.afpEmpleado + calculos.renta +
      prestamos + anticipos + otrosDescuentos + descuentosJudiciales;

    this.detalleSeleccionado.total_descuentos = this.round(totalDescuentos);
    this.detalleSeleccionado.sueldo_neto = this.round(calculos.totalIngresos - totalDescuentos);

    this.calcularTotalesPlanilla();
    this.cdr.markForCheck();
  }


  public calcularTotalesPlanilla(): void {
    if (!this.detalles?.length || !this.planilla) {
      this.notValue = true;
      return;
    }

    // Resetear totales
    this.resetearTotalesPlanilla();

    const detallesActivos = this.detalles.filter(d => d.estado !== 0);
    if (!detallesActivos.length) {
      this.notValue = true;
      return;
    }

    this.notValue = false;

    // Calcular totales usando valores del backend/sistema configurable
    detallesActivos.forEach((detalle) => {
      this.planilla.total_salarios += Number(detalle.salario_devengado) || 0;
      this.planilla.bonificaciones_total += Number(detalle.bonificaciones) || 0;
      this.planilla.comisiones_total += Number(detalle.comisiones) || 0;
      this.planilla.otros_ingresos_total += Number(detalle.otros_ingresos) || 0;
      this.planilla.total_ingresos += Number(detalle.total_ingresos) || 0;
      this.planilla.total_iss += Number(detalle.isss_empleado) || 0;
      this.planilla.total_afp += Number(detalle.afp_empleado) || 0;
      this.planilla.total_isr += Number(detalle.renta) || 0;
      this.planilla.total_neto += Number(detalle.sueldo_neto) || 0;
    });

    // Redondear totales
    this.roundTotalesPlanilla();
    this.cdr.markForCheck();
  }

  public calcularTotalesPatronales() {
    let totalISSS = 0;
    let totalAFP = 0;

    for (const detalle of this.detalles) {
      totalISSS += Number(detalle.isss_patronal || 0);
      totalAFP += Number(detalle.afp_patronal || 0);
    }

    this.descuentosPatronales = {
      resumen: {
        total_isss_patronal: totalISSS,
        total_afp_patronal: totalAFP,
      },
    };
  }

  private resetearTotalesPlanilla(): void {
    this.planilla.total_salarios = 0;
    this.planilla.bonificaciones_total = 0;
    this.planilla.comisiones_total = 0;
    this.planilla.otros_ingresos_total = 0;
    this.planilla.total_ingresos = 0;
    this.planilla.total_iss = 0;
    this.planilla.total_afp = 0;
    this.planilla.total_isr = 0;
    this.planilla.total_neto = 0;
  }

  private roundTotalesPlanilla(): void {
    Object.keys(this.planilla).forEach(key => {
      if (typeof this.planilla[key] === 'number') {
        this.planilla[key] = this.round(this.planilla[key]);
      }
    });
  }

  public compararSistemas(detalle: any): void {
    if (!detalle) return;

    const salarioDevengado = Number(detalle.salario_devengado) || 0;

    // Sistema legacy
    const calculosLegacy = PlanillaConstants.calcularDescuentosEmpleado(
      salarioDevengado, 0, 0, 0, 0, this.planilla.tipo_planilla
    );

    // Datos para sistema configurable
    const datosEmpleado = {
      salario_base: Number(detalle.salario_base) || 0,
      salario_devengado: salarioDevengado,
      tipo_planilla: this.planilla.tipo_planilla
    };

    this.configPlanillaService.probarCalculo(datosEmpleado).pipe(this.untilDestroyed()).subscribe({
      next: (resultado) => {
        // console.table({
        //   'Concepto': ['ISSS Empleado', 'AFP Empleado', 'Renta', 'Sueldo Neto'],
        //   'Sistema Legacy': [
        //     calculosLegacy.isssEmpleado,
        //     calculosLegacy.afpEmpleado,
        //     calculosLegacy.renta,
        //     calculosLegacy.totalIngresos - calculosLegacy.isssEmpleado - calculosLegacy.afpEmpleado - calculosLegacy.renta
        //   ],
        //   'Sistema Configurable': [
        //     resultado.resultados.isss_empleado,
        //     resultado.resultados.afp_empleado,
        //     resultado.resultados.renta,
        //     resultado.resultados.totales.sueldo_neto
        //   ]
        // });
      },
      error: (error) => {
        console.error('Error comparando sistemas:', error);
      }
    });
  }

  public recalcularDetalle(detalle: any): void {
    const detalleAnterior = this.detalleSeleccionado;
    this.detalleSeleccionado = detalle;
    this.calcularDescuentos();
    this.detalleSeleccionado = detalleAnterior;
  }

  public validarConfiguracionEmpresa(): void {
    this.configPlanillaService.obtenerConfiguracion().pipe(this.untilDestroyed()).subscribe({
      next: (response) => {
        if (response.configuracion?.configuracion?.conceptos) {
          const totalConceptos = Object.keys(response.configuracion.configuracion.conceptos).length;
        }
      },
      error: (error) => {
        console.warn('⚠️ Empresa sin configuración personalizada, usando sistema legacy');
      }
    });
  }

}
