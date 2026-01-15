import { Component, Input, OnInit } from '@angular/core';

@Component({
  selector: 'app-gastos',
  templateUrl: './gastos.component.html',
  styleUrls: ['./gastos.component.css']
})
export class GastosComponent implements OnInit {
  @Input() datos: any = {};

  constructor() { }

  ngOnInit(): void {
  }
}
