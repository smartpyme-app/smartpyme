import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { DashboardFiltrosCatalogoService } from '../../services/dashboard-filtros-catalogo.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { PlGerencialComponent } from './pl-gerencial/pl-gerencial.component';
import { PlGerencialQuery } from '../../services/finanzas-pl-data.service';
import { RentabilidadComponent } from './rentabilidad/rentabilidad.component';
import { RentabilidadHorizonteId } from './rentabilidad/rentabilidad.model';
import { FlujoCajaComponent } from './flujo-caja/flujo-caja.component';
import { CierreMesComponent } from './cierre-mes/cierre-mes.component';
import { ModeladoComponent } from './modelado/modelado.component';
import { AlertasResumenesComponent } from './alertas-resumenes/alertas-resumenes.component';

export type FinanzasSuiteTabId =
  | 'pl'
  | 'rentabilidad'
  | 'flujo'
  | 'cierre'
  | 'modelado'
  | 'alertas';

export interface FinanzasSuiteTab {
  id: FinanzasSuiteTabId;
  label: string;
}

export type PlPeriodoId = 'mes' | 'anio_completo';
export type PlBaseId = 'gerencial' | 'caja';
export type PlComparacionId = 'mes_anterior' | 'mes_anterior_2' | 'presupuesto';
export interface PlSegmentOption<T extends string = string> {
  id: T;
  label: string;
}

