import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { AsistenciaMarcadorComponent } from './asistencia-marcador.component';

describe('AsistenciaMarcadorComponent', () => {
  let component: AsistenciaMarcadorComponent;
  let fixture: ComponentFixture<AsistenciaMarcadorComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ AsistenciaMarcadorComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(AsistenciaMarcadorComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
