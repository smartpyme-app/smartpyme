import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-diagnostico-form',
  standalone: false,
  templateUrl: './diagnostico-form.component.html',
})
export class DiagnosticoFormComponent implements OnInit {
  idPaciente = 0;
  idDiagnostico = 0;
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
    const idDx = this.route.snapshot.paramMap.get('idDiagnostico');
    this.idDiagnostico = idDx ? Number(idDx) : 0;
    const permiso = this.idDiagnostico ? 'clinica.diagnosticos.editar' : 'clinica.diagnosticos.crear';
    if (!this.apiService.hasPermission(permiso)) {
      this.router.navigate(['/']);
      return;
    }
    const idConsulta = this.route.snapshot.queryParamMap.get('id_consulta');
    if (idConsulta && !this.idDiagnostico) {
      this.registro.id_consulta = Number(idConsulta);
    }
    this.cargarCatalogos();
    if (this.idDiagnostico) {
      this.cargar();
    } else {
      this.cargando = false;
    }
  }

  get esEdicion(): boolean {
    return this.idDiagnostico > 0;
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
    this.apiService.getAll('clinica/diagnosticos-catalogo').pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.catalogo = respuesta?.data ?? [];
      },
    });
  }

  cargar(): void {
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/diagnosticos/' + this.idDiagnostico)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          const data = respuesta?.data ?? {};
          this.registro = {
            ...this.vacio(),
            ...data,
            id_sucursal: data.id_sucursal ?? '',
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
    const payload = { ...this.registro };
    const peticion = this.esEdicion
      ? this.apiService.update('clinica/pacientes', this.idPaciente + '/diagnosticos/' + this.idDiagnostico, payload)
      : this.apiService.store('clinica/pacientes/' + this.idPaciente + '/diagnosticos', payload);
    peticion.pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.guardando = false;
        const id = respuesta?.data?.id ?? this.idDiagnostico;
        this.alertService.success('Listo', 'Diagnóstico guardado.');
        this.router.navigate(['/clinica/pacientes', this.idPaciente, 'diagnosticos', id]);
      },
      error: (error) => {
        this.guardando = false;
        this.alertService.error(error);
      },
    });
  }

  aplicarCodigoCatalogo(): void {
    const item = this.catalogo.find((c) => c.codigo === this.registro.codigo);
    if (item && !this.registro.descripcion?.trim()) {
      this.registro.descripcion = item.nombre;
    }
  }

  private vacio(): any {
    return {
      fecha: new Date().toISOString().slice(0, 10),
      descripcion: '',
      codigo: '',
      rol: 'secundario',
      id_sucursal: '',
      id_usuario_profesional: '',
      id_consulta: null,
    };
  }
}
