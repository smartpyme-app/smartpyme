import { Component, OnInit, Input, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { ChartData, ChartConfiguration, ChartType } from 'chart.js';
import { BaseChartDirective, NgChartsModule } from 'ng2-charts';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-clientes-dash',
    templateUrl: './clientes-dash.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgChartsModule],
    
})
export class ClientesDashComponent implements OnInit {

    @ViewChild(BaseChartDirective)
    public chart!: BaseChartDirective;

    @ViewChild(BaseChartDirective)
    public chart2!: BaseChartDirective;

    public dash:any = [];
    public filtro:any = {};
    public loading:boolean = false;

    public chartOptions:any = {maintainAspectRatio: false, responsive: true, indexAxis: 'x', labels: true};
    public chartLabels: string[] = [];
    public chartData: ChartData<'bar'> = {
        labels: [],
        datasets: [
          { data: [] }
        ]
    };
    
    public chartData2: ChartData<'bar'> = {
        labels: [],
        datasets: [
          { data: [], backgroundColor: '#727cf5' }
        ]
    };



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
        this.apiService.store('clientes/dash', this.filtro).subscribe(dash => {
            this.dash = dash;
            this.chartData.labels = this.dash?.municipios.map(function(a:any) {return a.municipio});
            this.chartLabels = this.dash?.municipios.map(function(a:any) {return a.municipio});
            this.chartData.datasets[0].data = this.dash?.municipios.map(function(a:any) {return a.total});
            console.log(this.dash);
            this.chartData2.labels = this.dash?.ventas.map(function(a:any) {return a.nombre});
            this.chartData2.datasets[0].data = this.dash?.ventas.map(function(a:any) {return a.total});
            if (this.chart)
                this.chart!.chart!.update();

            if (this.chart2)
                this.chart2!.chart!.update();
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
