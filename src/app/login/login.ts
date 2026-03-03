import { Component, ChangeDetectorRef, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { MatButtonModule } from '@angular/material/button';
import { MatInputModule } from '@angular/material/input';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { PLATFORM_ID, inject } from '@angular/core';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, MatButtonModule, MatInputModule, MatCardModule, MatFormFieldModule],
  templateUrl: './login.html',
  styleUrls: ['./login.css']
})
export class LoginComponent implements OnInit, OnDestroy {
  private apiUrl     = 'http://localhost/sia-api/enrollment.php';
  private authUrl    = 'http://localhost/sia-api/auth.php';
  private adminUrl   = 'http://localhost/sia-api/admin.php';
  private accountingUrl = 'http://localhost/sia-api/accounting.php';
  private platformId = inject(PLATFORM_ID);

  view: 'login' | 'enroll' = 'login';

  // ── Login ────────────────────────────────────────────────────
  email = ''; password = ''; errorMessage = ''; successMessage = ''; loading = false;

  // ── Wizard ───────────────────────────────────────────────────
  enrollStep: 'program' | 'info' | 'documents' | 'tor-review' | 'account' = 'program';
  enrollError = ''; isSubmitting = false;

  // Step 1
  studentTypeCategory: 'College' | 'SHS' | 'TVET' | '' = '';
  selectedProgram = ''; selectedProgramName = ''; selectedDepartment = '';
  // Step 1 — sub-selection state
  selectedDept      = '';   // College: selected department
  selectedGradeLevel = '';  // SHS: 'Grade 11' | 'Grade 12'
  selectedTvetType  = '';   // TVET: 'Diploma' | 'NC II'

  // Step 1 — Programs loaded from DB
  programsLoading = false;
  programsByType: Record<string, { id: string; name: string; dept: string }[]> = {
    College: [], SHS: [], TVET: []
  };

  get availablePrograms() { return this.studentTypeCategory ? (this.programsByType[this.studentTypeCategory] || []) : []; }

  get groupedPrograms(): { dept: string; programs: { id: string; name: string; dept: string }[] }[] {
    const map: Record<string, { id: string; name: string; dept: string }[]> = {};
    this.availablePrograms.forEach(p => { if (!map[p.dept]) map[p.dept] = []; map[p.dept].push(p); });
    return Object.entries(map).map(([dept, programs]) => ({ dept, programs }));
  }

  // Step 2 — Personal Info
  regForm = {
    lastName: '', firstName: '', middleName: '', suffix: '',
    studentType: 'New' as 'New' | 'Old' | 'Transferee',
    lrnNo: '', dateOfBirth: '', lastSchoolAttended: '', psaBirthCertNo: '',
    sex: '' as 'Male' | 'Female' | '', religion: '', age: '', placeOfBirth: '', citizenship: '',
    homeAddress: '', contactNumber: '',
    isIndigenous: '' as 'Yes' | 'No' | '',
    motherTongue: '',
    hasSpecialNeeds: '' as 'Yes' | 'No' | '', specialNeedsDetails: '',
    hasAssistiveTech: '' as 'Yes' | 'No' | '', assistiveTechDetails: '',
    strand: '',
    learningDelivery: '' as 'Face to Face' | 'Online' | 'Modular' | 'Combination of Face to face and Online' | 'Blended Methods of Learning' | '',
    guardianName: '', guardianAddress: '', guardianContact: '',
    yearLevel: '1st Year',
    semesterEnroll: '' as string,
    ayYear: '',
  };

  strandOptions = [
    'Accountancy Business and Management (ABM)',
    'Humanities Social Science Strand (HUMSS)',
    'Technical - Vocational and Livelihood - Home Economics (TVL-HE)',
    'Technical - Vocational and Livelihood - Information & Communications Technology (TVL-ICT)',
    'General Academic Strand (GAS)',
  ];
  deliveryOptions = ['Face to Face','Online','Modular','Combination of Face to face and Online','Blended Methods of Learning'];

  // ── Previous Schools (Step 2) ────────────────────────────────
  previousSchools: { level: string; schoolName: string; schoolYear: string }[] = [
    { level: '', schoolName: '', schoolYear: '' }
  ];

  addPreviousSchool(): void {
    this.previousSchools.push({ level: '', schoolName: '', schoolYear: '' });
  }

  removePreviousSchool(index: number): void {
    if (this.previousSchools.length > 1) {
      this.previousSchools.splice(index, 1);
    }
  }

  get yearLevelOptions(): string[] {
    if (this.studentTypeCategory === 'SHS') return ['Grade 11', 'Grade 12'];
    if (this.studentTypeCategory === 'TVET') return ['1st Year', '2nd Year'];
    return ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
  }

  get isSHS(): boolean { return this.studentTypeCategory === 'SHS'; }
  get isTVET(): boolean { return this.studentTypeCategory === 'TVET'; }
  get isCollege(): boolean { return this.studentTypeCategory === 'College'; }

  // Step 3 — Documents
  torFile: File | null = null;       torFileName = '';
  goodMoralFile: File | null = null; goodMoralFileName = '';
  psaFile: File | null = null;       psaFileName = '';
  form138File: File | null = null;   form138FileName = '';
  picFile: File | null = null;       picFileName = '';

  isScholar = false; scholarType = ''; scholarGrantor = ''; scholarshipAmount = 0;
  scholarTypes = ['CHED Scholarship','TESDA Scholarship','Local Government Unit (LGU) Scholarship',
    'School-Based Scholarship','Private Scholarship / Foundation','Sibling Discount',
    'Faculty/Staff Dependent Discount','Other'];

  paymentMethod: 'GCash' | 'Cash' = 'GCash';
  tuitionAmount = 25000;
  get discountedAmount(): number {
    return this.isScholar && this.scholarshipAmount > 0 ? Math.max(0, this.tuitionAmount - this.scholarshipAmount) : this.tuitionAmount;
  }

  // ── Fee Preview (computed from program units) ─────────────
  feePreview: {
    units: number;
    tuitionFee: number; miscellaneousFee: number; registrationFee: number;
    laboratoryFee: number; energyFee: number;
    subtotal: number; discount: number; installmentFee: number; totalAssessment: number;
  } | null = null;
  isFeePreviewLoading = false;
  feePreviewError = '';

  // ── Program course preview (for transferees in Step 3) ────
  programCourses: { code: string; name: string; credits: number; yearLevel: string; semester: string }[] = [];
  isProgramCoursesLoading = false;

  get isTransfereeEnrolling(): boolean { return this.regForm.studentType === 'Transferee'; }

  // ── TOR Review step (inside Step 3 for Transferees) ──────────────────
  torReviewPhase: 'idle' | 'sending' | 'waiting' | 'done' | 'rejected' = 'idle';
  torReviewError = '';
  torReviewStudentId = 0;
  torCreditedCodes: Set<string> = new Set();
  torEvalResult: {
    creditedUnits: number; approvedUnits: number; programUnits: number;
    creditedSubjects: { courseId: number; code: string; name: string; credits: number }[];
    registrarNotes: string; evaluatedAt: string;
    fee: { units:number; tuitionFee:number; miscellaneousFee:number; registrationFee:number;
           laboratoryFee:number; energyFee:number; subtotal:number; discount:number;
           installmentFee:number; totalAssessment:number } | null;
  } | null = null;
  private torPollTimer: any = null;

  // ── Payment Plan ──────────────────────────────────────────
  // 'full' = pay everything now
  // 'installment' = DP + Prelim + Midterm + Finals (each = total / 4)
  paymentPlan: 'full' | 'installment' = 'full';

  /** Active total assessment — uses TOR fee for transferees, feePreview for regular students */
  get activeTotalAssessment(): number {
    if (this.torEvalResult?.fee?.totalAssessment) return this.torEvalResult.fee.totalAssessment;
    if (this.feePreview?.totalAssessment) return this.feePreview.totalAssessment;
    return 0;
  }

  get installmentAmount(): number {
    if (!this.activeTotalAssessment) return 0;
    return Math.ceil(this.activeTotalAssessment / 4);
  }

  get dpAmount(): number      { return this.installmentAmount; }
  get prelimAmount(): number  { return this.installmentAmount; }
  get midtermAmount(): number { return this.installmentAmount; }
  get finalsAmount(): number  {
    if (!this.activeTotalAssessment) return 0;
    return this.activeTotalAssessment - (this.installmentAmount * 3);
  }

  // Step 4
  accountForm = { email: '', password: '', confirmPassword: '' };

