import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { VendedorDatosComponent } from './datos/vendedor-datos.component';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';


@Component({
    selector: 'app-vendedor-dash',
    templateUrl: './vendedor-dash.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, VendedorDatosComponent],
    
})
export class VendedorDashComponent implements OnInit {

    public dash:any = {};
    public loading:boolean = false;
    public dashResfresh:any;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        public apiService: ApiService, private alertService: AlertService
    ) { }


    ngOnInit() {
        this.loading = true;
        this.apiService.getAll('dash/vendedor')
          .pipe(this.untilDestroyed())
          .subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
        
        this.dashResfresh = setInterval(()=> {
            if (!this.loading)
                this.loadAll();
        }, 25000);
    }
    
    public loadAll(){
        this.loading = true;
        this.apiService.getAll('dash/vendedor')
          .pipe(this.untilDestroyed())
          .subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    ngOnDestroy(){
        clearInterval(this.dashResfresh);

    }


}
