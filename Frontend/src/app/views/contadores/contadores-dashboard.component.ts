import { Component, DestroyRef, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { forkJoin } from 'rxjs';
import {
  ContadorEmpresaPortafolio,
  ContadorMetricasEmpresa,
  ContadoresPortalService,
  EstadoCarteraContador,
} from '@services/contadores-portal.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import {
  mesAnioDesdeQuery,
  opcionesPeriodo,
  periodoCierrePorDefecto,
  textoVencimientoIva,
} from './contadores-periodo.util';

interface MetricasEmpresa {
  registradas: number;
  conPartida: number;
  avance: number;
  estado: EstadoCarteraContador;
  ivaPagar: number;
}

type OrdenCartera = 'prioridad' | 'nombre' | 'pendientes';

@Component({
  selector: 'app-contadores-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './contadores-dashboard.component.html',
  styleUrls: ['./contadores-dashboard.component.css'],
})
export class ContadoresDashboardComponent implements OnInit {
  empresas: ContadorEmpresaPortafolio[] = [];
  loading = true;
  error = '';
  usuario: { name?: string; empresa?: { nombre?: string } } | null = null;

  periodoLabel = '';
  periodoMes = periodoCierrePorDefecto().mes;
  periodoAnio = periodoCierrePorDefecto().anio;
  periodoEnCierre = false;
  subtituloFiscal = '';
  readonly opcionesPeriodo = opcionesPeriodo(24);

  buscador = '';
  /** ponytail: orden fijo por prioridad; el mockup ya no expone selector. */
  private readonly orden: OrdenCartera = 'prioridad';
  abriendoEmpresaId: number | null = null;
  readonly logoFallo = new Set<number>();

  private metricasPorEmpresa = new Map<number, MetricasEmpresa>();

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private contadoresPortal: ContadoresPortalService,
    private api: ApiService,
    private router: Router,
    private route: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    this.usuario = this.api.auth_user();
    let prevMes = -1;
    let prevAnio = -1;

