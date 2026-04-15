import { Component, OnInit, TemplateRef, Input, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { CrearCategoriaComponent } from '@shared/modals/crear-categoria/crear-categoria.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { LazyImageDirective } from '../../../../../directives/lazy-image.directive';
import { FE_PAIS_CR, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

@Component({
    selector: 'app-ver-producto',
    templateUrl: './ver-producto.component.html',
    styleUrls: ['./ver-producto.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class VerProductoComponent {

  producto: any = {};
  public usuario: any = {};
  public loading = false;
  public guardar = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(public apiService: ApiService, private alertService: AlertService, private route: ActivatedRoute, private cdr: ChangeDetectorRef){}

  esEmpresaCostaRica(): boolean {
    return resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_CR;
  }

  ngOnInit(){
    let param = this.route.snapshot.params;
  
    if(param['id']){
      this.apiService.read('producto/', param['id']).pipe(this.untilDestroyed()).subscribe(producto => {
        this.producto = producto;
        this.cdr.markForCheck();
      },error => {this.alertService.error(error);this.loading = false; this.cdr.markForCheck();});
    }
  }
  

}
