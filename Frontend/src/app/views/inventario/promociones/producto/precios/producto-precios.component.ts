import { Component, OnInit, Input, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgChartsModule } from 'ng2-charts';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';


@Component({
    selector: 'app-producto-precios',
    templateUrl: './producto-precios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class ProductoPreciosComponent implements OnInit {

    @Input() filtro:any = {};
    public producto:any = {};

    public options:any = {
        maintainAspectRatio: false,
        legend:{display: false, position: 'top', text: 'Ventas'},
    };

    public type:string = 'line';
    public colors = [{borderColor:['#428bca', '#d9534f', '#5cb85c', '#5bc0de', '#f0ad4e']}];
    public datasets:any[] = [];
    public labels:any = [];
    public Cdatasets:any[] = [];
    public Clabels:any = [];

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( private alertService:AlertService, private apiService:ApiService,
      private route: ActivatedRoute, private router: Router,
    ) { }

    ngOnInit() {

        this.producto.id = +this.route.snapshot.paramMap.get('id')!;
        // if (this.apiService.auth_user().tipo == 'Administrador') { 
        //     this.filtro.producto_id = this.producto.id;
        //     this.filtro.fecha_ini = this.apiService.date();
        //     this.filtro.hora_ini = '00:00';
        //     this.filtro.hora_fin = '23:59';
        //     this.filtro.fecha_fin = this.apiService.date();
        // }
        if(this.apiService.validateRole('super_admin', true) || this.apiService.validateRole('admin', true)) {
            this.filtro.producto_id = this.producto.id;
            this.filtro.fecha_ini = this.apiService.date();
            this.filtro.hora_ini = '00:00';
            this.filtro.hora_fin = '23:59';
            this.filtro.fecha_fin = this.apiService.date();
        }

        this.loadAll();

  }

    public loadAll(){


        this.apiService.getAll('producto/precios/historicos/' + this.producto.id).pipe(this.untilDestroyed()).subscribe(producto => { 
            this.datasets     = [{
                label: 'Precio de venta',
                data: producto.ventas_precios
            }];
            this.labels       = producto.ventas_fechas;

        }, error => {this.alertService.error(error); });
      
    }


}
