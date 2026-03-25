import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-traslado-detalle',
  templateUrl: './traslado-detalle.component.html'
})
export class TrasladoDetalleComponent implements OnInit {

  public traslado: any = {};
  public loading: boolean = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router
  ) { }

  ngOnInit() {
    this.route.params.subscribe(params => {
      const id = +params['id'];
      if (!isNaN(id)) {
        this.loadTraslado(id);
      }
    });
  }

  loadTraslado(id: number) {
    this.loading = true;
    this.apiService.read('traslado/', id).subscribe(traslado => {
      this.traslado = traslado;
      this.loading = false;
    }, error => {
      this.alertService.error(error);
      this.loading = false;
    });
  }

  imprimir() {
    window.open(this.apiService.baseUrl + '/api/traslado/' + this.traslado.id + '/pdf?token=' + this.apiService.auth_token());
  }

}
