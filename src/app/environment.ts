export const environment = {
  production: true,
  apiBase: 'http://localhost/sia-api',

  get api()          { return `${this.apiBase}/api.php`; },
  get authApi()      { return `${this.apiBase}/auth.php`; },
  get adminApi()     { return `${this.apiBase}/admin.php`; },
  get enrollApi()    { return `${this.apiBase}/enrollment.php`; },
  get gradesApi()    { return `${this.apiBase}/grades.php`; },
  get registrarApi() { return `${this.apiBase}/registrar.php`; },
  get accountingApi(){ return `${this.apiBase}/Accounting.php`; },
  get dashboardApi() { return `${this.apiBase}/dashboard.php`; },
  get facultyApi()   { return `${this.apiBase}/faculty.php`; },
  get receiptApi()   { return `${this.apiBase}/receipt.php`; },
  get retentionApi() { return `${this.apiBase}/retention.php`; },
  get notifyApi()    { return `${this.apiBase}/notify.php`; },
  get uploadBase()   { return `${this.apiBase}/uploads`; },
};