import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DashOrdenesComponent } from './dash-ordenes.component';

describe('DashOrdenesComponent', () => {
  let component: DashOrdenesComponent;
  let fixture: ComponentFixture<DashOrdenesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DashOrdenesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DashOrdenesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
