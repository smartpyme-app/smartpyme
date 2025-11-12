import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

@Component({
    selector: 'app-footer',
    templateUrl: './footer.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class FooterComponent implements OnInit {

    appVersion: string = '';

  constructor() { }

  ngOnInit() {
    fetch('/manifest.webmanifest')
      .then(response => response.json())
      .then(manifest => {
        this.appVersion = `Versión: ${manifest.version}`;
      })
      .catch(error => console.error('Error al cargar el manifest:', error));
  }

}
