import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Student } from '../masterlist/masterlist';
import { environment } from '../../environment';

interface CoeFormData {
  purpose: string;
  customPurpose: string;
  copies: number;
  academicYear: string;
  issuedBy: string;
}

@Component({
  selector: 'app-coe-generator',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './coe-generator.html',
  styleUrl: './coe-generator.css',
})
export class CoeGeneratorComponent implements OnInit {
  private api = environment.registrarApi;

  // ── Generate PDF ──────────────────────────────────────────
  students: Student[] = [];
  filtered: Student[] = [];
  isLoading = false;
  searchQuery = '';
  statusFilter = 'Enrolled';
  selected: Student | null = null;
  isGenerating = false;
  form: CoeFormData = {
    purpose: 'General Purpose',
    customPurpose: '',
    copies: 1,
    academicYear: '',
    issuedBy: '',
  };

  notification: { type: 'success' | 'error'; message: string } | null = null;

  purposeOptions = [
    'General Purpose',
    'Scholarship Application',
    'Bank / Loan Requirements',
    'Government ID / SSS / PhilHealth / Pag-IBIG',
    'Employment Requirements',
    'Transfer / Study Abroad',
    'Other (specify below)',
  ];

  currentAY = this.computeAY();

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    try {
      const u = JSON.parse(sessionStorage.getItem('currentUser') ?? '{}');
      this.form.issuedBy = `${u.first_name || ''} ${u.last_name || ''}`.trim();
    } catch {}
    this.form.academicYear = this.currentAY;
    this.loadStudents();
  }

  private computeAY(): string {
    const y = new Date().getFullYear();
    return new Date().getMonth() >= 5 ? `AY ${y}-${y + 1}` : `AY ${y - 1}-${y}`;
  }


  private buildCoeFormPDF(jsPDF: any, d: any): void {
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const W = 210, H = 297, ML = 15, MR = 15, CW = W - ML - MR;

    // shorthand helpers
    const B  = () => doc.setFont('helvetica', 'bold');
    const N  = () => doc.setFont('helvetica', 'normal');
    const BI = () => doc.setFont('helvetica', 'bolditalic');
    const sz = (n: number) => doc.setFontSize(n);
    const lw = (n: number) => doc.setLineWidth(n);
    const ln = (x1:number,y1:number,x2:number,y2:number) => doc.line(x1,y1,x2,y2);
    const rc = (x:number,y:number,w:number,h:number) => doc.rect(x,y,w,h);
    const tx = (text:string, x:number, y:number) => doc.text(text, x, y);
    const txC = (text:string, x:number, y:number) => doc.text(text, x, y, {align:'center'});
    const txR = (text:string, x:number, y:number) => doc.text(text, x, y, {align:'right'});
    const clip = (s:string, n:number) => s ? s.substring(0,n) : '';
    const pesos = (n:number) => `P  ${n.toLocaleString('en-PH',{minimumFractionDigits:2})}`;

    // data extraction
    const fees    = d.fees ?? {};
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

    const dob = d.date_of_birth
      ? new Date(d.date_of_birth).toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'}).toUpperCase()
      : '';
    const approvedDate = d.approved_at
      ? new Date(d.approved_at).toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'}).toUpperCase()
      : new Date().toLocaleDateString('en-US',{day:'2-digit',month:'short',year:'numeric'}).toUpperCase();
    const sName = `${(d.last_name??'').toUpperCase()}, ${(d.first_name??'').toUpperCase()}${d.middle_name ? ', '+d.middle_name.toUpperCase() : ''}`;
    const regName = d.reg_first && d.reg_last
      ? `${d.reg_first.toUpperCase()} ${d.reg_last.toUpperCase()}`
      : 'JONNA MAY B. TABARANZA';
    const studentType = (d.student_type ?? '').toLowerCase();
    const ayNow = new Date().getFullYear();
    const ayStr = `${ayNow-1} - ${ayNow}`;

    let y = 8; // current Y (top margin)

    // ── HEADER ──────────────────────────────────────────────────────────────
    B(); sz(17); tx('ST. BENILDE', ML+28, y+7);
    B(); sz(7.5); tx('CENTER FOR GLOBAL COMPETENCE, INC.', ML+28, y+11.5);
    N(); sz(6.5); tx('#2647 RIZAL AVENUE, WEST BAJAC-BAJAC, OLONGAPO CITY', ML+28, y+15.5);
    tx('TELEFAX: (047) 223 - 9031', ML+28, y+19);

    // AY/Sem/Type box
    const bx = W-MR-52, by = y+5, bw = 50, bh = 30;
    lw(0.5); rc(bx, by, bw, bh);
    N(); sz(6.5); tx('Academic Yr.:', bx+2, by+5);
    B(); sz(7); tx(ayStr, bx+24, by+5);
    N(); sz(6.5); tx('Semester:', bx+2, by+10);
    B(); sz(7); tx(d.semester ?? '1st', bx+24, by+10);
    lw(0.3); ln(bx+22, by, bx+22, by+bh);
    const types = ['New Student','Old Student','Transferee'];
    types.forEach((t,i) => {
      const ty = by+15+i*5;
      lw(0.4); rc(bx+2, ty, 3, 3);
      if ((i===0&&studentType.includes('new'))||(i===1&&studentType.includes('old'))||(i===2&&studentType.includes('transfer'))) {
        B(); sz(7); tx('X', bx+2.3, ty+2.5);
      }
      N(); sz(6.5); tx(t, bx+6.5, ty+2.5);
    });

    y += 23;

    // Title
    B(); sz(12); txC('CERTIFICATE OF ENROLLMENT', W/2, y+5);
    y += 10;

    // ── STUDENT INFO TABLE ───────────────────────────────────────────────────
    const c1w = CW*0.56, c2w = CW-c1w, rh = 7.5;
    const GRAY = [85,85,85] as any;
    const infoRows: [string,string,string,string][] = [
      ['Course & Year:',       clip(`${d.program??''} ${d.year_level??''} - ${d.student_category??'Regular'}`,60), 'Department:',                d.department??''],
      ['Name:',                sName,                                                                              'Student ID #:',              d.student_number??''],
      ['Address:',             clip(d.address??'',58),                                                             'Birth Date:',                dob],
      ['Name of Guardian:',    clip(d.guardian_name??'',45),                                                       "Student's Contact #:",       d.phone??''],
      ['Address of Guardian:', clip(d.guardian_address??'',58),                                                    "Guardian's Contact #:",      d.guardian_contact??''],
      ['Last School Attended:',clip(d.last_school_attended??'',45),                                                'Relationship to Guardian:',  'PARENT/GUARDIAN'],
    ];
    infoRows.forEach(([l1,v1,l2,v2]) => {
      lw(0.4); rc(ML,y,c1w,rh); rc(ML+c1w,y,c2w,rh);
      doc.setTextColor(85,85,85); sz(5.5); N(); tx(l1, ML+1.5, y+3.5); tx(l2, ML+c1w+1.5, y+3.5);
      doc.setTextColor(0,0,0); sz(7); B();
      tx(v1, ML+1.5, y+6.8); tx(clip(String(v2),36), ML+c1w+1.5, y+6.8);
      y += rh;
    });
    y += 2;

    // ── SCHEDULE TABLE ───────────────────────────────────────────────────────
    const schedW = 120, asmW = CW-schedW, asmX = ML+schedW, srh = 6;
    const schedStartY = y;

    // Headers
    lw(0.5); rc(ML,y,schedW,7); rc(asmX,y,asmW,7);
    B(); sz(8); txC('CLASS SCHEDULE', ML+schedW/2, y+4.8);
    tx('A S S E S S M E N T :', asmX+2, y+4.8);
    y+=7;
    lw(0.5); rc(ML,y,schedW,5.5);
    B(); sz(7.5); txC('SUBJECTS', ML+schedW/2, y+3.8);
    y+=5.5;

    const COLS = [{l:'Time',w:13},{l:'Day',w:9},{l:'Room',w:12},{l:'Code',w:15},{l:'Description',w:52}];
    const UCOLS = [{l:'Lec',w:8},{l:'Lab',w:11}];
    const allCols = [...COLS,...UCOLS];
    const ux = ML+COLS.reduce((a,c)=>a+c.w,0);
    const uw = UCOLS.reduce((a,c)=>a+c.w,0);
    lw(0.4); rc(ux,y,uw,5.5); B(); sz(6); txC('Units', ux+uw/2, y+1.5);
    y+=6.5;

    let cx0=ML;
    allCols.forEach(col=>{ lw(0.4); rc(cx0,y,col.w,6.5); B(); sz(5.8); txC(col.l, cx0+col.w/2, y+2); cx0+=col.w; });
    y+=6.5;

    // Assessment rows alongside subjects
    const feeRows:[string,number|null][] = [
      ['Tuition Fee',        tuition>0?tuition:null],
      ['Miscellaneous',      misc>0?misc:null],
      ['Registration',       reg>0?reg:null],
      ['Laboratory Fee',     labF>0?labF:null],
      ['Supervision Fee',    null],
      ['NSTP Fee',           null],
      ['Energy Fee',         energy>0?energy:null],
      ['TOTAL',              tuition+misc+reg+labF+energy],
      ['Discount',           discount>0?discount:null],
      ['All-in-Fee',         allIn>0?allIn:null],
      ['Installment Charge', instFee>0?instFee:null],
    ];
    let ay = y;
    feeRows.forEach(([lbl,val])=>{
      lw(0.4); rc(asmX,ay,asmW,srh);
      N(); sz(6.5); tx(lbl, asmX+1.5, ay+4);
      if(val!==null&&val!==undefined&&val>0){ B(); sz(7); txR(pesos(val), asmX+asmW-1.5, ay+4.2); }
      ay+=srh;
    });
    lw(1); rc(asmX,ay,asmW,9);
    B(); sz(6.5); tx('FINAL ASSESSMENT', asmX+1.5, ay+4);
    sz(9); txR(pesos(totalAmt), asmX+asmW-1.5, ay+7.5);

    // Subject rows
    const nRows = Math.max(subs.length,12);
    let totLec=0, totLab=0;
    for(let i=0;i<nRows;i++){
      const s = subs[i]??null;
      cx0=ML;
      allCols.forEach(col=>{ lw(0.4); rc(cx0,y,col.w,srh); cx0+=col.w; });
      if(s){
        const lec=parseInt(s.lec_units??s.credits??0), lab=parseInt(s.lab_units??0);
        cx0=ML; N(); sz(5.8);
        tx(clip(s.time??'',10), cx0+1, y+3.8); cx0+=COLS[0].w;
        tx(clip(s.day??'',6),   cx0+1, y+3.8); cx0+=COLS[1].w;
        tx(clip(s.room??'',7),  cx0+1, y+3.8); cx0+=COLS[2].w;
        B(); sz(6.5); tx(clip(s.code??'',9), cx0+1, y+3.8); cx0+=COLS[3].w;
        N(); sz(5.8); tx(clip(s.name??'',46), cx0+1, y+3.8); cx0+=COLS[4].w;
        B(); sz(7);
        txC(lec>0?String(lec):'0', cx0+UCOLS[0].w/2, y+3.8); cx0+=UCOLS[0].w;
        txC(lab>0?String(lab):'0', cx0+UCOLS[1].w/2, y+3.8);
        totLec+=lec; totLab+=lab;
      }
      y+=srh;
    }

    // Total rows
    const sdw = COLS.reduce((a,c)=>a+c.w,0);
    lw(0.4); rc(ML,y,sdw,srh); rc(ML+sdw,y,UCOLS[0].w,srh); rc(ML+sdw+UCOLS[0].w,y,UCOLS[1].w,srh);
    B(); sz(7); txC('Total Units', ML+sdw/2, y+3.8);
    txC(String(totLec), ML+sdw+UCOLS[0].w/2, y+3.8);
    txC(String(totLab), ML+sdw+UCOLS[0].w+UCOLS[1].w/2, y+3.8);
    y+=srh;
    rc(ML,y,sdw,srh); rc(ML+sdw,y,uw,srh);
    B(); sz(8); txC(String(totLec+totLab), ML+sdw+uw/2, y+3.8);
    y+=srh; y+=3;

    // ── SIGNATURES ───────────────────────────────────────────────────────────
    N(); sz(6.5); tx('Evaluated by:', ML, y+2);
    const axSig = ML+70;
    tx('Assessed by:', axSig, y+2);
    const sigY = y+14;
    B(); sz(8); tx('MARY JOANNES S. OLINO', ML+3, sigY+2);
    lw(0.5); ln(ML,sigY,ML+62,sigY);
    N(); sz(6); tx('Department Head/Coordinator', ML+3, sigY+3.5); tx('Signature over Printed Name', ML+3, sigY+7);
    B(); sz(8); tx('JHOMERA M. ONOYA', axSig+3, sigY+2);
    lw(0.5); ln(axSig,sigY,axSig+70,sigY);
    N(); sz(6); tx('Accounting Representative', axSig+3, sigY+3.5); tx('Signature over Printed Name', axSig+3, sigY+7);
    y = sigY+12;

    // ── PAYMENT SECTION + REMINDERS ─────────────────────────────────────────
    const payW = CW*0.57, pcw = payW/5;
    const remX = ML+payW+4, remW = MR-ML-payW-4;
    const payHdrs = ['','DOWNPAYMENT','PRELIM','MIDTERM','PRE-FINAL'];

    // Schedule of Payment
    lw(0.5); rc(ML,y,payW,6); B(); sz(7.5); txC('SCHEDULE OF PAYMENT', ML+payW/2, y+4); y+=6;
    payHdrs.forEach((h,i)=>{ lw(0.4); rc(ML+i*pcw,y,pcw,5.5); B(); sz(5.2); txC(h, ML+i*pcw+pcw/2, y+3.5); });
    y+=5.5;
    ['AMOUNT','DUE DATE'].forEach(lbl=>{
      for(let i=0;i<5;i++){ lw(0.4); rc(ML+i*pcw,y,pcw,6.5); }
      B(); sz(6.5); txC(lbl, ML+pcw/2, y+4);
      if(lbl==='AMOUNT'){ N(); sz(6.5); txC(`P${allIn.toLocaleString('en-PH',{maximumFractionDigits:0})} / ${instFee.toLocaleString('en-PH',{maximumFractionDigits:0})}`, ML+pcw+pcw/2, y+4); }
      y+=6.5;
    });
    y+=3;

    // Payment Record
    lw(0.5); rc(ML,y,payW,6); B(); sz(7.5); txC('PAYMENT RECORD', ML+payW/2, y+4); y+=6;
    payHdrs.forEach((h,i)=>{ lw(0.4); rc(ML+i*pcw,y,pcw,5.5); B(); sz(5.2); txC(h, ML+i*pcw+pcw/2, y+3.5); });
    y+=5.5;
    ['1st Payment','2nd Payment','3rd Payment','4th Payment','5th Payment','Total'].forEach(lbl=>{
      for(let i=0;i<5;i++){ lw(0.4); rc(ML+i*pcw,y,pcw,6); }
      B(); sz(6); txC(lbl, ML+pcw/2, y+3.8); y+=6;
    });
    N(); sz(5.3); tx('*Note: Record the payments made by placing the QRs and Amount paid.', ML, y+2.5);

    // ── REMINDERS ON STUDENT'S ACTIVITIES ────────────────────────────────────
    let ry = sigY+12; // align top with payment section
    B(); sz(6.5); tx("Reminders on Student's Activities", remX, ry); ry+=4;
    N(); sz(5.5); B(); tx('I. School Wide', remX, ry); tx('Date', remX+remW*0.60, ry); tx('Expected', remX+remW*0.78, ry); ry+=3;
    tx('Expenses', remX+remW*0.78, ry); ry+=4;
    const schoolActs = [
      '1) Acquaintance Party &','   Team Building',
      '2) Sports Fests/PRISAA Fee','3) Conversational English',
      '   Culmination','4) Tree Planting/Disaster',
      '   Preparedness Training','5) Buwan ng Wika','6) Others',
    ];
    N(); sz(5.5);
    schoolActs.forEach(act=>{
      tx(act, remX, ry);
      lw(0.3); ln(remX+remW*0.55, ry+0.5, remX+remW*0.73, ry+0.5);
      ln(remX+remW*0.75, ry+0.5, remX+remW, ry+0.5);
      ry+=4;
    });
    ry+=2;
    B(); sz(5.5); tx('II. Departmental', remX, ry); tx('Date', remX+remW*0.60, ry); tx('Expected', remX+remW*0.78, ry); ry+=3;
    tx('Expenses', remX+remW*0.78, ry); ry+=4;
    for(let i=1;i<=5;i++){
      N(); sz(5.5); tx(`${i})`, remX, ry);
      lw(0.3); ln(remX+5, ry+0.5, remX+remW*0.53, ry+0.5);
      ln(remX+remW*0.55, ry+0.5, remX+remW*0.73, ry+0.5);
      ln(remX+remW*0.75, ry+0.5, remX+remW, ry+0.5);
      ry+=4.5;
    }

    // ── BOTTOM CERTS ─────────────────────────────────────────────────────────
    y += 6;
    const halfW = CW/2-4;
    // Left cert
    BI(); sz(6.5);
    ['I hereby certify that I have TAKEN and PASSED',
     'the pre-requisite subjects. OTHERWISE, I will not claim',
     'credits for the above-listed subjects.'].forEach((line,j)=>tx(line, ML, y-j*4));
    const sig2Y = y+16;
    B(); sz(7.5); tx(sName, ML+3, sig2Y+2);
    lw(0.5); ln(ML, sig2Y, ML+halfW, sig2Y);
    N(); sz(6); tx("Student's Signature over Printed Name", ML+3, sig2Y+4);

    // Right cert
    const rx2 = ML+halfW+6;
    N(); sz(6.5); tx('I hereby certify that the above-named student is', rx2, y);
    BI(); sz(6.5); tx('OFFICIALLY ENROLLED', rx2, y+4.5);
    N(); sz(6.5); tx(' in the above-listed subjects.', rx2+33, y+4.5);
    B(); sz(7.5); tx(regName, rx2+3, sig2Y+2);
    lw(0.5); ln(rx2, sig2Y, rx2+halfW, sig2Y);
    N(); sz(6); tx('College Registrar', rx2+3, sig2Y+4);
    y = sig2Y+10;

    // Copy for
    N(); sz(7); tx('Copy for:', ML, y);
    let cxCopy = ML+18;
    [{l:'Dept.\nCoord.',w:20},{l:"Cashier's",w:18},{l:"Registrar's",w:18},{l:"Student's",w:18}].forEach(({l,w})=>{
      lw(0.4); rc(cxCopy, y-5, w, 6.5);
      sz(5.8); const pts=l.split('\n');
      tx(pts[0], cxCopy+1, y-0.5);
      if(pts[1]) tx(pts[1], cxCopy+1, y-4);
      cxCopy+=w+2;
    });

    // ── OFFICIAL ENROLLED STAMP ───────────────────────────────────────────────
    const stX = W-MR-55, stY = H-52;
    N(); sz(5.2); txC('ST. BENILDE CENTER FOR GLOBAL COMPETENCE, INC.', stX+27, stY+22);
    txC('PLACE STAMP', stX+27, stY+18.5);
    txC('OFFICE OF THE REGISTRAR', stX+27, stY+15.5);
    doc.setDrawColor(0,85,0); lw(2);
    rc(stX, stY+1, 54, 14);
    doc.setTextColor(0,85,0); B(); sz(15);
    txC('OFFICIALLY', stX+27, stY+11);
    txC('ENROLLED',   stX+27, stY+5);
    doc.setTextColor(0,0,0); doc.setDrawColor(0,0,0); B(); sz(8);
    txC(approvedDate, stX+27, stY-2);

    // Footer
    N(); sz(5.8);
    const now = new Date();
    tx(`Processing Time/Date: ${now.toLocaleTimeString('en-PH')} | ${now.toLocaleDateString('en-PH')}`, ML, H-5);

    doc.save(`COE_${d.student_number??d.last_name}_${d.last_name}.pdf`);
  }


  payBadgeClass(ps: string): string {
    if (ps === 'Paid' || ps === 'Free') return 'pay-paid';
    if (ps === 'Partial' || ps === 'Partially Paid') return 'pay-partial';
    return 'pay-pending';
  }

  // ── GENERATE PDF TAB ─────────────────────────────────────────────────────
  loadStudents(): void {
    this.isLoading = true;
    const q = new URLSearchParams({ action: 'masterlist_students', status: this.statusFilter, limit: '200' }).toString();
    this.http.get<any>(`${this.api}?${q}`).subscribe({
      next: res => {
        this.isLoading = false;
        if (res.success) { this.students = res.students; this.applySearch(); }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });
  }

  applySearch(): void {
    const q = this.searchQuery.trim().toLowerCase();
    this.filtered = !q ? [...this.students] : this.students.filter(s =>
      s.firstName?.toLowerCase().includes(q) ||
      s.lastName?.toLowerCase().includes(q) ||
      s.studentNumber?.toLowerCase().includes(q) ||
      s.program?.toLowerCase().includes(q)
    );
  }

  selectStudent(s: Student): void {
    this.selected = s;
    this.cdr.detectChanges();
    setTimeout(() => document.getElementById('coe-form')?.scrollIntoView({ behavior: 'smooth' }), 50);
  }

  clearSelection(): void { this.selected = null; this.isGenerating = false; }

  get finalPurpose(): string {
    return this.form.purpose === 'Other (specify below)'
      ? (this.form.customPurpose.trim() || 'Other')
      : this.form.purpose;
  }

  get isPaid(): boolean {
    if (!this.selected) return false;
    const ps = (this.selected as any).paymentStatus ?? '';
    return ['Paid', 'Partial', 'Partially Paid', 'Free', ''].includes(ps);
  }

  get paymentStatusLabel(): string { return (this.selected as any)?.paymentStatus ?? 'Unknown'; }

  get paymentStatusClass(): string {
    const ps = this.paymentStatusLabel;
    if (ps === 'Paid' || ps === 'Free') return 'pay-paid';
    if (ps === 'Partial' || ps === 'Partially Paid') return 'pay-partial';
    return 'pay-pending';
  }

  async generatePDF(): Promise<void> {
    if (!this.selected || this.isGenerating) return;
    if (!this.isPaid) {
      this.notify('error', `Cannot generate COE — payment not verified (${this.paymentStatusLabel}).`);
      return;
    }
    this.isGenerating = true;
    this.cdr.detectChanges();

    const studentId = (this.selected as any).id ?? 0;
    this.http.get<any>(
      `${this.api}?action=coe_detail_by_student&student_id=${studentId}`
    ).subscribe({
      next: async res => {
        this.isGenerating = false;
        if (!res.success) {
          this.notify('error', res.message || 'Could not load student COE data.');
          this.cdr.detectChanges();
          return;
        }
        try {
          const jsPDF = await this.loadJsPDF();
          this.buildCoeFormPDF(jsPDF, res.coe);
          this.notify('success', 'COE PDF generated successfully!');
        } catch (e) {
          this.notify('error', 'PDF generation failed. Please try again.');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isGenerating = false;
        this.notify('error', 'Network error. Could not generate COE.');
        this.cdr.detectChanges();
      }
    });
  }

  private loadJsPDF(): Promise<any> {
    return new Promise((resolve, reject) => {
      if ((window as any).jspdf?.jsPDF) { resolve((window as any).jspdf.jsPDF); return; }
      const s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
      s.onload = () => { const j = (window as any).jspdf?.jsPDF; j ? resolve(j) : reject(new Error('jsPDF not found')); };
      s.onerror = () => reject(new Error('Failed to load jsPDF'));
      document.head.appendChild(s);
    });
  }

  notify(type: 'success' | 'error', message: string): void {
    this.notification = { type, message };
    setTimeout(() => { this.notification = null; this.cdr.detectChanges(); }, 6000);
  }

  formatDate(d: string): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  enrollStatusClass(s: string): string {
    return s === 'Enrolled' ? 'st-enrolled' : s === 'Pending' ? 'st-pending' : 'st-other';
  }
}