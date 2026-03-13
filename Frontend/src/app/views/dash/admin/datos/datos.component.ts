import { Component, OnInit, Input, ChangeDetectionStrategy, ChangeDetectorRef, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgChartsModule } from 'ng2-charts';
import { LineChart } from 'chartist';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
    selector: 'app-datos',
    templateUrl: './datos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, CurrencyPipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class DatosComponent implements OnInit, OnChanges {

    @Input() dash:any = {};
    @Input() loading:boolean = false;

    public barChartOptions:any = {
        maintainAspectRatio: false,
        legend: false,
        intersect: false,
        tooltips:{
            mode: 'point',
            intersect: false
        },
        scales :{
            xAxes:[{
                stacked: true,
                barPercentage:0.4,
                gridLines:{
                    display:false,
                    drawBorder:false
                },
            }],
            yAxes:[{
                stacked: true,
                gridLines:{
                    display:false,
                    drawBorder:false
                },
                ticks: {
                    beginAtZero: true,
                }
            }],
        }
    };

    public barChartLabels: any[] = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dic'];
    public barChartType: any = 'bar';
    public barChartLegend = true;

    public barChartData: any[] = [
        {
            backgroundColor: 'rgba(0,123,255,1)',
            hoverBackgroundColor: 'rgba(0,123,255,1)',
            data: [30, 25, 20, 12, 6, 22, 23, 24, 20, 14, 18, 16],
            label: 'Earings'
        },
        {
            backgroundColor: 'rgba(0,123,255,0.5)',
            hoverBackgroundColor: 'rgba(0,123,255,0.5)',
            data: [20, 25, 30, 25, 27, 2, 11, 13, 7, 5, 8, 16],
            label: 'Earings'
        }
    ];

    public option:any = {
        low: 0,
        showArea: true,
        showPoint: true,
        showLine: true,
        fullWidth: true,
        lineSmooth: true,
        chartPadding: { top: 0, right: 0, bottom: 0, left: 0 },
        axisX: { showLabel: false, showGrid: false, offset: true },
        axisY:  { showLabel: false, showGrid: false, offset: true }
    }

    constructor( 
        private alertService:AlertService, 
        private apiService:ApiService,
        private cdr: ChangeDetectorRef
    ) { }

    ngOnInit() {


    }

    ngOnChanges(changes: SimpleChanges){

        if (this.dash?.totales_ventas) {
            new LineChart('#chart-ventas', {
                labels:[this.dash?.totales_ventas.map(function(a:any) {return a.time})],
                series: [this.dash?.totales_ventas.map(function(a:any) {return a.total})]
            }, this.option);
        }

        if (this.dash?.totales_salidas) {
            new LineChart('#chart-salidas', {
                labels:[this.dash?.totales_salidas.map(function(a:any) {return a.time})],
                series: [this.dash?.totales_salidas.map(function(a:any) {return a.total})]
            }, this.option);
        }

        if (this.dash?.totales_transacciones) {
            new LineChart('#chart-transacciones', {
                labels:[this.dash?.totales_transacciones.map(function(a:any) {return a.time})],
                series: [this.dash?.totales_transacciones.map(function(a:any) {return a.total})]
            }, this.option);
        }
        if (this.dash?.totales_balance) {
            new LineChart('#chart-balance', {
                labels:[this.dash?.totales_balance.map(function(a:any) {return a.time})],
                series: [this.dash?.totales_balance.map(function(a:any) {return a.total})]
            }, this.option);
        }
        this.cdr.markForCheck();

    }

}