@Component({
  selector: 'app-finanzas',
  templateUrl: './finanzas.component.html',
  styleUrls: ['./finanzas.component.css'],
  standalone: true,
  imports: [
    CommonModule,
    PlGerencialComponent,
    RentabilidadComponent,
    FlujoCajaComponent,
    CierreMesComponent,
    ModeladoComponent,
    AlertasResumenesComponent,
  ],
})
export class FinanzasComponent implements OnInit {
  private readonly api = inject(ApiService);
  private readonly catalogo = inject(DashboardFiltrosCatalogoService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly untilDestroyed = subscriptionHelper(this.destroyRef);

  readonly tabs: FinanzasSuiteTab[] = [
    { id: 'pl', label: 'P&L Gerencial' },
    { id: 'rentabilidad', label: 'Rentabilidad' },
    { id: 'flujo', label: 'Flujo de Caja' },
    { id: 'cierre', label: 'Cierre de Mes' },
    { id: 'modelado', label: 'Modelado' },
    { id: 'alertas', label: 'Alertas y resúmenes' },
  ];

  tabActiva: FinanzasSuiteTabId = 'pl';
  totalSucursales = 0;

  readonly plBaseOpciones: PlSegmentOption<PlBaseId>[] = [
    { id: 'gerencial', label: 'Gerencial (sin IVA)' },
    { id: 'caja', label: 'Caja (con IVA)' },
  ];

  /** Periodo P&L se sincroniza con suiteMes (0 = anio_completo, 1..12 = mes). */
  plPeriodo: PlPeriodoId = 'anio_completo';
  plBase: PlBaseId = 'gerencial';
  suiteSucursalId = 'consolidado';
  suiteMes = 0;
  suiteAnio = 0;
  readonly suiteMesOpciones: { value: number; label: string }[] = [
    { value: 0, label: 'Todo el año' },
    { value: 1, label: 'Enero' },
    { value: 2, label: 'Febrero' },
    { value: 3, label: 'Marzo' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Mayo' },
    { value: 6, label: 'Junio' },
    { value: 7, label: 'Julio' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Septiembre' },
    { value: 10, label: 'Octubre' },
    { value: 11, label: 'Noviembre' },
    { value: 12, label: 'Diciembre' },
  ];
  suiteAnioOpciones: number[] = [];
  rentHorizonte: RentabilidadHorizonteId = 'mes';
  readonly rentHorizonteOpciones: { id: RentabilidadHorizonteId; label: string }[] = [
    { id: 'mes', label: 'Mes' },
    { id: 'trimestre', label: 'Trimestre' },
    { id: 'anio', label: 'Año' },
  ];
  plComparacion: PlComparacionId = 'mes_anterior';

  plComparacionOpciones: PlSegmentOption<PlComparacionId>[] = [];
  plSucursalOpciones: PlSegmentOption[] = [
    { id: 'consolidado', label: 'Consolidado' },
  ];
  plQuery: PlGerencialQuery = {
    periodo: 'anio_completo',
    base: 'gerencial',
    sucursalId: 'consolidado',
    comparacion: 'mes_anterior',
    anio: new Date().getFullYear(),
    mes: 0,
  };

  ngOnInit(): void {
    const ref = this.mesReferenciaInicial();
    this.suiteMes = 0;
    this.plPeriodo = 'anio_completo';
    this.suiteAnio = ref.getFullYear();
    this.suiteAnioOpciones = this.aniosDisponibles();
    this.refreshPlQuery();
    this.plComparacionOpciones = this.buildComparacionOpciones();
    this.catalogo
      .sucursalesParaFiltro()
      .pipe(this.untilDestroyed())
      .subscribe((list) => {
        this.totalSucursales = list.length;
        this.plSucursalOpciones = [
          { id: 'consolidado', label: 'Consolidado' },
          ...list.map((s) => ({ id: s.id, label: s.nombre })),
        ];
      });
  }

  seleccionarTab(id: FinanzasSuiteTabId): void {
    this.tabActiva = id;
  }

  seleccionarPlBase(id: PlBaseId): void {
    this.plBase = id;
    this.refreshPlQuery();
  }

  seleccionarSuiteSucursal(event: Event): void {
    this.suiteSucursalId = (event.target as HTMLSelectElement).value;
    this.refreshPlQuery();
  }

  seleccionarSuiteMes(event: Event): void {
    this.suiteMes = Number((event.target as HTMLSelectElement).value);
    this.plPeriodo = this.suiteMes === 0 ? 'anio_completo' : 'mes';
    this.refreshPlQuery();
  }

  seleccionarSuiteAnio(event: Event): void {
    this.suiteAnio = Number((event.target as HTMLSelectElement).value);
    this.refreshPlQuery();
  }

  seleccionarRentHorizonte(event: Event): void {
    const id = (event.target as HTMLSelectElement).value as RentabilidadHorizonteId;
    if (id !== 'mes' && id !== 'trimestre' && id !== 'anio') {
      return;
    }
    if (id === this.rentHorizonte) {
      return;
    }
    this.rentHorizonte = id;
  }

  seleccionarPlComparacion(id: PlComparacionId): void {
    this.plComparacion = id;
    this.refreshPlQuery();
  }

  private refreshPlQuery(): void {
    this.plQuery = this.buildPlQuery();
  }

  private buildPlQuery(): PlGerencialQuery {
    return {
      periodo: this.plPeriodo,
      base: this.plBase,
      sucursalId: this.suiteSucursalId,
      comparacion: this.plComparacion,
      anio: this.suiteAnio,
      mes: this.suiteMes,
    };
  }

  private buildComparacionOpciones(): PlSegmentOption<PlComparacionId>[] {
    const hoy = new Date();
    const mesAnterior = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
    const dosMesesAtras = new Date(hoy.getFullYear(), hoy.getMonth() - 2, 1);
    return [
      {
        id: 'mes_anterior',
        label: `vs. ${this.mesAbr(mesAnterior)} ${mesAnterior.getFullYear()}`,
      },
      {
        id: 'mes_anterior_2',
        label: `vs. ${this.mesAbr(dosMesesAtras)} ${dosMesesAtras.getFullYear()}`,
      },
      { id: 'presupuesto', label: 'vs. presupuesto' },
    ];
  }

  private mesNombre(fecha: Date): string {
    const nombres = [
      'enero',
      'febrero',
      'marzo',
      'abril',
      'mayo',
      'junio',
      'julio',
      'agosto',
      'septiembre',
      'octubre',
      'noviembre',
      'diciembre',
    ];
    return nombres[fecha.getMonth()] ?? '—';
  }

  private mesAbr(fecha: Date): string {
    const abr = [
      'ene',
      'feb',
      'mar',
      'abr',
      'may',
      'jun',
      'jul',
      'ago',
      'sep',
      'oct',
      'nov',
      'dic',
    ];
    return abr[fecha.getMonth()] ?? '—';
  }

  get plPeriodoTitulo(): string {
    if (this.plPeriodo === 'anio_completo' || this.suiteMes === 0) {
      return `año ${this.suiteAnio}`;
    }
    return `${this.mesNombre(new Date(this.suiteAnio, this.suiteMes - 1, 1))} ${this.suiteAnio}`;
  }

  get suiteMesParaRentabilidad(): number {
    return this.suiteMes || this.mesReferenciaInicial().getMonth() + 1;
  }

  get plComparacionTitulo(): string {
    const opt = this.plComparacionOpciones.find((o) => o.id === this.plComparacion);
    const raw = opt?.label ?? '';
    return raw.replace(/^vs\.\s*/i, '').toUpperCase();
  }

  get plAnioAcumulado(): number {
    return this.suiteAnio;
  }

  /** Mes calendario anterior al día de hoy (referencia inicial del P&L). */
  private mesReferenciaInicial(): Date {
    const ref = new Date();
    return new Date(ref.getFullYear(), ref.getMonth() - 1, 1);
  }

  private aniosDisponibles(): number[] {
    const hasta = new Date().getFullYear();
    const desde = 2024;
    const out: number[] = [];
    for (let a = hasta; a >= desde; a--) {
      out.push(a);
    }
    return out;
  }

  get subtituloContexto(): string {
    const empresa = this.api.auth_user()?.empresa;
    const nombre = String(empresa?.nombre ?? '').trim() || 'Empresa';
    const pais = String(empresa?.pais ?? '').trim();
    const partes: string[] = [nombre];

    if (this.totalSucursales > 0) {
      partes.push(
        this.totalSucursales === 1
          ? '1 sucursal'
          : `${this.totalSucursales} sucursales`,
      );
    }
    if (pais) {
      partes.push(pais);
    }
    partes.push(`hoy: ${this.formatHoy()}`);
    return partes.join(' · ');
  }

  private formatHoy(): string {
    const raw = new Intl.DateTimeFormat('es', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(new Date());
    return raw.charAt(0).toLowerCase() + raw.slice(1);
  }
}
