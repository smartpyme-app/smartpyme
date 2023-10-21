import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ClienteDireccionComponent } from './cliente-direccion.component';

describe('ClienteDireccionComponent', () => {
  let component: ClienteDireccionComponent;
  let fixture: ComponentFixture<ClienteDireccionComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ClienteDireccionComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ClienteDireccionComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
