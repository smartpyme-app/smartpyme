import { TestBed, async } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { CatalogoCuentaComponent } from './catalogo-cuenta.component';

describe('CatalogoCuentaComponent', () => {
  beforeEach(async(() => {
    TestBed.configureTestingModule({
      imports: [
        RouterTestingModule
      ],
      declarations: [
        CatalogoCuentaComponent
      ],
    }).compileComponents();
  }));

  it('should create the app', () => {
    const fixture = TestBed.createComponent(CatalogoCuentaComponent);
    const app = fixture.debugElement.componentInstance;
    expect(app).toBeTruthy();
  });

  it(`should have as title 'wproject'`, () => {
    const fixture = TestBed.createComponent(CatalogoCuentaComponent);
    const app = fixture.debugElement.componentInstance;
    expect(app.title).toEqual('wproject');
  });

  it('should render title in a h1 tag', () => {
    const fixture = TestBed.createComponent(CatalogoCuentaComponent);
    fixture.detectChanges();
    const compiled = fixture.debugElement.nativeElement;
    expect(compiled.querySelector('h1').textContent).toContain('Welcome to wproject!');
  });
});
