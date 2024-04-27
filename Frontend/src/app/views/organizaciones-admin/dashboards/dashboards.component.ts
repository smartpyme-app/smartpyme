import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-dashboards',
  templateUrl: './dashboards.component.html'
})

export class DashboardsComponent implements OnInit {

    public dashboards:any = [];
    public dashboard:any = {};
    public empresas:any = [];
    public loading:boolean = false;
    public saving:boolean = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.filtros.id_empresa = '';
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarDashboards();
        this.apiService.getAll('empresas/list').subscribe(empresas => { 
            this.empresas = empresas;
        }, error => {this.alertService.error(error); });
    }

    public filtrarDashboards(){
        this.apiService.getAll('dashboards', this.filtros).subscribe(dashboards => { 
            this.dashboards = dashboards;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.loadAll();
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('dashboard/', id) .subscribe(data => {
                for (let i = 0; i < this.dashboards['data'].length; i++) { 
                    if (this.dashboards['data'][i].id == data.id )
                        this.dashboards['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.dashboards.path + '?page='+ event.page, this.filtros).subscribe(dashboards => { 
            this.dashboards = dashboards;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    openModal(template: TemplateRef<any>, dashboard:any) {
        this.dashboard = dashboard;
        if (!this.dashboard.id) {
            // this.dashboard.tipo = 'Administrador';
        }
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('dashboard', this.dashboard).subscribe(dashboard => {
            this.loadAll();
            this.saving = false;
            if(!this.dashboards.id){
                this.alertService.success('Dashboard creado', 'La dashboard fue añadido exitosamente.');
            }else{
                this.alertService.success('Dashboard guardado', 'La dashboard fue guardado exitosamente.');
            }
            this.modalRef?.hide();
        },error => {this.alertService.error(error); this.saving = false; });

    }


}
