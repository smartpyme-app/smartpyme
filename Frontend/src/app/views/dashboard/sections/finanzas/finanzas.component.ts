import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { DashboardFiltrosCatalogoService } from '../../services/dashboard-filtros-catalogo.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { PlGerencialComponent } from './pl-gerencial/pl-gerencial.component';
import { PlGerencialQuery } from '../../services/finanzas-pl-data.service';
import { RentabilidadComponent } from './rentabilidad/rentabilidad.component';
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

  readonly plPeriodoOpciones: PlSegmentOption<PlPeriodoId>[] = [
    { id: 'mes', label: 'Mes' },
    { id: 'anio_completo', label: 'Año completo' },
  ];
  readonly plBaseOpciones: PlSegmentOption<PlBaseId>[] = [
    { id: 'gerencial', label: 'Gerencial (sin IVA)' },
    { id: 'caja', label: 'Caja (con IVA)' },
  ];

  /** Mes por defecto: alinea con dashboard Ventas (un mes, no YTD). */
  plPeriodo: PlPeriodoId = 'mes';
  plBase: PlBaseId = 'gerencial';
  plSucursalId = 'consolidado';
  plComparacion: PlComparacionId = 'mes_anterior';

  plComparacionOpciones: PlSegmentOption<PlComparacionId>[] = [];
  plSucursalOpciones: PlSegmentOption[] = [
    { id: 'consolidado', label: 'Consolidado' },
  ];
  plQuery: PlGerencialQuery = this.buildPlQuery();

  ngOnInit(): void {
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

  seleccionarPlPeriodo(id: PlPeriodoId): void {
    this.plPeriodo = id;
    this.refreshPlQuery();
  }

  seleccionarPlBase(id: PlBaseId): void {
    this.plBase = id;
    this.refreshPlQuery();
  }

  seleccionarPlSucursal(event: Event): void {
    this.plSucursalId = (event.target as HTMLSelectElement).value;
    this.refreshPlQuery();
  }

  seleccionarPlComparacion(id: PlComparacionId): void {
    this.plComparacion = id;
    this.refreshPlQuery();
  }

  private refreshPlQuery(): void {
    this.plQuery = this.buildPlQuery();
  }

  private buildPlQuery(): PlGerencialQuery {
    // Año completo llega hasta el mes en curso (igual que Ventas con mes vacío).
    // Mes suelto sigue en el mes cerrado anterior.
    const mesRef = this.plPeriodo === 'anio_completo' ? new Date() : this.plMesReferencia;
    return {
      periodo: this.plPeriodo,
      base: this.plBase,
      sucursalId: this.plSucursalId,
      comparacion: this.plComparacion,
      anio: mesRef.getFullYear(),
      mes: mesRef.getMonth() + 1,
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
    if (this.plPeriodo === 'anio_completo') {
      return `año ${new Date().getFullYear()}`;
    }
    const ref = new Date();
    const mes = new Date(ref.getFullYear(), ref.getMonth() - 1, 1);
    return `${this.mesNombre(mes)} ${mes.getFullYear()}`;
  }

  get plComparacionTitulo(): string {
    const opt = this.plComparacionOpciones.find((o) => o.id === this.plComparacion);
    const raw = opt?.label ?? '';
    return raw.replace(/^vs\.\s*/i, '').toUpperCase();
  }

  get plAnioAcumulado(): number {
    return this.plMesReferencia.getFullYear();
  }

  /** Mes de referencia del P&L (mes calendario anterior, alineado al título de periodo). */
  private get plMesReferencia(): Date {
    const ref = new Date();
    return new Date(ref.getFullYear(), ref.getMonth() - 1, 1);
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
