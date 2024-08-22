import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

interface Producto {
    nombre: string;
    cantidad: number;
    fob: number;
    fobTotal: number;
    distribucion: number;
    daiTotal: number;
    daiProducto: number;
    gastosTotales: number;
    gastosProducto: number;
  }

@Component({
  selector: 'app-retaceo-ejem',
  templateUrl: './retaceo.component.html',
  styleUrls: ['./retaceo.component.css'],
})


export class RetaceoComponent implements OnInit {

   public producto:Producto = {
        nombre: '',
        cantidad: 0,
        fob: 0,
        fobTotal: 0,
        distribucion: 0, 
        daiTotal: 0,
        daiProducto: 0,
        gastosTotales: 0,
        gastosProducto: 0
      };
    
      public productos:Producto[] = []; // Lista para almacenar los productos agregados
     public totalFOB: number = 0;

      constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) {}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        console.log("retaceo example");
    }
    
    
      updateFOBTotal(): void {
        this.producto.fobTotal = this.producto.cantidad * this.producto.fob;
        this.producto.fobTotal = parseFloat(this.producto.fobTotal.toFixed(2));
        
        // Actualiza la sumatoria total de FOB
        this.updateDistribucion();
      }
    
      updateDistribucion(): void {
        this.totalFOB += this.producto.fobTotal;
    
        // Calcular distribución en porcentaje
        const distribucion = (this.producto.fobTotal / this.totalFOB) * 100;
        this.producto.distribucion = parseFloat(distribucion.toFixed(2)); // Mantener dos decimales
        this.updateDAIProducto();
        this.updateGastosProducto();
      }

      updateDAIProducto(): void {
        this.producto.daiProducto = parseFloat((this.producto.daiTotal * this.producto.distribucion / 100).toFixed(2));
      }

      updateGastosProducto(): void {
        this.producto.gastosProducto = parseFloat((this.producto.gastosTotales * this.producto.distribucion / 100).toFixed(2));
      }
    
      onSubmit(): void {
        // Agrega una copia del producto actual a la lista de productos
        this.productos.push({ ...this.producto });
    
        // Resetea los campos del producto después de agregarlo a la tabla
        this.resetForm();
      }
    
      resetForm(): void {
        this.producto = {
          nombre: '',
          cantidad: 0,
          fob: 0,
          fobTotal: 0,
          distribucion: 0,
          daiTotal: 0,
          daiProducto: 0,
          gastosTotales: 0,
          gastosProducto: 0
        };
      }

}
