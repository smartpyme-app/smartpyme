import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-tratamiento-form',
  standalone: false,
  templateUrl: './tratamiento-form.component.html',
})
export class TratamientoFormComponent implements OnInit {
  idPaciente = 0;
  idTratamiento = 0;
  plan: any = this.vacio();
  profesionales: any[] = [];
  sucursales: any[] = [];
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
    const idTratamiento = this.route.snapshot.paramMap.get('idTratamiento');
    this.idTratamiento = idTratamiento ? Number(idTratamiento) : 0;
    const permiso = this.idTratamiento ? 'clinica.tratamientos.editar' : 'clinica.tratamientos.crear';
    if (!this.apiService.hasPermission(permiso)) {
      this.router.navigate(['/']);
      return;
    }
    const idConsulta = this.route.snapshot.queryParamMap.get('id_consulta');
    if (idConsulta && !this.idTratamiento) {
      this.plan.id_consulta = Number(idConsulta);
    }
    this.cargarCatalogos();
    if (this.idTratamiento) {
      this.cargar();
    } else {
      this.cargando = false;
    }
  }

  get esEdicion(): boolean {
    return this.idTratamiento > 0;
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
  }

  cargar(): void {
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/tratamientos/' + this.idTratamiento)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          const data = respuesta?.data ?? {};
          this.plan = {
            ...this.vacio(),
            ...data,
            id_usuario_profesional: data.profesional?.id ?? data.id_usuario_profesional,
          };
          this.cargando = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.cargando = false;
        },
      });
  }

  guardar(): void {
    this.guardando = true;
    const solicitud = this.esEdicion
      ? this.apiService.update('clinica/pacientes/' + this.idPaciente + '/tratamientos', this.idTratamiento, this.plan)
      : this.apiService.store('clinica/pacientes/' + this.idPaciente + '/tratamientos', this.plan);

    solicitud.pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.guardando = false;
        const id = respuesta?.data?.id ?? this.idTratamiento;
        this.alertService.success('Listo', 'Plan de tratamiento guardado.');
        this.router.navigate(['/clinica/pacientes', this.idPaciente, 'tratamientos', id]);
      },
      error: (error) => {
        this.guardando = false;
        this.alertService.error(error);
      },
    });
  }

  private vacio(): any {
    const hoy = new Date().toISOString().slice(0, 10);
    return {
      nombre: '',
      descripcion: '',
      fecha_inicio: hoy,
      fecha_fin: '',
      frecuencia: '',
      duracion: '',
      indicaciones: '',
      id_sucursal: '',
      id_usuario_profesional: '',
      id_consulta: null,
    };
  }
}
