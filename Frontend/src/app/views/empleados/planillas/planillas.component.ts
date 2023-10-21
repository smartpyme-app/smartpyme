import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import * as moment from 'moment';

@Component({
  selector: 'app-planillas',
  templateUrl: './planillas.component.html'
})
export class PlanillasComponent implements OnInit {

    public planillas: any = [];
    public usuarios: any = [];
    public asistencia: any = {};
    public user: any = {};
    public loading = false;
    public buscador:any = '';
    public estado:any = '';

    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

  	constructor( 
  	    private apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
  	) { }

  	ngOnInit() {
  	    
        this.loadAll();

  	}

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('planillas').subscribe(planillas => {
            this.planillas = planillas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    openModal(template: TemplateRef<any>, asistencia:any) {
        this.asistencia = asistencia;
        if (!asistencia.id ){
            this.asistencia.entrada = moment(new Date()).format('YYYY-MM-DDTHH:mm');
        }
        this.modalRef = this.modalService.show(template);
    }
    
    public onSubmit() {
        this.loading = true;

        this.user.username = this.user.username.toLowerCase();
        this.user.password = this.user.password.toLowerCase();

        this.apiService.store('usuario-auth', this.user).subscribe(usuario => {
            this.asistencia.usuario_id = usuario.id;
            this.asistencia.entrada = moment(new Date()).format('YYYY-MM-DDTHH:mm');
          this.apiService.store('planilla', this.asistencia).subscribe(asistencia => {
                  this.alertService.success("Datos guardados");
                  this.loading = false;
                this.modalRef.hide();
            },error => {this.alertService.error(error); this.loading = false; });
        },
        error => {this.alertService.error(error); this.loading = false; });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.planillas.path + '?page='+ event.page).subscribe(planillas => { 
            this.planillas = planillas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('planilla/', id) .subscribe(data => {
                for (let i = 0; i < this.planillas.data.length; i++) { 
                    if (this.planillas.data[i].id == data.id )
                        this.planillas.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
    }

    public pdfPlanilla(planilla:any){
        var ventana = window.open(this.apiService.baseUrl + "/api/planilla/reporte/" + planilla.id + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

    public pdfBoletas(planilla:any){
        var ventana = window.open(this.apiService.baseUrl + "/api/planilla/boletas/" + planilla.id + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

    // Filtros

    openReportes(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    openFilter(template: TemplateRef<any>) {

        if(!this.filtrado) {
            this.filtro.inicio = this.apiService.date();
            this.filtro.fin = this.apiService.date();
            this.filtro.usuario_id = '';
        }
        if(!this.usuarios.length){
            this.apiService.getAll('empleados').subscribe(usuarios => { 
                this.usuarios = usuarios.data;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('planillas/filtrar', this.filtro).subscribe(planillas => { 
            this.planillas = planillas;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
