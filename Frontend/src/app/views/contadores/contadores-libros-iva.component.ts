import { ChangeDetectorRef, Component, DestroyRef, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { forkJoin } from 'rxjs';
import { ApiService } from '@services/api.service';
import {
  ContadorEmpresaPortafolio,
  ContadorLibrosIvaInforme,
  ContadorLibrosIvaResponse,
  ContadoresPortalService,
} from '@services/contadores-portal.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { mesAnioDesdeQuery, opcionesPeriodo, periodoCierrePorDefecto } from './contadores-periodo.util';

@Component({
  selector: 'app-contadores-libros-iva',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './contadores-libros-iva.component.html',
  styleUrls: ['./contadores-dashboard.component.css', './contadores-libros-iva.component.css'],
})
export class ContadoresLibrosIvaComponent implements OnInit {
  usuario: { name?: string; empresa?: { nombre?: string } } | null = null;
  empresas: ContadorEmpresaPortafolio[] = [];
  vista: ContadorLibrosIvaResponse | null = null;
  informe: ContadorLibrosIvaInforme | null = null;

  idEmpresa: number | null = null;
  informeClave = 'compras_libro';
  periodoMes = periodoCierrePorDefecto().mes;
  periodoAnio = periodoCierrePorDefecto().anio;
  readonly opcionesPeriodo = opcionesPeriodo(24);

  loading = true;
  loadingInforme = false;
  descargando = false;
  error = '';

  private destroyRef = inject(DestroyRef);
  private cdr = inject(ChangeDetectorRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private api: ApiService,
    private contadoresPortal: ContadoresPortalService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.usuario = this.api.auth_user();

    this.route.queryParamMap.pipe(this.untilDestroyed()).subscribe((params) => {
      const { mes, anio } = mesAnioDesdeQuery(params.get('mes'), params.get('anio'));
      const id = Number(params.get('empresa'));
      const idEmpresa = Number.isFinite(id) ? id : null;
      const informeParam = params.get('informe');
      const informeClave = informeParam || this.informeClave;

      const contextoCambio =
        idEmpresa !== this.idEmpresa || mes !== this.periodoMes || anio !== this.periodoAnio;
      const soloInforme =
        !contextoCambio && this.vista != null && informeClave !== this.informeClave;

      this.periodoMes = mes;
      this.periodoAnio = anio;
      this.idEmpresa = idEmpresa;
      this.informeClave = informeClave;

      if (soloInforme) {
        this.cargarInforme();
        return;
      }

      if (contextoCambio || !this.vista) {
        this.cargar();
      }
    });
  }

  get despachoNombre(): string {
    return this.usuario?.empresa?.nombre ?? 'Despacho contable';
  }

  get periodoKey(): string {
    return `${this.periodoMes}-${this.periodoAnio}`;
  }

  get empresaSeleccionada(): ContadorEmpresaPortafolio | null {
    if (!this.idEmpresa) {
      return null;
    }
    return this.empresas.find((e) => e.id === this.idEmpresa) ?? null;
  }

  get grupoInformeActivo(): string {
    if (!this.vista) {
      return '';
    }
    for (const g of this.vista.grupos) {
      if (g.informes.some((i) => i.clave === this.informeClave)) {
        return g.titulo;
      }
    }
    return '';
  }

  onEmpresaChange(id: number): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { empresa: id, mes: this.periodoMes, anio: this.periodoAnio, informe: this.informeClave },
      queryParamsHandling: 'merge',
    });
  }

  onPeriodoKeyChange(key: string): void {
    const [m, a] = key.split('-').map(Number);
    if (!Number.isFinite(m) || !Number.isFinite(a)) {
      return;
    }
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { mes: m, anio: a },
      queryParamsHandling: 'merge',
    });
  }

  seleccionarInforme(clave: string): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { informe: clave },
      queryParamsHandling: 'merge',
    });
  }

  volverCartera(): void {
    this.router.navigate(['/contadores/cartera'], {
      queryParams: { empresa: this.idEmpresa, mes: this.periodoMes, anio: this.periodoAnio },
    });
  }

  cerrarSesion(): void {
    this.api.logout();
    this.router.navigate(['/login']);
  }

  formatoMoneda(monto: number | null | undefined): string {
    if (monto == null) {
      return '—';
    }
    return new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' }).format(monto);
  }

  valorCelda(fila: Record<string, unknown>, key: string, numeric?: boolean): string {
    const v = fila[key];
    if (numeric && typeof v === 'number') {
      return this.formatoMoneda(v);
    }
    return v == null || v === '' ? '—' : String(v);
  }

  descargar(formato: 'pdf' | 'excel' | 'csv'): void {
    if (!this.idEmpresa || !this.informeClave) {
      return;
    }
    this.descargando = true;
    const q = new URLSearchParams({
      id_empresa: String(this.idEmpresa),
      mes: String(this.periodoMes),
      anio: String(this.periodoAnio),
      informe: this.informeClave,
      formato,
    });
    this.api
      .export(`contadores/libros-iva/export?${q.toString()}`, {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (data: Blob) => {
          const ext = formato === 'pdf' ? 'pdf' : formato === 'csv' ? 'csv' : 'xlsx';
          const blob = new Blob([data]);
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `${this.informeClave}.${ext}`;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(url);
          this.descargando = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.descargando = false;
          window.alert('No se pudo descargar el archivo.');
          this.cdr.markForCheck();
        },
      });
  }

  private cargar(): void {
    this.loading = true;
    this.error = '';
    this.vista = null;
    this.informe = null;

    forkJoin({
      empresas: this.contadoresPortal.listarEmpresas(),
      ...(this.idEmpresa
        ? {
            libros: this.contadoresPortal.librosIva(this.idEmpresa, {
              mes: this.periodoMes,
              anio: this.periodoAnio,
            }),
          }
        : {}),
    })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (res) => {
          this.empresas = res.empresas?.empresas ?? [];
          if (!this.idEmpresa && this.empresas.length) {
            this.idEmpresa = this.empresas[0].id;
            this.loading = false;
            this.router.navigate([], {
              relativeTo: this.route,
              queryParams: {
                empresa: this.idEmpresa,
                mes: this.periodoMes,
                anio: this.periodoAnio,
                informe: this.informeClave,
              },
              queryParamsHandling: 'merge',
              replaceUrl: true,
            });
            return;
          }
          if ('libros' in res && res.libros) {
            this.vista = res.libros;
            if (this.syncInformeConVista()) {
              this.loading = false;
              this.cdr.markForCheck();
              return;
            }
            this.contadoresPortal.establecerContextoEmpresa(this.idEmpresa!).pipe(this.untilDestroyed()).subscribe();
            this.cargarInforme();
          }
          this.loading = false;
          if (!this.idEmpresa) {
            this.error = 'Elige una empresa para ver libros de IVA.';
          }
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.loading = false;
          const raw = err?.error?.error;
          this.error = Array.isArray(raw) ? raw.join(', ') : (raw ?? 'No se pudo cargar libros de IVA.');
          this.cdr.markForCheck();
        },
      });
  }

  /** Si el informe de la URL no aplica al país de la empresa, redirige al default del catálogo. */
  private syncInformeConVista(): boolean {
    if (!this.vista) {
      return false;
    }
    const claves = this.vista.grupos.flatMap((g) => g.informes.map((i) => i.clave));
    if (claves.includes(this.informeClave)) {
      return false;
    }
    const def = this.vista.informe_default ?? claves[0];
    if (!def) {
      return false;
    }
    this.informeClave = def;
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { informe: def },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
    return true;
  }

  private cargarInforme(): void {
    if (!this.idEmpresa) {
      return;
    }
    this.loadingInforme = true;
    this.informe = null;
    this.contadoresPortal
      .librosIvaInforme(this.idEmpresa, this.informeClave, {
        mes: this.periodoMes,
        anio: this.periodoAnio,
      })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (informe) => {
          this.informe = informe;
          this.loadingInforme = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.loadingInforme = false;
          this.cdr.markForCheck();
        },
      });
  }
}
