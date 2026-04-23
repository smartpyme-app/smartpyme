import { Component, OnInit, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AbstractControl, FormBuilder, FormControl, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { SumPipe } from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { HttpCacheService } from '@services/http-cache.service';
import { lastValueFrom } from 'rxjs';
import { NgSelectModule } from '@ng-select/ng-select';
import { CrearProveedorComponent } from '@shared/modals/crear-proveedor/crear-proveedor.component';
import { CrearProyectoComponent } from '@shared/modals/crear-proyecto/crear-proyecto.component';
import { CompraDetallesComponent } from '@views/compras/facturacion/detalles/compra-detalles.component';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { LazyImageDirective } from '../../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-orden-compra-form',
    templateUrl: './orden-compra-form.component.html',
    styleUrls: ['./orden-compra-form.component.css'],
    standalone: true,
    imports: [CommonModule, PipesModule, ReactiveFormsModule, RouterModule, FormsModule, NgSelectModule, CrearProveedorComponent, CrearProyectoComponent, CompraDetallesComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class OrdenCompraFormComponent implements OnInit {
  ordenCompraForm?: FormGroup;
  sucursales: any[] = [];
  bodegas: any[] = [];
  usuarios: any[] = [];
  documentos: any[] = [];
  proveedores: any[] = [];
  proyectos: any[] = [];
  detalles: any[] = [];
  impuestos: any = [];
  get tipoDocumento(): AbstractControl | null {
    return this.ordenCompraForm?.get('tipo_documento') || null;
  }
  get canSubmit(): boolean {
    return (!!this.ordenCompraForm?.valid) && (this.detalles || []).length > 0;
  }
  saving: boolean = false;
  compraDTO: any = {
    detalles: []
  }
  deletedDetalles: number[] = [];
  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);
  private cacheService = inject(HttpCacheService, { optional: true });
  private cdr = inject(ChangeDetectorRef);

  constructor(private _fb: FormBuilder, public apiService: ApiService, private alertService: AlertService, private router: Router, private activatedRoute: ActivatedRoute) {
    this.ordenCompraForm = this._fb.group({
      id: [null],
      fecha: [this.apiService.date(), Validators.required],
      id_usuario: [this.apiService.auth_user().id, Validators.required],
      id_bodega: [null],
      tipo_documento: [null],
      referencia: [null],
      id_proveedor: [null, Validators.required],
      id_proyecto: [null],
      observaciones: [null],
      cobrar_impuestos: [null],
      cobrar_percepcion: [null],
    });

    this.ordenCompraForm.valueChanges
      .pipe(this.untilDestroyed())
      .subscribe(() => this.sumTotal())
  }

  ngOnInit(): void {
    Promise.all(
      [
        lastValueFrom(this.apiService.getAll('sucursales/list')),
        lastValueFrom(this.apiService.getAll('bodegas/list')),
        lastValueFrom(this.apiService.getAll('usuarios/list')),
        lastValueFrom(this.apiService.getAll('documentos/list')),
        lastValueFrom(this.apiService.getAll('proveedores/list')),
        lastValueFrom(this.apiService.getAll('proyectos/list')),
      ]
    ).then(
      ([sucursales, bodegas, usuarios, documentos, proveedores, proyectos]) => {
        this.sucursales = sucursales;
        this.bodegas = bodegas;
        this.usuarios = usuarios;
        this.documentos = documentos;
        this.proveedores = proveedores;
        this.proyectos = proyectos;
        this.cdr.markForCheck();
      }
    ).catch(
      error => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );

    this.activatedRoute.params
      .pipe(this.untilDestroyed())
      .subscribe((params: any) => {
      if (params.id) {
        this.apiService.read('orden-de-compra/', params.id)
          .pipe(this.untilDestroyed())
          .subscribe(compra => {
          console.log(compra);

          this.ordenCompraForm?.patchValue(compra);
          this.detalles = compra.detalles;
          this.compraDTO.detalles = [...compra.detalles];
          this.sumTotal();
          this.cdr.markForCheck();

        }, error => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        });
      }
    });
  }

  public sumTotal() {
    this.impuestos.sub_total = (parseFloat(new SumPipe().transform(this.detalles, 'total'))).toFixed(2);
    this.impuestos.percepcion = this.ordenCompraForm?.get("cobrar_percepcion")?.value ? this.impuestos.sub_total * 0.01 : 0;
    this.impuestos.iva_retenido = this.impuestos.retencion ? this.impuestos.sub_total * 0.01 : 0;
    this.impuestos.renta_retenida = this.impuestos.renta ? this.impuestos.sub_total * 0.10 : 0;

    if (this.ordenCompraForm?.get("cobrar_impuestos")?.value) {
      this.impuestos.iva = (this.impuestos.sub_total * 0.13).toFixed(2);
    } else {
      this.impuestos.iva = 0;
    }

    this.impuestos.descuento = (parseFloat(new SumPipe().transform(this.detalles, 'descuento'))).toFixed(2);
    this.impuestos.total_costo = (parseFloat(new SumPipe().transform(this.impuestos.detalles, 'total_costo'))).toFixed(2);
    this.impuestos.total = (parseFloat(this.impuestos.sub_total) + parseFloat(this.impuestos.iva)
      + parseFloat(this.impuestos.percepcion) - parseFloat(this.impuestos.iva_retenido)
      - parseFloat(this.impuestos.renta_retenida)).toFixed(2);
  }
  async onSubmit() {
    const postData = {
      ...this.ordenCompraForm?.getRawValue(),
      detalles: this.detalles,
      deletedDetalles: this.deletedDetalles
    };
    
    try {
      const res = await this.apiService.store("orden-de-compra", postData)
        .pipe(this.untilDestroyed())
        .toPromise();
      
      // Invalidar cache después de guardar
      if (this.cacheService) {
        this.cacheService.invalidatePattern('/ordenes-de-compras');
        if (res?.id) {
          this.cacheService.delete(`/orden-de-compra/${res.id}`);
        }
      }
      
      this.router.navigate(['/ordenes-de-compras']);
      this.alertService.success('Orden de compra creada', 'La orden de compra fue añadida exitosamente.');
    } catch (error: any) {
      this.alertService.error(error);
    }
  }
  limpiar() { }

  setProyectoFromCreate(proyecto: any) {
    this.ordenCompraForm?.get('id_proyecto')?.setValue(proyecto.id);
    this.proyectos.push(proyecto);
  }

  updateDetalle(compra: any) {
    this.detalles = compra.detalles;
    console.log(this.detalles);


    this.sumTotal();
  }
}
