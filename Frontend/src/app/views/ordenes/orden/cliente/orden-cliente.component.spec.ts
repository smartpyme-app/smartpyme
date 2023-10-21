import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { OrdenClienteComponent } from './orden-cliente.component';

describe('OrdenClienteComponent', () => {
  let component: OrdenClienteComponent;
  let fixture: ComponentFixture<OrdenClienteComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ OrdenClienteComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(OrdenClienteComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
