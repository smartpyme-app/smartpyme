import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { GastosDashComponent } from './gastos-dash.component';

describe('GastosDashComponent', () => {
  let component: GastosDashComponent;
  let fixture: ComponentFixture<GastosDashComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ GastosDashComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(GastosDashComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
