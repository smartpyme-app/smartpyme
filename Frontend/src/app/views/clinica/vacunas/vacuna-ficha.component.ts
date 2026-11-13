import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-vacuna-ficha',
  standalone: false,
  templateUrl: './vacuna-ficha.component.html',
})
export class VacunaFichaComponent implements OnInit {
  vacuna: any = null;
  cargando = true;
  idPaciente = 0;
  idVacuna = 0;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.vacunas.ver')) {
      this.router.navigate(['/']);
      return;
    }
    this.idPaciente = Number(this.route.snapshot.paramMap.get('id'));
    this.idVacuna = Number(this.route.snapshot.paramMap.get('idVacuna'));
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/vacunas/' + this.idVacuna)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.vacuna = respuesta?.data ?? null;
          this.cargando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.cargando = false;
        },
      });
  }

  anular(): void {
    const motivo = window.prompt('Motivo de anulación');
    if (!motivo?.trim()) {
      return;
    }
    this.apiService.patch('clinica/pacientes', this.idPaciente + '/vacunas/' + this.idVacuna + '/anular', { motivo_anulacion: motivo.trim() })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.vacuna = respuesta?.data ?? this.vacuna;
          this.alertService.success('Listo', 'Registro anulado.');
        },
        error: (error) => this.alertService.error(error),
      });
  }
}
