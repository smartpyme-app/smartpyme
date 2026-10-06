import { Component, DestroyRef, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { ContadoresPortalService } from '@services/contadores-portal.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { etiquetaMesAnio } from './contadores-periodo.util';

/** Shell mínimo para vista 4 (cumplimiento); el detalle llega en el siguiente paso del mockup. */
@Component({
  selector: 'app-contadores-cumplimiento',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './contadores-cumplimiento.component.html',
  styleUrls: ['./contadores-dashboard.component.css'],
})
export class ContadoresCumplimientoComponent implements OnInit {
  usuario: { name?: string; empresa?: { nombre?: string } } | null = null;
  empresaNombre = '';
  periodoLabel = '';
  loading = true;
  error = '';

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private api: ApiService,
    private contadoresPortal: ContadoresPortalService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.usuario = this.api.auth_user();
    const idEmpresa = Number(this.route.snapshot.queryParamMap.get('empresa'));
    const mes = Number(this.route.snapshot.queryParamMap.get('mes'));
    const anio = Number(this.route.snapshot.queryParamMap.get('anio'));

    if (Number.isFinite(mes) && Number.isFinite(anio)) {
      this.periodoLabel = etiquetaMesAnio(mes, anio);
    }

    if (!Number.isFinite(idEmpresa)) {
      this.loading = false;
      this.error = 'Elige una empresa desde la cartera para ver cumplimiento.';
      return;
    }

    this.contadoresPortal
      .establecerContextoEmpresa(idEmpresa)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (ctx) => {
          this.empresaNombre = ctx?.contexto?.nombre ?? '';
          this.loading = false;
        },
        error: (err) => {
          this.loading = false;
          const raw = err?.error?.error;
          this.error = Array.isArray(raw) ? raw.join(', ') : (raw ?? 'No se pudo abrir la empresa.');
        },
      });
  }

  get despachoNombre(): string {
    return this.usuario?.empresa?.nombre ?? 'Despacho contable';
  }

  volverCartera(): void {
    this.router.navigate(['/contadores/cartera'], {
      queryParams: {
        empresa: this.route.snapshot.queryParamMap.get('empresa'),
        mes: this.route.snapshot.queryParamMap.get('mes'),
        anio: this.route.snapshot.queryParamMap.get('anio'),
      },
    });
  }

  cerrarSesion(): void {
    this.api.logout();
    this.router.navigate(['/login']);
  }
}
