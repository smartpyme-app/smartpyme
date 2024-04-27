import { Component, OnInit, EventEmitter, Output, Input } from '@angular/core';

@Component({
  selector: 'app-descargar-excel',
  templateUrl: './descargar-excel.component.html'
})
export class DescargarExcelComponent implements OnInit {

	@Input() nombre:string = '';

	constructor() { }

	ngOnInit() {}

    public exportar(){
        var tmpElemento = document.createElement('a');
        var data_type = 'application/vnd.ms-excel';
        var tabla_div = document.getElementById('tablaReporte');
        var tabla_html = tabla_div!.outerHTML.replace(/ /g, '%20');
        tmpElemento.href = 'data:' + data_type + ', ' + tabla_html;
        tmpElemento.download = this.nombre + '.xls';
        tmpElemento.click();

        console.log(tabla_div);
    }


}
