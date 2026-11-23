import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-receta-ficha',
  standalone: false,
  templateUrl: './receta-ficha.component.html',
})
export class RecetaFichaComponent implements OnInit {
  receta: any = null;
  cargando = true;
  idPaciente = 0;
  idReceta = 0;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.recetas.ver')) {
      this.router.navigate(['/']);
      return;
    }
    this.idPaciente = Number(this.route.snapshot.paramMap.get('id'));
    this.idReceta = Number(this.route.snapshot.paramMap.get('idReceta'));
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/recetas/' + this.idReceta)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.receta = respuesta?.data ?? null;
          this.cargando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.cargando = false;
        },
      });
  }

  editar(): void {
    this.router.navigate(['/clinica/pacientes', this.idPaciente, 'recetas', this.idReceta, 'editar']);
  }

  emitir(): void {
    this.apiService.patch('clinica/pacientes', this.idPaciente + '/recetas/' + this.idReceta + '/emitir', {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.receta = respuesta?.data ?? this.receta;
          this.alertService.success('Listo', 'Receta emitida.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  anular(): void {
    const motivo = window.prompt('Motivo de anulación');
    if (!motivo?.trim()) {
      return;
    }
    this.apiService.patch('clinica/pacientes', this.idPaciente + '/recetas/' + this.idReceta + '/anular', { motivo_anulacion: motivo.trim() })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.receta = respuesta?.data ?? this.receta;
          this.alertService.success('Listo', 'Receta anulada.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  pdf(): void {
    const url = 'clinica/pacientes/' + this.idPaciente + '/recetas/' + this.idReceta + '/pdf';
    this.apiService.download(url).pipe(this.untilDestroyed()).subscribe({
      next: (blob) => this.apiService.downloadFile(blob, 'receta-' + this.idReceta + '.pdf'),
      error: (error) => this.alertService.error(error),
    });
  }
}
