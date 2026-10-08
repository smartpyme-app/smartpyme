import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-expediente-ficha',
  standalone: false,
  templateUrl: './expediente-ficha.component.html',
})
export class ExpedienteFichaComponent implements OnInit {
  expediente: any = null;
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
    if (!this.apiService.hasPermission('clinica.expediente.ver')) {
      this.router.navigate(['/']);
      return;
    }
    const id = this.route.snapshot.paramMap.get('id');
    this.idPaciente = Number(id);
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/expediente').pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.expediente = respuesta?.data ?? null;
        this.cargando = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.cargando = false;
      },
    });
  }

  archivar(reabrir: boolean): void {
    if (!this.expediente) {
      return;
    }
    const estado = reabrir ? 'abierto' : 'archivado';
    this.apiService.patch('clinica/pacientes', this.idPaciente + '/expediente/estado', { estado })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.expediente = respuesta?.data ?? this.expediente;
          this.alertService.success('Listo', reabrir ? 'Expediente reabierto.' : 'Expediente archivado.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  etiquetaEstado(estado: string): string {
    return estado === 'archivado' ? 'Archivado' : 'Abierto';
  }
}
