import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-canales',
  templateUrl: './canales.component.html'
})

export class CanalesComponent implements OnInit {

    public canales:any = [];
    public canal:any = {};
    public loading:boolean = false;
    public filtro:any = {};
    public filtrado:boolean = false;

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
        this.apiService.getAll('canales').subscribe(canales => { 
            this.canales = canales;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public openModal(template: TemplateRef<any>, canal:any) {
        this.canal = canal;
        if (!this.canal.id) {
            this.canal.id_empresa = this.apiService.auth_user().id_empresa;
            this.canal.enable = true;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public setEstado(canal:any){
        this.canal = canal;
        this.onSubmit();
    }

    public onSubmit(){
        this.loading = true;
        this.apiService.store('canal', this.canal).subscribe(canal => {
            if (!this.canal.id) {
                this.canales.push(canal);
                this.alertService.success('Canal creado', 'El canal fue añadido exitosamente.');
            } else {
                this.alertService.success('Canal guardado', 'El canal fue guardado exitosamente.');
            }
            this.loading = false;
            this.alertService.modal = false;
            
            if (this.modalRef) {
                this.modalRef.hide();
            }
        }, error => {
            this.alertService.error(error); 
            this.loading = false;
        });
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('gasto/', id) .subscribe(data => {
                for (let i = 0; i < this.canales.length; i++) { 
                    if (this.canales[i].id == data.id )
                        this.canales.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