  // ── SHS / TVET Fee State ─────────────────────────────────
  shsFeeResult: { isFree: boolean; fees: any } | null = null;
  tvetFeeResult: { isFree: boolean; fees: any } | null = null;
  isSHSFeeLoading = false;
  isTVETFeeLoading = false;

  // ── SHS-specific step tracking ───────────────────────────
  // SHS: gradeLevel → track → strand → program
  shsSelectedTrack = '';  // 'Academic' | 'TVL-HE' | 'TVL-ICT'
  shsTrackOptions = ['Academic Track', 'Technical-Vocational Livelihood (TVL)'];
  get shsStrandsByTrack(): { id: string; name: string; dept: string }[] {
    if (!this.shsSelectedTrack) return [];
    return (this.programsByType['SHS'] || []).filter(p => p.dept === this.shsSelectedTrack);
  }

  // ── TVET-specific step tracking ──────────────────────────
  // TVET: type (Diploma / NC II / NC III) → program
  tvetSelectedType = '';  // 'Diploma' | 'NCII' | 'NCIII'
  tvetTypeOptions = ['Diploma', 'NC II', 'NC III'];
  get tvetProgramsByType(): { id: string; name: string; dept: string }[] {
    if (!this.tvetSelectedType) return [];
    return (this.programsByType['TVET'] || []).filter(p => {
      const name = p.name.toUpperCase();
      if (this.tvetSelectedType === 'Diploma')  return name.includes('DIPLOMA') || name.startsWith('2-YR');
      if (this.tvetSelectedType === 'NC II')    return name.includes('NCII') || name.includes('NC II');
      if (this.tvetSelectedType === 'NC III')   return name.includes('NCIII') || name.includes('NC III');
      return false;
    });
  }

  constructor(private http: HttpClient, private router: Router, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    // Resume TOR polling if student refreshed mid-evaluation
    const saved = sessionStorage.getItem('torReviewStudentId');
    if (saved) {
      const sid = parseInt(saved, 10);
      if (sid > 0) {
        this.torReviewStudentId = sid;
        this.view        = 'enroll';
        this.enrollStep  = 'tor-review';
        this.torReviewPhase = 'waiting';
        console.log('[TOR RESUME] Resuming poll for student_id', sid);
        this.startTorPoll();
        this.cdr.detectChanges();
      }
    }
  }

  ngOnDestroy(): void {
    if (this.torPollTimer) clearInterval(this.torPollTimer);
  }



  // ══ LOAD PROGRAMS FROM DB ═════════════════════════════════════
  loadPrograms(): void {
    this.programsLoading = true;
    this.programsByType = { College: [], SHS: [], TVET: [] };
    this.http.get<any>(`${this.adminUrl}?action=get_programs`).subscribe({
      next: (res) => {
        this.programsLoading = false;
        if (res.success && res.programs) {
          res.programs.forEach((p: any) => {
            const level = p.level_type as 'College' | 'SHS' | 'TVET';
            if (!this.programsByType[level]) this.programsByType[level] = [];
            this.programsByType[level].push({
              id:   p.code || String(p.id),
              name: p.name,
              dept: p.department || level,
            });
          });
        }
        this.cdr.detectChanges();
      },
      error: () => { this.programsLoading = false; this.cdr.detectChanges(); }
    });
  }

  // ══ LOGIN ═════════════════════════════════════════════════════
  login(): void {
    if (!this.email || !this.password) { this.errorMessage = 'Please enter email and password'; return; }
    this.loading = true; this.errorMessage = ''; this.successMessage = '';
    this.http.post<any>(this.authUrl, { email: this.email, password: this.password }).subscribe({
      next: (res) => {
        if (res.success) {
          if (isPlatformBrowser(this.platformId)) {
            sessionStorage.setItem('currentUser', JSON.stringify(res.user));
            localStorage.setItem('currentUser', JSON.stringify(res.user));
            sessionStorage.setItem('token', res.token);
            localStorage.setItem('token', res.token);
          }
          this.loading = false;
          this.redirectByRole(res.user.role);
        } else { this.errorMessage = res.message || 'Login failed'; this.loading = false; this.cdr.detectChanges(); }
      },
      error: () => { this.errorMessage = 'Connection error. Make sure XAMPP is running.'; this.loading = false; this.cdr.detectChanges(); }
    });
  }

  private redirectByRole(role: string): void {
    const r: { [k: string]: string } = { student: '/student', admin: '/admin', accounting: '/accounting', registrar: '/registrar', faculty: '/admin' };
    this.router.navigate([r[role] || '/login']);
  }

  // ══ ENROLLMENT WIZARD ════════════════════════════════════════
  openEnrollment(): void {
    this.view = 'enroll'; this.enrollStep = 'program'; this.enrollError = '';
    this.studentTypeCategory = ''; this.selectedProgram = ''; this.selectedProgramName = ''; this.selectedDepartment = ''; this.selectedDept = ''; this.selectedGradeLevel = ''; this.selectedTvetType = '';
    this.regForm = { lastName:'',firstName:'',middleName:'',suffix:'',studentType:'New',lrnNo:'',dateOfBirth:'',lastSchoolAttended:'',psaBirthCertNo:'',sex:'',religion:'',age:'',placeOfBirth:'',citizenship:'',homeAddress:'',contactNumber:'',isIndigenous:'No',motherTongue:'',hasSpecialNeeds:'No',specialNeedsDetails:'',hasAssistiveTech:'No',assistiveTechDetails:'',strand:'',learningDelivery:'',guardianName:'',guardianAddress:'',guardianContact:'',yearLevel:'1st Year',semesterEnroll:'',ayYear:'' };
    this.torFile=null; this.goodMoralFile=null; this.psaFile=null; this.form138File=null; this.picFile=null;
    this.torFileName=''; this.goodMoralFileName=''; this.psaFileName=''; this.form138FileName=''; this.picFileName='';
    this.isScholar=false; this.scholarType=''; this.scholarGrantor=''; this.scholarshipAmount=0;
    this.paymentMethod='GCash'; this.paymentPlan='full';
    this.regForm.yearLevel = '1st Year'; this.regForm.semesterEnroll = '';
    this.feePreview=null; this.feePreviewError='';
    this.accountForm={email:'',password:'',confirmPassword:''};
    this.torReviewPhase='idle'; this.torReviewError=''; this.torReviewStudentId=0;
    this.torCreditedCodes=new Set(); this.torEvalResult=null;
    this.previousSchools = [{ level: '', schoolName: '', schoolYear: '' }];
    if(this.torPollTimer){clearInterval(this.torPollTimer);this.torPollTimer=null;}
    this.loadPrograms();
    this.cdr.detectChanges();
  }

  backToLogin(): void { this.view = 'login'; this.enrollError = ''; this.cdr.detectChanges(); }

  get stepNumber(): number { return ({program:1,info:2,documents:3,'tor-review':3,account:4} as any)[this.enrollStep] ?? 1; }

  studentTypeCategoryLabel(): string {
    return this.studentTypeCategory === 'College' ? '🎓 College' : this.studentTypeCategory === 'SHS' ? '📚 Senior High School' : this.studentTypeCategory === 'TVET' ? '🔧 TVET Program' : '';
  }

  // Step 1
  selectStudentTypeCategory(t: 'College' | 'SHS' | 'TVET'): void {
    this.studentTypeCategory = t;
    this.selectedProgram = ''; this.selectedProgramName = '';
    this.selectedDept = ''; this.selectedGradeLevel = ''; this.selectedTvetType = '';
    this.shsSelectedTrack = ''; this.tvetSelectedType = '';
    this.cdr.detectChanges();
  }

  selectDept(dept: string): void {
    this.selectedDept = dept;
    this.selectedProgram = ''; this.selectedProgramName = '';
    this.cdr.detectChanges();
  }

  selectGradeLevel(grade: string): void {
    this.selectedGradeLevel = grade;
    this.regForm.yearLevel  = grade;  // sync so Grade 11/12 is saved correctly
    this.selectedProgram = ''; this.selectedProgramName = '';
    this.cdr.detectChanges();
  }

  selectTvetType(type: string): void {
    this.selectedTvetType = type;
    this.selectedProgram = ''; this.selectedProgramName = '';
    this.cdr.detectChanges();
  }

  // Returns unique departments for the current level — fully dynamic from DB
  getDeptsForLevel(level: string): string[] {
    return [...new Set(
      (this.programsByType[level] || [])
        .map(p => p.dept)
        .filter(d => d && d.trim() !== '')
    )];
  }

