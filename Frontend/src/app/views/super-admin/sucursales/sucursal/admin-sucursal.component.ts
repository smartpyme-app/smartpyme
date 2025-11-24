import { Component, OnInit, ViewChild, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { TabsetComponent } from 'ngx-bootstrap/tabs';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-admin-sucursal',
    templateUrl: './admin-sucursal.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class AdminSucursalComponent implements OnInit {

    public sucursal: any = {};
    public loading = false;
    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    @ViewChild('staticTabs', { static:false }) staticTabs?: TabsetComponent;

      constructor( 
          public apiService: ApiService, private alertService: AlertService,
          private route: ActivatedRoute, private router: Router
      ) { }

      ngOnInit() {
            
            this.loadAll();
      }
      public loadAll(){
            const id = +this.route.snapshot.paramMap.get('id')!;
            this.loading = true;
            this.apiService.read('sucursal/', id).pipe(this.untilDestroyed()).subscribe(sucursal => {
                this.sucursal = sucursal;
                this.loading = false;
            },error => {this.alertService.error(error); this.loading = false; });

            let tabId = +this.route.snapshot.queryParamMap.get('tab')!;
            setTimeout(()=>{
                if (this.staticTabs?.tabs[tabId]) {
                  this.staticTabs.tabs[tabId].active = true;
                }
            },700);
      }

      public async onSubmit() {
          this.loading = true;
          try {
          // Guardamos la sucursal
              await this.apiService.store('sucursal', this.sucursal)
                  .pipe(this.untilDestroyed())
                  .toPromise();
              
              this.alertService.success('Sucursal guardada', 'La sucursal fue guardada exitosamente.');
          } catch (error: any) {
              this.alertService.error(error);
          } finally {
              this.loading = false;
          }
      }

}
