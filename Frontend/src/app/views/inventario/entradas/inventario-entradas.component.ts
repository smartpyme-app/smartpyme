import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-inventario-entradas',
  templateUrl: './inventario-entradas.component.html',
})
export class InventarioEntradasComponent implements OnInit {

    public entradas:any = [];
    public entrada:any = {};
    public loading:boolean = false;
    public saving:boolean = false;

    public usuarios:any = [];
    public filtros:any = {};
    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService, 
        private modalService: BsModalService, private router: Router, private route: ActivatedRoute
    ){ }

    ngOnInit() {
        this.route.queryParams.subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                usuario_id: params['usuario_id'] || '',
                estado: params['estado'] || '',
                tipo: params['tipo'] || '',
                inicio: params['inicio'] || '',
                fin: params['fin'] || '',
                orden: params['orden'] || 'fecha',
                direccion: params['direccion'] || 'desc',
                per_page: params['per_page'] || 10,
                page: params['page'] || 1,
            };

            this.filtrar();
        });
    }

    public loadAll() {
        this.filtros.buscador = '';
        this.filtros.usuario_id = '';
        this.filtros.estado = '';
        this.filtros.tipo = '';
        this.filtros.inicio = '';
        this.filtros.fin = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.per_page = 10;

        this.filtrar();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrar();
    }

    public filtrar(){
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });

        this.loading = true;
        this.apiService.store('entradas/filtrar', this.filtros).subscribe(entradas => { 
            this.entradas = entradas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setEstado(entrada:any, estado:any){
        if(estado == 'Aprobada'){
            Swal.fire({
              title: '¿Estás seguro?',
              text: '¡Confirma aprobar la entrada!',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, aprobar',
              cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.apiService.store('entrada/aprobar/' + entrada.id, {}).subscribe(data => {
                        this.alertService.success('Entrada aprobada correctamente', 'El registro fue aprobado exitosamente.');
                        this.filtrar();

                        //Generar partida contable
                        if(this.apiService.auth_user().empresa.generar_partidas == 'Auto'){
                            this.apiService.store('entrada/partida-contable/' + data.id, {}).subscribe(entrada => {
                            },error => {this.alertService.error(error);});
                        }

                    }, error => {this.alertService.error(error); });
                }
            });
        }
        if(estado == 'Anulada'){
            Swal.fire({
              title: '¿Estás seguro?',
              text: '¡Confirma anular la entrada!',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, anular',
              cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.apiService.store('entrada/anular/' + entrada.id, {}).subscribe(data => {
                        this.alertService.success('Entrada anulada correctamente', 'El registro fue anulado exitosamente.');
                        this.filtrar();
                    }, error => {this.alertService.error(error); });
                }
            });
        }
    }

    public delete(id:number) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¡Esta acción no se puede deshacer!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.apiService.delete('entrada/', id).subscribe(data => {
                    this.alertService.success('Entrada eliminada correctamente', 'El registro fue eliminado exitosamente.');
                    this.filtrar();
                }, error => {this.alertService.error(error); });
            }
        });
    }

    public setPagination(event:any):void{
        this.filtros.page = event.page;
        this.filtrar();
    }

    // Filtros
    openFilter(template: TemplateRef<any>) {
        if(!this.usuarios.length){
            this.apiService.getAll('usuarios/filtrar/tipo/Empleado').subscribe(usuarios => { 
                this.usuarios = usuarios.data;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    reemprimir(entrada:any){
        window.open(this.apiService.baseUrl + '/api/reporte/entrada/' + entrada.id + '?token=' + this.apiService.auth_token());
    }

    descargar(){
        this.loading = true;
        window.open(this.apiService.baseUrl + '/api/entradas/exportar?token=' + this.apiService.auth_token());
        this.loading = false;
    }

    public onSubmit() {
        this.saving = true;            
        this.apiService.store('entrada', this.entrada).subscribe(entrada => {
            this.alertService.success('Entrada actualizada correctamente', 'El registro fue actualizado exitosamente.');
            this.saving = false;
            this.filtrar();
        }, error => {
            this.alertService.error(error);
            this.saving = false;
        });
    }

    generarPartidaContable(entrada: any) {
        Swal.fire({
            title: '¿Generar partida contable?',
            text: '¿Estás seguro de que deseas generar la partida contable para esta entrada?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, generar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.loading = true;
                this.apiService.store('entrada/partida-contable/' + entrada.id, {}).subscribe(
                    (response) => {
                        this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
                        this.loading = false;
                        this.filtrar();
                    },
                    (error) => {
                        this.alertService.error(error);
                        this.loading = false;
                    }
                );
            }
        });
    }

}