  get collegeDepts(): string[] { return this.getDeptsForLevel('College'); }
  get shsDepts():     string[] { return this.getDeptsForLevel('SHS'); }
  get tvetDepts():    string[] { return this.getDeptsForLevel('TVET'); }

  get filteredPrograms(): { id: string; name: string; dept: string }[] {
    const all = this.availablePrograms;
    if (this.studentTypeCategory === 'College' && this.selectedDept) {
      return all.filter(p => p.dept === this.selectedDept);
    }
    if (this.studentTypeCategory === 'SHS' && this.selectedGradeLevel) {
      return all.filter(p => p.dept === this.selectedGradeLevel);
    }
    if (this.studentTypeCategory === 'TVET' && this.selectedTvetType) {
      return all.filter(p => p.dept === this.selectedTvetType);
    }
    return [];
  }
  selectProgram(p: { id: string; name: string; dept: string }): void { this.selectedProgram = p.id; this.selectedProgramName = p.name; this.selectedDepartment = p.dept; this.cdr.detectChanges(); }

  proceedFromProgram(): void {
    if (!this.studentTypeCategory) { this.enrollError = 'Please select a student level.'; this.cdr.detectChanges(); return; }
    if (!this.selectedProgram)     { this.enrollError = 'Please select a program.';        this.cdr.detectChanges(); return; }
    // Auto-set strand for SHS from the selected program name
    if (this.isSHS) { this.regForm.strand = this.selectedProgramName; }
    this.enrollError = ''; this.enrollStep = 'info'; this.cdr.detectChanges();
  }

  // Step 2 — per-field validation with console debug
  proceedFromInfo(): void {
    const f = this.regForm;

    // ── DEBUG: log every field value so we can see what's empty ──
    console.group('[STEP 2] proceedFromInfo — field values');
    console.log('lastName:',          JSON.stringify(f.lastName));
    console.log('firstName:',         JSON.stringify(f.firstName));
    console.log('studentType:',       JSON.stringify(f.studentType));
    console.log('lrnNo:',             JSON.stringify(f.lrnNo));
    console.log('dateOfBirth:',       JSON.stringify(f.dateOfBirth));
    console.log('lastSchoolAttended:',JSON.stringify(f.lastSchoolAttended));
    console.log('sex:',               JSON.stringify(f.sex));
    console.log('religion:',          JSON.stringify(f.religion));
    console.log('age:',               JSON.stringify(f.age));
    console.log('placeOfBirth:',      JSON.stringify(f.placeOfBirth));
    console.log('citizenship:',       JSON.stringify(f.citizenship));
    console.log('homeAddress:',       JSON.stringify(f.homeAddress));
    console.log('contactNumber:',     JSON.stringify(f.contactNumber));
    console.log('motherTongue:',      JSON.stringify(f.motherTongue));
    console.log('isIndigenous:',      JSON.stringify(f.isIndigenous));
    console.log('hasSpecialNeeds:',   JSON.stringify(f.hasSpecialNeeds));
    console.log('hasAssistiveTech:',  JSON.stringify(f.hasAssistiveTech));
    console.log('guardianName:',      JSON.stringify(f.guardianName));
    console.log('guardianAddress:',   JSON.stringify(f.guardianAddress));
    console.log('guardianContact:',   JSON.stringify(f.guardianContact));
    console.log('studentTypeCategory (College/SHS/TVET):', this.studentTypeCategory);
    if (this.isSHS) {
      console.log('strand:',          JSON.stringify(f.strand));
      console.log('learningDelivery:',JSON.stringify(f.learningDelivery));
    }
    console.groupEnd();

    // ── Per-field validation (tells user exactly which field is missing) ──
    if (!f.lastName?.trim())           { this.enrollError = 'Last Name is required.';                this.cdr.detectChanges(); return; }
    if (!f.firstName?.trim())          { this.enrollError = 'First Name is required.';               this.cdr.detectChanges(); return; }
    if (!f.studentType)                { this.enrollError = 'Type of Student is required.';          this.cdr.detectChanges(); return; }
    if (!f.dateOfBirth)                { this.enrollError = 'Date of Birth is required.';            this.cdr.detectChanges(); return; }
    if (!f.lastSchoolAttended?.trim()) {
      // Auto-populate lastSchoolAttended from previousSchools for backend submission
      const filledSchools = this.previousSchools.filter(s => s.schoolName?.trim());
      if (filledSchools.length === 0) {
        this.enrollError = 'Please enter at least one Previous School Attended.';
        this.cdr.detectChanges(); return;
      }
      f.lastSchoolAttended = filledSchools.map(s => `${s.level} - ${s.schoolName} (${s.schoolYear})`).join('; ');
    }
    if (!f.sex)                        { this.enrollError = 'Sex is required.';                      this.cdr.detectChanges(); return; }
    if (!f.religion?.trim())           { this.enrollError = 'Religion is required.';                 this.cdr.detectChanges(); return; }
    if (!f.age?.toString().trim())     { this.enrollError = 'Age is required.';                      this.cdr.detectChanges(); return; }
    if (!f.placeOfBirth?.trim())       { this.enrollError = 'Place of Birth is required.';           this.cdr.detectChanges(); return; }
    if (!f.citizenship?.trim())        { this.enrollError = 'Citizenship is required.';              this.cdr.detectChanges(); return; }
    if (!f.homeAddress?.trim())        { this.enrollError = 'Home Address is required.';             this.cdr.detectChanges(); return; }
    if (!f.contactNumber?.trim())      { this.enrollError = 'Contact Number is required.';           this.cdr.detectChanges(); return; }
    if (!f.motherTongue?.trim())       { this.enrollError = 'Mother Tongue is required.';            this.cdr.detectChanges(); return; }
    if (!f.isIndigenous)               { this.enrollError = 'Please answer: Are you Indigenous People / Lumad?'; this.cdr.detectChanges(); return; }
    if (!f.hasSpecialNeeds)            { this.enrollError = 'Please answer: Do you have Special Education Needs?'; this.cdr.detectChanges(); return; }
    if (f.hasSpecialNeeds === 'Yes' && !f.specialNeedsDetails?.trim()) { this.enrollError = 'Please specify your special education needs.'; this.cdr.detectChanges(); return; }
    if (!f.hasAssistiveTech)           { this.enrollError = 'Please answer: Do you use Assistive Technology?'; this.cdr.detectChanges(); return; }
    if (f.hasAssistiveTech === 'Yes' && !f.assistiveTechDetails?.trim()) { this.enrollError = 'Please specify your assistive technology.'; this.cdr.detectChanges(); return; }
    if (!f.guardianName?.trim())       { this.enrollError = 'Parent / Guardian Name is required.';   this.cdr.detectChanges(); return; }
    if (!f.guardianAddress?.trim())    { this.enrollError = 'Parent / Guardian Address is required.'; this.cdr.detectChanges(); return; }
    if (!f.guardianContact?.trim())    { this.enrollError = 'Parent / Guardian Contact is required.'; this.cdr.detectChanges(); return; }

    if (!f.yearLevel)            { this.enrollError = 'Year Level is required.';            this.cdr.detectChanges(); return; }
    if (!f.semesterEnroll)         { this.enrollError = 'Semester is required.';               this.cdr.detectChanges(); return; }

    // LRN — only strictly required for SHS/TVET; for College it is optional
    if ((this.isSHS || this.isTVET) && !f.lrnNo?.trim()) {
      this.enrollError = 'LRN No. is required for SHS / TVET students.'; this.cdr.detectChanges(); return;
    }

    // SHS-specific
    if (this.isSHS && !f.strand)         { this.enrollError = 'Please choose your strand.';                               this.cdr.detectChanges(); return; }
    if (this.isSHS && !f.learningDelivery) { this.enrollError = 'Please select your preferred learning delivery mode.';   this.cdr.detectChanges(); return; }

    console.log('[STEP 2] All validations passed → proceeding to documents');
    this.enrollError = ''; this.enrollStep = 'documents';
    if (this.isTransfereeEnrolling) {
      this.loadProgramCourses();
    } else if (this.isSHS) {
      this.loadSHSFee();
    } else if (this.isTVET) {
      this.loadTVETFee();
    } else {
      // College only
      this.loadFeePreview();
    }
    this.cdr.detectChanges();
  }

