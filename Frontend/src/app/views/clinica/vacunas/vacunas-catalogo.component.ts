import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-vacunas-catalogo',
  standalone: false,
  templateUrl: './vacunas-catalogo.component.html',
})
export class VacunasCatalogoComponent implements OnInit {
  items: any[] = [];
  cargando = true;
  guardando = false;
  nuevo: any = this.vacio();

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.vacunas.configurar')) {
      this.router.navigate(['/']);
      return;
    }
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.apiService.getAll('clinica/vacunas-catalogo').pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.items = respuesta?.data ?? [];
        this.cargando = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.cargando = false;
      },
    });
  }

  crear(): void {
    this.guardando = true;
    this.apiService.store('clinica/vacunas-catalogo', this.nuevo).pipe(this.untilDestroyed()).subscribe({
      next: () => {
        this.guardando = false;
        this.nuevo = this.vacio();
        this.alertService.success('Listo', 'Agregado al catálogo.');
        this.cargar();
      },
      error: (error) => {
        this.guardando = false;
        this.alertService.error(error);
      },
    });
  }

  private vacio(): any {
    return {
      nombre: '',
      tipo: 'vacuna',
      tipo_paciente: '',
      intervalo_dias: '',
      activo: true,
    };
  }
}
