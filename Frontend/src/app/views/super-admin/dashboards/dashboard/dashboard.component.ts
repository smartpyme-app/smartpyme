import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-dashboard',
    templateUrl: './dashboard.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class DashboardComponent implements OnInit {

    public dashboard: any = {};
    public loading = false;
    public htmlContent!: SafeHtml;
    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
        private sanitizer: DomSanitizer
    ) { }

    ngOnInit() {
        const id = +this.route.snapshot.paramMap.get('id')!;
        
        this.loading = true;

        this.apiService.read('dashboard/', id).pipe(this.untilDestroyed()).subscribe(dashboard => { 
            this.dashboard = dashboard;
            this.htmlContent = this.sanitizer.bypassSecurityTrustHtml(this.dashboard.codigo_embed);

            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});


    }

}
