import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
    selector: 'app-bancos',
    templateUrl: './bancos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})

export class BancosComponent implements OnInit {

    public bancos:any = [];
    public banco:any = {};
    public loading:boolean = false;
    public saving:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {        
        this.loading = true;
        this.apiService.getAll('bancos').subscribe(bancos => { 
            this.bancos = bancos;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public onSubmit(nombre:any){
        this.saving = true;

        this.banco.nombre = nombre;
        this.banco.id_empresa = this.apiService.auth_user().id_empresa;

        this.apiService.store('banco', this.banco).subscribe(banco => {
            this.alertService.success('Bancos actualizadas', 'Los bancos fueron actualizadas exitosamente.');
            this.saving = false;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
