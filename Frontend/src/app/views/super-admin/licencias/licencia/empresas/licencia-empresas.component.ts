import { Component, OnInit, TemplateRef, Input, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-licencia-empresas',
    templateUrl: './licencia-empresas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})
export class LicenciaEmpresasComponent implements OnInit {

    @Input() licencia: any = {};
    public empresas: any = [];
    public empresa: any = {};
    public buscador:string = '';
    public loading:boolean = false;
    public saving:boolean = false;
    modalRef!: BsModalRef;
    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(private apiService: ApiService, private alertService: AlertService,  
        private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ){ }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('empresas/list').pipe(this.untilDestroyed()).subscribe(empresas => {
            this.empresas = empresas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }


    openModal(template: TemplateRef<any>, empresa:any) {
        this.empresa = empresa;
        this.empresa.id_empresa = '';
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    // empresa
    public setEmpresa(empresa:any){
        if(!this.empresa.id_empresa){
            this.empresas.push(empresa);
        }
        this.empresa.id_empresa = empresa.id;
    }


    public onSubmit() {
        this.saving = true;
        this.empresa.id_licencia = this.licencia.id;
        this.apiService.store('licencia/empresa', this.empresa).pipe(this.untilDestroyed()).subscribe(empresa => {
            if(!this.empresa.id)
                this.licencia.empresas.push(empresa);
            this.empresa = {};
            this.saving = false;
            this.modalRef.hide();
            this.alertService.modal = false;
            this.alertService.success('Empresa agregado', 'El empresa fue agregado exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });
    }

    public delete(empresa:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('licencia/empresa/', empresa.id).pipe(this.untilDestroyed()).subscribe(data => {
                for (let i = 0; i < this.licencia.empresas.length; i++) { 
                    if (this.licencia.empresas[i].id == data.id )
                        this.licencia.empresas.splice(i, 1);
                }
                this.alertService.success('Empresa eliminado', 'El empresa fue eliminado exitosamente.');
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
