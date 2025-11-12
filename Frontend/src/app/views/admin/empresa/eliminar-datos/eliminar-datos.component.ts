import { Component, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { TabsetComponent } from 'ngx-bootstrap/tabs';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-eliminar-datos',
    templateUrl: './eliminar-datos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class EliminarDatosComponent implements OnInit {

    public empresa: any = {};
    public loading = false;
    public saving = false;

  	constructor( 
  	    public apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router
  	) { }

  	ngOnInit() {
        this.empresa.m_inventario   = false;
        this.empresa.m_categorias   = false;
        this.empresa.m_ventas       = false;
        this.empresa.m_clientes     = false;
        this.empresa.m_proveedores  = false;
        this.empresa.m_compras      = false;
        this.empresa.m_gastos      = false;
        this.empresa.m_presupuestos = false;

  	    this.loading = true;
        this.apiService.read('empresa/', this.apiService.auth_user().id_empresa).subscribe(empresa => {
            this.empresa = empresa;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });
  	}

    public marcarTodos(value:boolean){
        this.empresa.m_inventario   = value;
        this.empresa.m_categorias   = value;
        this.empresa.m_ventas       = value;
        this.empresa.m_clientes     = value;
        this.empresa.m_proveedores  = value;
        this.empresa.m_compras      = value;
        this.empresa.m_gastos      = value;
        this.empresa.m_presupuestos      = value;
        // this.empresa.m_promociones  = value;
    }

    public onDeleteData() {
        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlos',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.saving = true;
                this.apiService.store('empresa/eliminar/datos', this.empresa).subscribe(empresa => {
                    Swal.fire('Eliminado', 'Los datos fueron eliminados correctamente', 'success');
                    this.saving = false;
                },error => {this.alertService.error(error); this.saving = false; });
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire('Cancelado', 'Tus datos están seguro :)', 'info');
          }
        });
    }

}
