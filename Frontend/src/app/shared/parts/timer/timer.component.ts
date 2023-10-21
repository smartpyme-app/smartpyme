import { Component, OnInit, Input } from '@angular/core';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import * as moment from 'moment';
@Component({
  selector: 'app-timer',
  templateUrl: './timer.component.html'
})
export class TimerComponent implements OnInit {

    @Input() time:any;
    public timeAgo:number = 0;
    public timeResfresh:any;

    constructor(private apiService: ApiService, private alertService: AlertService) { }

    ngOnInit() {
        const today = new Date();
        this.timeAgo = moment(today).diff(this.time, "minutes");;
        console.log(this.time);
        this.timeResfresh = setInterval(()=> {
                this.loadAll();
        }, 60000);
    }

    loadAll(){
        const today = new Date();
        this.timeAgo = moment(today).diff(this.time, "minutes");;
        console.log(this.timeAgo);
    }

    ngOnDestroy(){
        clearInterval(this.timeResfresh);

    }


}
