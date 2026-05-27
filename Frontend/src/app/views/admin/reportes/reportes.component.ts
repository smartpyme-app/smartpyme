import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { TabsModule } from 'ngx-bootstrap/tabs';
import { FlujoEfectivoComponent } from '@views/reportes/flujo-efectivo/flujo-efectivo.component';
import { WebdatarocksPivotModule } from '@webdatarocks/ngx-webdatarocks';

@Component({
    selector: 'app-admin-reportes',
    templateUrl: './reportes.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, TabsModule, WebdatarocksPivotModule, FlujoEfectivoComponent],
})
export class AdminReportesComponent implements OnInit {

  constructor() { }

  ngOnInit() {
  }

}