  // ── Load fee preview from accounting API using program name ──
  loadFeePreview(): void {
    if (!this.selectedProgramName) return;
    this.isFeePreviewLoading = true;
    this.feePreviewError = '';
    this.feePreview = null;
    this.shsFeeResult = null;    // clear SHS state so SHS sections stay hidden
    this.tvetFeeResult = null;   // clear TVET state so TVET sections stay hidden
    this.cdr.detectChanges();

    const discount = this.isScholar && this.scholarshipAmount > 0 ? this.scholarshipAmount : 0;
    const hasInstallment = this.paymentPlan === 'installment';

    this.http.get<any>(
      `${this.accountingUrl}?action=get_fee_preview` +
      `&program=${encodeURIComponent(this.selectedProgramName)}` +
      `&year_level=${encodeURIComponent(this.regForm.yearLevel)}` +
      `&semester=${encodeURIComponent(this.fullSemester)}` +
      `&discount=${discount}` +
      `&has_installment=${hasInstallment ? 1 : 0}`
    ).subscribe({
      next: (res) => {
        this.isFeePreviewLoading = false;
        if (res.success && res.fees) {
          this.feePreview = res.fees;
          this.tuitionAmount = res.fees.totalAssessment;
        } else {
          this.feePreviewError = 'Could not load fee breakdown.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isFeePreviewLoading = false;
        this.feePreviewError = 'Could not connect to server for fee calculation.';
        this.cdr.detectChanges();
      }
    });
  }

  // ── Load program courses for transferee preview ───────────
  loadProgramCourses(): void {
    if (!this.selectedProgramName) return;
    this.isProgramCoursesLoading = true;
    this.programCourses = [];
    this.cdr.detectChanges();
    this.http.get<any>(
      `http://localhost/sia-api/registrar.php?action=get_program_courses&program=${encodeURIComponent(this.selectedProgramName)}`
    ).subscribe({
      next: (res) => {
        this.isProgramCoursesLoading = false;
        if (res.success && res.courses) {
          this.programCourses = res.courses;
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isProgramCoursesLoading = false; this.cdr.detectChanges(); }
    });
  }

  // Re-compute when payment plan or scholarship changes
  onPaymentPlanChange(): void {
    if (this.isSHS)        this.loadSHSFee();
    else if (this.isTVET)  this.loadTVETFee();
    else                   this.loadFeePreview(); // College only
  }

  // Called when transferee changes payment plan AFTER TOR evaluation
  // Re-fetches fee with correct has_installment flag so totalAssessment is accurate
  onTorPaymentPlanChange(): void {
    if (!this.torReviewStudentId || !this.torEvalResult) return;
    this.isFeePreviewLoading = true;
    this.cdr.detectChanges();
    const disc = this.isScholar && this.scholarshipAmount > 0 ? this.scholarshipAmount : 0;
    const inst = this.paymentPlan === 'installment' ? 1 : 0;
    this.http.get<any>(
      `${this.accountingUrl}?action=get_fee_preview&program=${encodeURIComponent(this.selectedProgramName)}&student_id=${this.torReviewStudentId}&discount=${disc}&has_installment=${inst}`
    ).subscribe({
      next: (fr) => {
        this.isFeePreviewLoading = false;
        if (fr.success && fr.fees && this.torEvalResult) {
          this.torEvalResult = { ...this.torEvalResult, fee: fr.fees };
          this.feePreview = fr.fees;
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isFeePreviewLoading = false; this.cdr.detectChanges(); }
    });
  }

  onScholarshipChange(): void {
    if (this.enrollStep !== 'documents') return;
    if (this.isSHS)        this.loadSHSFee();
    else if (this.isTVET)  this.loadTVETFee();
    else                   this.loadFeePreview(); // College only
  }

  // ── Program course grouping helpers ──────────────────────
  private yearOrder = ['1st Year','2nd Year','3rd Year','4th Year','5th Year'];
  private semOrder  = ['1st Semester','2nd Semester','Summer','Midyear'];

  getYearLevels(): string[] {
    const years = [...new Set(this.programCourses.map(c => c.yearLevel || '1st Year'))];
    return years.sort((a,b) => (this.yearOrder.indexOf(a) - this.yearOrder.indexOf(b)) || a.localeCompare(b));
  }

  getSemesters(yearLevel: string): string[] {
    const sems = [...new Set(this.programCourses.filter(c => (c.yearLevel||'1st Year') === yearLevel).map(c => c.semester || '1st Semester'))];
    return sems.sort((a,b) => (this.semOrder.indexOf(a) - this.semOrder.indexOf(b)) || a.localeCompare(b));
  }

  getCoursesFor(yearLevel: string, semester: string) {
    return this.programCourses.filter(c => (c.yearLevel||'1st Year') === yearLevel && (c.semester||'1st Semester') === semester);
  }

  get totalProgramUnits(): number { return this.programCourses.reduce((s,c) => s + (c.credits||0), 0); }

  // Step 3 — File handlers
  onFileChange(event: Event, target: 'tor'|'goodMoral'|'psa'|'form138'|'pic'): void {
    const file = (event.target as HTMLInputElement).files?.[0]; if (!file) return;
    if (target==='tor')       { this.torFile=file;       this.torFileName=file.name; }
    if (target==='goodMoral') { this.goodMoralFile=file; this.goodMoralFileName=file.name; }
    if (target==='psa')       { this.psaFile=file;       this.psaFileName=file.name; }
    if (target==='form138')   { this.form138File=file;   this.form138FileName=file.name; }
    if (target==='pic')       { this.picFile=file;       this.picFileName=file.name; }
    this.cdr.detectChanges();
  }

  proceedFromDocuments(): void {
    if (this.isSHS) {
      if (!this.form138File)   { this.enrollError='Report Card (Form 138) is required.';         this.cdr.detectChanges(); return; }
      if (!this.goodMoralFile) { this.enrollError='Certificate of Good Moral is required.';      this.cdr.detectChanges(); return; }
      if (!this.psaFile)       { this.enrollError='PSA Birth Certificate is required.';           this.cdr.detectChanges(); return; }
      if (!this.picFile)       { this.enrollError='2×2 picture is required.';                    this.cdr.detectChanges(); return; }
    } else {
      if (!this.torFile)  { this.enrollError='TOR / Form 137 is required.';              this.cdr.detectChanges(); return; }
      if (!this.psaFile)  { this.enrollError='PSA Birth Certificate is required.';        this.cdr.detectChanges(); return; }
    }
    if (this.isScholar && !this.scholarType) { this.enrollError='Please select a scholarship type.'; this.cdr.detectChanges(); return; }
    this.enrollError = '';
    // Warn if fee is still loading
    if (!this.isTransfereeEnrolling && this.isFeePreviewLoading) {
      this.enrollError = 'Fee assessment is still loading. Please wait a moment.';
      this.cdr.detectChanges(); return;
    }
    if (this.isTransfereeEnrolling) {
      // Transferees: go to TOR review sub-step — create account + send TOR + wait for evaluation
      this.enrollStep = 'tor-review';
      this.torReviewPhase = 'idle';
      this.torReviewError = '';
      this.torEvalResult = null;
    } else {
      this.enrollStep = 'account';
    }
    this.cdr.detectChanges();
  }

  // ══ TOR REVIEW: create account + send TOR + poll evaluation ═════════════
  sendTorNow(): void {
    const a = this.accountForm;
    if (!a.email || !a.password)           { this.torReviewError = 'Enter your email and password below first.'; this.cdr.detectChanges(); return; }
    if (a.password !== a.confirmPassword)  { this.torReviewError = 'Passwords do not match.';                   this.cdr.detectChanges(); return; }
    if (a.password.length < 6)             { this.torReviewError = 'Password must be at least 6 characters.';   this.cdr.detectChanges(); return; }
    this.torReviewPhase = 'sending'; this.torReviewError = ''; this.cdr.detectChanges();

    // STEP 1 — create user account
    this.http.post<any>(`${this.authUrl}?action=register`, {
      email: a.email, password: a.password, role: 'student',
      first_name: this.regForm.firstName, last_name: this.regForm.lastName
    }).subscribe({
      next: (r1) => {
        if (!r1.success && !r1.user_id) {
          this.torReviewPhase = 'idle'; this.torReviewError = r1.message || 'Account creation failed.';
          this.cdr.detectChanges(); return;
        }
        // STEP 2 — register student profile
        this.http.post<any>(`${this.apiUrl}?action=register_transferee`, {
          user_id: r1.user_id,
          firstName: this.regForm.firstName,   lastName: this.regForm.lastName,
          middleName: this.regForm.middleName, suffix: this.regForm.suffix,
          email: a.email, phone: this.regForm.contactNumber,
          dateOfBirth: this.regForm.dateOfBirth, address: this.regForm.homeAddress,
          emergencyContact: this.regForm.guardianName, emergencyPhone: this.regForm.guardianContact,
          program: this.selectedProgramName, studentType: this.regForm.studentType,
          studentCategory: this.studentTypeCategory, paymentMethod: this.paymentMethod, paymentPlan: this.paymentPlan,
          isScholar: this.isScholar ? 1 : 0, scholarType: this.scholarType,
          scholarGrantor: this.scholarGrantor, scholarshipAmount: this.scholarshipAmount,
          lrnNo: this.regForm.lrnNo, sex: this.regForm.sex, religion: this.regForm.religion,
          citizenship: this.regForm.citizenship, placeOfBirth: this.regForm.placeOfBirth,
          motherTongue: this.regForm.motherTongue, lastSchoolAttended: this.regForm.lastSchoolAttended,
          strand: this.regForm.strand, age: this.regForm.age, guardianAddress: this.regForm.guardianAddress,
          psaBirthCertNo: this.regForm.psaBirthCertNo, isIndigenous: this.regForm.isIndigenous,
          hasSpecialNeeds: this.regForm.hasSpecialNeeds, specialNeedsDetails: this.regForm.specialNeedsDetails,
          hasAssistiveTech: this.regForm.hasAssistiveTech, assistiveTechDetails: this.regForm.assistiveTechDetails,
          learningDelivery: this.regForm.learningDelivery,
          semester: this.fullSemester,
          yearLevel: this.regForm.yearLevel,
        }).subscribe({
          next: (r2) => {
            if (!r2.success && !r2.student_id && !r2.student_number) {
              this.torReviewPhase = 'idle'; this.torReviewError = r2.message || 'Registration failed.';
              this.cdr.detectChanges(); return;
            }
            const sid = r2.student_id || r2.studentId;
            this.torReviewStudentId = sid;
            // STEP 3 — upload TOR file or create blank record
            const afterUpload = () => {
              this.torReviewPhase = 'waiting'; this.cdr.detectChanges();
              this.startTorPoll();
            };
            if (this.torFile) {
              const fd = new FormData();
              fd.append('student_id', String(sid));
              fd.append('tor_file', this.torFile);
              this.http.post<any>('http://localhost/sia-api/registrar.php?action=upload_tor_file', fd)
                .subscribe({ next: () => afterUpload(), error: () => afterUpload() });
            } else {
              this.http.post<any>('http://localhost/sia-api/registrar.php?action=submit_tor', { student_id: sid })
                .subscribe({ next: () => afterUpload(), error: () => afterUpload() });
            }
          },
          error: () => { this.torReviewPhase='idle'; this.torReviewError='Registration failed.'; this.cdr.detectChanges(); }
        });
      },
      error: () => { this.torReviewPhase='idle'; this.torReviewError='Cannot connect to server. Check XAMPP is running.'; this.cdr.detectChanges(); }
    });
  }

  private startTorPoll(): void {
    if (this.torPollTimer) clearInterval(this.torPollTimer);
    this.checkTorEval();
    this.torPollTimer = setInterval(() => this.checkTorEval(), 10000);
  }

  private checkTorEval(): void {
    const sid = this.torReviewStudentId;
    if (!sid) { console.warn('[TOR POLL] No student_id — polling skipped'); return; }
    console.log('[TOR POLL] Checking evaluation for student_id', sid);
    this.http.get<any>(`http://localhost/sia-api/registrar.php?action=get_tor_evaluation&student_id=${sid}`).subscribe({
      next: (res) => {
        console.log('[TOR POLL] Response:', res);
        if (!res.success || !res.evaluation || res.evaluation.status === 'Pending') {
          console.log('[TOR POLL] Still pending — waiting...');
          return;
        }
        clearInterval(this.torPollTimer);
        const ev = res.evaluation;
        const disc = this.isScholar && this.scholarshipAmount > 0 ? this.scholarshipAmount : 0;
        const inst = this.paymentPlan === 'installment' ? 1 : 0;
        this.http.get<any>(
          `${this.accountingUrl}?action=get_fee_preview&program=${encodeURIComponent(this.selectedProgramName)}&student_id=${sid}&discount=${disc}&has_installment=${inst}`
        ).subscribe({
          next: (fr) => { this.setTorResult(ev, fr.success ? fr.fees : null); },
          error: ()  => { this.setTorResult(ev, null); }
        });
      }
    });
  }

  private setTorResult(ev: any, fee: any): void {
    console.log('[TOR RESULT] Evaluation:', ev, 'Fee:', fee);
    const subs: { courseId:number; code:string; name:string; credits:number }[] = ev.creditedSubjects || [];
    this.torCreditedCodes = new Set(subs.map(s => s.code));
    this.torEvalResult = {
      creditedUnits:    ev.creditedUnits  || 0,
      approvedUnits:    ev.approvedUnits  || 0,
      programUnits:     ev.programUnits   || 0,
      creditedSubjects: subs,
      registrarNotes:   ev.registrarNotes || '',
      evaluatedAt:      ev.evaluatedAt    || '',
      fee,
    };
    this.feePreview = fee;
    this.torReviewPhase = ev.status === 'Rejected' ? 'rejected' : 'done';
    this.cdr.detectChanges();
  }


  /** Combines semester term + AY year into e.g. "1st Semester, AY 2026-2027" */
  get fullSemester(): string {
    const term = this.regForm.semesterEnroll;
    const ay   = this.regForm.ayYear?.trim();
    if (term && ay) return `${term}, AY ${ay}`;
    if (term)       return term;
    return this.getCurrentSemester();
  }

  /** Returns the current academic year semester string, e.g. "1st Semester, AY 2025-2026" */
  getCurrentSemester(): string {
    const now = new Date();
    const year = now.getFullYear();
    // Assume 1st Semester Aug–Dec, 2nd Semester Jan–May
    const month = now.getMonth() + 1;
    const semLabel = month >= 6 ? '1st Semester' : '2nd Semester';
    const ayStart  = month >= 6 ? year : year - 1;
    return `${semLabel}, AY ${ayStart}-${ayStart + 1}`;
  }

  finishTorReview(): void {
    // Auto-login then navigate
    const a = this.accountForm;
    this.isSubmitting = true; this.cdr.detectChanges();
    if (isPlatformBrowser(this.platformId)) {
      sessionStorage.setItem('pendingPaymentMethod', this.paymentMethod);
      sessionStorage.setItem('pendingPaymentPlan',   this.paymentPlan);
    }

    // Save payment_plan + payment_method to DB so it persists after localStorage is cleared
    const studentId = this.torReviewStudentId;
    if (studentId > 0) {
      this.http.post<any>(`${this.apiUrl}?action=update_payment_plan`, {
        student_id:     studentId,
        payment_plan:   this.paymentPlan,
        payment_method: this.paymentMethod,
      }).subscribe(); // fire-and-forget
    }

    this.http.post<any>(this.authUrl, { email: a.email, password: a.password }).subscribe({
      next: (lr) => {
        if (lr.success && isPlatformBrowser(this.platformId)) {
          sessionStorage.setItem('currentUser', JSON.stringify(lr.user));
            localStorage.setItem('currentUser', JSON.stringify(lr.user));
          sessionStorage.setItem('token', lr.token);
            localStorage.setItem('token', lr.token);
        }
        this.router.navigate(['/student/enrollment']);
      },
      error: () => {
        this.view = 'login'; this.email = a.email;
        this.successMessage = 'Account created! Please log in.';
        this.cdr.detectChanges();
      }
    });
  }

  // ══════════════════════════════════════════════════════════
  // SHS ENROLLMENT SUBMIT — separate function, does NOT modify College flow
  // ══════════════════════════════════════════════════════════
  submitEnrollmentSHS(): void {
    const a = this.accountForm;
    if (!a.email || !a.password)             { this.enrollError = 'Email and password are required.';        this.cdr.detectChanges(); return; }
    if (a.password !== a.confirmPassword)    { this.enrollError = 'Passwords do not match.';                 this.cdr.detectChanges(); return; }
    if (a.password.length < 6)              { this.enrollError = 'Password must be at least 6 characters.'; this.cdr.detectChanges(); return; }

    this.isSubmitting = true; this.enrollError = ''; this.cdr.detectChanges();

    // STEP 1 — Create user account
    this.http.post<any>(`${this.authUrl}?action=register`, {
      email: a.email, password: a.password, role: 'student',
      first_name: this.regForm.firstName, last_name: this.regForm.lastName
    }).subscribe({
      next: (res) => {
        if (!res.success && !res.user_id) {
          this.isSubmitting = false;
          this.enrollError = res.message || 'Failed to create account.';
          this.cdr.detectChanges(); return;
        }
        const userId = res.user_id;

        // STEP 2 — Register SHS student profile
        this.http.post<any>(`${this.apiUrl}?action=register_student_shs`, {
          user_id: userId,
          firstName: this.regForm.firstName,   lastName: this.regForm.lastName,
          middleName: this.regForm.middleName, suffix: this.regForm.suffix,
          email: a.email, phone: this.regForm.contactNumber,
          dateOfBirth: this.regForm.dateOfBirth, address: this.regForm.homeAddress,
          guardianName: this.regForm.guardianName, guardianAddress: this.regForm.guardianAddress,
          guardianContact: this.regForm.guardianContact,
          program: this.selectedProgramName,
          studentType: this.regForm.studentType,
          studentCategory: 'SHS',
          lrnNo: this.regForm.lrnNo,
          sex: this.regForm.sex, religion: this.regForm.religion,
          age: this.regForm.age, placeOfBirth: this.regForm.placeOfBirth,
          citizenship: this.regForm.citizenship, motherTongue: this.regForm.motherTongue,
          isIndigenous: this.regForm.isIndigenous,
          hasSpecialNeeds: this.regForm.hasSpecialNeeds,
          specialNeedsDetails: this.regForm.specialNeedsDetails,
          hasAssistiveTech: this.regForm.hasAssistiveTech,
          assistiveTechDetails: this.regForm.assistiveTechDetails,
          psaBirthCertNo: this.regForm.psaBirthCertNo,
          lastSchoolAttended: this.regForm.lastSchoolAttended,
          strand: this.regForm.strand,
          learningDelivery: this.regForm.learningDelivery,
          gradeLevel: this.regForm.yearLevel,
          isScholar: this.isScholar ? 1 : 0,
          scholarType: this.scholarType, scholarGrantor: this.scholarGrantor,
          scholarshipAmount: this.scholarshipAmount,
          paymentMethod: this.paymentMethod, paymentPlan: this.paymentPlan,
          semester: this.fullSemester, yearLevel: this.regForm.yearLevel,
        }).subscribe({
          next: (sRes) => {
            this.isSubmitting = false;
            if (!sRes.success && !sRes.student_id && !sRes.student_number) {
              this.enrollError = sRes.message || 'SHS registration failed.';
              this.cdr.detectChanges(); return;
            }
            if (isPlatformBrowser(this.platformId)) {
              sessionStorage.setItem('pendingPaymentMethod', this.paymentMethod);
              sessionStorage.setItem('pendingPaymentPlan', this.paymentPlan);
            }
            // STEP 3 — Upload docs then auto-login
            const studentId = sRes.student_id || sRes.studentId;
            const doUploadsThenLogin = () => {
              const doLogin = () => {
                this.http.post<any>(this.authUrl, { email: a.email, password: a.password }).subscribe({
                  next: (lr) => {
                    if (lr.success && isPlatformBrowser(this.platformId)) {
                      sessionStorage.setItem('currentUser', JSON.stringify(lr.user));
            localStorage.setItem('currentUser', JSON.stringify(lr.user));
                      sessionStorage.setItem('token', lr.token);
            localStorage.setItem('token', lr.token);
                    }
                    this.router.navigate(['/student/enrollment']);
                  },
                  error: () => { this.view = 'login'; this.email = a.email; this.successMessage = 'Account created! Please log in.'; this.cdr.detectChanges(); }
                });
              };
              // Upload Form 138 if present
              if (this.form138File && studentId) {
                const fd = new FormData();
                fd.append('student_id', String(studentId));
                fd.append('document_type', 'form138');
                fd.append('file', this.form138File);
                this.http.post<any>('http://localhost/sia-api/registrar.php?action=upload_document', fd)
                  .subscribe({ next: () => doLogin(), error: () => doLogin() });
              } else { doLogin(); }
            };
            doUploadsThenLogin();
          },
          error: (err) => {
            this.isSubmitting = false;
            this.enrollError = err?.error?.message || 'SHS registration failed.';
            this.cdr.detectChanges();
          }
        });
      },
      error: () => {
        this.isSubmitting = false;
        this.enrollError = 'Cannot connect to server. Check XAMPP is running.';
        this.cdr.detectChanges();
      }
    });
  }

  // ══════════════════════════════════════════════════════════
  // TVET ENROLLMENT SUBMIT — separate function, does NOT modify College flow
  // ══════════════════════════════════════════════════════════
  submitEnrollmentTVET(): void {
    const a = this.accountForm;
    if (!a.email || !a.password)            { this.enrollError = 'Email and password are required.';        this.cdr.detectChanges(); return; }
    if (a.password !== a.confirmPassword)   { this.enrollError = 'Passwords do not match.';                 this.cdr.detectChanges(); return; }
    if (a.password.length < 6)             { this.enrollError = 'Password must be at least 6 characters.'; this.cdr.detectChanges(); return; }

    this.isSubmitting = true; this.enrollError = ''; this.cdr.detectChanges();

    // STEP 1 — Create user account
    this.http.post<any>(`${this.authUrl}?action=register`, {
      email: a.email, password: a.password, role: 'student',
      first_name: this.regForm.firstName, last_name: this.regForm.lastName
    }).subscribe({
      next: (res) => {
        if (!res.success && !res.user_id) {
          this.isSubmitting = false;
          this.enrollError = res.message || 'Failed to create account.';
          this.cdr.detectChanges(); return;
        }
        const userId = res.user_id;

        // STEP 2 — Register TVET student profile
        this.http.post<any>(`${this.apiUrl}?action=register_student_tvet`, {
          user_id: userId,
          firstName: this.regForm.firstName,   lastName: this.regForm.lastName,
          middleName: this.regForm.middleName, suffix: this.regForm.suffix,
          email: a.email, phone: this.regForm.contactNumber,
          dateOfBirth: this.regForm.dateOfBirth, address: this.regForm.homeAddress,
          guardianName: this.regForm.guardianName, guardianAddress: this.regForm.guardianAddress,
          guardianContact: this.regForm.guardianContact,
          program: this.selectedProgramName,
          studentType: this.regForm.studentType,
          studentCategory: 'TVET',
          tvetType: this.tvetSelectedType,
          lrnNo: this.regForm.lrnNo,
          sex: this.regForm.sex, religion: this.regForm.religion,
          age: this.regForm.age, placeOfBirth: this.regForm.placeOfBirth,
          citizenship: this.regForm.citizenship, motherTongue: this.regForm.motherTongue,
          isIndigenous: this.regForm.isIndigenous,
          hasSpecialNeeds: this.regForm.hasSpecialNeeds,
          specialNeedsDetails: this.regForm.specialNeedsDetails,
          hasAssistiveTech: this.regForm.hasAssistiveTech,
          assistiveTechDetails: this.regForm.assistiveTechDetails,
          psaBirthCertNo: this.regForm.psaBirthCertNo,
          lastSchoolAttended: this.regForm.lastSchoolAttended,
          isScholar: this.isScholar ? 1 : 0,
          scholarType: this.scholarType, scholarGrantor: this.scholarGrantor,
          scholarshipAmount: this.scholarshipAmount,
          paymentMethod: this.paymentMethod, paymentPlan: this.paymentPlan,
          semester: this.fullSemester, yearLevel: this.regForm.yearLevel,
        }).subscribe({
          next: (sRes) => {
            this.isSubmitting = false;
            if (!sRes.success && !sRes.student_id && !sRes.student_number) {
              this.enrollError = sRes.message || 'TVET registration failed.';
              this.cdr.detectChanges(); return;
            }
            if (isPlatformBrowser(this.platformId)) {
              sessionStorage.setItem('pendingPaymentMethod', this.paymentMethod);
              sessionStorage.setItem('pendingPaymentPlan', this.paymentPlan);
            }
            // Auto-login
            this.http.post<any>(this.authUrl, { email: a.email, password: a.password }).subscribe({
              next: (lr) => {
                if (lr.success && isPlatformBrowser(this.platformId)) {
                  sessionStorage.setItem('currentUser', JSON.stringify(lr.user));
            localStorage.setItem('currentUser', JSON.stringify(lr.user));
                  sessionStorage.setItem('token', lr.token);
            localStorage.setItem('token', lr.token);
                }
                this.router.navigate(['/student/enrollment']);
              },
              error: () => { this.view = 'login'; this.email = a.email; this.successMessage = 'Account created! Please log in.'; this.cdr.detectChanges(); }
            });
          },
          error: (err) => {
            this.isSubmitting = false;
            this.enrollError = err?.error?.message || 'TVET registration failed.';
            this.cdr.detectChanges();
          }
        });
      },
      error: () => {
        this.isSubmitting = false;
        this.enrollError = 'Cannot connect to server. Check XAMPP is running.';
        this.cdr.detectChanges();
      }
    });
  }

  // ══════════════════════════════════════════════════════════
  // VALIDATION helpers for SHS / TVET specific steps
  // ══════════════════════════════════════════════════════════
  proceedFromInfoSHS(): void {
    const f = this.regForm;
    if (!f.lastName?.trim())        { this.enrollError = 'Last Name is required.';        this.cdr.detectChanges(); return; }
    if (!f.firstName?.trim())       { this.enrollError = 'First Name is required.';       this.cdr.detectChanges(); return; }
    if (!f.studentType)             { this.enrollError = 'Type of Student is required.';  this.cdr.detectChanges(); return; }
    if (!f.lrnNo?.trim())           { this.enrollError = 'LRN No. is required for SHS.'; this.cdr.detectChanges(); return; }
    if (!f.dateOfBirth)             { this.enrollError = 'Date of Birth is required.';    this.cdr.detectChanges(); return; }
    if (!f.sex)                     { this.enrollError = 'Sex is required.';              this.cdr.detectChanges(); return; }
    if (!f.religion?.trim())        { this.enrollError = 'Religion is required.';         this.cdr.detectChanges(); return; }
    if (!f.age?.toString().trim())  { this.enrollError = 'Age is required.';              this.cdr.detectChanges(); return; }
    if (!f.placeOfBirth?.trim())    { this.enrollError = 'Place of Birth is required.';   this.cdr.detectChanges(); return; }
    if (!f.citizenship?.trim())     { this.enrollError = 'Citizenship is required.';      this.cdr.detectChanges(); return; }
    if (!f.homeAddress?.trim())     { this.enrollError = 'Home Address is required.';     this.cdr.detectChanges(); return; }
    if (!f.contactNumber?.trim())   { this.enrollError = 'Contact Number is required.';   this.cdr.detectChanges(); return; }
    if (!f.motherTongue?.trim())    { this.enrollError = 'Mother Tongue is required.';    this.cdr.detectChanges(); return; }
    if (!f.isIndigenous)            { this.enrollError = 'Please answer the IP question.'; this.cdr.detectChanges(); return; }
    if (!f.hasSpecialNeeds)         { this.enrollError = 'Please answer: Special Education Needs?'; this.cdr.detectChanges(); return; }
    if (f.hasSpecialNeeds === 'Yes' && !f.specialNeedsDetails?.trim()) { this.enrollError = 'Please specify your special education needs.'; this.cdr.detectChanges(); return; }
    if (!f.hasAssistiveTech)        { this.enrollError = 'Please answer: Assistive Technology?'; this.cdr.detectChanges(); return; }
    if (!f.guardianName?.trim())    { this.enrollError = 'Parent / Guardian Name is required.'; this.cdr.detectChanges(); return; }
    if (!f.guardianContact?.trim()) { this.enrollError = 'Parent / Guardian Contact is required.'; this.cdr.detectChanges(); return; }
    if (!f.strand)                  { this.enrollError = 'Please choose your SHS strand.'; this.cdr.detectChanges(); return; }
    if (!f.yearLevel)               { this.enrollError = 'Grade Level is required.';      this.cdr.detectChanges(); return; }
    // Build lastSchoolAttended from previousSchools
    const filledSchools = this.previousSchools.filter(s => s.schoolName?.trim());
    if (filledSchools.length === 0) { this.enrollError = 'Please enter at least one previous school.'; this.cdr.detectChanges(); return; }
    f.lastSchoolAttended = filledSchools.map(s => `${s.level} - ${s.schoolName} (${s.schoolYear})`).join('; ');
    this.enrollError = '';
    this.enrollStep = 'documents';
    this.loadSHSFee();
    this.cdr.detectChanges();
  }

  loadSHSFee(): void {
    this.isSHSFeeLoading = true;
    this.shsFeeResult = null;
    this.feePreviewError = '';   // clear any stale College error
    this.feePreview = null;      // clear College fee so College section stays hidden
    this.cdr.detectChanges();
    const discount = this.isScholar && this.scholarshipAmount > 0 ? this.scholarshipAmount : 0;
    const inst = this.paymentPlan === 'installment' ? 1 : 0;
    this.http.get<any>(
      `${this.accountingUrl}?action=get_shs_fee` +
      `&student_type=${encodeURIComponent(this.regForm.studentType)}` +
      `&discount=${discount}&has_installment=${inst}`
    ).subscribe({
      next: (res) => {
        this.isSHSFeeLoading = false;
        if (res.success) {
          this.shsFeeResult = res;
          if (!res.isFree) {
            this.tuitionAmount = res.fees.totalAssessment;
            // NOTE: do NOT set this.feePreview here — that's College-only
          }
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSHSFeeLoading = false; this.cdr.detectChanges(); }
    });
  }

  proceedFromInfoTVET(): void {
    const f = this.regForm;
    if (!f.lastName?.trim())        { this.enrollError = 'Last Name is required.';        this.cdr.detectChanges(); return; }
    if (!f.firstName?.trim())       { this.enrollError = 'First Name is required.';       this.cdr.detectChanges(); return; }
    if (!f.studentType)             { this.enrollError = 'Type of Student is required.';  this.cdr.detectChanges(); return; }
    if (!f.lrnNo?.trim())           { this.enrollError = 'LRN No. is required for TVET.'; this.cdr.detectChanges(); return; }
    if (!f.dateOfBirth)             { this.enrollError = 'Date of Birth is required.';    this.cdr.detectChanges(); return; }
    if (!f.sex)                     { this.enrollError = 'Sex is required.';              this.cdr.detectChanges(); return; }
    if (!f.religion?.trim())        { this.enrollError = 'Religion is required.';         this.cdr.detectChanges(); return; }
    if (!f.age?.toString().trim())  { this.enrollError = 'Age is required.';              this.cdr.detectChanges(); return; }
    if (!f.placeOfBirth?.trim())    { this.enrollError = 'Place of Birth is required.';   this.cdr.detectChanges(); return; }
    if (!f.citizenship?.trim())     { this.enrollError = 'Citizenship is required.';      this.cdr.detectChanges(); return; }
    if (!f.homeAddress?.trim())     { this.enrollError = 'Home Address is required.';     this.cdr.detectChanges(); return; }
    if (!f.contactNumber?.trim())   { this.enrollError = 'Contact Number is required.';   this.cdr.detectChanges(); return; }
    if (!f.motherTongue?.trim())    { this.enrollError = 'Mother Tongue is required.';    this.cdr.detectChanges(); return; }
    if (!f.isIndigenous)            { this.enrollError = 'Please answer the IP question.'; this.cdr.detectChanges(); return; }
    if (!f.hasSpecialNeeds)         { this.enrollError = 'Please answer: Special Education Needs?'; this.cdr.detectChanges(); return; }
    if (f.hasSpecialNeeds === 'Yes' && !f.specialNeedsDetails?.trim()) { this.enrollError = 'Please specify your special education needs.'; this.cdr.detectChanges(); return; }
    if (!f.hasAssistiveTech)        { this.enrollError = 'Please answer: Assistive Technology?'; this.cdr.detectChanges(); return; }
    if (!f.guardianName?.trim())    { this.enrollError = 'Parent / Guardian Name is required.'; this.cdr.detectChanges(); return; }
    if (!f.guardianContact?.trim()) { this.enrollError = 'Parent / Guardian Contact is required.'; this.cdr.detectChanges(); return; }
    if (!f.yearLevel)               { this.enrollError = 'Year Level is required.';       this.cdr.detectChanges(); return; }
    // Build lastSchoolAttended
    const filledSchools = this.previousSchools.filter(s => s.schoolName?.trim());
    if (filledSchools.length === 0) { this.enrollError = 'Please enter at least one previous school.'; this.cdr.detectChanges(); return; }
    f.lastSchoolAttended = filledSchools.map(s => `${s.level} - ${s.schoolName} (${s.schoolYear})`).join('; ');
    this.enrollError = '';
    this.enrollStep = 'documents';
    this.loadTVETFee();
    this.cdr.detectChanges();
  }

  loadTVETFee(): void {
    this.isTVETFeeLoading = true;
    this.tvetFeeResult = null;
    this.feePreviewError = '';   // clear any stale College error
    this.feePreview = null;      // clear College fee so College section stays hidden
    this.cdr.detectChanges();
    this.http.get<any>(
      `${this.accountingUrl}?action=get_tvet_fee` +
      `&program=${encodeURIComponent(this.selectedProgramName)}` +
      `&student_type=${encodeURIComponent(this.regForm.studentType)}`
    ).subscribe({
      next: (res) => {
        this.isTVETFeeLoading = false;
        if (res.success) { this.tvetFeeResult = res; }
        this.cdr.detectChanges();
      },
      error: () => { this.isTVETFeeLoading = false; this.cdr.detectChanges(); }
    });
  }

  proceedFromDocumentsSHS(): void {
    if (this.isScholar && !this.scholarType) { this.enrollError = 'Please select a scholarship type.'; this.cdr.detectChanges(); return; }
    // SHS Transferee: check fee is loaded before proceeding
    if (this.isTransfereeEnrolling && this.isSHSFeeLoading) {
      this.enrollError = 'Fee assessment is still loading. Please wait.'; this.cdr.detectChanges(); return;
    }
    this.enrollError = '';
    this.enrollStep = 'account';
    this.cdr.detectChanges();
  }

  proceedFromDocumentsTVET(): void {
    if (this.isScholar && !this.scholarType) { this.enrollError = 'Please select a scholarship type.'; this.cdr.detectChanges(); return; }
    this.enrollError = '';
    this.enrollStep = 'account';
    this.cdr.detectChanges();
  }

  // Step 4 — Submit
  submitEnrollment(): void {
    const a = this.accountForm;
    if (!a.email||!a.password)              { this.enrollError='Email and password are required.';        this.cdr.detectChanges(); return; }
    if (a.password !== a.confirmPassword)   { this.enrollError='Passwords do not match.';                 this.cdr.detectChanges(); return; }
    if (a.password.length < 6)              { this.enrollError='Password must be at least 6 characters.'; this.cdr.detectChanges(); return; }

    this.isSubmitting = true; this.enrollError = ''; this.cdr.detectChanges();

    // ── STEP 1: Create user account ──────────────────────────
    console.log('[ENROLL] STEP 1 — Calling auth.php?action=register', { email: a.email });
    this.http.post<any>(`${this.authUrl}?action=register`, {
      email: a.email, password: a.password, role: 'student',
      first_name: this.regForm.firstName, last_name: this.regForm.lastName
    }).subscribe({
      next: (res) => {
        console.log('[ENROLL] STEP 1 response:', res);

        // FIX: If email already exists, auth.php returns success:false but we should continue
        // by fetching the existing user_id, OR auth.php was updated to return user_id on duplicate
        if (!res.success && !res.user_id) {
          this.isSubmitting = false;
          this.enrollError = res.message || 'Failed to create account.';
          this.cdr.detectChanges();
          return;
        }

        const userId = res.user_id;
        console.log('[ENROLL] STEP 2 — Calling enrollment.php?action=register_student', { user_id: userId });

        // ── STEP 2: Save student profile ────────────────────
        this.http.post<any>(`${this.apiUrl}?action=register_student`, {
          user_id: userId,
          firstName: this.regForm.firstName, lastName: this.regForm.lastName,
          middleName: this.regForm.middleName, suffix: this.regForm.suffix,
          email: a.email, phone: this.regForm.contactNumber,
          dateOfBirth: this.regForm.dateOfBirth,
          address: this.regForm.homeAddress,
          emergencyContact: this.regForm.guardianName,
          emergencyPhone: this.regForm.guardianContact,
          program: this.selectedProgramName,
          studentType: this.regForm.studentType,
          studentCategory: this.studentTypeCategory,
          paymentMethod: this.paymentMethod,
          paymentPlan: this.paymentPlan,
          isScholar: this.isScholar ? 1 : 0,
          scholarType: this.scholarType, scholarGrantor: this.scholarGrantor,
          scholarshipAmount: this.scholarshipAmount,
          lrnNo: this.regForm.lrnNo,
          sex: this.regForm.sex,
          religion: this.regForm.religion,
          citizenship: this.regForm.citizenship,
          placeOfBirth: this.regForm.placeOfBirth,
          motherTongue: this.regForm.motherTongue,
          lastSchoolAttended: this.regForm.lastSchoolAttended,
          strand: this.regForm.strand,
          // Extended fields
          age: this.regForm.age,
          guardianAddress: this.regForm.guardianAddress,
          psaBirthCertNo: this.regForm.psaBirthCertNo,
          isIndigenous: this.regForm.isIndigenous,
          hasSpecialNeeds: this.regForm.hasSpecialNeeds,
          specialNeedsDetails: this.regForm.specialNeedsDetails,
          hasAssistiveTech: this.regForm.hasAssistiveTech,
          assistiveTechDetails: this.regForm.assistiveTechDetails,
          learningDelivery: this.regForm.learningDelivery,
          semester: this.fullSemester,
          yearLevel: this.regForm.yearLevel,
        }).subscribe({
          next: (sRes) => {
            console.log('[ENROLL] STEP 2 response:', sRes);
            this.isSubmitting = false;

            // FIX: Only treat as real failure if there's truly no student record
            if (!sRes.success && !sRes.student_id && !sRes.student_number) {
              this.enrollError = sRes.message || 'Registration failed.';
              console.error('[ENROLL] STEP 2 FAILED — no student_id returned:', sRes);
              this.cdr.detectChanges();
              return;
            }

            console.log('[ENROLL] STEP 3 — Auto-login after registration');
            if (isPlatformBrowser(this.platformId)) {
              sessionStorage.setItem('pendingPaymentMethod', this.paymentMethod);
              sessionStorage.setItem('pendingPaymentPlan', this.paymentPlan);
            }

            // ── STEP 2b: If Transferee, auto-submit TOR for registrar evaluation ──
            const isTransferee = this.regForm.studentType === 'Transferee';
            const studentId    = sRes.student_id || sRes.studentId;

            const doAutoLogin = () => {
              // ── STEP 3: Auto-login ───────────────────────────
              this.http.post<any>(this.authUrl, { email: a.email, password: a.password }).subscribe({
                next: (lr) => {
                  console.log('[ENROLL] STEP 3 login response:', lr);
                  if (lr.success && isPlatformBrowser(this.platformId)) {
                    sessionStorage.setItem('currentUser', JSON.stringify(lr.user));
            localStorage.setItem('currentUser', JSON.stringify(lr.user));
                    sessionStorage.setItem('token', lr.token);
            localStorage.setItem('token', lr.token);
                  }
                  this.router.navigate(['/student/enrollment']);
                },
                error: (err) => {
                  console.error('[ENROLL] STEP 3 login error:', err);
                  this.view = 'login'; this.email = a.email;
                  this.successMessage = 'Account created! Please log in to continue.';
                  this.cdr.detectChanges();
                }
              });
            };

            if (isTransferee && studentId) {
              console.log('[ENROLL] Transferee — uploading TOR file, student_id:', studentId);
              if (this.torFile) {
                const formData = new FormData();
                formData.append('student_id', String(studentId));
                formData.append('tor_file', this.torFile);
                this.http.post<any>(
                  `http://localhost/sia-api/registrar.php?action=upload_tor_file`, formData
                ).subscribe({
                  next: (torRes) => { console.log('[ENROLL] TOR uploaded:', torRes); doAutoLogin(); },
                  error: () => { console.warn('[ENROLL] TOR upload failed — submitting without file'); doAutoLogin(); }
                });
              } else {
                // No file but still create the tor_evaluation record
                this.http.post<any>(`http://localhost/sia-api/registrar.php?action=submit_tor`,
                  { student_id: studentId }
                ).subscribe({
                  next: (torRes) => { console.log('[ENROLL] TOR submitted (no file):', torRes); doAutoLogin(); },
                  error: () => { doAutoLogin(); }
                });
              }
            } else {
              doAutoLogin();
            }
          },
          error: (err) => {
            console.error('[ENROLL] STEP 2 HTTP error:', err);
            console.error('[ENROLL] STEP 2 error body:', err?.error);
            this.isSubmitting = false;
            this.enrollError = err?.error?.message || 'Registration failed.';
            this.cdr.detectChanges();
          }
        });
      },
      error: (err) => {
        console.error('[ENROLL] STEP 1 HTTP error:', err);
        this.isSubmitting = false;
        this.enrollError = 'Cannot connect to server. Check XAMPP is running.';
        this.cdr.detectChanges();
      }
    });
  }
}