import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-receta-form',
  standalone: false,
  templateUrl: './receta-form.component.html',
})
export class RecetaFormComponent implements OnInit {
  idPaciente = 0;
  idReceta = 0;
  registro: any = this.vacio();
  profesionales: any[] = [];
  sucursales: any[] = [];
  medicamentos: any[] = [];
  busquedaMed = '';
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
    const id = this.route.snapshot.paramMap.get('idReceta');
    this.idReceta = id ? Number(id) : 0;
    const permiso = this.idReceta ? 'clinica.recetas.editar' : 'clinica.recetas.crear';
    if (!this.apiService.hasPermission(permiso)) {
      this.router.navigate(['/']);
      return;
    }
    const idConsulta = this.route.snapshot.queryParamMap.get('id_consulta');
    if (idConsulta && !this.idReceta) {
      this.registro.id_consulta = Number(idConsulta);
    }
    const idTratamiento = this.route.snapshot.queryParamMap.get('id_tratamiento');
    if (idTratamiento && !this.idReceta) {
      this.registro.id_tratamiento = Number(idTratamiento);
    }
    this.cargarCatalogos();
    if (this.idReceta) {
      this.cargar();
    } else {
      this.registro.fecha = this.apiService.date();
      this.cargando = false;
    }
  }

  get esEdicion(): boolean {
    return this.idReceta > 0;
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

  buscarMedicamentos(): void {
    const q = this.busquedaMed.trim();
    if (q.length < 2) {
      this.medicamentos = [];
      return;
    }
    this.apiService.getAll('clinica/medicamentos-productos', { q }).pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.medicamentos = respuesta?.data ?? [];
      },
    });
  }

  agregarLinea(): void {
    this.registro.lineas.push({
      nombre_medicamento: '',
      dosis: '',
      frecuencia: '',
      duracion: '',
      via: '',
      indicaciones: '',
      id_producto: null,
    });
  }

  quitarLinea(i: number): void {
    this.registro.lineas.splice(i, 1);
  }

  aplicarMedicamento(linea: any, item: any): void {
    linea.id_producto = item.id_producto ?? item.id;
    linea.nombre_medicamento = item.nombre ?? item.nombre_producto ?? '';
    if (item.dosis_sugerida && !linea.dosis) {
      linea.dosis = item.dosis_sugerida;
    }
    this.medicamentos = [];
    this.busquedaMed = '';
  }

  cargar(): void {
    this.apiService.get('clinica/pacientes/' + this.idPaciente + '/recetas/' + this.idReceta)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          const data = respuesta?.data ?? {};
          if (!data.editable) {
            this.router.navigate(['/clinica/pacientes', this.idPaciente, 'recetas', this.idReceta]);
            return;
          }
          this.registro = {
            ...this.vacio(),
            ...data,
            id_sucursal: data.id_sucursal ?? '',
            id_usuario_profesional: data.profesional?.id ?? data.id_usuario_profesional,
            lineas: data.lineas?.length ? data.lineas : this.vacio().lineas,
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
      ? this.apiService.update('clinica/pacientes/' + this.idPaciente + '/recetas', this.idReceta, payload)
      : this.apiService.store('clinica/pacientes/' + this.idPaciente + '/recetas', payload);
    peticion.pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.guardando = false;
        const id = respuesta?.data?.id ?? this.idReceta;
        this.alertService.success('Listo', 'Receta guardada.');
        this.router.navigate(['/clinica/pacientes', this.idPaciente, 'recetas', id]);
      },
      error: (error) => {
        this.guardando = false;
        this.alertService.error(error);
      },
    });
  }

  private vacio(): any {
    return {
      fecha: '',
      id_sucursal: '',
      id_usuario_profesional: '',
      id_consulta: null,
      id_tratamiento: null,
      observaciones: '',
      lineas: [{
        nombre_medicamento: '',
        dosis: '',
        frecuencia: '',
        duracion: '',
        via: '',
        indicaciones: '',
        id_producto: null,
      }],
    };
  }
}
