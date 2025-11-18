import { Component, OnInit,TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { LicenciaEmpresasComponent } from './empresas/licencia-empresas.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-licencia',
    templateUrl: './licencia.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, LicenciaEmpresasComponent],
    
})
export class LicenciaComponent implements OnInit {
    public licencia:any = {};
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;
    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.loadAll();

        // this.apiService.getAll('sucursales/list').subscribe(sucursales => {
        //     this.sucursales = sucursales;
        // }, error => {this.alertService.error(error);});
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('licencia/', id).pipe(this.untilDestroyed()).subscribe(licencia => {
                this.licencia = licencia;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.licencia = {};
        }

    }

    public onSubmit(){
        this.saving = true;

        this.apiService.store('licencia', this.licencia).pipe(this.untilDestroyed()).subscribe(licencia => {
            if (!this.licencia.id) {
                this.alertService.success('Licencia guardado', 'El licencia fue guardado exitosamente.');
            }else{
                this.alertService.success('Licencia creado', 'El licencia fue añadido exitosamente.');
            }
            this.router.navigate(['/admin/licencias']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
