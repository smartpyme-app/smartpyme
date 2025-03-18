import { Component, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/environments/environment';
import { AlertService } from '@services/alert.service';
import { Subject, debounceTime, distinctUntilChanged, takeUntil } from 'rxjs';

interface Empresa {
  id: number;
  nombre: string;
}

interface Funcionalidad {
  id: number;
  nombre: string;
  slug: string;
  descripcion: string;
  icono: string;
  orden: number;
  asignada: boolean;
  configuracion: any;
  estado?: string; // Para seguimiento de cambios
}

@Component({
  selector: 'app-empresas-funcionalidades',
  templateUrl: './empresas-funcionalidades.component.html',
  styleUrls: ['./empresas-funcionalidades.component.css']
})
export class EmpresasFuncionalidadesComponent implements OnInit, OnDestroy {
  // Datos principales
  empresas: Empresa[] = [];
  empresasFiltradas: Empresa[] = [];
  funcionalidades: Funcionalidad[] = [];
  empresaSeleccionada: number | null = null;
  empresaActualNombre: string = '';
  
  // Estado de la interfaz
  isLoading = false;
  guardando = false;
  mensajeExito = '';
  mensajeError = '';
  
  // Búsqueda
  terminoBusqueda = '';
  searchTerms = new Subject<string>();
  
  // Control de componente
  private destroy$ = new Subject<void>();

  constructor(
    private http: HttpClient,
    private alertService: AlertService
  ) { }

  ngOnInit(): void {
    this.cargarEmpresas();
    
    // Configurar búsqueda con debounce
    this.searchTerms.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      takeUntil(this.destroy$)
    ).subscribe(term => {
      this.filtrarEmpresas(term);
    });
  }
  
  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  cargarEmpresas() {
    this.isLoading = true;
    this.http.get<Empresa[]>(`${environment.API_URL}/api/empresas/list`).subscribe({
      next: (data) => {
        this.empresas = data;
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error al cargar empresas:', error);
        this.mensajeError = 'Error al cargar las empresas. Intente nuevamente.';
        this.isLoading = false;
      }
    });
  }

  buscarEmpresas() {
    this.searchTerms.next(this.terminoBusqueda);
  }
  
  filtrarEmpresas(termino: string) {
    const term = termino.toLowerCase().trim();
    
    if (!term) {
      // Si el campo está vacío, no mostramos empresas
      this.empresasFiltradas = [];
    } else {
      // Solo filtramos y mostramos empresas cuando hay un término de búsqueda
      this.empresasFiltradas = this.empresas.filter(empresa => 
        empresa.nombre.toLowerCase().includes(term)
      );
    }
  }

  limpiarBusqueda() {
    this.terminoBusqueda = '';
    // Al limpiar la búsqueda, ocultamos las empresas
    this.empresasFiltradas = [];
  }

  seleccionarEmpresa(idEmpresa: number) {
    this.empresaSeleccionada = idEmpresa;
    
    // Guardar el nombre de la empresa seleccionada
    const empresaSeleccionada = this.empresas.find(e => e.id === idEmpresa);
    if (empresaSeleccionada) {
      this.empresaActualNombre = empresaSeleccionada.nombre;
    }
    
    this.cargarFuncionalidades(idEmpresa);
  }

  cargarFuncionalidades(idEmpresa: number) {
    this.isLoading = true;
    this.mensajeError = '';
    this.mensajeExito = '';
    
    this.http.get<{empresa: Empresa, funcionalidades: Funcionalidad[]}>(`${environment.API_URL}/api/empresas/${idEmpresa}/funcionalidades`)
      .subscribe({
        next: (data) => {
          // Agregar propiedad de seguimiento para cada funcionalidad
          this.funcionalidades = data.funcionalidades.map(f => ({
            ...f,
            estado: f.asignada ? 'activado' : 'desactivado'
          }));
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Error al cargar funcionalidades:', error);
          this.mensajeError = 'Error al cargar las funcionalidades. Intente nuevamente.';
          this.isLoading = false;
        }
      });
  }

  // Detecta cambios en el estado de la funcionalidad
  cambioEstadoFuncionalidad(funcionalidad: Funcionalidad) {
    funcionalidad.estado = funcionalidad.asignada ? 'activado' : 'desactivado';
  }

  actualizarFuncionalidad(funcionalidad: Funcionalidad) {
    if (!this.empresaSeleccionada) return;
    
    this.guardando = true;
    this.mensajeError = '';
    this.mensajeExito = '';
    
    const datos = {
      id_empresa: this.empresaSeleccionada,
      id_funcionalidad: funcionalidad.id,
      activo: funcionalidad.asignada,
      configuracion: funcionalidad.configuracion
    };
    
    this.http.post(`${environment.API_URL}/api/empresas/funcionalidades/actualizar`, datos)
      .subscribe({
        next: (response: any) => {
          this.mensajeExito = `Funcionalidad "${funcionalidad.nombre}" ${funcionalidad.asignada ? 'activada' : 'desactivada'} correctamente`;
          this.guardando = false;
          
          // Actualizar estado
          funcionalidad.estado = funcionalidad.asignada ? 'activado' : 'desactivado';
          
          // Auto-ocultar mensaje de éxito después de 3 segundos
          setTimeout(() => {
            this.mensajeExito = '';
          }, 3000);
        },
        error: (error) => {
          console.error('Error al actualizar funcionalidad:', error);
          this.mensajeError = 'Error al actualizar la funcionalidad. Intente nuevamente.';
          this.guardando = false;
        }
      });
  }

  guardarTodo() {
    if (!this.empresaSeleccionada) return;
    
    this.guardando = true;
    this.mensajeError = '';
    this.mensajeExito = '';
    
    const datos = {
      id_empresa: this.empresaSeleccionada,
      funcionalidades: this.funcionalidades.map(f => ({
        id: f.id,
        activo: f.asignada,
        configuracion: f.configuracion
      }))
    };
    
    this.http.post(`${environment.API_URL}/api/empresas/funcionalidades/actualizar-multiple`, datos)
      .subscribe({
        next: (response: any) => {
          this.mensajeExito = `Configuración de funcionalidades para "${this.empresaActualNombre}" guardada correctamente`;
          this.guardando = false;
          
          // Actualizar estado de todas las funcionalidades
          this.funcionalidades.forEach(f => {
            f.estado = f.asignada ? 'activado' : 'desactivado';
          });
          
          // Auto-ocultar mensaje de éxito después de 3 segundos
          setTimeout(() => {
            this.mensajeExito = '';
          }, 3000);
        },
        error: (error) => {
          console.error('Error al guardar cambios:', error);
          this.mensajeError = 'Error al guardar los cambios. Intente nuevamente.';
          this.guardando = false;
        }
      });
  }
  
  // Método para contar cambios pendientes
  contarCambiosPendientes(): number {
    return this.funcionalidades.filter(f => 
      (f.asignada && f.estado === 'desactivado') || 
      (!f.asignada && f.estado === 'activado')
    ).length;
  }
  
  // Método para verificar si hay cambios pendientes
  hayCambiosPendientes(): boolean {
    return this.contarCambiosPendientes() > 0;
  }
}