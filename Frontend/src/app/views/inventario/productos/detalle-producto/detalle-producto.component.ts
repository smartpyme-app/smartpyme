import { Component, OnInit } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-detalle-producto',
  templateUrl: './detalle-producto.component.html',
  standalone: true,
  imports: [CommonModule, RouterModule],
})
export class DetalleProductoComponent implements OnInit {

  public producto: any = {};
  public loading = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private location: Location
  ) {}

  ngOnInit() {
    this.loadAll();
  }

  loadAll() {
    const id = +this.route.snapshot.paramMap.get('id')!;
    if (!id) return;
    this.loading = true;
    this.apiService.read('producto/', id).subscribe(
      producto => {
        this.producto = producto;
        this.loading = false;
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  goBack() {
    this.location.back();
  }

}
