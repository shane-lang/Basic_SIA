import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { MatSidenavModule } from '@angular/material/sidenav';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    RouterOutlet,
    MatSidenavModule
  ],
  templateUrl: './app.html',
  styleUrls: ['./app.css'],
  template: '<router-outlet></router-outlet>',
  
})
export class App {}
