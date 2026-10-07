import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-diagnosticos-paciente',
  standalone: false,
  templateUrl: './diagnosticos-paciente.component.html',
})
export class DiagnosticosPacienteComponent implements OnInit {
  diagnosticos: any[] = [];
  cargando = true;
  idPaciente = 0;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.diagnosticos.ver') && !this.apiService.hasPermission('clinica.expediente.ver')) {
      this.router.navigate(['/']);
      return;
    }
    this.idPaciente = Number(this.route.snapshot.paramMap.get('id'));
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.apiService.getAll('clinica/pacientes/' + this.idPaciente + '/diagnosticos')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.diagnosticos = respuesta?.data ?? [];
          this.cargando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.cargando = false;
        },
      });
  }
}
