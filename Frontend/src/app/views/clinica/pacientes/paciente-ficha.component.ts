import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-paciente-ficha',
  standalone: false,
  templateUrl: './paciente-ficha.component.html',
})
export class PacienteFichaComponent implements OnInit {
  paciente: any = null;
  cargando = true;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.pacientes.ver')) {
      this.router.navigate(['/']);
      return;
    }
    const id = this.route.snapshot.paramMap.get('id');
    this.apiService.get('clinica/pacientes/' + id).pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.paciente = respuesta?.data ?? null;
        this.cargando = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.cargando = false;
      },
    });
  }

  cambiarEstado(activo: boolean): void {
    if (!this.paciente) {
      return;
    }
    this.apiService.patch('clinica/pacientes', this.paciente.id + '/estado', { activo })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.paciente = respuesta?.data ?? this.paciente;
          this.alertService.success('Listo', activo ? 'Paciente reactivado.' : 'Paciente desactivado. El expediente se conserva.');
        },
        error: (error) => this.alertService.error(error),
      });
  }
}
