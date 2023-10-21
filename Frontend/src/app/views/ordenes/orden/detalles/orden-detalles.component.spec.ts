import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { OrdenDetallesComponent } from './orden-detalles.component';

describe('OrdenDetallesComponent', () => {
  let component: OrdenDetallesComponent;
  let fixture: ComponentFixture<OrdenDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ OrdenDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(OrdenDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
