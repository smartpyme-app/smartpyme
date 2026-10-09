import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-diagnostico-ficha',
  standalone: false,
  templateUrl: './diagnostico-ficha.component.html',
})
export class DiagnosticoFichaComponent implements OnInit {
  diagnostico: any = null;
  cargando = true;
  idPaciente = 0;
  idDiagnostico = 0;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.diagnosticos.ver')) {
      this.router.navigate(['/']);
      return;
    }
    this.idPaciente = Number(this.route.snapshot.paramMap.get('id'));
    this.idDiagnostico = Number(this.route.snapshot.paramMap.get('idDiagnostico'));
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/diagnosticos/' + this.idDiagnostico)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.diagnostico = respuesta?.data ?? null;
          this.cargando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.cargando = false;
        },
      });
  }

  editar(): void {
    this.router.navigate(['/clinica/pacientes', this.idPaciente, 'diagnosticos', this.idDiagnostico, 'editar']);
  }

  cerrar(): void {
    this.apiService.patch('clinica/pacientes', this.idPaciente + '/diagnosticos/' + this.idDiagnostico + '/cerrar', {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.diagnostico = respuesta?.data ?? this.diagnostico;
          this.alertService.success('Listo', 'Diagnóstico cerrado y registrado en el historial.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  anular(): void {
    const motivo = window.prompt('Motivo de anulación');
    if (!motivo?.trim()) {
      return;
    }
    this.apiService.patch('clinica/pacientes', this.idPaciente + '/diagnosticos/' + this.idDiagnostico + '/anular', { motivo_anulacion: motivo.trim() })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.diagnostico = respuesta?.data ?? this.diagnostico;
          this.alertService.success('Listo', 'Diagnóstico anulado.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  corregir(): void {
    const motivo = window.prompt('Motivo de la corrección (se creará un registro nuevo)');
    if (!motivo?.trim()) {
      return;
    }
    const descripcion = window.prompt('Nueva descripción');
    if (!descripcion?.trim()) {
      return;
    }
    this.apiService.store('clinica/pacientes/' + this.idPaciente + '/diagnosticos/' + this.idDiagnostico + '/corregir', {
      motivo: motivo.trim(),
      descripcion: descripcion.trim(),
      fecha: this.diagnostico?.fecha,
      rol: this.diagnostico?.rol,
      id_sucursal: this.diagnostico?.id_sucursal,
      id_usuario_profesional: this.diagnostico?.profesional?.id,
    })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          const id = respuesta?.data?.id;
          this.alertService.success('Listo', 'Corrección registrada.');
          if (id) {
            this.router.navigate(['/clinica/pacientes', this.idPaciente, 'diagnosticos', id]);
          } else {
            this.cargar();
          }
        },
        error: (error) => this.alertService.error(error),
      });
  }
}
