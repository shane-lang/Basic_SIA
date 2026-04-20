import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule, DecimalPipe, UpperCasePipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';
import { PasswordGateService } from '../password-gate/password-gate.service';

interface CoeRequest {
  id: number;
  status: 'Pending' | 'Approved' | 'Rejected';
  control_number: string;
  approved_at: string;
  approved_by_name: string;
  registrar_notes: string;
  semester: string;
  school_year: string;
}

interface SemesterEntry {
  label: string;
  semester: string;
  school_year: string;
  has_approved_coe: boolean;
}

@Component({
  selector: 'app-coe',
  standalone: true,
  imports: [CommonModule, DecimalPipe, UpperCasePipe],
  templateUrl: './coe-student.html',
  styleUrl: './coe-student.css',
})
export class CoeComponent implements OnInit, OnDestroy {
  private apiUrl = environment.registrarApi;

  userId = (() => {
    try { return parseInt(JSON.parse(sessionStorage.getItem('currentUser') ?? '{}')?.id ?? '0'); }
    catch { return 0; }
  })();

  // ── Loading / UI state ────────────────────────────────────────────────────
  isLoading        = true;
  // ── Password gate inactivity lock (5 min) ─────────────────────────────────
  _locked          = true;
  private _lockTimer: any = null;
  private readonly _LOCK_MS = 300000;  // 5 minutes

  _startLockTimer(): void {
    this._clearLockTimer();
    this._lockTimer = setTimeout(() => {
      this._locked = true;
      // Clear the password-gate cache so re-navigating here after inactivity
      // requires the student to re-enter their password.
      sessionStorage.removeItem('pgv_ts_coe');
      this.cdr.detectChanges();
    }, this._LOCK_MS);
  }

  _clearLockTimer(): void {
    if (this._lockTimer) { clearTimeout(this._lockTimer); this._lockTimer = null; }
  }

  resetLockTimer(): void {
    if (!this._locked) this._startLockTimer();
  }


  isTermLoading    = false;   // spinner only on the COE panel when switching terms
  isPrinting       = false;
  notification: { type: 'success' | 'error' | 'info'; message: string } | null = null;

  // ── Eligibility (from coe_check_eligibility) ──────────────────────────────
  eligible         = false;
  enrollmentStatus = '';
  paymentStatus    = '';

  // ── Semester list + currently selected term ───────────────────────────────
  semesters: SemesterEntry[] = [];
  selectedSem: SemesterEntry | null = null;

  // ── Per-term COE data ─────────────────────────────────────────────────────
  approvedCoe: CoeRequest | null = null;
  coeData: any     = null;
  subjects: any[]  = [];
  fees: any        = null;

  // ── Parsed current-term values returned by eligibility check ─────────────
  // These are the exact semester / school_year strings stored in coe_requests,
  // used to default-select the right tab on load.
  private currentSemLabel  = '';
  private currentSchoolYear = '';

  get totalUnits(): number {
    // BUG-COE-UNITS-FIX: use credits (lec+lab total), not lec_units alone
    return this.subjects.reduce((sum, s) => sum + (+(s.credits ?? 0)), 0);
  }

  get hasApproved(): boolean { return !!this.approvedCoe; }

