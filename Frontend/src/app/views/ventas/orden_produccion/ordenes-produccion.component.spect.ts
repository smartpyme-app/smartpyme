import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { OrdenesProduccionComponent } from './ordenes-produccion.component';

describe('OrdenesProduccionComponent', () => {
  let component: OrdenesProduccionComponent;
  let fixture: ComponentFixture<OrdenesProduccionComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ OrdenesProduccionComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(OrdenesProduccionComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
