import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-empleado',
  templateUrl: './empleado.component.html'
})
export class EmpleadoComponent implements OnInit {

	public empleado: any = {};
	public sucursales: any = [];
    public cargos: any = [];
  	public loading = false;

  	// Img Upload
  	  public file?:File;
  	  public preview = false;
  	  public url_img_preview:string = '';

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router
	) { this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };}

	ngOnInit() {
		this.loadAll();
		
	    this.apiService.getAll('sucursales').subscribe(sucursales => { 
	        this.sucursales = sucursales;
	        this.loading = false;
	    }, error => {this.alertService.error(error); });

        this.apiService.getAll('empleados/cargos').subscribe(cargos => { 
            this.cargos = cargos;
            this.loading = false;
        }, error => {this.alertService.error(error); });

	}

	public loadAll() {
		const id = +this.route.snapshot.paramMap.get('id')!;
		this.empleado = {};
        this.empleado.sucursal_id = this.apiService.auth_user().sucursal_id;
        if (this.route.snapshot.queryParamMap.get('cargo')!) {
            this.empleado.cargo_id = this.route.snapshot.queryParamMap.get('cargo')!;
        }

		if(!isNaN(id)){
		    this.loading = true;
		    this.apiService.read('empleado/', id).subscribe(empleado => { 
		        this.empleado = empleado;
		        this.loading = false;
		    }, error => {this.alertService.error(error); this.loading = false;});
		}

	}

    public setCargo(cargo:any){
        this.cargos.push(cargo);
        this.empleado.cargo_id = cargo.id;
    }

    public setTipoComision(){
        if (this.empleado.tipo_comision == 'Ninguna') {
            this.empleado.comision = 0.0;
        }
    }

	public onSubmit() {
	    this.loading = true;

	    let formData:FormData = new FormData();
	    for (var key in this.empleado) {
	        if (key == 'activo' || key == 'isss' || key == 'afp' || key == 'renta') {
	            this.empleado[key] = this.empleado[key] ? 1 : 0;
	        }
	        formData.append(key, this.empleado[key] == null ? '' : this.empleado[key]);
	    }

	    // Guardamos al empleado
	    this.apiService.store('empleado', formData).subscribe(empleado => {
	        this.empleado = empleado;
            this.router.navigate(['/empleado/' + empleado.id]);
	        this.alertService.success("Guardado");
	        this.loading = false;
	    },error => {this.alertService.error(error); this.loading = false; });

	}

	setFile(event:any){
	    this.file = event.target.files[0];
	    this.empleado.file = this.file;
	    var reader = new FileReader();
	    reader.onload = ()=> {
	        var url:any;
	        url = reader.result;
	        this.url_img_preview = url;
	        this.preview = true;
	       };
	    reader.readAsDataURL(this.file!);
	}

}
