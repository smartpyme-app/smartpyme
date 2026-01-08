import { Component, OnInit, ViewChild, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { TabsetComponent } from 'ngx-bootstrap/tabs';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { BaseComponent } from '@shared/base/base.component';

@Component({
    selector: 'app-sucursal',
    templateUrl: './sucursal.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class SucursalComponent extends BaseComponent implements OnInit {

    public sucursal: any = {};
    public loading = false;

    @ViewChild('staticTabs', { static:false }) staticTabs?: TabsetComponent;

      constructor( 
          public apiService: ApiService, private alertService: AlertService,
          private route: ActivatedRoute, private router: Router,
          private cdr: ChangeDetectorRef
      ) {
        super();
      }

      ngOnInit() {
            
            this.loadAll();
      }
      public loadAll(){
            const id = +this.route.snapshot.paramMap.get('id')!;
            this.loading = true;
            this.cdr.markForCheck();
            this.apiService.read('sucursal/', id)
                .pipe(this.untilDestroyed())
                .subscribe(sucursal => {
                this.sucursal = sucursal;
                this.loading = false;
                this.cdr.markForCheck();
            },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });

            let tabId = +this.route.snapshot.queryParamMap.get('tab')!;
            setTimeout(()=>{
                if (this.staticTabs?.tabs[tabId]) {
                  this.staticTabs.tabs[tabId].active = true;
                  this.cdr.markForCheck();
                }
            },700);
      }

      public onSubmit() {
          this.loading = true;
          this.cdr.markForCheck();
          // Guardamos la sucursal
          this.apiService.store('sucursal', this.sucursal)
              .pipe(this.untilDestroyed())
              .subscribe(sucursal => {
              // this.sucursal = sucursal;
              this.alertService.success('Sucursal guardada', 'La sucursal fue guardada exitosamente.');
              this.loading = false;
              this.cdr.markForCheck();
          },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
      }

}
