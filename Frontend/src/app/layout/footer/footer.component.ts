import { Component, OnInit } from '@angular/core';

@Component({
  selector: 'app-footer',
  templateUrl: './footer.component.html'
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
