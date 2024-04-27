import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ContribuyentesComponent } from './contribuyentes.component';

describe('ContribuyentesComponent', () => {
  let component: ContribuyentesComponent;
  let fixture: ComponentFixture<ContribuyentesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ContribuyentesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ContribuyentesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
