import { Component, Input, OnInit } from '@angular/core';

@Component({
  selector: 'app-control-cuentas',
  templateUrl: './control-cuentas.component.html',
  styleUrls: ['./control-cuentas.component.css']
})
export class ControlCuentasComponent implements OnInit {
  @Input() datos: any = {};

  constructor() { }

  ngOnInit(): void {
  }
}
