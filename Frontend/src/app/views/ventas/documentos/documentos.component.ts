import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-documentos',
  templateUrl: './documentos.component.html'
})

export class DocumentosComponent implements OnInit {

    public documentos:any = [];
    public documento:any = {};
    public sucursales:any = [];
    public loading:boolean = false;
    public filtro:any = {};
    public filtrado:boolean = false;

    public nuevaResolucion:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {

        this.loadAll();
    }

    public loadAll() {        
        this.loading = true;
        this.filtro.estado = '';
        this.apiService.getAll('documentos').subscribe(documentos => { 
            this.documentos = documentos;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public openModal(template: TemplateRef<any>, documento:any, nuevaResolucion:boolean) {
        this.documento = documento;
      
        this.nuevaResolucion = nuevaResolucion;
        if (!this.documento.id) {
            this.documento.id_empresa = this.apiService.auth_user().id_empresa;
            this.documento.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.documento.activo = true;
            this.documento.correlativo = 1;
        }
        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error);});
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    
        if (nuevaResolucion) {
            this.documento.correlativo = '';
            this.documento.rangos = '';
            this.documento.numero_autorizacion = '';
            this.documento.resolucion = '';
            this.documento.fecha = '';
            this.documento.activo = true;
            this.documento.nota = '';
        }
    }

    public setEstado(documento:any){
        this.documento = documento;
        this.onSubmit();
    }

    public onSubmit(){
        this.loading = true;
        this.documento.nuevaResolucion = this.nuevaResolucion;
        this.apiService.store('documento', this.documento).subscribe(documento => {
            if (!this.documento.id) {
                this.documentos.push(documento);
                this.alertService.success('Documento creado', 'El documento fue añadido exitosamente.');
            }else{
                this.alertService.success('Documento guardado', 'El documento fue guardado exitosamente.');
            }
            this.loading = false;
            this.loadAll();
            this.alertService.modal = false;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('gasto/', id) .subscribe(data => {
                for (let i = 0; i < this.documentos.length; i++) { 
                    if (this.documentos[i].id == data.id )
                        this.documentos.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
