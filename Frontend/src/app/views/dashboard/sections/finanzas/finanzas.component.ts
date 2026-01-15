import { Component, Input, OnInit } from '@angular/core';

@Component({
  selector: 'app-finanzas',
  templateUrl: './finanzas.component.html',
  styleUrls: ['./finanzas.component.css']
})
export class FinanzasComponent implements OnInit {
  @Input() datos: any = {};

  constructor() { }

  ngOnInit(): void {
  }
}
