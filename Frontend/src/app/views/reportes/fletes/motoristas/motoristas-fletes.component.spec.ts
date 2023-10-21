import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { MotoristasFletesComponent } from './motoristas-fletes.component';

describe('MotoristasFletesComponent', () => {
  let component: MotoristasFletesComponent;
  let fixture: ComponentFixture<MotoristasFletesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ MotoristasFletesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(MotoristasFletesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
