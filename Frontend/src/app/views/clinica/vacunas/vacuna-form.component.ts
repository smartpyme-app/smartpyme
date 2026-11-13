import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-vacuna-form',
  standalone: false,
  templateUrl: './vacuna-form.component.html',
})
export class VacunaFormComponent implements OnInit {
  idPaciente = 0;
  registro: any = this.vacio();
  profesionales: any[] = [];
  sucursales: any[] = [];
  catalogo: any[] = [];
  cargando = true;
  guardando = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.idPaciente = Number(this.route.snapshot.paramMap.get('id'));
    if (!this.apiService.hasPermission('clinica.vacunas.crear')) {
      this.router.navigate(['/']);
      return;
    }
    this.registro.fecha_aplicacion = this.apiService.date();
    this.cargarCatalogos();
    this.cargando = false;
  }

  cargarCatalogos(): void {
    this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe({
      next: (lista) => {
        this.sucursales = Array.isArray(lista) ? lista : [];
      },
    });
    this.apiService.getAll('clinica/profesionales', { estado: '1' }).pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.profesionales = respuesta?.data ?? [];
      },
    });
    this.apiService.getAll('clinica/pacientes/' + this.idPaciente + '/vacunas-catalogo')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.catalogo = respuesta?.data ?? [];
        },
      });
  }

  onCatalogoChange(): void {
    const item = this.catalogo.find((c) => c.id === Number(this.registro.id_catalogo));
    if (item) {
      this.registro.nombre_vacuna = item.nombre;
      this.registro.tipo = item.tipo;
    }
  }

  guardar(): void {
    this.guardando = true;
    this.apiService.store('clinica/pacientes/' + this.idPaciente + '/vacunas', this.registro)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.guardando = false;
          const id = respuesta?.data?.id;
          this.alertService.success('Listo', 'Vacuna registrada.');
          this.router.navigate(['/clinica/pacientes', this.idPaciente, 'vacunas', id]);
        },
        error: (error) => {
          this.guardando = false;
          this.alertService.error(error);
        },
      });
  }

  private vacio(): any {
    return {
      id_catalogo: '',
      nombre_vacuna: '',
      tipo: 'vacuna',
      fecha_aplicacion: '',
      dosis: '',
      proxima_fecha: '',
      id_sucursal: '',
      id_usuario_profesional: '',
      lote_numero: '',
      lote_vencimiento: '',
      fabricante: '',
      observaciones: '',
    };
  }
}
