import { Component, OnInit } from '@angular/core';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { DomSanitizer } from '@angular/platform-browser';
import { FuncionalidadesService } from '@services/functionalities.service';

/** Debe coincidir con el slug en Backend (FuncionalidadesSeeder / VerificarAccesoFuncionalidad). */
const SLUG_INTELIGENCIA_NEGOCIOS_V2 = 'inteligencia-negocios-v2';

@Component({
    selector: 'app-reportes',
    templateUrl: './reportes.component.html'
})
export class ReportesComponent implements OnInit {

    public usuario:any = {};
    public indicadores:any = {};
    public dashboards:any = [];
    public filtros:any = {};
    /** Si es false, solo se muestra el dashboard V1 (embeds); si es true, pestañas V1/V2. */
    public tieneInteligenciaNegociosV2 = false;
    /** Evita mostrar V1 solo un instante antes de las pestañas cuando V2 sí aplica. */
    public verificacionFuncionalidadLista = false;

    constructor(
        public apiService: ApiService,
        public alertService: AlertService,
        private sanitizer: DomSanitizer,
        private funcionalidadesService: FuncionalidadesService
    ) {}

    ngOnInit(){
        this.usuario = this.apiService.auth_user();
        this.loadAll();
        this.funcionalidadesService.verificarAcceso(SLUG_INTELIGENCIA_NEGOCIOS_V2).subscribe({
            next: (acceso) => {
                this.tieneInteligenciaNegociosV2 = acceso;
                this.verificacionFuncionalidadLista = true;
            }
        });
    }

    loadAll(){
        this.filtros.id_empresa = this.usuario.id_empresa;
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.apiService.getAll('dashboards', this.filtros).subscribe(dashboards => { 
            this.dashboards = dashboards;
            for (let i = 0; i < this.dashboards['data'].length; i++) { 
                this.dashboards['data'][i].codigo_embed = this.sanitizer.bypassSecurityTrustHtml(this.dashboards['data'][i].codigo_embed);
            }
        }, error => {this.alertService.error(error); });
    }
    
}
