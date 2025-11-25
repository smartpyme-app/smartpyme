import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-usuario',
    templateUrl: './usuario.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, LazyImageDirective],
    
})
export class UsuarioComponent implements OnInit {

	public usuario: any = {};

	public roles: any = [];
	public cajas: any = [];
	public sucursales: any = [];
    public empleados: any = [];
	public departamentos: any = [];
    public loading = false;

  // Img Upload
    public file?:File;
    public preview = false;
    public url_img_preview:string = '';

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router
	) { }

	ngOnInit() {
	    const id = +this.route.snapshot.paramMap.get('id')!;
	    
	    if(isNaN(id)){
	        this.usuario = {};
	        this.usuario.tipo = 'Vendedor'
			this.usuario.rol_id = 2;
	        this.usuario.sucursal_id = this.apiService.auth_user().sucursal_id;
            this.usuario.caja_id = 1;
	        this.usuario.activo = true;
	    }
	    else{
	        this.loadAll(id);
	    }

	    this.apiService.getAll('cajas')
            .pipe(this.untilDestroyed())
            .subscribe(cajas => { 
	        this.cajas = cajas;
	        this.loading = false;
	    }, error => {this.alertService.error(error); });

	    this.apiService.getAll('sucursales')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => { 
	        this.sucursales = sucursales;
	        this.loading = false;
	    }, error => {this.alertService.error(error); });


        this.apiService.getAll('empleados/list')
            .pipe(this.untilDestroyed())
            .subscribe(empleados => { 
            this.empleados = empleados;
            this.loading = false;
        }, error => {this.alertService.error(error); });

	    this.apiService.getAll('departamentos')
            .pipe(this.untilDestroyed())
            .subscribe(departamentos => { 
	        this.departamentos = departamentos;
	        this.loading = false;
	    }, error => {this.alertService.error(error); });

	}

	public loadAll(id:number) {
	    this.loading = true;

	    this.apiService.read('usuario/', id)
            .pipe(this.untilDestroyed())
            .subscribe(usuario => { 
	        this.usuario = usuario;
			this.usuario.rol_id = usuario.roles[0].id;
			this.usuario.rol_name = usuario.roles[0].name;
	        this.loading = false;
	    }, error => {this.alertService.error(error); this.loading = false;});

		this.apiService.getAll('roles')
            .pipe(this.untilDestroyed())
            .subscribe(roles => { 
	        this.roles = roles;

			this.roles.forEach((rol:any) => {
				rol.name = rol.name.split('_')
				.map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
				.join(' ');
			});

			
	        this.loading = false;
	    }, error => {this.alertService.error(error); });


	}

	public async onSubmit() {
	    this.loading = true;
	    if(this.usuario.tipo == 1) {
	    	this.usuario.caja_id == null;
	    }

	    const formData: FormData = new FormData();
	    for (const key in this.usuario) {
	        if (key == 'activo' || key == 'empleado') {
	            this.usuario[key] = this.usuario[key] ? 1 : 0;
	        }
	        formData.append(key, this.usuario[key] == null ? '' : this.usuario[key]);
	    }

	    try {
	        // Guardamos al usuario
	        const usuarioGuardado = await this.apiService.store('usuario', formData)
                .pipe(this.untilDestroyed())
                .toPromise();
	        
	        if (!this.usuario.id) {
		        this.router.navigate(['/usuarios']);
	        }
	        this.usuario = usuarioGuardado;
	        this.preview = false;
	        this.alertService.success('Usuario guardado', 'El usuario fue guardado exitosamente.');
	    } catch (error: any) {
	        this.alertService.error(error);
	    } finally {
	        this.loading = false;
	    }
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
