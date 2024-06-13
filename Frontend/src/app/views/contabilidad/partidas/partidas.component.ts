import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-partidas',
  templateUrl: './partidas.component.html'
})
export class PartidasComponent {

  constructor(public apiService: ApiService){};

}
