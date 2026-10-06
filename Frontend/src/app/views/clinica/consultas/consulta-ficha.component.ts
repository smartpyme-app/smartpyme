import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-consulta-ficha',
  standalone: false,
  templateUrl: './consulta-ficha.component.html',
})
export class ConsultaFichaComponent implements OnInit {
  consulta: any = null;
  cargando = true;
  idPaciente = 0;
  idConsulta = 0;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.consultas.ver')) {
      this.router.navigate(['/']);
      return;
    }
    this.idPaciente = Number(this.route.snapshot.paramMap.get('id'));
    this.idConsulta = Number(this.route.snapshot.paramMap.get('idConsulta'));
    this.cargar();
  }

  cargar(): void {
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/consultas/' + this.idConsulta)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.consulta = respuesta?.data ?? null;
          this.cargando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.cargando = false;
        },
      });
  }

  cerrar(): void {
    this.apiService.patch('clinica/pacientes', this.idPaciente + '/consultas/' + this.idConsulta + '/cerrar', {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.consulta = respuesta?.data ?? this.consulta;
          this.alertService.success('Listo', 'Consulta cerrada y registrada en el historial.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  editar(): void {
    this.router.navigate(['/clinica/pacientes', this.idPaciente, 'consultas', this.idConsulta, 'editar']);
  }

  anular(): void {
    const motivo = window.prompt('Motivo de anulación');
    if (!motivo?.trim()) {
      return;
    }
    this.apiService.patch('clinica/pacientes', this.idPaciente + '/consultas/' + this.idConsulta + '/anular', { motivo_anulacion: motivo.trim() })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.consulta = respuesta?.data ?? this.consulta;
          this.alertService.success('Listo', 'Consulta anulada.');
        },
        error: (error) => this.alertService.error(error),
      });
  }
}
