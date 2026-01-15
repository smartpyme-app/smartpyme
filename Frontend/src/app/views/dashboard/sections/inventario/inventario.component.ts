import { Component, Input, OnInit } from '@angular/core';

@Component({
  selector: 'app-inventario',
  templateUrl: './inventario.component.html',
  styleUrls: ['./inventario.component.css']
})
export class InventarioComponent implements OnInit {
  @Input() datos: any = {};

  constructor() { }

  ngOnInit(): void {
  }
}
