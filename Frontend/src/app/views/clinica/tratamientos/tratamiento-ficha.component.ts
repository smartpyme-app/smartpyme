import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-tratamiento-ficha',
  standalone: false,
  templateUrl: './tratamiento-ficha.component.html',
})
export class TratamientoFichaComponent implements OnInit {
  tratamiento: any = null;
  cargando = true;
  idPaciente = 0;
  idTratamiento = 0;
  avance = { fecha: '', nota: '', incumplimiento: false };
  terapia = { fecha: '', tipo: '', descripcion: '', notas_resultado: '' };

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.tratamientos.ver')) {
      this.router.navigate(['/']);
      return;
    }
    this.idPaciente = Number(this.route.snapshot.paramMap.get('id'));
    this.idTratamiento = Number(this.route.snapshot.paramMap.get('idTratamiento'));
    this.avance.fecha = new Date().toISOString().slice(0, 10);
    this.terapia.fecha = this.avance.fecha;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/tratamientos/' + this.idTratamiento)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.tratamiento = respuesta?.data ?? null;
          this.cargando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.cargando = false;
        },
      });
  }

  editar(): void {
    this.router.navigate(['/clinica/pacientes', this.idPaciente, 'tratamientos', this.idTratamiento, 'editar']);
  }

  iniciar(): void {
    this.patch('iniciar', {}, 'Tratamiento en curso.');
  }

  suspender(): void {
    const motivo = window.prompt('Motivo de suspensión');
    if (!motivo?.trim()) {
      return;
    }
    this.patch('suspender', { motivo_suspension: motivo.trim() }, 'Tratamiento suspendido.');
  }

  finalizar(): void {
    const motivo = window.prompt('Motivo de cierre');
    if (!motivo?.trim()) {
      return;
    }
    this.patch('finalizar', { motivo_cierre: motivo.trim() }, 'Tratamiento finalizado.');
  }

  guardarAvance(): void {
    this.apiService.store('clinica/pacientes/' + this.idPaciente + '/tratamientos/' + this.idTratamiento + '/avances', this.avance)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.tratamiento = respuesta?.data ?? this.tratamiento;
          this.avance = { fecha: new Date().toISOString().slice(0, 10), nota: '', incumplimiento: false };
          this.alertService.success('Listo', 'Avance registrado.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  guardarTerapia(): void {
    this.apiService.store('clinica/pacientes/' + this.idPaciente + '/tratamientos/' + this.idTratamiento + '/terapias', this.terapia)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.tratamiento = respuesta?.data ?? this.tratamiento;
          this.terapia = { fecha: new Date().toISOString().slice(0, 10), tipo: '', descripcion: '', notas_resultado: '' };
          this.alertService.success('Listo', 'Terapia registrada.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  private patch(accion: string, body: any, mensaje: string): void {
    this.apiService.patch('clinica/pacientes', this.idPaciente + '/tratamientos/' + this.idTratamiento + '/' + accion, body)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.tratamiento = respuesta?.data ?? this.tratamiento;
          this.alertService.success('Listo', mensaje);
        },
        error: (error) => this.alertService.error(error),
      });
  }
}
