import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CrearEventoComponent } from './crear-evento.component';

describe('CrearEventoActivoComponent', () => {
  let component: CrearEventoComponent;
  let fixture: ComponentFixture<CrearEventoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CrearEventoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CrearEventoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
