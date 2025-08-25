import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-entrada-detalle',
  templateUrl: './entrada-detalle.component.html'
})
export class EntradaDetalleComponent implements OnInit {

  public entrada: any = {};
  public loading: boolean = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router
  ) { }

  ngOnInit() {
    this.route.params.subscribe(params => {
      if (params['id']) {
        this.loadEntrada(params['id']);
      }
    });
  }

  loadEntrada(id: number) {
    this.loading = true;
    this.apiService.read('entrada/', id).subscribe(entrada => {
      this.entrada = entrada;
      this.loading = false;
    }, error => {
      this.alertService.error(error);
      this.loading = false;
    });
  }


} 