import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ClienteVentasComponent } from './cliente-ventas.component';

describe('ClienteVentasComponent', () => {
  let component: ClienteVentasComponent;
  let fixture: ComponentFixture<ClienteVentasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ClienteVentasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ClienteVentasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
