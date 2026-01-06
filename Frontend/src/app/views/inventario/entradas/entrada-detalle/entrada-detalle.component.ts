import { Component, OnInit, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-entrada-detalle',
    templateUrl: './entrada-detalle.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EntradaDetalleComponent implements OnInit {

  public entrada: any = {};
  public loading: boolean = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);
  private cdr = inject(ChangeDetectorRef);

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router
  ) { }

  ngOnInit() {
    this.route.params
      .pipe(this.untilDestroyed())
      .subscribe(params => {
      if (params['id']) {
        this.loadEntrada(params['id']);
      }
    });
  }

  loadEntrada(id: number) {
    this.loading = true;
    this.apiService.read('entrada/', id)
      .pipe(this.untilDestroyed())
      .subscribe(entrada => {
      this.entrada = entrada;
      this.loading = false;
      this.cdr.markForCheck();
    }, error => {
      this.alertService.error(error);
      this.loading = false;
      this.cdr.markForCheck();
    });
  }


} 