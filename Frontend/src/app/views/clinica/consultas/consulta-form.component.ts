import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-consulta-form',
  standalone: false,
  templateUrl: './consulta-form.component.html',
})
export class ConsultaFormComponent implements OnInit {
  idPaciente = 0;
  idConsulta = 0;
  consulta: any = this.vacio();
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
    const idConsulta = this.route.snapshot.paramMap.get('idConsulta');
    this.idConsulta = idConsulta ? Number(idConsulta) : 0;
    const permiso = this.idConsulta ? 'clinica.consultas.editar' : 'clinica.consultas.crear';
    if (!this.apiService.hasPermission(permiso)) {
      this.router.navigate(['/']);
      return;
    }
    this.cargarCatalogos();
    if (this.idConsulta) {
      this.cargarConsulta();
    } else {
      this.cargando = false;
    }
  }

  get esEdicion(): boolean {
    return this.idConsulta > 0;
  }

  cargarCatalogos(): void {
    this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe({
      next: (lista) => {
        this.sucursales = Array.isArray(lista) ? lista : [];
      },
    });
    this.apiService.getAll('clinica/profesionales', { estado: '1' }).pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.profesionales = respuesta?.data ?? respuesta ?? [];
      },
    });
  }

  cargarConsulta(): void {
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/consultas/' + this.idConsulta)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          const data = respuesta?.data ?? {};
          this.consulta = { ...this.vacio(), ...data, id_usuario_profesional: data.profesional?.id ?? data.id_usuario_profesional };
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
    const cuerpo = { ...this.consulta };
    const solicitud = this.esEdicion
      ? this.apiService.update('clinica/pacientes/' + this.idPaciente + '/consultas', this.idConsulta, cuerpo)
      : this.apiService.store('clinica/pacientes/' + this.idPaciente + '/consultas', cuerpo);

    solicitud.pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.guardando = false;
        const id = respuesta?.data?.id ?? this.idConsulta;
        this.alertService.success('Listo', 'Consulta guardada.');
        this.router.navigate(['/clinica/pacientes', this.idPaciente, 'consultas', id]);
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
      fecha: hoy,
      hora: '',
      id_sucursal: '',
      id_usuario_profesional: '',
      motivo: '',
      anamnesis: '',
      antecedentes: '',
      examen_fisico: '',
      observaciones: '',
      indicaciones: '',
    };
  }
}
