import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

@Component({
    selector: 'app-reportes',
    templateUrl: './reportes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class ReportesComponent implements OnInit {

  constructor() { }

  ngOnInit() {
  }

}
