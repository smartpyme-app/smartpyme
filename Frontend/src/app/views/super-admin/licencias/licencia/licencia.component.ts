import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { LicenciaEmpresasComponent } from './empresas/licencia-empresas.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-licencia',
    templateUrl: './licencia.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, LicenciaEmpresasComponent, LazyImageDirective],
    
})
export class LicenciaComponent implements OnInit {
    public licencia: any = {};
    public loading = false;
    public saving = false;
    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        private apiService: ApiService,
        private alertService: AlertService,
        private route: ActivatedRoute,
        private router: Router
    ) { }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('licencia/', id)
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (licencia) => {
                this.licencia = licencia;
                this.loading = false;
                    },
                    error: (error) => {
                        this.alertService.error(error);
                        this.loading = false;
                    }
                });
        } else {
            this.licencia = {};
        }
    }

    public async onSubmit(){
        this.saving = true;

        try {
            const licencia = await this.apiService.store('licencia', this.licencia)
                .pipe(this.untilDestroyed())
                .toPromise();

            const isNew = !this.licencia.id;
            const title = isNew ? 'Licencia creada' : 'Licencia guardada';
            const message = isNew 
                ? 'La licencia fue añadida exitosamente.' 
                : 'La licencia fue guardada exitosamente.';
            
            this.alertService.success(title, message);
            this.router.navigate(['/admin/licencias']);
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.saving = false;
        }
    }
}
