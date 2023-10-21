import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { FleteDetallesComponent } from './flete-detalles.component';

describe('FleteDetallesComponent', () => {
  let component: FleteDetallesComponent;
  let fixture: ComponentFixture<FleteDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ FleteDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(FleteDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
