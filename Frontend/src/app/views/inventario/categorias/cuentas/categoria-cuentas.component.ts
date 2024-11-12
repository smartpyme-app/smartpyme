import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-categoria-cuentas',
  templateUrl: './categoria-cuentas.component.html'
})

export class CategoriaCuentasComponent implements OnInit {

    @Input() categoria:any = {};
    public cuenta:any = {};
    public sucursales:any = [];
    public catalogo:any = [];
    public loading:boolean = false;
    public saving:boolean = false;

    modalRef?: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('catalogo/list').subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});

        
    }

    public openModal(template: TemplateRef<any>, cuenta:any) {
        this.cuenta = cuenta;
        if (!this.cuenta.id) {
            this.cuenta.id_categoria = this.categoria.id;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }

    public onSubmit():void{
        this.saving = true;
        this.apiService.store('categoria/cuenta', this.cuenta).subscribe(cuenta => {
            if (!this.cuenta.id) {
                this.categoria.cuentas.push(cuenta);
                this.alertService.success('Cuenta creada', 'La cuenta fue añadida exitosamente.');
            }else{
                this.alertService.success('Cuenta guardada', 'La cuenta fue guardada exitosamente.');
            }
            this.alertService.modal = false;
            this.saving = false;
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.saving = false;});

    }

    public delete(cuenta:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('categoria/cuenta', cuenta.id) .subscribe(data => {
                for (let i = 0; i < this.categoria.cuentas.length; i++) { 
                    if (this.categoria.cuentas[i].id == data.id )
                        this.categoria.cuentas.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }


}
