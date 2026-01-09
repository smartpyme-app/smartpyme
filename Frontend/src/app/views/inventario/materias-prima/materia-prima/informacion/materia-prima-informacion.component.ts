import { Component, OnInit, TemplateRef, Input, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';
import { BaseComponent } from '@shared/base/base.component';

@Component({
    selector: 'app-materia-prima-informacion',
    templateUrl: './materia-prima-informacion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MateriaPrimaInformacionComponent extends BaseComponent implements OnInit {

    @Input() producto: any = {};
    public categorias:any[] = [];
    public loading = false;

    constructor( 
        protected apiService: ApiService, 
        protected alertService: AlertService,
        private route: ActivatedRoute, 
        private router: Router,
        private cdr: ChangeDetectorRef
    ) {
        super();
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {

        this.apiService.getAll('categorias')
          .pipe(this.untilDestroyed())
          .subscribe(categorias => {
            this.categorias = categorias;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});
        
    }


    public onSubmit() {
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.store('materia-prima', this.producto)
          .pipe(this.untilDestroyed())
          .subscribe(producto => {
            this.loading = false;
            if(!this.producto.id) {
                this.producto = producto;
                this.router.navigate(['/materia-prima/'+ producto.id]);
            }
            this.alertService.success('Materia prima guardada', 'La materia prima fue guardada exitosamente');
            this.cdr.markForCheck();
        },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }
    

}
