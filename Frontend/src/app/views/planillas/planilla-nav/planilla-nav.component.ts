import { ChangeDetectionStrategy, Component, OnInit, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { environment } from 'src/environments/environment';

@Component({
    selector: 'app-planilla-nav',
    standalone: true,
    imports: [CommonModule, RouterModule],
    templateUrl: './planilla-nav.component.html',
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PlanillaNavComponent implements OnInit {
    private comisionesActiva = signal(false);
    private bonosActiva = signal(false);
    private giftCardsActiva = signal(false);

    constructor(
        public apiService: ApiService,
        private http: HttpClient,
        private router: Router,
    ) {}

    ngOnInit(): void {
        this.marcar('comisiones-vendedores', this.comisionesActiva);
        this.marcar('bonos-vendedores', this.bonosActiva);
        this.marcar('gift-cards', this.giftCardsActiva);
    }

    get mostrarComisiones(): boolean {
        return this.comisionesActiva() && this.puedeVer('planilla.comisiones.ver');
    }

    get mostrarBonos(): boolean {
        return this.bonosActiva() && this.puedeVer('planilla.bonos.ver');
    }

    get mostrarIncentivos(): boolean {
        const funcionalidad = this.comisionesActiva() || this.bonosActiva() || this.giftCardsActiva();
        return funcionalidad && this.puedeVer('planilla.incentivos.ver');
    }

    private puedeVer(permiso: string): boolean {
        return this.apiService.hasPermission(permiso) || this.apiService.hasPermission('planilla.ver');
    }

    get enComisiones(): boolean {
        return this.router.url.startsWith('/comisiones');
    }

    get enBonos(): boolean {
        return this.router.url.startsWith('/bonos');
    }

    get enIncentivos(): boolean {
        return this.router.url.startsWith('/incentivos');
    }

    private marcar(slug: string, destino: { set: (valor: boolean) => void }): void {
        this.http.get<{ acceso: boolean }>(`${environment.API_URL}/api/verificar-acceso/${slug}`).subscribe({
            next: (respuesta) => destino.set(!!respuesta?.acceso),
            error: () => destino.set(false),
        });
    }
}
