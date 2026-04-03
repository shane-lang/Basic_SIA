import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Router } from '@angular/router';
import { environment } from '../../environment';
import { MaskEmailPipe } from '../../pipes/mask-email.pipe';
import { EnrollmentHistoryComponent } from '../enrollment-history/enrollment-history';
import { CoeCountPipe } from '../../pipes/coe-count.pipe';

// ── Add 'coe' to the tab union ──────────────────────────────────────────────
type MainTab = 'info' | 'student-masterlist' | 'subject-masterlist' | 'enrollment-history' | 'coe';

interface Student {
  id: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  middleName: string;
  suffix: string;
  fullName: string;
  email: string;
  phone: string;
  dateOfBirth: string;
  age: string;
  sex: string;
  religion: string;
  placeOfBirth: string;
  citizenship: string;
  motherTongue: string;
  address: string;
  lrnNo: string;
  psaBirthCertNo: string;
  isIndigenous: string;
  hasSpecialNeeds: string;
  specialNeedsDetails: string;
  hasAssistiveTech: string;
  assistiveTechDetails: string;
  strand: string;
  learningDelivery: string;
  lastSchoolAttended: string;
  guardianName: string;
  guardianAddress: string;
  guardianContact: string;
  program: string;
  yearLevel: string;
  semester: string;
  studentType: string;
  studentCategory: string;
  enrollmentStatus: string;
  paymentStatus: string;
  approvalStatus: string;
  isScholar: number;
  scholarType: string;
  enrollmentDate: string;
  initials: string;
}

interface SubjectRecord {
  studentNumber: string;
  fullName: string;
  program: string;
  yearLevel: string;
  courseCode: string;
  courseName: string;
  credits: number;
  instructor: string;
  semester: string;
  prelimGrade: number | null;
  midtermGrade: number | null;
  finalGrade: number | null;
  overall: number | null;
  remarks: string;
  status: string;
}

// ── COE record shape returned by coe_get_pending ─────────────────────────────
interface CoeRecord {
  id: number;
  student_id: number;
  control_number: string;
  purpose: string;
  copies: number;
  status: 'Pending' | 'Approved' | 'Rejected';
  registrar_notes: string;
  approved_by: number;
  approved_at: string;
  requested_at: string;
  approved_by_name: string;
}