    this.route.queryParamMap.pipe(this.untilDestroyed()).subscribe((params) => {
      const { mes, anio } = mesAnioDesdeQuery(params.get('mes'), params.get('anio'));

      if (mes !== prevMes || anio !== prevAnio) {
        prevMes = mes;
        prevAnio = anio;
        this.periodoMes = mes;
        this.periodoAnio = anio;
        this.cargarDatos();
      }
    });
  }

  get periodoKey(): string {
    return `${this.periodoMes}-${this.periodoAnio}`;
  }

  onPeriodoKeyChange(key: string): void {
    const [m, a] = key.split('-').map(Number);
    if (!Number.isFinite(m) || !Number.isFinite(a)) {
      return;
    }
    this.periodoMes = m;
    this.periodoAnio = a;
    this.onPeriodoChange();
  }

  get despachoNombre(): string {
    return this.usuario?.empresa?.nombre ?? 'Despacho contable';
  }

  get totalEmpresas(): number {
    return this.empresas.length;
  }

  get empresasTabla(): ContadorEmpresaPortafolio[] {
    const q = this.buscador.trim().toLowerCase();
    let list = this.empresas.filter((e) => {
      if (!q) {
        return true;
      }
      return e.nombre.toLowerCase().includes(q) || (e.giro ?? '').toLowerCase().includes(q);
    });

    const peso: Record<EstadoCarteraContador, number> = {
      atrasada: 0,
      proceso: 1,
      casi: 2,
      lista: 3,
    };

    list = [...list].sort((a, b) => {
      if (this.orden === 'nombre') {
        return a.nombre.localeCompare(b.nombre, 'es');
      }
      if (this.orden === 'pendientes') {
        return this.pendientes(b) - this.pendientes(a);
      }
      const ea = peso[this.metricas(a).estado];
      const eb = peso[this.metricas(b).estado];
      if (ea !== eb) {
        return ea - eb;
      }
      return this.pendientes(b) - this.pendientes(a);
    });

    return list;
  }

  get totalPorContabilizar(): number {
    return this.empresas.reduce((sum, e) => sum + this.pendientes(e), 0);
  }

  get listasParaDeclarar(): number {
    return this.empresas.filter((e) => this.metricas(e).estado === 'lista').length;
  }

  get ivaCartera(): number {
    return this.empresas.reduce((sum, e) => sum + this.metricas(e).ivaPagar, 0);
  }

  metricas(empresa: ContadorEmpresaPortafolio): MetricasEmpresa {
    return (
      this.metricasPorEmpresa.get(empresa.id) ?? {
        registradas: 0,
        conPartida: 0,
        avance: 100,
        estado: 'lista',
        ivaPagar: 0,
      }
    );
  }

  pendientes(empresa: ContadorEmpresaPortafolio): number {
    const m = this.metricas(empresa);
    return Math.max(0, m.registradas - m.conPartida);
  }

  etiquetaEstado(estado: EstadoCarteraContador): string {
    const map: Record<EstadoCarteraContador, string> = {
      lista: 'Lista para declarar',
      casi: 'Casi lista',
      proceso: 'En proceso',
      atrasada: 'Atrasada',
    };
    return map[estado];
  }

  onPeriodoChange(): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { mes: this.periodoMes, anio: this.periodoAnio },
      queryParamsHandling: 'merge',
    });
  }

  irCumplimiento(empresa: ContadorEmpresaPortafolio): void {
    if (this.abriendoEmpresaId != null) {
      return;
    }
    this.abriendoEmpresaId = empresa.id;
    this.contadoresPortal.registrarAccesoReciente(empresa);
    this.contadoresPortal
      .cambiarEmpresaActiva(empresa.id)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.abriendoEmpresaId = null;
          this.router.navigate(['/despacho/cumplimiento'], {
            queryParams: {
              empresa: empresa.id,
              mes: this.periodoMes,
              anio: this.periodoAnio,
            },
          });
        },
        error: () => {
          this.abriendoEmpresaId = null;
        },
      });
  }

  cerrarSesion(): void {
    this.api.logout();
    this.router.navigate(['/login']);
  }

  logoUrl(logo: string | null | undefined): string | null {
    if (!logo?.trim()) {
      return null;
    }
    const path = logo.trim();
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }
    return `${this.api.baseUrl}/img/${path}`;
  }

  mostrarLogoEmpresa(empresa: ContadorEmpresaPortafolio): boolean {
    return !!this.logoUrl(empresa.logo) && !this.logoFallo.has(empresa.id);
  }

  onLogoError(idEmpresa: number): void {
    this.logoFallo.add(idEmpresa);
  }

  iniciales(nombre: string): string {
    const parts = (nombre || '?').trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return (nombre || '?').trim().slice(0, 2).toUpperCase();
  }

  colorMarca(nombre: string): string {
    const palette = ['#1775e5', '#0d9488', '#6366f1', '#ea580c', '#7c3aed', '#0891b2'];
    let h = 0;
    for (let i = 0; i < nombre.length; i++) {
      h = (h + nombre.charCodeAt(i)) % palette.length;
    }
    return palette[h];
  }

  formatoMoneda(monto: number): string {
    return new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' }).format(monto);
  }

  etiquetaPermiso(permisos: string[]): string {
    const set = new Set((permisos ?? []).map((p) => p.toLowerCase()));
    if (set.has('aprobar')) {
      return 'Aprobar partidas';
    }
    if (set.has('registrar')) {
      return 'Registrar';
    }
    if (set.has('ver')) {
      return 'Solo ver';
    }
    return permisos?.length ? permisos.join(', ') : 'Ver';
  }

  private cargarDatos(): void {
    this.loading = true;
    this.error = '';

    forkJoin({
      empresas: this.contadoresPortal.listarEmpresas(),
      cartera: this.contadoresPortal.carteraMetricas({
        mes: this.periodoMes,
        anio: this.periodoAnio,
      }),
    })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: ({ empresas, cartera }) => {
          this.empresas = empresas?.empresas ?? [];
          this.periodoLabel = cartera?.periodo?.label ?? '';
          if (cartera?.periodo?.mes) {
            this.periodoMes = cartera.periodo.mes;
          }
          if (cartera?.periodo?.anio) {
            this.periodoAnio = cartera.periodo.anio;
          }
          this.periodoEnCierre = !!cartera?.periodo?.en_cierre;
          this.subtituloFiscal = textoVencimientoIva(this.periodoMes, this.periodoAnio);
          this.metricasPorEmpresa = this.mapearMetricas(cartera?.metricas ?? {});
          this.loading = false;
        },
        error: (err) => {
          this.loading = false;
          const raw = err?.error?.error;
          this.error = Array.isArray(raw) ? raw.join(', ') : (raw ?? 'No se pudo cargar la cartera.');
        },
      });
  }

  private mapearMetricas(raw: Record<string, ContadorMetricasEmpresa>): Map<number, MetricasEmpresa> {
    const map = new Map<number, MetricasEmpresa>();
    for (const [idStr, m] of Object.entries(raw)) {
      const id = Number(idStr);
      if (!Number.isFinite(id) || !m) {
        continue;
      }
      map.set(id, {
        registradas: m.registradas ?? 0,
        conPartida: m.con_partida ?? 0,
        avance: m.avance ?? 0,
        estado: m.estado ?? 'proceso',
        ivaPagar: m.iva_pagar ?? 0,
      });
    }
    return map;
  }

}
