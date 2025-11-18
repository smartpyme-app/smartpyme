import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { ActivatedRoute } from '@angular/router';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-notificaciones-container',
    templateUrl: './notificaciones-container.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule]
})
export class NotificacionesContainerComponent implements OnInit {

    public alertMessage!:any;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(private alertService: AlertService,
                private sanitizer: DomSanitizer,
                private route: ActivatedRoute
    ) {}

    ngOnInit() {
        this.alertService.getAlert()
            .pipe(this.untilDestroyed())
            .subscribe(message => {
                this.alertMessage = message;
                if (this.alertMessage && (this.alertMessage.tipo == 'alert-success')) {
                    setTimeout(() => {
                        this.closeAlert();
                    }, 10000);
                }
            });
    }

    closeAlert() {
        this.alertMessage = null;
    }

    sanitizeHtml(html: string): SafeHtml {
        return this.sanitizer.bypassSecurityTrustHtml(html);
    }

}

