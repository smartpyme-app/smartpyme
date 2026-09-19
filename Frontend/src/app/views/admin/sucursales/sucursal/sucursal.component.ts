import { Component, OnInit, ViewChild, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { TabsetComponent } from 'ngx-bootstrap/tabs';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { FeCrUbicacionService } from '@services/fe-cr-ubicacion.service';
import { BaseComponent } from '@shared/base/base.component';
import { TranslatePipe } from '@ngx-translate/core';
import {
    alCambiarDepartamento,
    alCambiarDistrito,
    alCambiarMunicipio,
    hidratarCodigosUbicacion,
    trackUbicacionCod,
} from '@utils/ubicacion-catalogo.util';

@Component({
    selector: 'app-sucursal',
    templateUrl: './sucursal.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TranslatePipe, NgSelectModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class SucursalComponent extends BaseComponent implements OnInit {

    public sucursal: any = {};
    public loading = false;
    public departamentos: any[] = [];
    public municipios: any[] = [];
    public distritos: any[] = [];
    readonly trackUbicacion = trackUbicacionCod;

    @ViewChild('staticTabs', { static:false }) staticTabs?: TabsetComponent;

      constructor( 
          public apiService: ApiService, private alertService: AlertService,
          private route: ActivatedRoute, private router: Router,
          private cdr: ChangeDetectorRef,
          private feCrUbic: FeCrUbicacionService,
      ) {
        super();
      }

      ngOnInit() {
            this.cargarCatalogosUbicacion();
            this.loadAll();
      }
      public loadAll(){
            const id = +this.route.snapshot.paramMap.get('id')!;
            this.loading = true;
            this.cdr.markForCheck();
            this.apiService.read('sucursal/', id)
                .pipe(this.untilDestroyed())
                .subscribe(sucursal => {
                this.sucursal = sucursal;
                this.hidratarUbicacion();
                this.loading = false;
                this.cdr.markForCheck();
            },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });

            let tabId = +this.route.snapshot.queryParamMap.get('tab')!;
            setTimeout(()=>{
                if (this.staticTabs?.tabs[tabId]) {
                  this.staticTabs.tabs[tabId].active = true;
                  this.cdr.markForCheck();
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
          this.cdr.markForCheck();
      }

      public setMunicipio(cod?: unknown): void {
          alCambiarMunicipio(this.sucursal, this.municipios, cod ?? this.sucursal.cod_municipio);
          this.cdr.markForCheck();
      }

      public setDistrito(cod?: unknown): void {
          alCambiarDistrito(this.sucursal, this.distritos, this.municipios, cod ?? this.sucursal.cod_distrito);
          this.cdr.markForCheck();
      }

      private cargarCatalogosUbicacion(): void {
          this.departamentos = this.parseLsArray('departamentos');
          this.municipios = this.parseLsArray('municipios');
          this.distritos = this.parseLsArray('distritos');
          this.feCrUbic.cargarCatalogosYLs().pipe(this.untilDestroyed()).subscribe((r) => {
              if (r) {
                  this.departamentos = r.dep;
                  this.municipios = r.mun;
                  this.distritos = r.dis;
                  this.hidratarUbicacion();
                  this.cdr.markForCheck();
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

      private parseLsArray(key: string): any[] {
          try {
              return JSON.parse(localStorage.getItem(key) || '[]');
          } catch {
              return [];
          }
      }

      public onSubmit() {
          this.loading = true;
          this.cdr.markForCheck();
          // Guardamos la sucursal
          this.apiService.store('sucursal', this.sucursal)
              .pipe(this.untilDestroyed())
              .subscribe(sucursal => {
              // this.sucursal = sucursal;
              this.alertService.success('Sucursal guardada', 'La sucursal fue guardada exitosamente.');
              this.loading = false;
              this.cdr.markForCheck();
          },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
      }

}