@Component({
  selector: 'app-student-masterlist',
  standalone: true,
  imports: [CommonModule, FormsModule, MaskEmailPipe, EnrollmentHistoryComponent, CoeCountPipe],
  templateUrl: './student-masterlist.html',
  styleUrl: './student-masterlist.css',
})
export class StudentMasterlistComponent implements OnInit {
  private api = environment.registrarApi;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef, private router: Router) {}

  activeTab: MainTab = 'student-masterlist';

  // ── Shared filters ──────────────────────────────────────
  searchQuery    = '';
  filterCategory = '';
  filterProgram  = '';
  filterYearLevel = '';
  filterSemester  = '';
  filterStatus    = '';
  filterScholar   = '';
  searchTimeout: any;

  // ── Student Masterlist ──────────────────────────────────
  students: Student[] = [];
  isLoadingStudents = false;
  currentPage   = 1;
  totalPages    = 1;
  totalStudents = 0;
  programs: string[] = [];

  // ── Student Info (detail) ───────────────────────────────
  selectedStudent: Student | null = null;
  isLoadingDetail = false;

  // ── Subject Masterlist ──────────────────────────────────
  subjectRecords: SubjectRecord[] = [];
  isLoadingSubjects = false;
  subjectSearch = '';
  subjectFilterCourse = '';
  courses: string[] = [];
  subjectSearchTimeout: any;

  // ── COE History ─────────────────────────────────────────
  coeRecords: CoeRecord[]  = [];
  isLoadingCoe             = false;
  isLoadingCoeDetail       = false;
  coeDetail: any           = null;
  coeSubjects: any[]       = [];
  coeFees: any             = null;
  approvedCoeRecord: CoeRecord | null = null;
  isGeneratingCoe: { [id: number]: boolean } = {};

  get coeTotalUnits(): number {
    return this.coeSubjects.reduce((sum, s) => sum + (+(s.lec_units ?? s.credits ?? 0)), 0);
  }

  ngOnInit(): void {
    this.loadStudents();
  }

  // ── Load Students ────────────────────────────────────────
  loadStudents(page = 1): void {
    this.isLoadingStudents = true;
    this.currentPage = page;
    const p = new URLSearchParams({
      action: 'masterlist_students',
      page: String(page), limit: '20',
      ...(this.searchQuery     && { q: this.searchQuery }),
      ...(this.filterCategory  && { category: this.filterCategory }),
      ...(this.filterProgram   && { program: this.filterProgram }),
      ...(this.filterYearLevel && { year_level: this.filterYearLevel }),
      ...(this.filterSemester  && { semester: this.filterSemester }),
      ...(this.filterStatus    && { status: this.filterStatus }),
      ...(this.filterScholar !== '' && { scholar: this.filterScholar }),
    });
    this.http.get<any>(`${this.api}?${p}`).subscribe({
      next: (res) => {
        this.isLoadingStudents = false;
        if (res.success) {
          this.students      = res.students || [];
          this.totalPages    = res.totalPages || 1;
          this.totalStudents = res.total || 0;
          if (!this.programs.length) this.programs = res.programs || [];
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingStudents = false; this.cdr.detectChanges(); }
    });
  }

  onSearch(): void {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => this.loadStudents(1), 350);
  }

  clearFilters(): void {
    this.searchQuery = ''; this.filterCategory = ''; this.filterProgram = '';
    this.filterYearLevel = ''; this.filterSemester = ''; this.filterStatus = '';
    this.filterScholar = '';
    this.loadStudents(1);
  }

  prevPage(): void { if (this.currentPage > 1) this.loadStudents(this.currentPage - 1); }
  nextPage(): void { if (this.currentPage < this.totalPages) this.loadStudents(this.currentPage + 1); }

  // ── Student Info View ────────────────────────────────────
  viewStudentInfo(s: Student): void {
    this.selectedStudent = s;
    this.activeTab = 'info';
    this.cdr.detectChanges();
  }

  backToMasterlist(): void {
    this.selectedStudent = null;
    this.activeTab = 'student-masterlist';
    this.coeRecords = [];
    this.cdr.detectChanges();
  }

  // ── Navigate to Enrollment History inline tab ─────────────────────────────
  viewEnrollmentHistory(s: Student): void {
    this.selectedStudent = s;
    this.activeTab = 'enrollment-history';
    this.cdr.detectChanges();
  }

  // ── Navigate to COE History inline tab ───────────────────────────────────
  viewCoeHistory(s: Student): void {
    this.selectedStudent = s;
    this.activeTab = 'coe';
    this.coeRecords = [];
    this.loadCoeHistory(s.id);
    this.cdr.detectChanges();
  }

  loadCoeHistory(studentId: number): void {
    this.isLoadingCoe = true;
    this.coeDetail = null;
    this.coeSubjects = [];
    this.coeFees = null;
    this.approvedCoeRecord = null;
    this.http.get<any>(
      `${this.api}?action=coe_get_pending&status=All&student_id=${studentId}`
    ).subscribe({
      next: (res) => {
        this.isLoadingCoe = false;
        this.coeRecords = res.requests ?? [];
        // Auto-load the most recent approved COE for inline display
        const approved = this.coeRecords.find(r => r.status === 'Approved') ?? null;
        this.approvedCoeRecord = approved;
        if (approved) { this.loadCoeDetail(approved.id); }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingCoe = false; this.cdr.detectChanges(); },
    });
  }

  loadCoeDetail(coeId: number): void {
    this.isLoadingCoeDetail = true;
    this.http.get<any>(`${this.api}?action=coe_get_detail&id=${coeId}`)
      .subscribe({
        next: (res) => {
          this.isLoadingCoeDetail = false;
          if (res.success && res.coe) {
            this.coeDetail   = res.coe;
            this.coeSubjects = res.coe.subjects ?? [];
            this.coeFees     = res.coe.fees     ?? null;
          }
          this.cdr.detectChanges();
        },
        error: () => { this.isLoadingCoeDetail = false; this.cdr.detectChanges(); },
      });
  }

  // ── Generate / re-download a COE PDF from history ────────────────────────
  downloadCoePdf(coe: CoeRecord): void {
    if (this.isGeneratingCoe[coe.id]) return;
    this.isGeneratingCoe[coe.id] = true;
    this.cdr.detectChanges();

    this.http.get<any>(
      `${this.api}?action=coe_get_detail&id=${coe.id}`
    ).subscribe({
      next: async (res) => {
        this.isGeneratingCoe[coe.id] = false;
        if (!res.success) {
          alert('Could not load COE data: ' + (res.message || 'Unknown error'));
          this.cdr.detectChanges();
          return;
        }
        try {
          const jsPDF = await this.loadJsPDF();
          this.buildCoePdf(jsPDF, res.coe);
        } catch {
          alert('PDF generation failed. Please try again.');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isGeneratingCoe[coe.id] = false;
        alert('Network error. Could not generate COE PDF.');
        this.cdr.detectChanges();
      },
    });
  }

  private loadJsPDF(): Promise<any> {
    return new Promise((resolve, reject) => {
      if ((window as any).jspdf?.jsPDF) { resolve((window as any).jspdf.jsPDF); return; }
      const s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
      s.onload = () => {
        const j = (window as any).jspdf?.jsPDF;
        j ? resolve(j) : reject(new Error('jsPDF not found'));
      };
      s.onerror = () => reject(new Error('Failed to load jsPDF'));
      document.head.appendChild(s);
    });
  }

  private buildCoePdf(jsPDF: any, d: any): void {
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
    const ayNow = new Date().getFullYear();
    const ayStr = `${ayNow-1} - ${ayNow}`;
    let y = 8;
    B(); sz(17); tx('ST. BENILDE', ML+28, y+7);
    B(); sz(7.5); tx('CENTER FOR GLOBAL COMPETENCE, INC.', ML+28, y+11.5);
    N(); sz(6.5); tx('#2647 RIZAL AVENUE, WEST BAJAC-BAJAC, OLONGAPO CITY', ML+28, y+15.5);
    tx('TELEFAX: (047) 223 - 9031', ML+28, y+19);
    const bx = W-MR-52, by = y+5, bw = 50, bh = 30;
    lw(0.5); rc(bx,by,bw,bh);
    N(); sz(6.5); tx('Academic Yr.:', bx+2, by+5); B(); sz(7); tx(ayStr, bx+24, by+5);
    N(); sz(6.5); tx('Semester:', bx+2, by+10); B(); sz(7); tx(d.semester??'1st', bx+24, by+10);
    lw(0.3); ln(bx+22,by,bx+22,by+bh);
    ['New Student','Old Student','Transferee'].forEach((t,i)=>{ const ty=by+15+i*5; lw(0.4); rc(bx+2,ty,3,3); if((i===0&&studentType.includes('new'))||(i===1&&studentType.includes('old'))||(i===2&&studentType.includes('transfer'))){ B(); sz(7); tx('X',bx+2.3,ty+2.5); } N(); sz(6.5); tx(t,bx+6.5,ty+2.5); });
    y+=23;
    B(); sz(12); txC('CERTIFICATE OF ENROLLMENT', W/2, y+5); y+=10;
    const c1w=CW*0.56, c2w=CW-c1w, rh=7.5;
    const infoRows:[string,string,string,string][]=[
      ['Course & Year:', clip(`${d.program??''} ${d.year_level??''} - ${d.student_category??'Regular'}`,60), 'Department:', d.department??''],
      ['Name:', sName, 'Student ID #:', d.student_number??''],
      ['Address:', clip(d.address??'',58), 'Birth Date:', dob],
      ['Name of Guardian:', clip(d.guardian_name??'',45), "Student's Contact #:", d.phone??''],
      ['Address of Guardian:', clip(d.guardian_address??'',58), "Guardian's Contact #:", d.guardian_contact??''],
      ['Last School Attended:', clip(d.last_school_attended??'',45), 'Relationship to Guardian:', 'PARENT/GUARDIAN'],
    ];
    infoRows.forEach(([l1,v1,l2,v2])=>{ lw(0.4); rc(ML,y,c1w,rh); rc(ML+c1w,y,c2w,rh); doc.setTextColor(85,85,85); sz(5.5); N(); tx(l1,ML+1.5,y+3.5); tx(l2,ML+c1w+1.5,y+3.5); doc.setTextColor(0,0,0); sz(7); B(); tx(v1,ML+1.5,y+6.8); tx(clip(String(v2),36),ML+c1w+1.5,y+6.8); y+=rh; });
    y+=2;
    const schedW=120, asmW=CW-schedW, asmX=ML+schedW, srh=6;
    lw(0.5); rc(ML,y,schedW,7); rc(asmX,y,asmW,7); B(); sz(8); txC('CLASS SCHEDULE',ML+schedW/2,y+4.8); tx('A S S E S S M E N T :',asmX+2,y+4.8); y+=7;
    lw(0.5); rc(ML,y,schedW,5.5); B(); sz(7.5); txC('SUBJECTS',ML+schedW/2,y+3.8); y+=5.5;
    const COLS=[{l:'Time',w:13},{l:'Day',w:9},{l:'Room',w:12},{l:'Code',w:15},{l:'Description',w:52}];
    const UCOLS=[{l:'Lec',w:8},{l:'Lab',w:11}];
    const allCols=[...COLS,...UCOLS];
    const ux=ML+COLS.reduce((a,c)=>a+c.w,0), uw=UCOLS.reduce((a,c)=>a+c.w,0);
    lw(0.4); rc(ux,y,uw,5.5); B(); sz(6); txC('Units',ux+uw/2,y+1.5); y+=6.5;
    let cx0=ML; allCols.forEach(col=>{ lw(0.4); rc(cx0,y,col.w,6.5); B(); sz(5.8); txC(col.l,cx0+col.w/2,y+2); cx0+=col.w; }); y+=6.5;
    const feeRows:[string,number|null][]=[['Tuition Fee',tuition>0?tuition:null],['Miscellaneous',misc>0?misc:null],['Registration',reg>0?reg:null],['Laboratory Fee',labF>0?labF:null],['Supervision Fee',null],['NSTP Fee',null],['Energy Fee',energy>0?energy:null],['TOTAL',tuition+misc+reg+labF+energy],['Discount',discount>0?discount:null],['All-in-Fee',allIn>0?allIn:null],['Installment Charge',instFee>0?instFee:null]];
    let ay=y;
    feeRows.forEach(([lbl,val])=>{ lw(0.4); rc(asmX,ay,asmW,srh); N(); sz(6.5); tx(lbl,asmX+1.5,ay+4); if(val!==null&&val>0){ B(); sz(7); txR(pesos(val),asmX+asmW-1.5,ay+4.2); } ay+=srh; });
    lw(1); rc(asmX,ay,asmW,9); B(); sz(6.5); tx('FINAL ASSESSMENT',asmX+1.5,ay+4); sz(9); txR(pesos(totalAmt),asmX+asmW-1.5,ay+7.5);
    const nRows=Math.max(subs.length,12); let totLec=0,totLab=0;
    for(let i=0;i<nRows;i++){ const s=subs[i]??null; cx0=ML; allCols.forEach(col=>{ lw(0.4); rc(cx0,y,col.w,srh); cx0+=col.w; }); if(s){ const lec=parseInt(s.lec_units??s.credits??0),lab=parseInt(s.lab_units??0); cx0=ML; N(); sz(5.8); tx(clip(s.time??'',10),cx0+1,y+3.8); cx0+=COLS[0].w; tx(clip(s.day??'',6),cx0+1,y+3.8); cx0+=COLS[1].w; tx(clip(s.room??'',7),cx0+1,y+3.8); cx0+=COLS[2].w; B(); sz(6.5); tx(clip(s.code??s.course_code??'',9),cx0+1,y+3.8); cx0+=COLS[3].w; N(); sz(5.8); tx(clip(s.name??s.course_name??'',46),cx0+1,y+3.8); cx0+=COLS[4].w; B(); sz(7); txC(lec>0?String(lec):'0',cx0+UCOLS[0].w/2,y+3.8); cx0+=UCOLS[0].w; txC(lab>0?String(lab):'0',cx0+UCOLS[1].w/2,y+3.8); totLec+=lec; totLab+=lab; } y+=srh; }
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
    doc.save(`COE_${d.student_number??d.last_name}_${d.control_number??'draft'}.pdf`);
  }

  // ── Helpers ──────────────────────────────────────────────
  get previousSchools(): { level: string; school: string; year: string }[] {
    if (!this.selectedStudent?.lastSchoolAttended) return [];
    return this.selectedStudent.lastSchoolAttended.split(';').map(entry => {
      const clean = entry.trim();
      const match = clean.match(/^(.*?)\s*-\s*(.*?)\s*\(([^)]*)\)\s*$/);
      if (match) return { level: match[1].trim(), school: match[2].trim(), year: match[3].trim() };
      return { level: '—', school: clean, year: '—' };
    }).filter(s => s.school);
  }

  switchToSubjects(): void {
    this.activeTab = 'subject-masterlist';
    if (!this.subjectRecords.length) this.loadSubjectMasterlist();
    this.cdr.detectChanges();
  }

  loadSubjectMasterlist(): void {
    this.isLoadingSubjects = true;
    const p = new URLSearchParams({
      action: 'masterlist_subjects',
      ...(this.subjectSearch       && { q: this.subjectSearch }),
      ...(this.filterCategory      && { category: this.filterCategory }),
      ...(this.filterSemester      && { semester: this.filterSemester }),
      ...(this.subjectFilterCourse && { course: this.subjectFilterCourse }),
    });
    this.http.get<any>(`${this.api}?${p}`).subscribe({
      next: (res) => {
        this.isLoadingSubjects = false;
        if (res.success) {
          this.subjectRecords = res.records || [];
          if (!this.courses.length) this.courses = res.courses || [];
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingSubjects = false; this.cdr.detectChanges(); }
    });
  }

  onSubjectSearch(): void {
    clearTimeout(this.subjectSearchTimeout);
    this.subjectSearchTimeout = setTimeout(() => this.loadSubjectMasterlist(), 350);
  }

  fmtGrade(g: number | null): string { return g !== null ? g.toFixed(2) : '—'; }

  gradeClass(g: number | null): string {
    if (g === null) return '';
    if (g <= 1.5) return 'g-excel';
    if (g <= 2.0) return 'g-good';
    if (g <= 3.0) return 'g-pass';
    return 'g-fail';
  }

  statusClass(s: string): string {
    const m: Record<string, string> = {
      Enrolled: 'st-enrolled', Pending: 'st-pending',
      Approved: 'st-approved', Rejected: 'st-rejected',
      Completed: 'st-completed', Failed: 'st-failed'
    };
    return m[s] || '';
  }

  categoryClass(c: string): string {
    return c === 'College' ? 'cat-college' : c === 'SHS' ? 'cat-shs' : c === 'TVET' ? 'cat-tvet' : '';
  }

  coeStatusClass(s: string): string {
    const m: Record<string, string> = { Approved: 'coe-approved', Pending: 'coe-pending', Rejected: 'coe-rejected' };
    return m[s] ?? '';
  }

  formatDate(d: string): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  // ── Send Enrollment Report ────────────────────────────────────────────────
  isSendingEnrollReport: { [id: number]: boolean } = {};

  sendEnrollmentReport(studentId: number, studentName: string): void {
    if (this.isSendingEnrollReport[studentId]) return;
    if (!confirm('Send Enrollment Report to the parent/guardian of ' + studentName + '?\n\nMake sure a guardian email is saved in their record.')) return;

    this.isSendingEnrollReport[studentId] = true;
    this.cdr.detectChanges();

    this.http.post<any>(environment.notifyApi + '?action=send_enrollment_report', {
      student_id: studentId,
    }).subscribe({
      next: (res: any) => {
        this.isSendingEnrollReport[studentId] = false;
        if (res.success) {
          alert('✅ Enrollment report sent to:\n' + (res.recipients || []).map((r: any) => r.email).join('\n'));
        } else {
          alert('❌ Failed: ' + (res.message || 'Check guardian email in student record.'));
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSendingEnrollReport[studentId] = false;
        alert('❌ Network error. Could not send enrollment report.');
        this.cdr.detectChanges();
      }
    });
  }
}