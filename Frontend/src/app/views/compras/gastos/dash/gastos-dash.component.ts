import { Component, OnInit, Input, ViewChild, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { ChartData, ChartConfiguration, ChartType } from 'chart.js';
import { BaseChartDirective, NgChartsModule } from 'ng2-charts';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-gastos-dash',
    templateUrl: './gastos-dash.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgChartsModule],
    
})
export class GastosDashComponent implements OnInit {

    @ViewChild(BaseChartDirective)
    public chart!: BaseChartDirective;

    @ViewChild(BaseChartDirective)
    public chart2!: BaseChartDirective;

    public dash:any = [];
    public filtro:any = {};
    public loading:boolean = false;

    public chartOptions:any = {maintainAspectRatio: false, responsive: true, indexAxis: 'x',};
    public chartLabels: string[] = [];
    public chartData: ChartData<'bar'> = {
        labels: [],
        datasets: [
          { data: [] }
        ]
    };
    public chartType: ChartType = 'bar';
    
    public chartData2: ChartData<'bar'> = {
        labels: [],
        datasets: [
          { data: [], backgroundColor: '#727cf5' }
        ]
    };
    public chartType2: ChartType = 'bar';

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( private alertService:AlertService, private apiService:ApiService
    ) { }

    ngOnInit() {
        this.filtro.inicio  = this.apiService.date();
        this.filtro.fin     = this.apiService.date();
        this.filtro.sucursal_id = '';
        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.store('gastos/dash', this.filtro)
          .pipe(this.untilDestroyed())
          .subscribe(dash => {
            this.dash = dash;
            this.chartData.labels = this.dash?.categorias.map(function(a:any) {return a.categoria});
            this.chartData.datasets[0].data = this.dash?.categorias.map(function(a:any) {return a.total});

            this.chartData2.labels = this.dash?.meses.map(function(a:any) {return a.nombre_mes});
            this.chartData2.datasets[0].data = this.dash?.meses.map(function(a:any) {return a.total});
            if (this.chart)
                this.chart!.chart!.update();

            if (this.chart2)
                this.chart2!.chart!.update();
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
