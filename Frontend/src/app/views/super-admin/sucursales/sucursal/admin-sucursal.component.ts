import { Component, OnInit, ViewChild, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { TabsetComponent } from 'ngx-bootstrap/tabs';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FeCrUbicacionService } from '@services/fe-cr-ubicacion.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { TranslatePipe } from '@ngx-translate/core';
import {
    alCambiarDepartamento,
    alCambiarDistrito,
    alCambiarMunicipio,
    hidratarCodigosUbicacion,
    trackUbicacionCod,
} from '@utils/ubicacion-catalogo.util';

@Component({
    selector: 'app-admin-sucursal',
    templateUrl: './admin-sucursal.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TranslatePipe, NgSelectModule],
    
})
export class AdminSucursalComponent implements OnInit {

    public sucursal: any = {};
    public loading = false;
    public departamentos: any[] = [];
    public municipios: any[] = [];
    public distritos: any[] = [];
    readonly trackUbicacion = trackUbicacionCod;
    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    @ViewChild('staticTabs', { static:false }) staticTabs?: TabsetComponent;

      constructor( 
          public apiService: ApiService, private alertService: AlertService,
          private route: ActivatedRoute, private router: Router,
          private feCrUbic: FeCrUbicacionService,
      ) { }

      ngOnInit() {
            this.cargarCatalogosUbicacion();
            this.loadAll();
      }
      public loadAll(){
            const id = +this.route.snapshot.paramMap.get('id')!;
            this.loading = true;
            this.apiService.read('sucursal/', id).pipe(this.untilDestroyed()).subscribe(sucursal => {
                this.sucursal = sucursal;
                this.hidratarUbicacion();
                this.loading = false;
            },error => {this.alertService.error(error); this.loading = false; });

            let tabId = +this.route.snapshot.queryParamMap.get('tab')!;
            setTimeout(()=>{
                if (this.staticTabs?.tabs[tabId]) {
                  this.staticTabs.tabs[tabId].active = true;
                }
            },700);
      }

      public esCostaRicaFe(): boolean {
          return this.feCrUbic.esCostaRicaFe();
      }

      public municipiosFiltradosCr(): any[] {
          return this.feCrUbic.municipiosPorProvincia(this.municipios, this.sucursal?.cod_departamento);
      }

      public distritosFiltradosCr(): any[] {
          return this.feCrUbic.distritosPorCanton(
              this.distritos,
              this.sucursal?.cod_departamento,
              this.sucursal?.cod_municipio,
          );
      }

      public setDepartamento(cod?: unknown): void {
          alCambiarDepartamento(this.sucursal, this.departamentos, cod ?? this.sucursal.cod_departamento);
      }

      public setMunicipio(cod?: unknown): void {
          alCambiarMunicipio(this.sucursal, this.municipios, cod ?? this.sucursal.cod_municipio);
      }

      public setDistrito(cod?: unknown): void {
          alCambiarDistrito(this.sucursal, this.distritos, this.municipios, cod ?? this.sucursal.cod_distrito);
      }

      private cargarCatalogosUbicacion(): void {
          try {
              this.departamentos = JSON.parse(localStorage.getItem('departamentos') || '[]');
              this.municipios = JSON.parse(localStorage.getItem('municipios') || '[]');
              this.distritos = JSON.parse(localStorage.getItem('distritos') || '[]');
          } catch {
              this.departamentos = [];
              this.municipios = [];
              this.distritos = [];
          }
          this.feCrUbic.cargarCatalogosYLs().pipe(this.untilDestroyed()).subscribe((r) => {
              if (r) {
                  this.departamentos = r.dep;
                  this.municipios = r.mun;
                  this.distritos = r.dis;
                  this.hidratarUbicacion();
              }
          });
      }

      private hidratarUbicacion(): void {
          if (!this.sucursal || !this.esCostaRicaFe()) {
              return;
          }
          hidratarCodigosUbicacion(this.sucursal, {
              departamentos: this.departamentos,
              municipios: this.municipios,
              distritos: this.distritos,
          });
      }

      public async onSubmit() {
          this.loading = true;
          try {
          // Guardamos la sucursal
              await this.apiService.store('sucursal', this.sucursal)
                  .pipe(this.untilDestroyed())
                  .toPromise();
              
              this.alertService.success('Sucursal guardada', 'La sucursal fue guardada exitosamente.');
          } catch (error: any) {
              this.alertService.error(error);
          } finally {
              this.loading = false;
          }
      }

}
