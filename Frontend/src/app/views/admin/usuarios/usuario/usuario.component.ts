import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-usuario',
  templateUrl: './usuario.component.html'
})
export class UsuarioComponent implements OnInit {

	public usuario: any = {};
	public sucursales: any = [];
    public empleados: any = [];
    public loading = false;

  // Img Upload
    public file?:File;
    public preview = false;
    public url_img_preview:string = '';

	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router
	) { }

	ngOnInit() {
	    const id = +this.route.snapshot.paramMap.get('id')!;
	    
	    if(isNaN(id)){
	        this.usuario = {};
	        this.usuario.tipo = 'Vendedor';
	        this.usuario.sucursal_id = this.apiService.auth_user().sucursal_id;
            this.usuario.caja_id = 1;
	        this.usuario.activo = true;
	    }
	    else{
	        this.loadAll(id);
	    }
	    this.apiService.getAll('sucursales').subscribe(sucursales => { 
	        this.sucursales = sucursales;
	        this.loading = false;
	    }, error => {this.alertService.error(error); });


        this.apiService.getAll('empleados/list').subscribe(empleados => { 
            this.empleados = empleados;
            this.loading = false;
        }, error => {this.alertService.error(error); });

	}

	public loadAll(id:number) {
	    this.loading = true;

	    this.apiService.read('usuario/', id).subscribe(usuario => { 
	        this.usuario = usuario;
	        this.loading = false;
	    }, error => {this.alertService.error(error); this.loading = false;});


	}

	public onSubmit() {
	    this.loading = true;
	    if(this.usuario.tipo == 1) {
	    	this.usuario.caja_id == null;
	    }

	    let formData:FormData = new FormData();
	    for (var key in this.usuario) {
	        if (key == 'activo' || key == 'empleado') {
	            this.usuario[key] = this.usuario[key] ? 1 : 0;
	        }
	        formData.append(key, this.usuario[key] == null ? '' : this.usuario[key]);
	    }

	    // Guardamos al usuario
	    this.apiService.store('usuario', formData).subscribe(usuario => {
	        if (!this.usuario.id) {
		        this.router.navigate(['/usuarios']);
	        }
	        this.usuario = usuario;
	        this.loading = false;
	        this.preview = false;
	        this.alertService.success('Usuario guardado', 'El usuario fue guardado exitosamente.');
	    },error => {this.alertService.error(error); this.loading = false; });

	}

	setFile(event:any){
	    this.file = event.target.files[0];
	    this.usuario.file = this.file;
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
