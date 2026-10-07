import { Component, DestroyRef, ElementRef, inject, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { forkJoin } from 'rxjs';
import {
  ContadoresPortalService,
  ContadorEmpresaPortafolio,
  ContadorEmpresaReciente,
} from '@services/contadores-portal.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { periodoCierrePorDefecto } from './contadores-periodo.util';
import { CONTADOR_PORTAL_STORAGE_KEY } from '../../auth/login/login-host';

type FiltroCartera = 'todas' | 'pendiente' | 'al_dia';

@Component({
  selector: 'app-contadores-portafolio',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './contadores-portafolio.component.html',
  styleUrls: ['./contadores-portafolio.component.css'],
})
export class ContadoresPortafolioComponent implements OnInit {
  @ViewChild('seccionTodas') seccionTodas?: ElementRef<HTMLElement>;

  empresas: ContadorEmpresaPortafolio[] = [];
  recientes: ContadorEmpresaReciente[] = [];
  usuario: { name?: string; email?: string; empresa?: { nombre?: string } } | null = null;

  loading = true;
  error = '';
  buscador = '';
  filtro: FiltroCartera = 'todas';
  /** IDs cuyo logo remoto falló → mostramos iniciales. */
  readonly logoFallo = new Set<number>();

  private readonly pendientesPorEmpresa = new Map<number, number>();

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private contadoresPortal: ContadoresPortalService,
    private api: ApiService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.usuario = this.api.auth_user();
    this.recientes = this.contadoresPortal.leerRecientes();

    if (this.api.esPortalContador()) {
      this.contadoresPortal.restablecerContextoDespacho().pipe(this.untilDestroyed()).subscribe();
    }

    forkJoin({
      empresas: this.contadoresPortal.listarEmpresas(),
      cartera: this.contadoresPortal.carteraMetricas(),
    })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: ({ empresas, cartera }) => {
          this.empresas = empresas?.empresas ?? [];
          if (this.empresas.length) {
            localStorage.setItem(CONTADOR_PORTAL_STORAGE_KEY, '1');
          }
          this.pendientesPorEmpresa.clear();
          for (const [idStr, m] of Object.entries(cartera?.metricas ?? {})) {
            const id = Number(idStr);
            if (Number.isFinite(id) && m) {
              this.pendientesPorEmpresa.set(id, m.por_contabilizar ?? 0);
            }
          }
          this.loading = false;
        },
        error: (err) => {
          this.loading = false;
          const raw = err?.error?.error;
          this.error = Array.isArray(raw) ? raw.join(', ') : (raw ?? 'No se pudieron cargar las empresas asignadas.');
        },
      });
  }

  get despachoNombre(): string {
    return this.usuario?.empresa?.nombre ?? 'Despacho contable';
  }

  get empresasVisibles(): ContadorEmpresaPortafolio[] {
    const q = this.buscador.trim().toLowerCase();
    return this.empresas.filter((e) => {
      if (q && !e.nombre.toLowerCase().includes(q) && !(e.giro ?? '').toLowerCase().includes(q)) {
        return false;
      }
      if (this.filtro === 'pendiente') {
        return (this.pendientesPorEmpresa.get(e.id) ?? 0) > 0;
      }
      if (this.filtro === 'al_dia') {
        return (this.pendientesPorEmpresa.get(e.id) ?? 0) === 0;
      }
      return true;
    });
  }

  get totalAsignadas(): number {
    return this.empresas.length;
  }

  get countPendiente(): number {
    return this.empresas.filter((e) => (this.pendientesPorEmpresa.get(e.id) ?? 0) > 0).length;
  }

  get countAlDia(): number {
    return this.empresas.filter((e) => (this.pendientesPorEmpresa.get(e.id) ?? 0) === 0).length;
  }

  recientesEnPortafolio(): ContadorEmpresaReciente[] {
    const ids = new Set(this.empresas.map((e) => e.id));
    return this.recientes.filter((r) => ids.has(r.id));
  }

  recientesConEmpresa(): { rec: ContadorEmpresaReciente; empresa: ContadorEmpresaPortafolio }[] {
    return this.recientesEnPortafolio()
      .map((rec) => {
        const empresa = this.empresas.find((e) => e.id === rec.id);
        return empresa ? { rec, empresa } : null;
      })
      .filter((x): x is { rec: ContadorEmpresaReciente; empresa: ContadorEmpresaPortafolio } => x != null);
  }

  elegirEmpresa(empresa: ContadorEmpresaPortafolio): void {
    this.contadoresPortal
      .cambiarEmpresaActiva(empresa.id)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.contadoresPortal.registrarAccesoReciente(empresa);
          this.recientes = this.contadoresPortal.leerRecientes();
          this.router.navigate(['/']);
        },
      });
  }

  irACarteraCompleta(): void {
    this.router.navigate(['/despacho/cartera'], { queryParams: this.queryPeriodoCartera() });
  }

  private queryPeriodoCartera(empresaId?: number): Record<string, number> {
    const { mes, anio } = periodoCierrePorDefecto();
    const q: Record<string, number> = { mes, anio };
    if (empresaId != null) {
      q['empresa'] = empresaId;
    }
    return q;
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

  pendientesCount(empresa: ContadorEmpresaPortafolio): number {
    return this.pendientesPorEmpresa.get(empresa.id) ?? 0;
  }

  estaAlDia(empresa: ContadorEmpresaPortafolio): boolean {
    return this.pendientesCount(empresa) === 0;
  }

  ultimoAccesoTexto(empresa: ContadorEmpresaPortafolio): string {
    const rec = this.recientes.find((r) => r.id === empresa.id);
    if (!rec) {
      return 'Sin acceso reciente';
    }
    return this.formatearRelativo(rec.accedidoEn);
  }

  ultimoAccesoReciente(rec: ContadorEmpresaReciente): string {
    return this.formatearRelativo(rec.accedidoEn);
  }

  private formatearRelativo(ts: number): string {
    const diff = Date.now() - ts;
    const min = Math.floor(diff / 60000);
    if (min < 1) {
      return 'ahora';
    }
    if (min < 60) {
      return `hace ${min} min`;
    }
    const horas = Math.floor(min / 60);
    if (horas < 24) {
      const d = new Date(ts);
      return `hoy, ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
    }
    const dias = Math.floor(horas / 24);
    if (dias === 1) {
      return 'ayer';
    }
    if (dias < 7) {
      return `hace ${dias} días`;
    }
    return `hace ${Math.floor(dias / 7)} semana${Math.floor(dias / 7) > 1 ? 's' : ''}`;
  }
}
