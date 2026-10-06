import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-historial-expediente',
  standalone: false,
  templateUrl: './historial-expediente.component.html',
})
export class HistorialExpedienteComponent implements OnInit {
  eventos: any[] = [];
  cargando = true;
  idPaciente = 0;
  filtros: any = { tipo: '', fecha_desde: '', fecha_hasta: '', id_usuario_profesional: '' };
  profesionales: any[] = [];

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.expediente.ver')) {
      this.router.navigate(['/']);
      return;
    }
    this.idPaciente = Number(this.route.snapshot.paramMap.get('id'));
    this.cargarProfesionales();
    this.cargar();
  }

  cargarProfesionales(): void {
    this.apiService.getAll('clinica/profesionales', { estado: '1' }).pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.profesionales = respuesta?.data ?? [];
      },
    });
  }

  cargar(): void {
    this.cargando = true;
    this.apiService.getAll('clinica/pacientes/' + this.idPaciente + '/expediente/historial', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.eventos = respuesta?.data ?? [];
          this.cargando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.cargando = false;
        },
      });
  }

  abrirEvento(evento: any): void {
    if (evento?.enlace?.ruta) {
      this.router.navigateByUrl(evento.enlace.ruta);
    }
  }
}
