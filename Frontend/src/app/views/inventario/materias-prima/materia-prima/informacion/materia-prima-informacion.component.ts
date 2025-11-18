import { Component, OnInit, TemplateRef, Input, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-materia-prima-informacion',
    templateUrl: './materia-prima-informacion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class MateriaPrimaInformacionComponent implements OnInit {

    @Input() producto: any = {};
    public categorias:any[] = [];
    public loading = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
    ) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {

        this.apiService.getAll('categorias')
          .pipe(this.untilDestroyed())
          .subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});
        
    }


    public onSubmit() {
        this.loading = true;
        this.apiService.store('materia-prima', this.producto)
          .pipe(this.untilDestroyed())
          .subscribe(producto => {
            this.loading = false;
            if(!this.producto.id) {
                this.producto = producto;
                this.router.navigate(['/materia-prima/'+ producto.id]);
            }
            this.alertService.success('Materia prima guardada', 'La materia prima fue guardada exitosamente');
        },error => {this.alertService.error(error); this.loading = false; });
    }
    

}
