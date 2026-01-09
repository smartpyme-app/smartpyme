import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-retaceos-list',
  templateUrl: './retaceos-list.component.html'
})
export class RetaceosListComponent implements OnInit {

  public retaceos: any = [];
  public loading = false;

  public filtros: any = {};
  public modalRef!: BsModalRef;
  public clientes: any = [];
  public usuarios: any = [];
  public sucursales: any = [];



  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private modalService: BsModalService
  ) { }

  ngOnInit() {
    this.loadAll();
  }

  public loadAll() {

    const filtrosGuardados = localStorage.getItem('retaceosFiltros');

    if (filtrosGuardados) {
      this.filtros = JSON.parse(filtrosGuardados);
      console.log(this.filtros);
    } else {

    const hoy = new Date();
    const mesAnterior = new Date();
    mesAnterior.setMonth(hoy.getMonth() - 1);
    this.filtros.id_sucursal = '';
  //  this.filtros.id_cliente = '';
    this.filtros.id_usuario = '';
    this.filtros.inicio = this.formatDate(mesAnterior);
    this.filtros.fin = this.formatDate(hoy);
    this.filtros.busqueda = '';
    this.filtros.paginate = 10;
    this.filtros.estado = '';
    this.filtros.orden = 'fecha';
    this.filtros.direccion = 'desc';
    }

    this.cargarRetaceos();
  }



  formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  cargarRetaceos() {
    localStorage.setItem('retaceosFiltros', JSON.stringify(this.filtros));
    this.loading = true;

    this.apiService.getAll('retaceos', this.filtros).subscribe(response => {
      this.retaceos = response
      this.loading = false;
      if(this.modalRef){
        this.modalRef.hide();
    }
    }, error => {
      this.alertService.error(error);
      this.loading = false;
    });
  }


  verRetaceo(retaceo: any) {
    this.router.navigate(['/retaceo', retaceo.id]);
  }

  eliminarRetaceo(id: number) {
    Swal.fire({
      title: '¿Está seguro?',
      text: 'Esta acción no se puede revertir. Los costos de los productos volverán a su valor original.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then(result => {
      if (result.isConfirmed) {
        this.apiService.delete('retaceo/', id).subscribe(() => {
          this.alertService.success('Retaceo eliminado correctamente', 'Retaceo eliminado');
          this.cargarRetaceos();
        }, error => {
          this.alertService.error(error);
        });
      }
    });
  }

  filtrarRetaceos() {
    this.cargarRetaceos();
  }
  openFilter(template: TemplateRef<any>) {
    if(!this.clientes.length){
      this.apiService.getAll('clientes/list').subscribe(clientes => {
          this.clientes = clientes;
      }, error => {this.alertService.error(error); });
    }
    if(!this.usuarios.length){
      this.apiService.getAll('usuarios/list').subscribe(usuarios => {
          this.usuarios = usuarios;
      }, error => {this.alertService.error(error); });
    }
    if(!this.sucursales.length){
      this.apiService.getAll('sucursales/list').subscribe(sucursales => {
        this.sucursales = sucursales;
      }, error => {this.alertService.error(error); });
    }
    this.modalRef = this.modalService.show(template);
  }

  public limpiarFiltros() {
    localStorage.removeItem('retaceosFiltros');
    this.loadAll();
  }
  generarPartidaContable(retaceo: any) {
    this.loading = true;

    this.apiService.store('contabilidad/partida/retaceo', { id_retaceo: retaceo.id })
      .subscribe(
        (response) => {
          this.alertService.success(
            `Partidas contables generadas correctamente. Se crearon ${response.partidas_creadas} partidas.`,
            'Partidas generadas'
          );
          // Marcar el retaceo como contabilizado
          retaceo.contabilizado = true;
          this.loading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
  }

  imprimir(retaceo: any) {
    window.open(this.apiService.baseUrl + '/api/retaceo/imprimir/' + retaceo.id + '?token=' + this.apiService.auth_token());
  }

  cambiarEstado(retaceo: any) {
    const estadoAnterior = retaceo.estado;
    
    // Validar transiciones de estado
    if (retaceo.estado === 'Anulado') {
      this.alertService.warning('Un retaceo anulado no puede cambiar de estado', 'Cambio de estado');
      retaceo.estado = estadoAnterior;
      return;
    }

    // Confirmar el cambio de estado
    let mensaje = '';
    if (retaceo.estado === 'Aplicado') {
      mensaje = 'Esta acción aplicará el retaceo y los costos serán aplicados. ¿Desea continuar?';
    } else if (retaceo.estado === 'Anulado') {
      mensaje = 'Esta acción anulará el retaceo y los costos no serán aplicados. ¿Desea continuar?';
    } else {
      mensaje = '¿Desea cambiar el estado del retaceo?';
    }

    Swal.fire({
      title: 'Cambiar estado',
      text: mensaje,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, cambiar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        this.actualizarEstado(retaceo);
      } else {
        // Revertir el cambio si el usuario cancela
        retaceo.estado = estadoAnterior;
      }
    });
  }

  actualizarEstado(retaceo: any) {
    this.loading = true;

    const datosActualizacion = {
      id: retaceo.id,
      estado: retaceo.estado,
    };

    this.apiService.store('retaceo/estado', datosActualizacion).subscribe(
      (response) => {
        this.alertService.success('Estado actualizado correctamente', 'Cambio de estado');
        this.loading = false;
        // Recargar los retaceos para asegurar que los datos estén actualizados
        this.cargarRetaceos();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
        // Revertir el estado en caso de error
        this.cargarRetaceos();
      }
    );
  }

}
