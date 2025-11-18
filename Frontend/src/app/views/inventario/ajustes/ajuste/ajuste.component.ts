import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-ajuste',
    templateUrl: './ajuste.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class AjusteComponent implements OnInit {

	public ajuste: any = {};
 	public loading = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

   constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {
        if (+this.route.snapshot.paramMap.get('id')!) {
            this.loadAll();
        }
	}

    public loadAll(){
        this.ajuste.id = +this.route.snapshot.paramMap.get('id')!;
        this.loading = true;
        this.apiService.read('ajuste/', this.ajuste.id)
          .pipe(this.untilDestroyed())
          .subscribe(ajuste => {
        this.ajuste = ajuste;
        this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
