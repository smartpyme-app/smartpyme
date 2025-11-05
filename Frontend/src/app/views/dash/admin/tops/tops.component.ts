import { Component, OnInit, Input, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { ChartData, ChartEvent, ChartType } from 'chart.js';
import { BaseChartDirective } from 'ng2-charts';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { SumPipe } from '../../../../pipes/sum.pipe';

@Component({
    selector: 'app-tops',
    templateUrl: './tops.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, SumPipe],
    
})
export class TopsComponent implements OnInit {
	@ViewChild(BaseChartDirective)
	public chart!: BaseChartDirective;

    @Input() dash:any = {};
    @Input() loading:boolean = false;

    public chartOptions:any = {maintainAspectRatio: false,};
    public chartLabels: string[] = [];
    public chartData: ChartData<'doughnut'> = {
        labels: [],
        datasets: [
          { data: [] }
        ]
    };
    public chartType: ChartType = 'doughnut';

	constructor( private alertService:AlertService, private apiService:ApiService
	) { }

	ngOnInit() {

	}

	ngOnChanges(){
    // setTimeout(()=>{  
  	// 	this.chartData.labels = this.dash?.productos.map(function(a:any) {return a.nombre});
  	// 	this.chartData.datasets[0].data = this.dash?.productos.map(function(a:any) {return a.total});
  	// 	if (this.chart)
  	// 		this.chart!.chart!.update();
    // },2000);
	}

}
