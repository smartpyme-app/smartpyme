import { Component, OnInit, DestroyRef, inject, ChangeDetectorRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { ActivatedRoute } from '@angular/router';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { skip, filter } from 'rxjs/operators';

@Component({
    selector: 'app-notificaciones-container',
    templateUrl: './notificaciones-container.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule]
})
export class NotificacionesContainerComponent implements OnInit {

    /** Si true, muestra todos los alertas aunque `alertService.modal` esté activo (p. ej. dentro de un modal). */
    @Input() forzarMostrar = false;

    public alertMessage: any = null;
    public showAlert: boolean = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(
        private alertService: AlertService,
        private sanitizer: DomSanitizer,
        private route: ActivatedRoute,
        private cdr: ChangeDetectorRef
    ) {}

    ngOnInit() {
        // Suscribirse a cambios en el estado del modal
        // Usar skip(1) para ignorar el valor inicial del BehaviorSubject (mensajes antiguos)
        // y solo mostrar mensajes nuevos que se emitan después de la suscripción
        this.alertService.getAlert()
            .pipe(
                skip(1), // Ignorar el primer valor (valor inicial del BehaviorSubject)
                filter(message => message !== null), // Solo procesar mensajes no nulos
                this.untilDestroyed()
            )
            .subscribe(message => {
                const tieneTexto =
                    message &&
                    ((message.titulo != null && String(message.titulo).trim() !== '') ||
                        (message.mensaje != null && String(message.mensaje).trim() !== ''));
                if (tieneTexto) {
                    // Para errores de validación (422), siempre mostrar incluso si hay modal
                    const isError = message.tipo === 'alert-warning' || message.tipo === 'alert-danger';
                    
                    // Mostrar si no hay modal, si es error, o si este contenedor está forzado (dentro de modal)
                    if (this.forzarMostrar || !this.alertService.modal || isError) {
                        this.alertMessage = message;
                        this.showAlert = true;
                        // Forzar detección de cambios
                        this.cdr.detectChanges();
                        
                        try {
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        } catch (_) {}

                        if (this.alertMessage && (this.alertMessage.tipo == 'alert-success')) {
                            setTimeout(() => {
                                this.closeAlert();
                            }, 10000);
                        }
                    }
                }
            });
    }

    closeAlert() {
        this.alertMessage = null;
        this.showAlert = false;
        this.cdr.detectChanges();
    }

    sanitizeHtml(html: string): SafeHtml {
        return this.sanitizer.bypassSecurityTrustHtml(html);
    }

}

