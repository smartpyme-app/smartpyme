import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-multimedia',
    templateUrl: './multimedia.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterModule]
})
export class MultimediaComponent implements OnInit {

    public multimedia:any = [];
    public file:any;
    public loading:boolean = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( public apiService:ApiService, private alertService:AlertService ){}

    ngOnInit() {
        // this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('multimedias')
            .pipe(this.untilDestroyed())
            .subscribe(multimedia => { 
            this.multimedia = multimedia;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(multimedia:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('multimedia/', multimedia.nombre)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                for (let i = 0; i < this.multimedia.length; i++) { 
                    if (this.multimedia[i].nombre == data.nombre )
                        this.multimedia.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }
    }

    copyLink(text:string) {
        const event = (e: ClipboardEvent) => {
            e.clipboardData?.setData('text/plain', this.apiService.baseUrl + '/' + text);
            e.preventDefault();
        }
        document.addEventListener('copy', event);
        document.execCommand('copy');
        document.removeEventListener('copy', event);
    }

    setFile(event:any) {
        this.file = event.target.files[0];
        
        let formData:FormData = new FormData();
        formData.append('file', this.file);

        this.loading = true;
        this.apiService.store('multimedia', formData)
            .pipe(this.untilDestroyed())
            .subscribe(foto => {
            this.multimedia.unshift(foto);
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.multimedia.path + '?page='+ event.page)
            .pipe(this.untilDestroyed())
            .subscribe(multimedia => { 
            this.multimedia = multimedia;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
