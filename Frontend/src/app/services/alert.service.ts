import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { NotifierService } from 'angular-notifier';

@Injectable()
export class AlertService {

    public notifier: NotifierService;
     
    constructor(private router: Router, notifierService: NotifierService ) {
        this.notifier = notifierService;
    }

    success(message: any) {
        console.log(message);
        this.notifier.notify( 'success', message );
    }

    info(message: any) {
        console.log(message);
        this.notifier.notify( 'info', message );

    }

    error(message: any) {
        console.log(message);
        if(message.status == 0) {
            this.notifier.notify( 'error', 'No hay conección, intentar nuevamente' );
        }
        else if(message.status == 404) {
            this.notifier.notify( 'error', 'El registro no ha sido encontrado' );
        }
        else if(message.status == 401) {
            this.notifier.notify( 'error', message.error.error );
            this.router.navigate(['/login']);
        }
        else if(message.status == 400) {
            this.notifier.notify( 'info', message.error.error );
        }
        else if(message.status == 422) {
            for (var i = 0; i < message.error.error.length; ++i) {
                this.notifier.notify( 'warning', message.error.error[i] );
            }
        }
        else if(message.status == 500) {
            this.notifier.notify( 'error', message.error.error );
        }
        else {
            this.notifier.notify( 'warning', message);
        }
        
    }

}