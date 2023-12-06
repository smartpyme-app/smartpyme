import { Component, OnInit, OnDestroy } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'app-notificaciones-container',
  templateUrl: './notificaciones-container.component.html'
})
export class NotificacionesContainerComponent implements OnInit, OnDestroy {

    public alertMessage!:any;
    private alertSubscription!: Subscription;

    constructor(private alertService: AlertService,
                private sanitizer: DomSanitizer,
                private route: ActivatedRoute
    ) {}

    ngOnInit() {
        this.alertSubscription = this.alertService.getAlert().subscribe(message => {
            this.alertMessage = message;
            // if (this.alertMessage) {
            //     setTimeout(() => {
            //         this.closeAlert();
            //     }, 5000);
            // }
        });
    }

    closeAlert() {
        this.alertMessage = null;
    }

    sanitizeHtml(html: string): SafeHtml {
        return this.sanitizer.bypassSecurityTrustHtml(html);
      }

      ngOnDestroy() {
        // Desuscribirse para evitar fugas de memoria
        // this.alertSubscription?.unsubscribe();
      }

}

