import { BootstrapContext, bootstrapApplication } from '@angular/platform-browser';
import { App } from './app/app';
import { appConfig } from './app/app.config';
import { provideClientHydration } from '@angular/platform-browser';
const bootstrap = (context: BootstrapContext) =>
    bootstrapApplication(App, appConfig, context);

export default bootstrap;
