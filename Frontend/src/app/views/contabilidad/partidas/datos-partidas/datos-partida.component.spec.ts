import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ParditasDatosComponent } from './datos-partida.component';

describe('DatosComponent', () => {
  let component: ParditasDatosComponent;
  let fixture: ComponentFixture<ParditasDatosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ParditasDatosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ParditasDatosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
