import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { PartidaDetallesComponent } from './partida-detalles.component';

describe('PartidaDetallesComponent', () => {
  let component: PartidaDetallesComponent;
  let fixture: ComponentFixture<PartidaDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ PartidaDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(PartidaDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
