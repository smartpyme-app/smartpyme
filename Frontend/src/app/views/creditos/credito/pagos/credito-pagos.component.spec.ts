import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ComandaDetallesComponent } from './comanda-detalles.component';

describe('ComandaDetallesComponent', () => {
  let component: ComandaDetallesComponent;
  let fixture: ComponentFixture<ComandaDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ComandaDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ComandaDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
