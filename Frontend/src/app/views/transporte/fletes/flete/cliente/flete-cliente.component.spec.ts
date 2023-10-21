import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { FleteClienteComponent } from './flete-cliente.component';

describe('FleteClienteComponent', () => {
  let component: FleteClienteComponent;
  let fixture: ComponentFixture<FleteClienteComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ FleteClienteComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(FleteClienteComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
