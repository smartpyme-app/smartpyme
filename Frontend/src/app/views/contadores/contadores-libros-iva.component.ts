import { ChangeDetectorRef, Component, DestroyRef, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { forkJoin } from 'rxjs';
import { ApiService } from '@services/api.service';
import {
  ContadorEmpresaPortafolio,
  ContadorLibrosIvaResponse,
  ContadoresPortalService,
} from '@services/contadores-portal.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { mesAnioDesdeQuery, opcionesPeriodo, periodoCierrePorDefecto } from './contadores-periodo.util';
import {
  LivaModuloDescarga,
  modulosDescargaLibrosIva,
} from './contadores-libros-iva-layout.util';

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

  idEmpresa: number | null = null;
  periodoMes = periodoCierrePorDefecto().mes;
  periodoAnio = periodoCierrePorDefecto().anio;
  readonly opcionesPeriodo = opcionesPeriodo(24);

  loading = true;
  descargandoKey: string | null = null;
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

      const contextoCambio =
        idEmpresa !== this.idEmpresa || mes !== this.periodoMes || anio !== this.periodoAnio;

      this.periodoMes = mes;
      this.periodoAnio = anio;
      this.idEmpresa = idEmpresa;

      if (contextoCambio || !this.vista) {
        this.cargar();
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
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { mes: m, anio: a },
      queryParamsHandling: 'merge',
    });
  }

  modulosDescarga(vista: ContadorLibrosIvaResponse): LivaModuloDescarga[] {
    return modulosDescargaLibrosIva(vista);
  }

  formatoMoneda(monto: number | null | undefined): string {
    if (monto == null) {
      return '—';
    }
    return new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' }).format(monto);
  }

  estaDescargando(clave: string, formato: 'pdf' | 'excel' | 'csv'): boolean {
    return this.descargandoKey === `${clave}:${formato}`;
  }

  descargar(informeClave: string, formato: 'pdf' | 'excel' | 'csv'): void {
    if (!this.idEmpresa || !informeClave) {
      return;
    }
    const key = `${informeClave}:${formato}`;
    this.descargandoKey = key;
    const q = new URLSearchParams({
      id_empresa: String(this.idEmpresa),
      mes: String(this.periodoMes),
      anio: String(this.periodoAnio),
      informe: informeClave,
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
          a.download = `${informeClave}.${ext}`;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(url);
          this.descargandoKey = null;
          this.cdr.markForCheck();
        },
        error: () => {
          this.descargandoKey = null;
          window.alert('No se pudo descargar el archivo.');
          this.cdr.markForCheck();
        },
      });
  }

  private cargar(): void {
    this.loading = true;
    this.error = '';
    this.vista = null;

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
              },
              queryParamsHandling: 'merge',
              replaceUrl: true,
            });
            return;
          }
          if ('libros' in res && res.libros) {
            this.vista = res.libros;
            if (this.idEmpresa) {
              this.contadoresPortal.cambiarEmpresaActiva(this.idEmpresa).pipe(this.untilDestroyed()).subscribe();
            }
          }
          this.loading = false;
          if (!this.idEmpresa) {
            this.error = 'Elige una empresa en el header para ver libros de IVA.';
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
}