  // SHS / TVET / Transferee helpers — drive conditional display in template
  get isSHS():        boolean { return (this.coeData?.student_category ?? '').toUpperCase() === 'SHS'; }
  get isTVET():       boolean { return (this.coeData?.student_category ?? '').toUpperCase() === 'TVET'; }
  get isTransferee(): boolean { return (this.coeData?.student_type ?? '').toLowerCase() === 'transferee'; }
  // SHS/TVET non-transferees are free — hide Assessment section on COE
  get isSHSFree():    boolean { return this.isSHS  && !this.isTransferee; }
  get isTVETFree():   boolean { return this.isTVET && !this.isTransferee; }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef, private gate: PasswordGateService) {}

  async ngOnInit(): Promise<void> {
    // ── Password gate ─────────────────────────────────────────────────────────
    // NOTE: Do NOT clear pgv_ts_coe here — cache is only cleared on inactivity lock.
    const verified = await this.gate.requirePassword('COE');
    if (!verified) {
      this.isLoading = false;
      this.notification = { type: 'error', message: 'Password verification is required to view your COE.' };
      this.cdr.detectChanges();
      return;
    }
    this._locked = false;
    this._startLockTimer();
    this.loadAll();
  }

  ngOnDestroy(): void {
    this._clearLockTimer();  // stop JS timer only; lock state preserved across tabs
  }

  // ── Step 1: eligibility → Step 2: semester list → Step 3: load default term
  loadAll(): void {
    this.isLoading = true;

    this.http.get<any>(`${this.apiUrl}?action=coe_check_eligibility&user_id=${this.userId}`)
      .subscribe({
        next: res => {
          if (res.success) {
            this.eligible         = res.eligible;
            this.enrollmentStatus = res.enrollment_status;
            this.paymentStatus    = res.payment_status;
            // FIX COE-SWITCH-01: capture the parsed semester + school_year that the
            // backend now returns so we can pass them as exact filter params.
            // The raw students.semester string (e.g. "1st Semester, AY 2025-2026")
            // is NOT reliable for filtering — the split fields are.
            this.currentSemLabel   = res.semester    ?? '';
            this.currentSchoolYear = res.school_year ?? '';
          }
          this.loadSemesters();
        },
        error: () => this.loadSemesters()
      });
  }

  // ── Step 2: load semester list from coe_get_semesters ────────────────────
  loadSemesters(): void {
    this.http.get<any>(`${this.apiUrl}?action=coe_get_semesters`)
      .subscribe({
        next: res => {
          if (res.success && Array.isArray(res.semesters) && res.semesters.length > 0) {
            this.semesters = res.semesters;

            // ── Choose default tab ────────────────────────────────────────────
            // Priority:
            //   1. The current enrolled semester (from eligibility, exact match)
            //   2. The backend's suggested default (first with has_approved_coe)
            //   3. First entry in the list
            let defaultEntry: SemesterEntry | null = null;

            if (this.currentSemLabel && this.currentSchoolYear) {
              defaultEntry = this.semesters.find(s =>
                s.semester === this.currentSemLabel &&
                s.school_year === this.currentSchoolYear
              ) ?? null;
            }

            if (!defaultEntry && res.default_semester) {
              defaultEntry = this.semesters.find(s =>
                s.semester === res.default_semester.semester &&
                s.school_year === res.default_semester.school_year
              ) ?? null;
            }

            if (!defaultEntry) defaultEntry = this.semesters[0];

            this.selectSemester(defaultEntry, /* initialLoad */ true);
          } else {
            // No semester list — fall back to unfiltered load
            this.isLoading = false;
            this.loadRequestsForTerm(null);
          }
        },
        error: () => {
          this.isLoading = false;
          this.loadRequestsForTerm(null);
        }
      });
  }

  // ── Called when student clicks a semester tab ─────────────────────────────
  selectSemester(entry: SemesterEntry, initialLoad = false): void {
    if (!initialLoad && this.selectedSem?.semester === entry.semester &&
        this.selectedSem?.school_year === entry.school_year) return;

    this.selectedSem  = entry;
    this.approvedCoe  = null;
    this.coeData      = null;
    this.subjects     = [];
    this.fees         = null;

    if (initialLoad) {
      // Initial load — full page spinner is already showing
      this.loadRequestsForTerm(entry);
    } else {
      // Term switch — show only the COE panel spinner, not the full page
      this.isTermLoading = true;
      this.cdr.detectChanges();
      this.loadRequestsForTerm(entry);
    }
  }

  // ── Step 3 / on term switch: fetch COE requests filtered by term ──────────
  // FIX COE-SWITCH-01 (frontend side): previously loadRequests() called
  //   coe_get_my_requests with NO semester params.
  // The backend returned ALL coe rows, and .find(r => r.status === 'Approved')
  // always found the FIRST approved row — which was the current term's COE —
  // regardless of which semester tab was selected.
  //
  // Fix: always pass semester + school_year params so the backend's semester
  // filter runs, and only an exact-match COE for that term is returned.
  loadRequestsForTerm(entry: SemesterEntry | null): void {
    let url = `${this.apiUrl}?action=coe_get_my_requests&user_id=${this.userId}`;
    if (entry) {
      url += `&semester=${encodeURIComponent(entry.semester)}&school_year=${encodeURIComponent(entry.school_year)}`;
    }

    this.http.get<any>(url).subscribe({
      next: res => {
        this.isLoading     = false;
        this.isTermLoading = false;

        if (res.success) {
          const reqs: CoeRequest[] = res.requests ?? [];
          // Pick the first Approved COE in the filtered result set.
          // Because the backend now scopes the query to the requested semester,
          // this will be the correct term's document — not a past or future one.
          this.approvedCoe = reqs.find(r => r.status === 'Approved') ?? null;

          if (this.approvedCoe) {
            this.loadCoeDetail(this.approvedCoe.id);
          } else {
            this.cdr.detectChanges();
          }
        } else {
          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.isLoading     = false;
        this.isTermLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  // ── Load full COE detail (subjects + fees) for display / PDF ─────────────
  loadCoeDetail(coeId: number): void {
    this.http.get<any>(`${this.apiUrl}?action=coe_get_detail&id=${coeId}`)
      .subscribe({
        next: res => {
          this.isLoading     = false;
          this.isTermLoading = false;
          if (res.success && res.coe) {
            this.coeData  = res.coe;
            this.subjects = res.coe.subjects ?? [];
            this.fees     = res.coe.fees     ?? null;
          }
          this.cdr.detectChanges();
        },
        error: () => {
          this.isLoading     = false;
          this.isTermLoading = false;
          this.cdr.detectChanges();
        }
      });
  }

  // ── PDF download ──────────────────────────────────────────────────────────
  // FIX COE-PDF-REUSE-01: Reuse already-loaded coeData instead of making a
  // second API call. Eliminates double round-trip and the race condition where
  // session expiry between page-load and download caused a 401 error.
  printCOE(req: CoeRequest): void {
    if (req.status !== 'Approved' || this.isPrinting) return;
    this.isPrinting = true;
    this.notify('info', 'Preparing your COE PDF...');

    if (this.coeData) {
      this.loadJsPDF()
        .then(jsPDF => {
          this.isPrinting = false;
          this.buildCoeFormPDF(jsPDF, this.coeData);
          this.cdr.detectChanges();
        })
        .catch(() => {
          this.isPrinting = false;
          this.notify('error', 'PDF generation failed.');
          this.cdr.detectChanges();
        });
      return;
    }

    // Fallback: fetch fresh only if coeData was somehow cleared
    this.http.get<any>(`${this.apiUrl}?action=coe_get_detail&id=${req.id}`)
      .subscribe({
        next: async res => {
          this.isPrinting = false;
          if (!res.success) { this.notify('error', 'Could not load COE data.'); return; }
          try {
            const jsPDF = await this.loadJsPDF();
            this.buildCoeFormPDF(jsPDF, res.coe);
          } catch { this.notify('error', 'PDF generation failed.'); }
          this.cdr.detectChanges();
        },
        error: () => { this.isPrinting = false; this.notify('error', 'Network error.'); this.cdr.detectChanges(); }
      });
  }

  private loadJsPDF(): Promise<any> {
    return new Promise((resolve, reject) => {
      if ((window as any).jspdf?.jsPDF) { resolve((window as any).jspdf.jsPDF); return; }
      const s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
      s.onload = () => { const j = (window as any).jspdf?.jsPDF; j ? resolve(j) : reject(); };
      s.onerror = () => reject();
      document.head.appendChild(s);
    });
  }

  notify(type: 'success' | 'error' | 'info', message: string): void {
    this.notification = { type, message };
    setTimeout(() => { this.notification = null; this.cdr.detectChanges(); }, 5000);
  }

  formatDate(d: string): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
  }

  // ── PDF builder (unchanged) ───────────────────────────────────────────────
  private buildCoeFormPDF(jsPDF: any, d: any): void {
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const W = 210, H = 297, ML = 15, MR = 15, CW = W - ML - MR;
    const B  = () => doc.setFont('helvetica', 'bold');
    const N  = () => doc.setFont('helvetica', 'normal');
    const BI = () => doc.setFont('helvetica', 'bolditalic');
    const sz = (n: number) => doc.setFontSize(n);
    const lw = (n: number) => doc.setLineWidth(n);
    const ln = (x1:number,y1:number,x2:number,y2:number) => doc.line(x1,y1,x2,y2);
    const rc = (x:number,y:number,w:number,h:number) => doc.rect(x,y,w,h);
    const tx = (text:string, x:number, y:number) => doc.text(String(text||''), x, y);
    const txC = (text:string, x:number, y:number) => doc.text(String(text||''), x, y, {align:'center'});
    const txR = (text:string, x:number, y:number) => doc.text(String(text||''), x, y, {align:'right'});
    const clip = (s:string, n:number) => (s||'').substring(0,n);
    const pesos = (n:number) => `P  ${(+n).toLocaleString('en-PH',{minimumFractionDigits:2})}`;
    const fees = d.fees ?? {};
    const subs: any[] = d.subjects ?? [];
    const tuition  = parseFloat(fees.tuition_fee       ?? 0);
    const misc     = parseFloat(fees.miscellaneous_fee ?? 0);
    const reg      = parseFloat(fees.registration_fee  ?? 0);
    const labF     = parseFloat(fees.laboratory_fee    ?? 0);
    const energy   = parseFloat(fees.energy_fee        ?? 0);
    const discount = parseFloat(fees.discount          ?? 0);
    const instFee  = parseFloat(fees.installment_fee   ?? 0);
    const totalAmt = parseFloat(fees.total_assessment  ?? 0) || (tuition+misc+reg+labF+energy-discount+instFee);
    const allIn    = totalAmt - instFee;
    const dob = d.date_of_birth ? new Date(d.date_of_birth).toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'}).toUpperCase() : '';
    const approvedDate = d.approved_at ? new Date(d.approved_at).toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'}).toUpperCase() : new Date().toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'}).toUpperCase();
    const sName = `${(d.last_name??'').toUpperCase()}, ${(d.first_name??'').toUpperCase()}${d.middle_name ? ', '+d.middle_name.toUpperCase() : ''}`;
    const regName = d.reg_first && d.reg_last ? `${d.reg_first.toUpperCase()} ${d.reg_last.toUpperCase()}` : 'JONNA MAY B. TABARANZA';
    const studentType = (d.student_type ?? '').toLowerCase();
    // FIX COE-PDF-AY-01: Use the COE's own school_year, not the current calendar year.
    // Using new Date().getFullYear() caused past-term COEs to show the wrong AY in
    // the PDF header (e.g. a 2025-2026 COE viewed in 2026 would show "2025 - 2026"
    // but next year would silently become "2026 - 2027").
    const ayStr = d.school_year
      ? d.school_year.replace('-', ' - ')
      : (() => { const y = new Date().getFullYear(); return `${y-1} - ${y}`; })();
    let y = 8;
    B(); sz(17); tx('ST. BENILDE', ML+28, y+7);
    B(); sz(7.5); tx('CENTER FOR GLOBAL COMPETENCE, INC.', ML+28, y+11.5);
    N(); sz(6.5); tx('#2647 RIZAL AVENUE, WEST BAJAC-BAJAC, OLONGAPO CITY', ML+28, y+15.5);
    tx('TELEFAX: (047) 223 - 9031', ML+28, y+19);
    const bx = W-MR-52, by = y+5, bw = 50, bh = 30;
    const isSHSDoc  = (d.student_category ?? '').toUpperCase() === 'SHS';
    const isTVETDoc = (d.student_category ?? '').toUpperCase() === 'TVET';
    const isFreeSHS = isSHSDoc && studentType !== 'transferee';
    const isFreeTVET = isTVETDoc && studentType !== 'transferee';
    // Use grade_level (Grade 11/12) for SHS, fall back to year_level for College
    const displayGradeLevel = isSHSDoc
      ? (d.grade_level || (d.year_level?.includes('12') ? 'Grade 12' : 'Grade 11'))
      : (d.year_level ?? '');
    lw(0.5); rc(bx,by,bw,bh);
    N(); sz(6.5); tx('Academic Yr.:', bx+2, by+5); B(); sz(7); tx(ayStr, bx+24, by+5);
    // SHS: hide "Semester" (not used in PH K-12), show School Year + Grade Level
    N(); sz(6.5); tx(isSHSDoc ? 'Grade Level:' : 'Semester:', bx+2, by+10);
    B(); sz(7); tx(isSHSDoc ? displayGradeLevel : (d.semester??'1st'), bx+24, by+10);
    lw(0.3); ln(bx+22,by,bx+22,by+bh);
    ['New Student','Old Student','Transferee'].forEach((t,i)=>{ const ty=by+15+i*5; lw(0.4); rc(bx+2,ty,3,3); if((i===0&&studentType.includes('new'))||(i===1&&studentType.includes('old'))||(i===2&&studentType.includes('transfer'))){ B(); sz(7); tx('X',bx+2.3,ty+2.5); } N(); sz(6.5); tx(t,bx+6.5,ty+2.5); });
    y+=23;
    B(); sz(12); txC('CERTIFICATE OF ENROLLMENT', W/2, y+5); y+=10;
    const c1w=CW*0.56, c2w=CW-c1w, rh=7.5;
    const infoRows:[string,string,string,string][]=[
      // SHS: label is "Strand & Grade Level"; College: "Course & Year"
      [isSHSDoc ? 'Strand & Grade Level:' : 'Course & Year:', clip(`${d.program??''} ${displayGradeLevel} - ${d.student_category??'Regular'}`,60), 'Department:', d.department??''],
      ['Name:', sName, 'Student ID #:', d.student_number??''],
      ['Address:', clip(d.address??'',58), 'Birth Date:', dob],
      ['Name of Guardian:', clip(d.guardian_name??'',45), "Student's Contact #:", d.phone??''],
      ['Address of Guardian:', clip(d.guardian_address??'',58), "Guardian's Contact #:", d.guardian_contact??''],
      ['Last School Attended:', clip(d.last_school_attended??'',45), 'Relationship to Guardian:', 'PARENT/GUARDIAN'],
    ];
    infoRows.forEach(([l1,v1,l2,v2])=>{ lw(0.4); rc(ML,y,c1w,rh); rc(ML+c1w,y,c2w,rh); doc.setTextColor(85,85,85); sz(5.5); N(); tx(l1,ML+1.5,y+3.5); tx(l2,ML+c1w+1.5,y+3.5); doc.setTextColor(0,0,0); sz(7); B(); tx(v1,ML+1.5,y+6.8); tx(clip(String(v2),36),ML+c1w+1.5,y+6.8); y+=rh; });
    y+=2;
    const schedW=120, asmW=CW-schedW, asmX=ML+schedW, srh=6;
    lw(0.5); rc(ML,y,schedW,7); rc(asmX,y,asmW,7); B(); sz(8); txC('CLASS SCHEDULE',ML+schedW/2,y+4.8);
    tx('A S S E S S M E N T :', asmX+2, y+4.8);
    y+=7;
    lw(0.5); rc(ML,y,schedW,5.5); B(); sz(7.5); txC('SUBJECTS',ML+schedW/2,y+3.8); y+=5.5;
    const COLS=[{l:'Time',w:13},{l:'Day',w:9},{l:'Room',w:12},{l:'Code',w:15},{l:'Description',w:52}];
    const UCOLS=[{l:'Lec',w:8},{l:'Lab',w:11}];
    const allCols=[...COLS,...UCOLS];
    const ux=ML+COLS.reduce((a,c)=>a+c.w,0), uw=UCOLS.reduce((a,c)=>a+c.w,0);
    lw(0.4); rc(ux,y,uw,5.5); B(); sz(6); txC('Units',ux+uw/2,y+1.5); y+=6.5;
    let cx0=ML; allCols.forEach(col=>{ lw(0.4); rc(cx0,y,col.w,6.5); B(); sz(5.8); txC(col.l,cx0+col.w/2,y+2); cx0+=col.w; }); y+=6.5;
    // SHS/TVET Free assessment: show government subsidy notice instead of fee rows
    const isFreeStudent = isFreeSHS || isFreeTVET;
    const freeSubsidyLabel = isFreeSHS
      ? 'FREE — SHS Voucher Program'
      : 'FREE — TESDA Scholarship';
    const freeSubsidyNote = isFreeSHS
      ? '(RA 10931 / DepEd SHS VP)'
      : '(PESFA / STEP Program)';
    const feeRows:[string,number|null][] = isFreeStudent
      ? [[freeSubsidyLabel,null],[freeSubsidyNote,null],['Tuition Fee',0],['Total',0]]
      : [['Tuition Fee',tuition>0?tuition:null],['Miscellaneous',misc>0?misc:null],['Registration',reg>0?reg:null],['Laboratory Fee',labF>0?labF:null],['Supervision Fee',null],['NSTP Fee',null],['Energy Fee',energy>0?energy:null],['TOTAL',tuition+misc+reg+labF+energy],['Discount',discount>0?discount:null],['All-in-Fee',allIn>0?allIn:null],['Installment Charge',instFee>0?instFee:null]];
    let ay=y;
    feeRows.forEach(([lbl,val])=>{ lw(0.4); rc(asmX,ay,asmW,srh); N(); sz(6.5); tx(lbl,asmX+1.5,ay+4); if(val!==null&&val>0){ B(); sz(7); txR(pesos(val),asmX+asmW-1.5,ay+4.2); } ay+=srh; });
    lw(1); rc(asmX,ay,asmW,9); B(); sz(6.5); tx('FINAL ASSESSMENT',asmX+1.5,ay+4); sz(9); txR(pesos(totalAmt),asmX+asmW-1.5,ay+7.5);
    const nRows=Math.max(subs.length,12); let totLec=0,totLab=0;
    for(let i=0;i<nRows;i++){ const s=subs[i]??null; cx0=ML; allCols.forEach(col=>{ lw(0.4); rc(cx0,y,col.w,srh); cx0+=col.w; }); if(s){ const lec=parseInt(s.lec_units??s.credits??0),lab=parseInt(s.lab_units??0); cx0=ML; N(); sz(5.8); tx(clip(s.time??'',10),cx0+1,y+3.8); cx0+=COLS[0].w; tx(clip(s.day??'',6),cx0+1,y+3.8); cx0+=COLS[1].w; tx(clip(s.room??'',7),cx0+1,y+3.8); cx0+=COLS[2].w; B(); sz(6.5); tx(clip(s.code??'',9),cx0+1,y+3.8); cx0+=COLS[3].w; N(); sz(5.8); tx(clip(s.name??'',46),cx0+1,y+3.8); cx0+=COLS[4].w; B(); sz(7); txC(lec>0?String(lec):'0',cx0+UCOLS[0].w/2,y+3.8); cx0+=UCOLS[0].w; txC(lab>0?String(lab):'0',cx0+UCOLS[1].w/2,y+3.8); totLec+=lec; totLab+=lab; } y+=srh; }
    const sdw=COLS.reduce((a,c)=>a+c.w,0);
    lw(0.4); rc(ML,y,sdw,srh); rc(ML+sdw,y,UCOLS[0].w,srh); rc(ML+sdw+UCOLS[0].w,y,UCOLS[1].w,srh);
    B(); sz(7); txC('Total Units',ML+sdw/2,y+3.8); txC(String(totLec),ML+sdw+UCOLS[0].w/2,y+3.8); txC(String(totLab),ML+sdw+UCOLS[0].w+UCOLS[1].w/2,y+3.8); y+=srh;
    rc(ML,y,sdw,srh); rc(ML+sdw,y,uw,srh); B(); sz(8); txC(String(totLec+totLab),ML+sdw+uw/2,y+3.8); y+=srh; y+=3;
    N(); sz(6.5); tx('Evaluated by:',ML,y+2); const axSig=ML+70; tx('Assessed by:',axSig,y+2); const sigY=y+14;
    B(); sz(8); tx('MARY JOANNES S. OLINO',ML+3,sigY+2); lw(0.5); ln(ML,sigY,ML+62,sigY); N(); sz(6); tx('Department Head/Coordinator',ML+3,sigY+3.5); tx('Signature over Printed Name',ML+3,sigY+7);
    B(); sz(8); tx('JHOMERA M. ONOYA',axSig+3,sigY+2); lw(0.5); ln(axSig,sigY,axSig+70,sigY); N(); sz(6); tx('Accounting Representative',axSig+3,sigY+3.5); tx('Signature over Printed Name',axSig+3,sigY+7);
    y=sigY+12;
    const payW=CW*0.57, pcw=payW/5, remX=ML+payW+4, remW=CW-payW-4;
    const payHdrs=['','DOWNPAYMENT','PRELIM','MIDTERM','PRE-FINAL'];
    lw(0.5); rc(ML,y,payW,6); B(); sz(7.5); txC('SCHEDULE OF PAYMENT',ML+payW/2,y+4); y+=6;
    payHdrs.forEach((h,i)=>{ lw(0.4); rc(ML+i*pcw,y,pcw,5.5); B(); sz(5.2); txC(h,ML+i*pcw+pcw/2,y+3.5); }); y+=5.5;
    ['AMOUNT','DUE DATE'].forEach(lbl=>{ for(let i=0;i<5;i++){ lw(0.4); rc(ML+i*pcw,y,pcw,6.5); } B(); sz(6.5); txC(lbl,ML+pcw/2,y+4); if(lbl==='AMOUNT'){ N(); sz(6.5); txC(`P${allIn.toLocaleString('en-PH',{maximumFractionDigits:0})} / ${instFee.toLocaleString('en-PH',{maximumFractionDigits:0})}`,ML+pcw+pcw/2,y+4); } y+=6.5; });
    y+=3;
    lw(0.5); rc(ML,y,payW,6); B(); sz(7.5); txC('PAYMENT RECORD',ML+payW/2,y+4); y+=6;
    payHdrs.forEach((h,i)=>{ lw(0.4); rc(ML+i*pcw,y,pcw,5.5); B(); sz(5.2); txC(h,ML+i*pcw+pcw/2,y+3.5); }); y+=5.5;
    ['1st Payment','2nd Payment','3rd Payment','4th Payment','5th Payment','Total'].forEach(lbl=>{ for(let i=0;i<5;i++){ lw(0.4); rc(ML+i*pcw,y,pcw,6); } B(); sz(6); txC(lbl,ML+pcw/2,y+3.8); y+=6; });
    N(); sz(5.3); tx('*Note: Record the payments made by placing the QRs and Amount paid.',ML,y+2.5);
    let ry=sigY+12; B(); sz(6.5); tx("Reminders on Student's Activities",remX,ry); ry+=4;
    sz(5.5); tx('I. School Wide',remX,ry); tx('Date',remX+remW*0.60,ry); tx('Expected',remX+remW*0.78,ry); ry+=3; tx('Expenses',remX+remW*0.78,ry); ry+=4;
    N(); sz(5.5); ['1) Acquaintance Party &','   Team Building','2) Sports Fests/PRISAA Fee','3) Conversational English','   Culmination','4) Tree Planting/Disaster','   Preparedness Training','5) Buwan ng Wika','6) Others'].forEach(act=>{ tx(act,remX,ry); lw(0.3); ln(remX+remW*0.55,ry+0.5,remX+remW*0.73,ry+0.5); ln(remX+remW*0.75,ry+0.5,remX+remW,ry+0.5); ry+=4; });
    ry+=2; B(); sz(5.5); tx('II. Departmental',remX,ry); tx('Date',remX+remW*0.60,ry); tx('Expected',remX+remW*0.78,ry); ry+=3; tx('Expenses',remX+remW*0.78,ry); ry+=4;
    for(let i=1;i<=5;i++){ N(); sz(5.5); tx(`${i})`,remX,ry); lw(0.3); ln(remX+5,ry+0.5,remX+remW*0.53,ry+0.5); ln(remX+remW*0.55,ry+0.5,remX+remW*0.73,ry+0.5); ln(remX+remW*0.75,ry+0.5,remX+remW,ry+0.5); ry+=4.5; }
    y+=6; const halfW=CW/2-4;
    BI(); sz(6.5); ['I hereby certify that I have TAKEN and PASSED','the pre-requisite subjects. OTHERWISE, I will not claim','credits for the above-listed subjects.'].forEach((line,j)=>tx(line,ML,y-j*4));
    const sig2Y=y+16; B(); sz(7.5); const sNameFull=`${(d.last_name??'').toUpperCase()}, ${(d.first_name??'').toUpperCase()}`; tx(sNameFull,ML+3,sig2Y+2); lw(0.5); ln(ML,sig2Y,ML+halfW,sig2Y); N(); sz(6); tx("Student's Signature over Printed Name",ML+3,sig2Y+4);
    const rx2=ML+halfW+6; N(); sz(6.5); tx('I hereby certify that the above-named student is',rx2,y); BI(); sz(6.5); tx('OFFICIALLY ENROLLED',rx2,y+4.5); N(); sz(6.5); tx(' in the above-listed subjects.',rx2+33,y+4.5);
    B(); sz(7.5); tx(regName,rx2+3,sig2Y+2); lw(0.5); ln(rx2,sig2Y,rx2+halfW,sig2Y); N(); sz(6); tx('College Registrar',rx2+3,sig2Y+4); y=sig2Y+10;
    N(); sz(7); tx('Copy for:',ML,y); let cxCopy=ML+18;
    [{l:'Dept.\nCoord.',w:20},{l:"Cashier's",w:18},{l:"Registrar's",w:18},{l:"Student's",w:18}].forEach(({l,w})=>{ lw(0.4); rc(cxCopy,y-5,w,6.5); sz(5.8); const pts=l.split('\n'); tx(pts[0],cxCopy+1,y-0.5); if(pts[1]) tx(pts[1],cxCopy+1,y-4); cxCopy+=w+2; });
    const stX=W-MR-55, stY=H-52;
    N(); sz(5.2); txC('ST. BENILDE CENTER FOR GLOBAL COMPETENCE, INC.',stX+27,stY+22); txC('PLACE STAMP',stX+27,stY+18.5); txC('OFFICE OF THE REGISTRAR',stX+27,stY+15.5);
    doc.setDrawColor(0,85,0); lw(2); rc(stX,stY+1,54,14); doc.setTextColor(0,85,0); B(); sz(15); txC('OFFICIALLY',stX+27,stY+11); txC('ENROLLED',stX+27,stY+5);
    doc.setTextColor(0,0,0); doc.setDrawColor(0,0,0); B(); sz(8); txC(approvedDate,stX+27,stY-2);
    N(); sz(5.8); const now=new Date(); tx(`Processing Time/Date: ${now.toLocaleTimeString('en-PH')} | ${now.toLocaleDateString('en-PH')}`,ML,H-5);
    // FIX COE-PDF-FILENAME-01: Previously used last_name as fallback for student_number
    // AND as the second segment, producing "COE_Nodado_Nodado.pdf" when no student_number.
    const pdfStudentId = d.student_number || `${d.last_name ?? 'student'}`.toUpperCase();
    doc.save(`COE_${pdfStudentId}_${(d.last_name ?? '').toUpperCase()}.pdf`);
  }
}