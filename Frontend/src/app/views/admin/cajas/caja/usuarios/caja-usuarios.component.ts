import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
  selector: 'app-caja-usuarios',
  templateUrl: './caja-usuarios.component.html'
})

export class CajaUsuariosComponent implements OnInit {

    @Input() caja:any = {};
    public usuarios:any = {};
    public loading:boolean = false;

    modalRef!: BsModalRef;

    constructor(private apiService: ApiService, private alertService: AlertService, 
        private modalService: BsModalService, private route: ActivatedRoute, private router: Router)
    { }

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>, usuarios:any) {
        this.usuarios = usuarios;
        this.modalRef = this.modalService.show(template, {class:'modal-sm'});
    }

    public onSubmit() {
        this.loading = true;
        this.usuarios.caja_id = this.caja.id;
        this.apiService.store('usuarios', this.usuarios).subscribe(usuarios => {
            if (!this.usuarios.id) {
                this.caja.formas_pago.push(usuarios);
            }
            this.usuarios = {};
            this.modalRef.hide();
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('usuarios/', id) .subscribe(data => {
                for (let i = 0; i < this.caja.formas_pago.length; i++) { 
                    if (this.caja.formas_pago[i].id == data.id )
                        this.caja.formas_pago.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }
    }


}
