import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FilterPipe } from '@pipes/filter.pipe';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-empleados-ventas',
    templateUrl: './empleados-ventas.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, FilterPipe]
})

export class EmpleadosVentasComponent implements OnInit {

	public empleados:any = [];
    public loading:boolean = false;
    public buscador:any = '';
    public filtro:any = {};
    
    modalRef?: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( public apiService:ApiService, private alertService:AlertService, private modalService: BsModalService ){}

	ngOnInit() {
        let today = new Date();

        this.filtro.mes = today.getMonth() + 1;
        this.filtro.ano = today.getFullYear();
        this.loadAll();
    }

    public loadAll() {
    	this.loading = true;
        this.apiService.store('empleados/ventas', this.filtro)
            .pipe(this.untilDestroyed())
            .subscribe(empleados => { 
                this.empleados = empleados;
                this.loading = false;
            }, error => {this.alertService.error(error); });
    }


}

