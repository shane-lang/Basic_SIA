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
import { environment } from '../environment';
import { AuthService } from '../services/auth';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, MatButtonModule, MatInputModule, MatCardModule, MatFormFieldModule],
  templateUrl: './login.html',
  styleUrls: ['./login.css']
})
export class LoginComponent implements OnInit, OnDestroy {
  private apiUrl     = environment.enrollApi;
  private authUrl    = environment.authApi;
  private adminUrl   = environment.adminApi;
  private accountingUrl = environment.accountingApi;
  private platformId = inject(PLATFORM_ID);

  view: 'login' | 'enroll' | 'forgot' | 'reset' = 'login';

  // ── Forgot / Reset Password state ────────────────────────────
  fpEmail           = '';
  fpOtp             = '';
  fpNewPassword     = '';
  fpConfirmPassword = '';
  fpLoading         = false;
  fpError           = '';
  fpSuccess         = '';
  fpResendCountdown = 0;
  private fpResendTimer: any = null;

  // ── Login ────────────────────────────────────────────────────
  email = ''; password = ''; errorMessage = ''; successMessage = ''; loading = false;

  // ── Inline field errors ───────────────────────────────────────
  loginErrors:  Record<string, string> = {};
  loginTouched: Record<string, boolean> = {};
  fieldErrors:  Record<string, string> = {};   // enrollment wizard per-field errors
  accountErrors: Record<string, string> = {};  // step-4 account form errors
  forgotErrors: Record<string, string> = {};   // forgot / reset password errors

  setFieldError(bag: Record<string, string>, field: string, msg: string): void {
    bag[field] = msg;
    this.cdr.detectChanges();
  }
  clearErrors(...bags: Record<string, string>[]): void {
    bags.forEach(b => { for (const k in b) delete b[k]; });
    this.cdr.detectChanges();
  }

  // ── OTP 2FA state (student portal) ─────────────────────────
  showOtpModal   = false;
  otpToken       = '';
  otpInput       = '';
  otpCode        = '';   // dev-mode preview of the OTP (shown in-app when APP_ENV=development)
  otpError       = '';
  otpVerifying   = false;
  otpCountdown   = 300;
  private otpTimer: any = null;

  // ── Wizard ───────────────────────────────────────────────────
  enrollStep: 'program' | 'info' | 'subjects' | 'documents' | 'tor-review' | 'account' | 'confirmed' = 'program';
  enrollError = ''; isSubmitting = false;
  privacyConsentGiven = false;  // RA 10173 — must be true before account creation
  showPrivacyNotice   = false;

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
    homeAddress: '', addrStreet: '', addrPurok: '', addrBarangay: '', addrCity: '', addrProvince: '', contactNumber: '',
    isIndigenous: 'No' as 'Yes' | 'No' | '',
    motherTongue: '',
    hasSpecialNeeds: 'No' as 'Yes' | 'No' | '', specialNeedsDetails: '',
    hasAssistiveTech: 'No' as 'Yes' | 'No' | '', assistiveTechDetails: '',
    strand: '',
    learningDelivery: '' as 'Face to Face' | 'Online' | 'Modular' | 'Combination of Face to face and Online' | 'Blended Methods of Learning' | '',
    guardianName: '', guardianAddress: '',
    guardianAddrStreet: '', guardianAddrPurok: '', guardianAddrBarangay: '', guardianAddrCity: '', guardianAddrProvince: '',
    guardianContact: '', guardianEmail: '', guardianRelationship: '',
    yearLevel: '1st Year',
    semesterEnroll: '' as string,
    ayYear: (() => { const now = new Date(); const m = now.getMonth()+1; const s = m>=6?now.getFullYear():now.getFullYear()-1; return `${s}-${s+1}`; })(),
  };

  strandOptions = [
    'Accountancy Business and Management (ABM)',
    'Humanities Social Science Strand (HUMSS)',
    'Technical - Vocational and Livelihood - Home Economics (TVL-HE)',
    'Technical - Vocational and Livelihood - Information & Communications Technology (TVL-ICT)',
    'General Academic Strand (GAS)',
  ];

  religionOptions = [
    'Roman Catholic',
    'Islam / Muslim',
    'Iglesia ni Cristo',
    'Seventh-day Adventist',
    'Born-again Christian / Evangelical',
    'Philippine Independent Church (Aglipayan)',
    'United Church of Christ in the Philippines (UCCP)',
    'Jehovah\'s Witnesses',
    'The Church of Jesus Christ of Latter-day Saints (Mormon)',
    'Baptist',
    'Dating Daan / Members Church of God International',
    'El Shaddai',
    'Christian (Non-denominational)',
    'Buddhism',
    'Hinduism',
    'Other',
    'None / Prefer not to say',
  ];

  motherTongueOptions = [
    'Tagalog', 'Cebuano', 'Ilocano', 'Hiligaynon / Ilonggo', 'Bikol',
    'Waray', 'Kapampangan', 'Pangasinense', 'Maranao', 'Maguindanaon',
    'Tausug', 'Chavacano', 'Aklanon', 'Surigaonon', 'Yakan',
    'Ibanag', 'Ivatan', 'Kinaray-a', 'Sambal', 'English', 'Other',
  ];

  get yearFromOptions(): number[] {
    const current = new Date().getFullYear();
    const years: number[] = [];
    // Go back to 1980 to cover students who attended elementary in the early 1980s
    for (let y = current; y >= 1980; y--) years.push(y);
    return years;
  }

  yearToOptions(fromYear: string | number): number[] {
    const current = new Date().getFullYear();
    const from = Number(fromYear);
    if (!from) {
      // No from selected yet — show 1980 up to current year + 1
      const years: number[] = [];
      for (let y = current + 1; y >= 1980; y--) years.push(y);
      return years;
    }
    // "To" starts from the selected "From" year up to current year + 1
    const years: number[] = [];
    for (let y = current + 1; y >= from; y--) years.push(y);
    return years;
  }

  collapseAddress(): void {
    const f = this.regForm;
    // Build homeAddress string then collapse
    const parts = [
      f.addrStreet?.trim(),
      f.addrPurok?.trim()    ? 'Purok ' + f.addrPurok.trim()      : '',
      f.addrBarangay?.trim() ? 'Brgy. ' + f.addrBarangay.trim()   : '',
      f.addrCity?.trim(),
      f.addrProvince?.trim(),
    ].filter(Boolean);
    f.homeAddress = parts.join(', ');
    this.addrExpanded = false;
    this.cdr.detectChanges();
  }
  deliveryOptions = ['Face to Face','Online','Modular','Combination of Face to face and Online','Blended Methods of Learning'];

  // ── Previous Schools (Step 2) ────────────────────────────────
  previousSchools: { level: string; schoolName: string; yearFrom: string; yearTo: string }[] = [
    { level: '', schoolName: '', yearFrom: '', yearTo: '' }
  ];

  addrExpanded = false;  // address breakdown toggle
  guardianAddrExpanded = false; // guardian address breakdown toggle

  collapseGuardianAddress(): void {
    const f = this.regForm;
    const parts = [
      f.guardianAddrStreet?.trim(),
      f.guardianAddrPurok?.trim()    ? 'Purok ' + f.guardianAddrPurok.trim()    : '',
      f.guardianAddrBarangay?.trim() ? 'Brgy. ' + f.guardianAddrBarangay.trim() : '',
      f.guardianAddrCity?.trim(),
      f.guardianAddrProvince?.trim(),
    ].filter(Boolean);
    f.guardianAddress = parts.join(', ');
    this.guardianAddrExpanded = false;
    this.cdr.detectChanges();
  }

  copyStudentAddressToGuardian(): void {
    const f = this.regForm;
    f.guardianAddrStreet   = f.addrStreet   ?? '';
    f.guardianAddrPurok    = f.addrPurok    ?? '';
    f.guardianAddrBarangay = f.addrBarangay ?? '';
    f.guardianAddrCity     = f.addrCity     ?? '';
    f.guardianAddrProvince = f.addrProvince ?? '';
    this.collapseGuardianAddress();
  }


  // ═══════════════════════════════════════════════════════════════════════════
  // FIX INLINE-VALIDATION-01: Per-field real-time validators.
  // Called on (blur) so errors appear the moment a user leaves a field,
  // and on (input) while a field already has an error (clear-as-you-type).
  // ═══════════════════════════════════════════════════════════════════════════

  /** Validate a single Step 2 field and update fieldErrors immediately. */
  validateStep2Field(field: string): void {
    const f = this.regForm;
    const phoneRe = /^09\d{9}$/;

    const set   = (msg: string) => { this.fieldErrors[field] = msg; this.cdr.detectChanges(); };
    const clear = ()            => { delete this.fieldErrors[field]; this.cdr.detectChanges(); };

    switch (field) {
      case 'lastName':
        if (!f.lastName?.trim()) { set('Last Name is required.'); return; }
        if (!/^[\p{L}\s'\-\.]+$/u.test(f.lastName.trim())) { set('Last name contains invalid characters.'); return; }
        clear(); break;

      case 'firstName':
        if (!f.firstName?.trim()) { set('First Name is required.'); return; }
        if (!/^[\p{L}\s'\-\.]+$/u.test(f.firstName.trim())) { set('First name contains invalid characters.'); return; }
        clear(); break;

      case 'lrnNo':
        if (!f.lrnNo?.trim()) {
          set(this.isTransfereeEnrolling && (this.isCollege || this.isTVET)
            ? 'Previous Student No. is required.'
            : 'LRN No. is required.');
          return;
        }
        if (!this.isTransfereeEnrolling || this.isSHS) {
          if (!/^\d{12}$/.test(f.lrnNo.trim())) { set('LRN must be exactly 12 digits.'); return; }
        } else {
          if (f.lrnNo.trim().length < 3 || f.lrnNo.trim().length > 30) { set('Must be 3–30 characters.'); return; }
        }
        clear(); break;

      case 'dateOfBirth':
        if (!f.dateOfBirth) { set('Date of Birth is required.'); return; }
        const dob = new Date(f.dateOfBirth);
        const today = new Date(); today.setHours(0,0,0,0);
        if (isNaN(dob.getTime())) { set('Please enter a valid date.'); return; }
        if (dob >= today) { set('Date of Birth must be before today.'); return; }
        const age = (today.getTime() - dob.getTime()) / (365.25*24*3600*1000);
        if (age < 14) { set('Student must be at least 14 years old.'); return; }
        if (age > 80) { set('Please enter a valid Date of Birth.'); return; }
        clear(); break;

      case 'religion':
        if (!f.religion?.trim()) { set('Religion is required.'); return; }
        clear(); break;

      case 'placeOfBirth':
        if (!f.placeOfBirth?.trim()) { set('Place of Birth is required.'); return; }
        clear(); break;

      case 'citizenship':
        if (!f.citizenship?.trim()) { set('Nationality is required.'); return; }
        clear(); break;

      case 'addrBarangay':
        if (!f.addrBarangay?.trim()) { set('Barangay is required.'); return; }
        clear(); break;

      case 'addrCity':
        if (!f.addrCity?.trim()) { set('City / Municipality is required.'); return; }
        clear(); break;

      case 'addrProvince':
        if (!f.addrProvince?.trim()) { set('Province is required.'); return; }
        clear(); break;

      case 'contactNumber':
        if (!f.contactNumber?.trim()) { set('Contact Number is required.'); return; }
        const cn = f.contactNumber.replace(/\s/g,'');
        if (/[a-zA-Z]/.test(cn)) { set('Contact Number must not contain letters.'); return; }
        if (!phoneRe.test(cn)) { set('Enter a valid 11-digit PH mobile number (e.g. 09XXXXXXXXX).'); return; }
        clear(); break;

      case 'motherTongue':
        if (!f.motherTongue?.trim()) { set('Mother Tongue is required.'); return; }
        clear(); break;

      case 'specialNeedsDetails':
        if (f.hasSpecialNeeds === 'Yes' && !f.specialNeedsDetails?.trim()) { set('Please specify your special education needs.'); return; }
        clear(); break;

      case 'assistiveTechDetails':
        if (f.hasAssistiveTech === 'Yes' && !f.assistiveTechDetails?.trim()) { set('Please specify the assistive technology used.'); return; }
        clear(); break;

      case 'guardianName':
        if (!f.guardianName?.trim()) { set('Guardian Name is required.'); return; }
        clear(); break;

      case 'guardianAddrBarangay':
        if (!f.guardianAddrBarangay?.trim()) { set('Barangay is required.'); return; }
        clear(); break;

      case 'guardianAddrCity':
        if (!f.guardianAddrCity?.trim()) { set('City / Municipality is required.'); return; }
        clear(); break;

      case 'guardianAddrProvince':
        if (!f.guardianAddrProvince?.trim()) { set('Province is required.'); return; }
        clear(); break;

      case 'guardianContact':
        if (!f.guardianContact?.trim()) { set('Guardian Contact is required.'); return; }
        const gc = f.guardianContact.replace(/\s/g,'');
        if (/[a-zA-Z]/.test(gc)) { set('Guardian Contact must not contain letters.'); return; }
        if (!phoneRe.test(gc)) { set('Enter a valid 11-digit PH mobile number (e.g. 09XXXXXXXXX).'); return; }
        clear(); break;

      case 'guardianEmail':
        if (!f.guardianEmail?.trim()) { set('Guardian Email is required.'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.guardianEmail.trim())) { set('Enter a valid email address.'); return; }
        clear(); break;

      case 'guardianRelationship':
        if (!f.guardianRelationship?.trim()) { set('Relationship is required.'); return; }
        clear(); break;

      default:
        clear(); break;
    }
  }

  /** Validate a single Step 4 account field and update accountErrors immediately. */
  /** Real-time email availability check on blur — prevents duplicate accounts */
  checkEmailAvailability(): void {
    const email = (this.accountForm.email ?? '').trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;
    this.http.get<any>(`${this.authUrl}?action=check_email&email=${encodeURIComponent(email)}`).subscribe({
      next: (res) => {
        if (!res.available) {
          this.accountErrors['email'] = 'This email is already registered. Please use a different email or log in to your existing account.';
          this.cdr.detectChanges();
        } else {
          // Clear any previous email error if now available
          if (this.accountErrors['email']?.includes('already registered')) {
            delete this.accountErrors['email'];
            this.cdr.detectChanges();
          }
        }
      }
    });
  }

  /** Real-time name + DOB duplicate check on blur of any personal info field */
  checkNameDuplicate(): void {
    const first = (this.regForm.firstName   ?? '').trim();
    const last  = (this.regForm.lastName    ?? '').trim();
    const dob   = (this.regForm.dateOfBirth ?? '').trim();
    if (!first || !last || !dob) return; // need all three to check

    this.http.get<any>(
      `${this.authUrl}?action=check_name` +
      `&first_name=${encodeURIComponent(first)}` +
      `&last_name=${encodeURIComponent(last)}` +
      `&date_of_birth=${encodeURIComponent(dob)}`
    ).subscribe({
      next: (res) => {
        if (!res.available) {
          this.fieldErrors['lastName'] = res.message ||
            'A student with this name and date of birth already exists. Please log in instead.';
          this.enrollError = this.fieldErrors['lastName'];
          this.cdr.detectChanges();
        } else {
          if (this.fieldErrors['lastName']?.includes('already exists')) {
            delete this.fieldErrors['lastName'];
            if (this.enrollError?.includes('already exists')) this.enrollError = '';
            this.cdr.detectChanges();
          }
        }
      }
    });
  }

  validateStep4Field(field: string): void {
    const a = this.accountForm;
    const set   = (msg: string) => { this.accountErrors[field] = msg; this.cdr.detectChanges(); };
    const clear = ()            => { delete this.accountErrors[field]; this.cdr.detectChanges(); };

    switch (field) {
      case 'email':
        if (!a.email?.trim()) { set('Email is required.'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(a.email.trim())) { set('Enter a valid email address.'); return; }
        clear(); break;

      case 'password':
        if (!a.password) { set('Password is required.'); return; }
        const pwErr = this.validatePassword(a.password);
        if (pwErr) { set(pwErr); return; }
        // Re-check confirm password live if already typed
        if (a.confirmPassword && a.password !== a.confirmPassword) {
          this.accountErrors['confirmPassword'] = 'Passwords do not match.';
        } else if (a.confirmPassword && a.password === a.confirmPassword) {
          delete this.accountErrors['confirmPassword'];
        }
        clear(); break;

      case 'confirmPassword':
        if (!a.confirmPassword) { set('Please confirm your password.'); return; }
        if (a.password !== a.confirmPassword) { set('Passwords do not match.'); return; }
        clear(); break;

      default:
        clear(); break;
    }
    this.cdr.detectChanges();
  }

  // FIX PREV-SCHOOL-LEVEL-01 (revised)
  getPrevSchoolLevels(): string[] {
    const transferee = this.isTransfereeEnrolling;
    if (this.isSHS) {
      return transferee
        ? ['Junior High School', 'Senior High School']
        : ['Junior High School'];
    }
    // College or TVET
    return transferee
      ? ['College', 'Vocational']
      : ['Senior High School'];
  }

  sanitizePrevSchoolLevels(): void {
    const allowed = this.getPrevSchoolLevels();
    this.previousSchools.forEach(s => {
      if (s.level && !allowed.includes(s.level)) s.level = '';
    });
    this.cdr.detectChanges();
  }

  // FIX LRN-FIELD-01
  getIdFieldMeta(): { label: string; placeholder: string; hint: string; isLrn: boolean } {
    const transferee = this.isTransfereeEnrolling;
    if ((this.isCollege || this.isTVET) && transferee) {
      return {
        label: 'Previous Student No.',
        placeholder: 'Student number from previous school',
        hint: 'Enter the student number assigned by your previous school.',
        isLrn: false,
      };
    }
    return {
      label: 'LRN No.',
      placeholder: 'Learner Reference Number (12 digits)',
      hint: 'Your 12-digit Learner Reference Number from DepEd.',
      isLrn: true,
    };
  }

  addPreviousSchool(): void {
    this.previousSchools.push({ level: '', schoolName: '', yearFrom: '', yearTo: '' });
  }

  removePreviousSchool(index: number): void {
    if (this.previousSchools.length > 1) {
      this.previousSchools.splice(index, 1);
    }
  }

  get yearLevelOptions(): string[] {
    if (this.studentTypeCategory === 'SHS') return ['Grade 11', 'Grade 12'];
    if (this.studentTypeCategory === 'TVET') return ['1st Year', '2nd Year', '3rd Year'];
    return ['1st Year', '2nd Year', '3rd Year', '4th Year'];
  }

  /** Auto-correct regForm.yearLevel when the category changes so the dropdown
   *  never shows a blank selection. Called from proceedFromProgram(). */
  private syncYearLevelToCategory(): void {
    const opts = this.yearLevelOptions;
    // If the current value is not in the list for this category, reset it.
    if (!opts.includes(this.regForm.yearLevel)) {
      // For SHS, prefer the grade picked in Step 1 (selectedGradeLevel).
      this.regForm.yearLevel = (this.isSHS && this.selectedGradeLevel)
        ? this.selectedGradeLevel
        : opts[0];
    }
  }

  get ayYearOptions(): string[] {
    const now = new Date();
    const month = now.getMonth() + 1; // 1-12
    // Current AY: if June-Dec → current year is start; if Jan-May → previous year is start
    const startYear = month >= 6 ? now.getFullYear() : now.getFullYear() - 1;
    // Offer current AY + 1 year back and 1 year forward
    return [
      `${startYear}-${startYear + 1}`,
      `${startYear + 1}-${startYear + 2}`,
      `${startYear - 1}-${startYear}`,
    ];
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
  isFullScholarship = false; // true when Full Scholarship selected — covers entire tuition
  expandedScholarCat: number | null = null;
  scholarClaimCode  = '';
  scholarCodeStatus: 'idle' | 'checking' | 'valid' | 'invalid' = 'idle';
  scholarCodeMsg    = '';
  scholarPreapprovalId = 0;

  // ── Scholarship types grouped by grantor ─────────────────────────────────
  scholarCategories: { group: string; types: string[] }[] = [
    {
      group: 'CHED Scholarships',
      types: [
        'CHED — UniFAST Free Higher Education (RA 10931)',
        'CHED — Tertiary Education Subsidy (TES)',
        'CHED — Student Financial Assistance Program (StuFAP)',
        'CHED — Full Merit Scholarship',
        'CHED — Half Merit Scholarship',
        'CHED — Scholarship for Student with Disabilities (SSD)',
        'CHED — Tulong Dunong Program',
        'CHED — Graduate Education Scholarship',
        'CHED — State Scholarship Program (SSP)',
        'CHED — Presidential Scholarship',
      ]
    },
    {
      group: 'TESDA Scholarships',
      types: [
        'TESDA — Training for Work Scholarship Program (TWSP)',
        'TESDA — Private Education Student Financial Assistance (PESFA)',
        'TESDA — Unified Student Financial Assistance System for Tertiary Education (UniFAST)',
        'TESDA — STEP (Special Training for Employment Program)',
      ]
    },
    {
      group: 'Government / LGU',
      types: [
        'Local Government Unit (LGU) Scholarship',
        'DepEd Scholarship',
        'DOST — Science and Technology Scholarship',
        'DOST — PAGASA Scholarship',
        'AFP / PNP Dependents Scholarship',
        'PVAO (Veterans) Scholarship',
        'PCSO Scholarship',
        'Solo Parent Scholarship (RA 8972)',
      ]
    },
    {
      group: 'School-Based',
      types: [
        'School-Based Merit Scholarship',
        'Faculty / Staff Dependent Discount',
        'Sibling Discount',
        'Academic Excellence Award',
        'Athletic Scholarship',
        'Cultural / Arts Scholarship',
      ]
    },
    {
      group: 'Private / Foundation',
      types: [
        'Private Scholarship / Foundation',
        'Corporate Scholarship (Company-Sponsored)',
        'Religious / Church Scholarship',
        'NGO Scholarship',
      ]
    },
    {
      group: 'Other',
      types: ['Other']
    },
  ];

  // Flat list kept for backward compatibility with any existing references
  get scholarTypes(): string[] {
    return this.scholarCategories.flatMap(g => g.types);
  }

  paymentMethod: 'GCash' | 'Cash' = 'Cash'; // FIX FE-PM-NULL-01: default Cash — safer when method is unknown
  tuitionAmount = 25000;
  get discountedAmount(): number {
    return this.isScholar && this.scholarshipAmount > 0 ? Math.max(0, this.tuitionAmount - this.scholarshipAmount) : this.tuitionAmount;
  }

  // ── Fee Preview (computed from program units) ─────────────
  feePreview: {
    units: number;
    tuitionFee: number; miscellaneousFee: number; registrationFee: number;
    laboratoryFee: number; energyFee: number;
    extraFees: { fee_key: string; fee_label: string; is_per_unit: number; rate: number; amount: number }[];
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
           laboratoryFee:number; energyFee:number;
           extraFees?: { fee_key: string; fee_label: string; is_per_unit: number; rate: number; amount: number }[];
           subtotal:number; discount:number;
           installmentFee:number; totalAssessment:number } | null;
  } | null = null;
  private torPollTimer: any = null;

  // ── Payment Plan ──────────────────────────────────────────
  // 'full' = pay everything now
  // 'installment' = DP + Prelim + Midterm + Finals (each = total / 4)
  paymentPlan: 'full' | 'installment' = 'full';

  // ── Enrollment Period ─────────────────────────────────────────
  enrollmentPeriod: { is_open: boolean; start: string|null; end: string|null; label: string } | null = null;
  enrollmentIsOpen = true;
  enrollmentClosedMsg = '';
  isCheckingPeriod = false;

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

  // ── Registration confirmation data ─────────────────────────
  confirmedStudentNumber = '';
  confirmedName          = '';
  confirmedProgram       = '';
  confirmedNextStep      = ''; // 'payment' | 'tor' | 'free'
  confirmedAutoLoginSec  = 5;  // countdown before auto-login
  private confirmCountdown?: ReturnType<typeof setInterval>;
  // FIX REFRESH-REGISTER-01: Prevents submitEnrollment() from firing twice
  // when the browser is refreshed mid-Step-4 and form state is restored.
  private enrollmentSubmitted = false;

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

  private sessionPollTimer: any = null;
  checkingSession = false;
  redirectingToDashboard = false;
  redirectCountdown = 3;

  constructor(private http: HttpClient, private router: Router, private cdr: ChangeDetectorRef, private auth: AuthService) {
    // Check localStorage in the constructor — before Angular renders the template.
    // This ensures redirectingToDashboard=true on the FIRST render, so the login
    // form never flashes and the overlay shows immediately.
    try {
      const token     = localStorage.getItem('sia_student_token');
      const expiresAt = Number(localStorage.getItem('sia_student_expiry') ?? 0);
      const userRaw   = localStorage.getItem('sia_student_user');
      if (token && expiresAt && Date.now() < expiresAt && userRaw) {
        const user = JSON.parse(userRaw);
        if (user?.role === 'student') {
          this.redirectingToDashboard = true;
          this.redirectCountdown = 3;
        }
      }
    } catch { /* localStorage blocked — show login normally */ }
  }

  // ── Wizard state persistence helpers ─────────────────────────
  saveWizardState(): void { // public so template can call after inline property assignments
    // FIX REFRESH-REGISTER-01: Never persist the 'account' step.
    // If the browser is refreshed while on Step 4, restoring this state
    // leaves all form data intact so the Submit button fires a second
    // register_student call, creating a duplicate record.
    // Drop back to 'documents' on restore — one extra Next-click is
    // far safer than a phantom registration.
    if (this.enrollStep === 'account') return;
    const state = {
      view: this.view, enrollStep: this.enrollStep,
      studentTypeCategory: this.studentTypeCategory,
      selectedProgram: this.selectedProgram, selectedProgramName: this.selectedProgramName,
      selectedDepartment: this.selectedDepartment, selectedDept: this.selectedDept,
      selectedGradeLevel: this.selectedGradeLevel, selectedTvetType: this.selectedTvetType,
      shsSelectedTrack: this.shsSelectedTrack, tvetSelectedType: this.tvetSelectedType,
      regForm: this.regForm, previousSchools: this.previousSchools,
      isScholar: this.isScholar, scholarType: this.scholarType,
      scholarGrantor: this.scholarGrantor, scholarshipAmount: this.scholarshipAmount,
      isFullScholarship: this.isFullScholarship,
      scholarClaimCode: this.scholarClaimCode, scholarPreapprovalId: this.scholarPreapprovalId,
      paymentMethod: this.paymentMethod, paymentPlan: this.paymentPlan,
      torReviewStudentId: this.torReviewStudentId,
      // Never save 'sending' — it's transient. If reload happens mid-send, reset to idle.
      torReviewPhase: this.torReviewPhase === 'sending' ? 'idle' : this.torReviewPhase,
      torEvalResult: this.torEvalResult,
    };
    sessionStorage.setItem('enrollWizardState', JSON.stringify(state));
  }

  private clearWizardState(): void {
    sessionStorage.removeItem('enrollWizardState');
    sessionStorage.removeItem('torReviewStudentId');
  }

  // Fires when another tab logs in (sets sia_student_token in localStorage)
  private _onStorageLogin = (e: StorageEvent): void => {
    console.log('[SIA] storage event on login page:', e.key, '| newValue:', e.newValue ? e.newValue.substring(0,10) : 'null');
    if (e.key !== 'sia_student_token' || !e.newValue) return;
    console.log('[SIA] sia_student_token set — redirecting immediately!');
    // Don't wait for Angular change detection — redirect straight away
    window.location.replace('/#/student/dashboard');
  };

  ngOnInit(): void {
    console.log('[SIA] login ngOnInit — adding storage listener');
    if (typeof window !== 'undefined') {
      window.addEventListener('storage', this._onStorageLogin);
    }

    // If constructor detected an active session, start the countdown now.
    if (this.redirectingToDashboard) {
      const countdown = setInterval(() => {
        this.redirectCountdown--;
        this.cdr.detectChanges();
        if (this.redirectCountdown <= 0) {
          clearInterval(countdown);
          window.location.replace('/#/student/dashboard');
        }
      }, 1000);
      return; // don't restore wizard state
    }

    // Restore wizard state after reload
    const raw = sessionStorage.getItem('enrollWizardState');
    if (raw) {
      try {
        const s = JSON.parse(raw);
        this.view                = s.view                ?? 'enroll';
        this.enrollStep          = s.enrollStep          ?? 'program';
        this.studentTypeCategory = s.studentTypeCategory ?? '';
        this.selectedProgram     = s.selectedProgram     ?? '';
        this.selectedProgramName = s.selectedProgramName ?? '';
        this.selectedDepartment  = s.selectedDepartment  ?? '';
        this.selectedDept        = s.selectedDept        ?? '';
        this.selectedGradeLevel  = s.selectedGradeLevel  ?? '';
        this.selectedTvetType    = s.selectedTvetType    ?? '';
        this.shsSelectedTrack    = s.shsSelectedTrack    ?? '';
        this.tvetSelectedType    = s.tvetSelectedType    ?? '';
        if (s.regForm)                this.regForm          = { ...this.regForm, ...s.regForm };
        if (!this.regForm.isIndigenous)    this.regForm.isIndigenous    = 'No';
        if (!this.regForm.hasSpecialNeeds) this.regForm.hasSpecialNeeds = 'No';
        if (!this.regForm.hasAssistiveTech) this.regForm.hasAssistiveTech = 'No';
        if (s.previousSchools?.length) this.previousSchools = s.previousSchools;
        this.isScholar           = s.isScholar           ?? false;
        this.scholarType         = s.scholarType         ?? '';
        this.scholarGrantor      = s.scholarGrantor      ?? '';
        this.scholarshipAmount   = s.scholarshipAmount   ?? 0;
        // FIX FE-PM-NULL-01: restore as-is; if empty, keep current value (class default = 'Cash')
        this.paymentMethod       = (s.paymentMethod === 'Cash' || s.paymentMethod === 'GCash') ? s.paymentMethod : this.paymentMethod;
        this.paymentPlan         = s.paymentPlan         ?? 'full';
        this.torReviewStudentId  = s.torReviewStudentId  ?? 0;
        // Never restore a mid-flight 'sending' state — if they reloaded during submission
        // the request was lost; reset to 'idle' so they can re-submit safely
        const restoredPhase = s.torReviewPhase ?? 'idle';
        this.torReviewPhase      = (restoredPhase === 'sending') ? 'idle' : restoredPhase;
        this.torEvalResult       = s.torEvalResult       ?? null;
        // isSubmitting always resets to false on restore — never leave stuck spinner
        this.isSubmitting        = false;
        // Resume TOR poll if mid-evaluation
        if (this.enrollStep === 'tor-review' && this.torReviewStudentId > 0 && this.torReviewPhase === 'waiting') {
          this.startTorPoll();
        }
        // FIX TVET-TRANSFEREE-SUBJECTS-03: programCourses is in-memory only — it is
        // lost on page refresh. Reload it whenever state is restored to a phase that
        // shows the Program Subjects list (done or rejected), so the subject grid is
        // never blank after a refresh.
        if ((this.torReviewPhase === 'done' || this.torReviewPhase === 'rejected') && this.selectedProgramName) {
          this.loadProgramCourses();
        }
        if (this.studentTypeCategory) this.loadPrograms();
        // FIX SHS-YEARLEVEL-01 (restore): re-sync yearLevel after category is known
        this.syncYearLevelToCategory();

        // FIX REFRESH-FEE-01: If restoring to the 'documents' step for non-transferee
        // College students, feePreview is lost (not persisted). Reload it so the
        // formula template in Step 4 has the data it needs after a refresh.
        // We clear feePreview first so the template shows cleanly, not stale values.
        this.feePreview = null;

        // FIX REFRESH-PAYMENT-01: Ensure paymentPlan/paymentMethod always have
        // a valid value after restore. Default 'full'/'Cash' if null/undefined.
        if (!this.paymentPlan)   this.paymentPlan   = 'full';
        if (!this.paymentMethod) this.paymentMethod = 'Cash';

        this.cdr.detectChanges();
        return;
      } catch (e) {
        this.clearWizardState();
      }
    }
    // Legacy fallback
    const saved = sessionStorage.getItem('torReviewStudentId');
    if (saved) {
      const sid = parseInt(saved, 10);
      if (sid > 0) {
        this.torReviewStudentId = sid;
        this.view = 'enroll'; this.enrollStep = 'tor-review'; this.torReviewPhase = 'waiting';
        this.startTorPoll(); this.cdr.detectChanges();
      }
    }
  }

  ngOnDestroy(): void {
    if (typeof window !== 'undefined') {
      window.removeEventListener('storage', this._onStorageLogin);
    }
    if (this.torPollTimer) clearInterval(this.torPollTimer);
    if (this.otpTimer) clearInterval(this.otpTimer);
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
              dept: (p.department || '').trim(),  // empty string if no dept — never fall back to level name
            });
          });
        }
        this.cdr.detectChanges();
      },
      error: () => { this.programsLoading = false; this.cdr.detectChanges(); }
    });
  }

  // ══ LOGIN ═════════════════════════════════════════════════════
  /** Validate a single login field on blur or on input (after touched). */
  validateLoginField(field: 'email' | 'password'): void {
    this.loginTouched[field] = true;
    if (field === 'email') {
      if (!this.email?.trim())
        this.loginErrors['email'] = 'Email is required.';
      else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim()))
        this.loginErrors['email'] = 'Enter a valid email address.';
      else
        this.loginErrors['email'] = '';
    }
    if (field === 'password') {
      if (!this.password)
        this.loginErrors['password'] = 'Password is required.';
      else if (this.password.length < 6)
        this.loginErrors['password'] = 'Password must be at least 6 characters.';
      else
        this.loginErrors['password'] = '';
    }
    this.cdr.detectChanges();
  }

  login(): void {
    this.loginErrors = {};
    let loginValid = true;
    if (!this.email?.trim()) { this.loginErrors['email'] = 'Email is required.'; loginValid = false; }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim())) { this.loginErrors['email'] = 'Enter a valid email address.'; loginValid = false; }
    if (!this.password) { this.loginErrors['password'] = 'Password is required.'; loginValid = false; }
    else if (this.password.length < 6) { this.loginErrors['password'] = 'Password must be at least 6 characters.'; loginValid = false; }
    if (!loginValid) { this.cdr.detectChanges(); return; }

    // Guard: if already logged in, redirect instead of re-authenticating
    const existingToken = this.auth.getToken();
    const existingUser  = JSON.parse(sessionStorage.getItem('currentUser') ?? 'null');
    if (existingToken && existingUser?.role) {
      this.redirectByRole(existingUser.role);
      return;
    }

    this.loading = true; this.errorMessage = ''; this.successMessage = '';
    this.http.post<any>(`${this.authUrl}?action=login`, { email: this.email, password: this.password, portal: 'student' }).subscribe({
      next: (res) => {
        this.loading = false;
        // ── 2FA: backend returns otp_required=true, show OTP modal ──
        if (res.otp_required) {
          this.otpToken     = res.otp_token ?? '';
          this.otpInput     = '';
          this.otpError     = '';
          this.otpCountdown = res.otp_expires_in ?? 300;
          this.showOtpModal = true;
          this._startOtpTimer();
          this.cdr.detectChanges();
          return;
        }
        if (res.success) {
          // ── Student portal only — block non-student roles ──
          if (res.user?.role !== 'student') {
            this.errorMessage = 'This portal is for students only. Please use the correct portal.';
            this.cdr.detectChanges();
            return;
          }
          if (isPlatformBrowser(this.platformId)) {
            this.auth.storeSession(res.token, res.user, res.user.role);
          }
          this.redirectByRole(res.user.role);
        } else { this.errorMessage = res.message || 'Login failed'; this.cdr.detectChanges(); }
      },
      error: () => { this.errorMessage = 'Connection error. Make sure XAMPP is running.'; this.loading = false; this.cdr.detectChanges(); }
    });
  }

  private redirectByRole(role: string): void {
    const r: { [k: string]: string } = { student: '/student', admin: '/admin', accounting: '/accounting', registrar: '/registrar', faculty: '/instructor' };
    this.clearWizardState();
    this.router.navigate([r[role] || '/login']);
  }

  // ── OTP 2FA methods (student portal login) ─────────────────
  private _startOtpTimer(): void {
    if (this.otpTimer) clearInterval(this.otpTimer);
    this.otpTimer = setInterval(() => {
      this.otpCountdown--;
      this.cdr.detectChanges();
      if (this.otpCountdown <= 0) {
        clearInterval(this.otpTimer);
        this.showOtpModal = false;
        this.otpError = 'OTP expired. Please log in again.';
        this.cdr.detectChanges();
      }
    }, 1000);
  }

  verifyStudentOtp(): void {
    if (!this.otpInput || this.otpInput.trim().length !== 6) {
      this.otpError = 'Please enter the 6-digit code.'; this.cdr.detectChanges(); return;
    }
    this.otpVerifying = true; this.otpError = ''; this.cdr.detectChanges();
    this.http.post<any>(`${this.authUrl}?action=verify_otp`, {
      otp_token: this.otpToken,
      otp_code:  this.otpInput.trim(),
    }).subscribe({
      next: (res) => {
        this.otpVerifying = false;
        if (res.success) {
          if (this.otpTimer) clearInterval(this.otpTimer);
          this.showOtpModal = false;
          if (isPlatformBrowser(this.platformId)) {
            this.auth.storeSession(res.token, res.user, 'student');
          }
          this.redirectByRole(res.user?.role ?? 'student');
        } else {
          this.otpError = res.message || 'Incorrect OTP. Please try again.';
          this.cdr.detectChanges();
        }
      },
      error: (err) => {
        this.otpVerifying = false;
        this.otpError = err.error?.message || 'Verification failed.';
        this.cdr.detectChanges();
      }
    });
  }

  cancelStudentOtp(): void {
    this.showOtpModal = false;
    this.otpInput = '';
    this.otpToken = '';
    this.otpError = '';
    if (this.otpTimer) clearInterval(this.otpTimer);
  }

  // ══ ENROLLMENT WIZARD ════════════════════════════════════════
  openEnrollment(): void {
    // Check if enrollment is open before showing the wizard
    this.isCheckingPeriod = true;
    this.enrollmentClosedMsg = '';
    this.http.get<any>(`${this.apiUrl}?action=get_enrollment_period`).subscribe({
      next: res => {
        this.isCheckingPeriod = false;
        this.enrollmentIsOpen = res.is_open ?? true;
        this.enrollmentPeriod = res.period ?? null;
        // Only show closed message if explicitly closed AND has a label/date
        if (!this.enrollmentIsOpen) {
          const p = res.period ?? {};
          let msg = 'Enrollment is currently closed.';
          if (p.label)  msg += ` (${p.label})`;
          if (p.start)  msg += ` Opens: ${new Date(p.start).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'})}`;
          this.enrollmentClosedMsg = msg;
        } else {
          this.enrollmentClosedMsg = ''; // clear if open
        }
        this.view = 'enroll'; this.enrollStep = 'program'; this.enrollError = '';
        this.saveWizardState();
    this.studentTypeCategory = ''; this.selectedProgram = ''; this.selectedProgramName = ''; this.selectedDepartment = ''; this.selectedDept = ''; this.selectedGradeLevel = ''; this.selectedTvetType = '';
    this.regForm = { lastName:'',firstName:'',middleName:'',suffix:'',studentType:'New',lrnNo:'',dateOfBirth:'',lastSchoolAttended:'',psaBirthCertNo:'',sex:'',religion:'',age:'',placeOfBirth:'',citizenship:'',homeAddress:'',addrStreet:'',addrPurok:'',addrBarangay:'',addrCity:'',addrProvince:'',contactNumber:'',isIndigenous:'No',motherTongue:'',hasSpecialNeeds:'No',specialNeedsDetails:'',hasAssistiveTech:'No',assistiveTechDetails:'',strand:'',learningDelivery:'',guardianName:'',guardianAddress:'',guardianAddrStreet:'',guardianAddrPurok:'',guardianAddrBarangay:'',guardianAddrCity:'',guardianAddrProvince:'',guardianContact:'',guardianEmail:'',guardianRelationship:'',yearLevel:'1st Year',semesterEnroll:'',ayYear:'' };
    this.torFile=null; this.goodMoralFile=null; this.psaFile=null; this.form138File=null; this.picFile=null;
    this.torFileName=''; this.goodMoralFileName=''; this.psaFileName=''; this.form138FileName=''; this.picFileName='';
    this.isScholar=false; this.scholarType=''; this.scholarGrantor=''; this.scholarshipAmount=0;
    this.isFullScholarship=false; this.scholarClaimCode=''; this.scholarCodeStatus='idle';
    this.expandedScholarCat=null;
    this.scholarCodeMsg=''; this.scholarPreapprovalId=0;
    this.paymentMethod='Cash'; this.paymentPlan='full'; // FIX FE-PM-NULL-01: reset to Cash, not GCash
    this.regForm.yearLevel = '1st Year';
    this.applyEnrollmentPeriodToForm();
    this.feePreview=null; this.feePreviewError='';
    this.accountForm={email:'',password:'',confirmPassword:''};
    this.torReviewPhase='idle'; this.torReviewError=''; this.torReviewStudentId=0;
    this.torCreditedCodes=new Set(); this.torEvalResult=null;
    this.previousSchools = [{ level: '', schoolName: '', yearFrom: '', yearTo: '' }];
    this.addrExpanded = false;
    this.guardianAddrExpanded = false;
    if(this.torPollTimer){clearInterval(this.torPollTimer);this.torPollTimer=null;}
    this.loadPrograms();
    this.cdr.detectChanges();
      },
      error: () => {
        this.isCheckingPeriod = false;
        // On error, still open wizard — server will block registration if closed
        this.enrollmentIsOpen = true;
        this.view = 'enroll'; this.enrollStep = 'program';
        this.loadPrograms();
        this.cdr.detectChanges();
      }
    });
  }

  backToLogin(): void { this.view = 'login'; this.enrollError = ''; this.cdr.detectChanges(); }

  get stepNumber(): number { return ({program:1,info:2,subjects:3,documents:4,'tor-review':4,account:5,confirmed:5} as any)[this.enrollStep] ?? 1; }

  // ── Subject Selection Step ────────────────────────────────────────────────
  subjectSelectionCourses: {
    id: number; code: string; name: string; credits: number;
    yearLevel: string; semester: string; selected: boolean;
  }[] = [];
  isSubjectSelectionLoading = false;
  subjectSelectionError = '';

  get selectedSubjects() { return this.subjectSelectionCourses.filter(s => s.selected); }
  get selectedSubjectUnits() { return this.selectedSubjects.reduce((t, s) => t + s.credits, 0); }

  loadSubjectSelection(): void {
    if (!this.selectedProgramName) return;
    this.isSubjectSelectionLoading = true;
    this.subjectSelectionError = '';
    this.subjectSelectionCourses = [];
    this.cdr.detectChanges();

    // FIX SUBJ-FILTER-01: get_program_courses returns ALL subjects for the full
    // curriculum (every year level & semester). We filter to the student's current
    // year level + semester on the frontend so only the relevant term is shown.
    // The backend `year_level` / `semester` query params are kept for servers that
    // do support them, but we never rely on them for correctness.
    this.http.get<any>(
      `${environment.registrarApi}?action=get_program_courses&program=${encodeURIComponent(this.selectedProgramName)}`
    ).subscribe({
      next: (res) => {
        this.isSubjectSelectionLoading = false;
        if (res.success && res.courses) {
          const studentYearLevel = (this.regForm.yearLevel || '1st Year').trim();
          // Derive the semester term only (strip "AY YYYY-YYYY" suffix for matching)
          // e.g. "1st Semester, AY 2025-2026" → "1st Semester"
          const studentSemTerm = (this.fullSemester || '').split(',')[0].trim();

          const all: typeof this.subjectSelectionCourses = res.courses.map((c: any) => ({
            id:        +(c.courseId ?? c.id ?? 0),   // backend returns 'courseId'
            code:      c.code,
            name:      c.name,
            credits:   +(c.credits ?? 0),
            yearLevel: (c.yearLevel ?? c.year_level ?? '').trim(),
            semester:  (c.semester  ?? '').trim(),
            selected:  true,
          }));

          // Filter to current year level + semester only
          const filtered = all.filter(c => {
            const ylMatch  = !c.yearLevel  || c.yearLevel  === studentYearLevel;
            const semMatch = !c.semester   || c.semester   === studentSemTerm
                             // also try full semester string match as fallback
                             || c.semester === this.fullSemester.trim();
            return ylMatch && semMatch;
          });

          // If filtering yields nothing (curriculum data has no year/sem tags),
          // fall back to showing the full list so the student isn't stranded.
          this.subjectSelectionCourses = filtered.length > 0 ? filtered : all;
        } else {
          this.subjectSelectionError = 'Could not load subjects for this program.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSubjectSelectionLoading = false;
        this.subjectSelectionError = 'Could not connect to server.';
        this.cdr.detectChanges();
      }
    });
  }

  // ── Year / Semester grouping helpers for Subject Selection step ───────────
  // Separate from getYearLevels() / getSemesters() which operate on programCourses
  // (the transferee TOR subject list). These operate on subjectSelectionCourses.
  getSubjectYearLevels(): string[] {
    const years = [...new Set(this.subjectSelectionCourses.map(c => c.yearLevel || '1st Year'))];
    return years.sort((a, b) =>
      (this.yearOrder.indexOf(a) - this.yearOrder.indexOf(b)) || a.localeCompare(b));
  }

  getSubjectSemesters(yearLevel: string): string[] {
    const sems = [...new Set(
      this.subjectSelectionCourses
        .filter(c => (c.yearLevel || '1st Year') === yearLevel)
        .map(c => c.semester || '1st Semester')
    )];
    return sems.sort((a, b) =>
      (this.semOrder.indexOf(a) - this.semOrder.indexOf(b)) || a.localeCompare(b));
  }

  toggleSubject(course: { selected: boolean }): void {
    course.selected = !course.selected;
    this.cdr.detectChanges();
  }

  selectAllSubjects(): void {
    this.subjectSelectionCourses.forEach(c => c.selected = true);
    this.cdr.detectChanges();
  }

  // ── Subject Approval Waiting State ────────────────────────────────────────
  // After the student submits subjects, the step stays on 'subjects' (waiting sub-view)
  // until the registrar approves. We poll every few seconds.
  subjectSubmissionStatus: 'idle' | 'submitting' | 'pending-account' | 'waiting' | 'approved' | 'rejected' = 'idle';
  accountAlreadyCreated = false; // true after account creation + subject submission
  subjectSubmittedStudentId = 0;
  private subjectPollInterval: any = null;
  subjectRegistrarNotes = '';

  /** Navigate from Step 3 pending-account state to Step 4 Documents.
   *  NOTE: Fee is NOT loaded here — it will only be loaded after Registrar approves
   *  the subject selection (proceedAfterSubjectApproval). Showing fee before approval
   *  would show wrong unit count. */
  goToDocumentsFromSubjects(): void {
    this.enrollStep = 'documents';
    this.feePreview = null;           // clear any stale fee data
    this.feePreviewError = '';
    this.saveWizardState();
    this.cdr.detectChanges();
  }

  proceedFromSubjects(): void {
    if (this.selectedSubjects.length === 0) {
      this.enrollError = 'Please select at least one subject before proceeding.';
      this.cdr.detectChanges(); return;
    }
    this.enrollError = '';
    // Save selection in memory + session for submitEnrollment() to POST later.
    // Do NOT advance to Documents yet — show a waiting state inside Step 3.
    // The student must stay here until the Registrar approves.
    // Flow: subject submit → waiting screen → Registrar approves → Step 4 unlocked.
    this._saveSelectedSubjectsToSession();
    this.subjectSubmissionStatus = 'pending-account'; // waiting for account creation
    this.saveWizardState();
    this.cdr.detectChanges();
  }

  /** Called when Registrar approves — now allow proceed to Step 4 with correct units */
  proceedAfterSubjectApproval(): void {
    this._stopSubjectApprovalPoll();
    this.subjectSubmissionStatus = 'idle';
    this.cdr.detectChanges();

    if (this.accountAlreadyCreated) {
      // Account was created before approval — navigate to student portal.
      // If token is already stored, navigate directly.
      if (this.auth.getToken()) {
        this.clearWizardState();
        this.router.navigate(['/student/enrollment'], {
          queryParams: { _pp: this.paymentPlan, _pm: this.paymentMethod }
        });
      } else {
        // Token not available — do a login first then navigate
        const a = this.accountForm;
        this.http.post<any>(`${this.authUrl}?action=login`, {
          email: a.email, password: a.password, portal: 'student'
        }).subscribe({
          next: (lr) => {
            if (lr.success) this.auth.storeSession(lr.token, lr.user, 'student');
            this.clearWizardState();
            this.router.navigate(['/student/enrollment'], {
              queryParams: { _pp: this.paymentPlan, _pm: this.paymentMethod }
            });
          },
          error: () => {
            // Can't auto-login — redirect to login page with success message
            this.clearWizardState();
            this.view = 'login';
            this.successMessage = 'Your subjects are approved! Please log in to access your Student Portal.';
            this.cdr.detectChanges();
          }
        });
      }
      return;
    }

    // Account not yet created — go to documents step with approved fee
    this.enrollStep = 'documents';
    this.saveWizardState();
    if (!this.isTransfereeEnrolling && !this.isSHS && !this.isTVET) {
      this.loadFeePreviewWithUnits(this.selectedSubjectUnits);
    } else if (this.isSHS) {
      this.loadSHSFee();
    } else if (this.isTVET) {
      this.loadTVETFee();
    } else {
      this.loadProgramCourses();
    }
    this.cdr.detectChanges();
  }

  /** Load fee preview with an explicit unit count (used after subject approval) */
  loadFeePreviewWithUnits(approvedUnits: number): void {
    if (!this.selectedProgramName) return;
    this.isFeePreviewLoading = true;
    this.feePreviewError = '';
    this.feePreview = null;
    this.cdr.detectChanges();
    const discount = this.isScholar && this.scholarshipAmount > 0 ? this.scholarshipAmount : 0;
    const hasInstallment = this.paymentPlan === 'installment';
    this.http.get<any>(
      `${this.accountingUrl}?action=get_fee_preview` +
      `&program=${encodeURIComponent(this.selectedProgramName)}` +
      `&year_level=${encodeURIComponent(this.regForm.yearLevel)}` +
      `&semester=${encodeURIComponent(this.fullSemester)}` +
      `&units=${approvedUnits}` +
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

  private _saveSelectedSubjectsToSession(): void {
    if (typeof sessionStorage === 'undefined') return;
    sessionStorage.setItem('pendingSubjectSelection', JSON.stringify(
      this.selectedSubjects.map(s => ({
        id:      (s as any).id ?? 0,   // already resolved from courseId in loadSubjectSelection
        code:    s.code,
        name:    s.name,
        credits: s.credits
      }))
    ));
  }

  private _submitSubjectSelectionToBackend(studentId: number, userId: number = 0): void {
    // The backend needs course_ids (DB integers). Since at this wizard stage we only
    // have course codes from the program course API, we first resolve them via the
    // same get_program_courses response (course.id should be present). Fall back to
    // code-based matching in enrollSubjectsByCode if course.id is unavailable.
    const courseIds = this.selectedSubjects
      .map(s => (s as any).id)
      .filter((id): id is number => id > 0);

    this.subjectSubmissionStatus = 'submitting';
    this.cdr.detectChanges();

    const payload: any = { notes: '' };
    if (studentId > 0) payload.student_id = studentId;
    if (userId > 0) payload.user_id = userId;
    if (courseIds.length > 0) {
      payload.course_ids = courseIds;
    } else {
      // Fallback: send course codes; backend will resolve to IDs
      payload.course_codes = this.selectedSubjects.map(s => s.code);
    }

    this.http.post<any>(
      `${this.apiUrl}?action=submit_subject_selection`, payload
    ).subscribe({
      next: (res) => {
        if (res.success) {
          this.subjectSubmissionStatus = 'waiting';
          if (studentId) this.subjectSubmittedStudentId = studentId;
          this._saveSelectedSubjectsToSession();
          this.saveWizardState();
          this._startSubjectApprovalPoll(studentId, userId);
        } else {
          this.subjectSubmissionStatus = 'idle';
          this.enrollError = res.message || 'Could not submit your subject selection. Please try again.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.subjectSubmissionStatus = 'idle';
        this.enrollError = 'Could not connect to server. Make sure XAMPP is running.';
        this.cdr.detectChanges();
      }
    });
  }

  private _startSubjectApprovalPoll(studentId: number, userId: number = 0): void {
    this._stopSubjectApprovalPoll();
    const check = () => {
      const url = studentId
        ? `${this.apiUrl}?action=get_subject_selection&student_id=${studentId}`
        : `${this.apiUrl}?action=get_subject_selection&user_id=${userId}`;
      this.http.get<any>(url).subscribe({
        next: (res) => {
          const status = res.status ?? res.selection?.status ?? '';
          if (status === 'Approved') {
            this._stopSubjectApprovalPoll();
            this.subjectSubmissionStatus = 'approved';
            this.subjectRegistrarNotes = res.selection?.registrar_notes ?? '';
            // Update selectedSubjects to reflect APPROVED courses only
            const approvedCourses: any[] = res.selection?.approved_courses ?? [];
            if (approvedCourses.length > 0) {
              this.subjectSelectionCourses.forEach(c => {
                c.selected = approvedCourses.some((a: any) =>
                  a.id === (c as any).id || a.code === c.code);
              });
            }
            // Account was already created before waiting — mark it so Step 5 is skipped
            this.accountAlreadyCreated = true;
            this.cdr.detectChanges();
            // Auto-navigate to student portal immediately — no button press needed
            setTimeout(() => {
              this.clearWizardState();
              this.router.navigate(['/student/enrollment'], {
                queryParams: { _pp: this.paymentPlan, _pm: this.paymentMethod }
              });
            }, 1500); // brief delay so student sees the "Approved!" screen
          } else if (status === 'Rejected') {
            this._stopSubjectApprovalPoll();
            this.subjectSubmissionStatus = 'rejected';
            this.subjectRegistrarNotes = res.selection?.registrar_notes ?? '';
            this.cdr.detectChanges();
          }
          // 'Submitted' | 'Pending' → keep polling
        },
        error: () => {} // silently ignore network hiccups during poll
      });
    };
    check(); // immediate first check
    this.subjectPollInterval = setInterval(check, 8000);
  }

  private _stopSubjectApprovalPoll(): void {
    if (this.subjectPollInterval) {
      clearInterval(this.subjectPollInterval);
      this.subjectPollInterval = null;
    }
  }

  /** Called after registrar approves — student clicks "Continue to Documents" */
  /** Student wants to change selection after rejection */
  resetSubjectSelection(): void {
    this._stopSubjectApprovalPoll();
    this.subjectSubmissionStatus = 'idle';
    this.subjectRegistrarNotes = '';
    // Re-select all (fresh start)
    this.subjectSelectionCourses.forEach(c => c.selected = true);
    this.cdr.detectChanges();
  }

  studentTypeCategoryLabel(): string {
    return this.studentTypeCategory === 'College' ? '🎓 College' : this.studentTypeCategory === 'SHS' ? '📚 Senior High School' : this.studentTypeCategory === 'TVET' ? '🔧 TVET Program' : '';
  }

  // Step 1
  selectStudentTypeCategory(t: 'College' | 'SHS' | 'TVET'): void {
    this.studentTypeCategory = t;
    this.selectedProgram = ''; this.selectedProgramName = '';
    this.selectedDept = ''; this.selectedGradeLevel = ''; this.selectedTvetType = '';
    this.shsSelectedTrack = ''; this.tvetSelectedType = '';
    this.loadPrograms();
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

  // Returns UNIQUE departments for the current level.
  // Get unique dept/strand/program-type labels for a level.
  // Primary: unique non-empty dept values from DB programs (deduplicated).
  // Fallback: localStorage admin entries (only for newly-added depts with no programs yet).
  getDeptsForLevel(level: string): string[] {
    // Step 1 — unique real dept values from DB programs
    const dbDepts = [...new Set(
      (this.programsByType[level] || [])
        .map(p => p.dept.trim())
        .filter(d => d !== '' && d !== level)  // skip empty or accidental level-name fallbacks
    )];

    // Step 2 — localStorage admin dept manager (only entries truly missing from DB)
    let storageDepts: string[] = [];
    try {
      const raw = localStorage.getItem('sia_dept_entries');
      if (raw) {
        const entries: { label: string; dbName?: string; type: string }[] = JSON.parse(raw);
        storageDepts = [...new Set(
          entries
            .filter(e => e.type === level)
            .map(e => (e.dbName ?? e.label).trim())
            .filter(d => d !== '' && d !== level)
        )];
      }
    } catch {}

    // Merge: DB is truth; localStorage only adds entries not already in DB
    const seen = new Set<string>(dbDepts);
    const merged = [...dbDepts];
    for (const s of storageDepts) {
      if (!seen.has(s)) { seen.add(s); merged.push(s); }
    }
    return merged.sort();
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



    // FIX SHS-YEARLEVEL-01: Sync yearLevel to the selected category before
    // entering Step 2 so the Grade Level dropdown is never blank for SHS.
    // Default regForm.yearLevel is '1st Year' which is not a valid SHS option.
    this.syncYearLevelToCategory();

    this.enrollError = ''; this.enrollStep = 'info'; this.cdr.detectChanges();
    this.saveWizardState();
  }

  // ── Step 2 shared validator ────────────────────────────────────────────────
  // Returns true when ALL fields are valid. Populates this.fieldErrors for
  // every broken field so the user sees all problems at once.
  //
  // Options:
  //   requireLrn   – LRN field is mandatory (SHS / TVET)
  //   requireStrand – SHS strand + learning delivery required
  //
  private _validateStep2(opts: { requireLrn?: boolean; requireStrand?: boolean; isTransferee?: boolean } = {}): boolean {
    this.clearErrors(this.fieldErrors);
    this.enrollError = '';
    const f = this.regForm;
    let ok = true;

    const fail = (field: string, msg: string) => { this.fieldErrors[field] = msg; ok = false; };

    // ── NAME ────────────────────────────────────────────────────────────────
    if (!f.lastName?.trim())  fail('lastName',  'Last Name is required.');
    if (!f.firstName?.trim()) fail('firstName', 'First Name is required.');

    // ── STUDENT TYPE ────────────────────────────────────────────────────────
    if (!f.studentType) fail('studentType', 'Please select student type.');

    // ── LRN (required for SHS / TVET, optional for College) ─────────────────
    if (opts.requireLrn) {
      if (!f.lrnNo?.trim()) {
        fail('lrnNo', opts.isTransferee ? 'Previous Student No. is required.' : 'LRN No. is required.');
      } else if (!opts.isTransferee && !/^\d{12}$/.test(f.lrnNo.trim())) {
        fail('lrnNo', 'LRN must be exactly 12 digits.');
      } else if (opts.isTransferee && (f.lrnNo.trim().length < 3 || f.lrnNo.trim().length > 30)) {
        fail('lrnNo', 'Previous Student No. must be between 3 and 30 characters.');
      }
    }

    // ── DATE OF BIRTH ────────────────────────────────────────────────────────
    // Must be present, a real date, strictly before today,
    // and student must be 14–80 years old.
    if (!f.dateOfBirth) {
      fail('dateOfBirth', 'Date of Birth is required.');
    } else {
      const dob = new Date(f.dateOfBirth);
      const todayMidnight = new Date();
      todayMidnight.setHours(0, 0, 0, 0);

      if (isNaN(dob.getTime())) {
        fail('dateOfBirth', 'Please enter a valid date.');
      } else if (dob >= todayMidnight) {
        fail('dateOfBirth', 'Date of Birth must be before today.');
      } else {
        const ageYears = (todayMidnight.getTime() - dob.getTime()) / (365.25 * 24 * 3600 * 1000);
        if (ageYears < 14) fail('dateOfBirth', 'Student must be at least 14 years old to enroll.');
        if (ageYears > 80) fail('dateOfBirth', 'Please enter a valid Date of Birth (age cannot exceed 80).');
      }
    }

    // ── PREVIOUS SCHOOLS ─────────────────────────────────────────────────────
    // Accepts entries already stored in lastSchoolAttended (wizard resume)
    // or builds it from the dynamic rows.
    if (!f.lastSchoolAttended?.trim()) {
      const filled = this.previousSchools.filter(s => s.schoolName?.trim());
      if (filled.length === 0) {
        fail('previousSchools', 'Please enter at least one Previous School Attended.');
      } else {
        f.lastSchoolAttended = filled.map(s => `${s.level} - ${s.schoolName} (${s.yearFrom}${s.yearTo ? '–' + s.yearTo : ''})`).join('; ');
      }
    }

    // ── SEX ──────────────────────────────────────────────────────────────────
    if (!f.sex) fail('sex', 'Please select sex.');

    // ── PERSONAL DETAILS ─────────────────────────────────────────────────────
    if (!f.religion?.trim())     fail('religion',     'Religion is required.');
    if (!f.placeOfBirth?.trim()) fail('placeOfBirth', 'Place of Birth is required.');
    if (!f.citizenship?.trim())  fail('citizenship',  'Nationality is required.');
    // ── HOME ADDRESS (broken down) ────────────────────────────────────────────
    if (!f.addrBarangay?.trim()) fail('addrBarangay', 'Barangay is required.');
    if (!f.addrCity?.trim())     fail('addrCity',     'City / Municipality is required.');
    if (!f.addrProvince?.trim()) fail('addrProvince', 'Province is required.');
    // Build the combined homeAddress string for backend submission
    if (f.addrBarangay?.trim() && f.addrCity?.trim() && f.addrProvince?.trim()) {
      const parts = [
        f.addrStreet?.trim(),
        f.addrPurok?.trim() ? 'Purok ' + f.addrPurok.trim() : '',
        f.addrBarangay?.trim() ? 'Brgy. ' + f.addrBarangay.trim() : '',
        f.addrCity?.trim(),
        f.addrProvince?.trim(),
      ].filter(Boolean);
      f.homeAddress = parts.join(', ');
    }

    // Auto-expand address breakdown if any address field failed
    if (this.fieldErrors['addrBarangay'] || this.fieldErrors['addrCity'] || this.fieldErrors['addrProvince']) {
      this.addrExpanded = true;
    }

    // ── CONTACT NUMBER ───────────────────────────────────────────────────────
    // Philippine mobile only: exactly 11 digits, must start with 09.
    const phoneRe = /^09\d{9}$/;
    const contactRaw = (f.contactNumber?.replace(/\s/g, '') ?? '');
    if (!contactRaw) {
      fail('contactNumber', 'Contact Number is required.');
    } else if (/[a-zA-Z]/.test(contactRaw)) {
      fail('contactNumber', 'Contact Number must not contain letters.');
    } else if (!phoneRe.test(contactRaw)) {
      fail('contactNumber', 'Enter a valid 11-digit PH mobile number (e.g. 09XXXXXXXXX).');
    }

    // ── MOTHER TONGUE ────────────────────────────────────────────────────────
    if (!f.motherTongue?.trim()) fail('motherTongue', 'Mother Tongue is required.');

    // ── INDIGENOUS PEOPLES ───────────────────────────────────────────────────
    if (!f.isIndigenous) fail('isIndigenous', 'Please answer this question.');

    // ── SPECIAL NEEDS ────────────────────────────────────────────────────────
    if (!f.hasSpecialNeeds) {
      fail('hasSpecialNeeds', 'Please answer this question.');
    } else if (f.hasSpecialNeeds === 'Yes' && !f.specialNeedsDetails?.trim()) {
      fail('specialNeedsDetails', 'Please specify your special education needs.');
    }

    // ── ASSISTIVE TECH ───────────────────────────────────────────────────────
    if (!f.hasAssistiveTech) {
      fail('hasAssistiveTech', 'Please answer this question.');
    } else if (f.hasAssistiveTech === 'Yes' && !f.assistiveTechDetails?.trim()) {
      fail('assistiveTechDetails', 'Please specify the assistive technology used.');
    }

    // ── GUARDIAN ─────────────────────────────────────────────────────────────
    if (!f.guardianName?.trim())         fail('guardianName',    'Guardian Name is required.');
    // Validate using the breakdown fields; open the panel so the user can see the error
    if (!f.guardianAddrBarangay?.trim() || !f.guardianAddrCity?.trim() || !f.guardianAddrProvince?.trim()) {
      this.guardianAddrExpanded = true;
      if (!f.guardianAddrBarangay?.trim()) fail('guardianAddrBarangay', 'Barangay is required.');
      if (!f.guardianAddrCity?.trim())     fail('guardianAddrCity',     'City / Municipality is required.');
      if (!f.guardianAddrProvince?.trim()) fail('guardianAddrProvince', 'Province is required.');
    }

    // Guardian contact — same Philippine mobile rule
    const gContactRaw = (f.guardianContact?.replace(/\s/g, '') ?? '');
    if (!gContactRaw) {
      fail('guardianContact', 'Guardian Contact is required.');
    } else if (/[a-zA-Z]/.test(gContactRaw)) {
      fail('guardianContact', 'Guardian Contact must not contain letters.');
    } else if (!phoneRe.test(gContactRaw)) {
      fail('guardianContact', 'Enter a valid 11-digit PH mobile number (e.g. 09XXXXXXXXX).');
    }

    // Guardian email — basic format check
    const gEmail = f.guardianEmail?.trim() ?? '';
    if (!gEmail) {
      fail('guardianEmail', 'Guardian Email is required.');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(gEmail)) {
      fail('guardianEmail', 'Enter a valid email address.');
    }

    if (!f.guardianRelationship?.trim()) fail('guardianRelationship', 'Relationship is required.');

    // ── YEAR LEVEL ───────────────────────────────────────────────────────────
    if (!f.yearLevel) fail('yearLevel', 'Year Level is required.');

    // ── SHS-SPECIFIC ─────────────────────────────────────────────────────────
    if (opts.requireStrand) {
      if (!f.strand) fail('strand', 'Strand is required.');
    }

    // ── Scroll to first error ─────────────────────────────────────────────────
    if (!ok) {
      this.cdr.detectChanges();
      setTimeout(() => {
        const el = document.querySelector<HTMLElement>('.is-invalid, .field-error');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 50);
    }

    return ok;
  }

  // ── Step 2 entry point — College ─────────────────────────────────────────
  proceedFromInfo(): void {
    if (!this._validateStep2({ requireLrn: true, isTransferee: this.isTransfereeEnrolling })) return;
    this.enrollError = '';
    // Transferees skip subject selection (handled by TOR review); others go to subject step
    if (this.isTransfereeEnrolling) {
      this.enrollStep = 'documents';
      this.saveWizardState();
      this.loadProgramCourses();
    } else {
      this.enrollStep = 'subjects';
      this.saveWizardState();
      this.loadSubjectSelection();
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
          this.feePreview    = res.fees;
          this.tuitionAmount = res.fees.totalAssessment;
          // If Full Scholarship selected, sync amount with the actual subtotal
          if (this.isFullScholarship && res.fees.subtotal > 0) {
            this.scholarshipAmount = res.fees.subtotal;
          }
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
      `${environment.registrarApi}?action=get_program_courses&program=${encodeURIComponent(this.selectedProgramName)}`
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

    // FIX PLAN-REVERT-01: Save payment_plan to DB immediately when the user toggles it.
    // Without this, getStudentContext (called on portal load) reads the stale DB value
    // and reverts the plan back to 'full', overwriting what the user chose in the wizard.
    this.http.post<any>(`${this.apiUrl}?action=update_payment_plan`, {
      student_id:     this.torReviewStudentId,
      payment_plan:   this.paymentPlan,
      payment_method: this.paymentMethod,
    }).subscribe();

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

  // Save payment method to DB immediately — called when user clicks GCash/Cash
  // in the tor-review step so the choice persists on portal load.
  // FIX METHOD-REVERT-01: previously only saveWizardState() was called, which
  // stores in sessionStorage only. If sessionStorage is cleared or another tab
  // opens the portal, payment_method defaults back to 'Cash' from DB (harmless)
  // but payment_plan could also be stale. This ensures both are always in sync.
  savePaymentMethodToDB(): void {
    if (!this.torReviewStudentId) { this.saveWizardState(); return; }
    this.saveWizardState();
    this.http.post<any>(`${this.apiUrl}?action=update_payment_plan`, {
      student_id:     this.torReviewStudentId,
      payment_plan:   this.paymentPlan,
      payment_method: this.paymentMethod,
    }).subscribe();
  }

  // ── Scholarship accordion ────────────────────────────────────
  toggleScholarCat(i: number): void {
    this.expandedScholarCat = this.expandedScholarCat === i ? null : i;
  }

  selectScholarType(t: string): void {
    this.scholarType = t;
    this.expandedScholarCat = null;
    this.onScholarshipChange();
  }

  clearScholarType(): void {
    this.scholarType        = '';
    this.scholarGrantor     = '';
    this.scholarshipAmount  = 0;
    this.isFullScholarship  = false;
    this.expandedScholarCat = null;
    this.scholarClaimCode   = '';
    this.scholarCodeStatus  = 'idle';
    this.scholarCodeMsg     = '';
    this.scholarPreapprovalId = 0;
    this.onScholarshipChange();
  }

  getCatIcon(group: string): string {
    if (group.includes('CHED'))                          return '🏛️';
    if (group.includes('TESDA'))                         return '🔧';
    if (group.includes('DOST'))                          return '🔬';
    if (group.includes('LGU') || group.includes('Government')) return '🏢';
    return '🎓';
  }
  // ─────────────────────────────────────────────────────────────


  onScholarshipChange(): void {
    // If Full Scholarship (School-Granted) selected, auto-set amount = subtotal (before any discount)
    if (this.scholarType === 'Full Scholarship (School-Granted)') {
      this.isFullScholarship = true;
      // Use subtotal (gross amount before discounts) so full coverage is guaranteed
      const fullAmount = this.feePreview?.subtotal
                      ?? this.torEvalResult?.fee?.subtotal
                      ?? this.tuitionAmount;
      this.scholarshipAmount = fullAmount > 0 ? fullAmount : this.scholarshipAmount;
    } else {
      this.isFullScholarship    = false;
      this.scholarClaimCode     = '';
      this.scholarCodeStatus    = 'idle';
      this.scholarCodeMsg       = '';
      this.scholarPreapprovalId = 0;
    }
    if (this.enrollStep !== 'documents') return;
    if (this.isSHS)        this.loadSHSFee();
    else if (this.isTVET)  this.loadTVETFee();
    else                   this.loadFeePreview();
  }

  verifyScholarCode(): void {
    const code = this.scholarClaimCode.trim().toUpperCase();
    if (!code) { this.scholarCodeStatus = 'idle'; this.scholarCodeMsg = ''; return; }
    this.scholarCodeStatus = 'checking';
    this.scholarCodeMsg    = '';
    this.cdr.detectChanges();
    this.http.get<any>(`${this.accountingUrl}?action=verify_scholarship_code&code=${encodeURIComponent(code)}`).subscribe({
      next: (res) => {
        if (res.success && res.valid) {
          this.scholarCodeStatus    = 'valid';
          this.scholarCodeMsg       = `✓ Valid — ${res.scholar_type}${res.grantor ? ' · ' + res.grantor : ''}`;
          this.scholarType          = res.scholar_type || 'Full Scholarship';
          this.scholarGrantor       = res.grantor || '';
          this.scholarPreapprovalId = res.preapproval_id || 0;
          this.isFullScholarship    = true;
          this.scholarshipAmount    = this.feePreview?.subtotal ?? this.torEvalResult?.fee?.subtotal ?? this.tuitionAmount;
        } else {
          this.scholarCodeStatus    = 'invalid';
          this.scholarCodeMsg       = res.message || 'Invalid code.';
          this.scholarPreapprovalId = 0;
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.scholarCodeStatus = 'invalid';
        this.scholarCodeMsg    = 'Could not connect to server.';
        this.cdr.detectChanges();
      }
    });
  }

  // ── Program course grouping helpersgrouping helpers ──────────────────────
  private yearOrder = ['1st Year','2nd Year','3rd Year','4th Year'];
  private semOrder  = ['1st Semester','2nd Semester','Midyear'];

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

  /** Filter subjectSelectionCourses by year level + semester for template grouping */
  getSubjectSelectionFor(yearLevel: string, semester: string) {
    return this.subjectSelectionCourses.filter(
      c => (c.yearLevel || '1st Year') === yearLevel && (c.semester || '1st Semester') === semester
    );
  }

  get totalProgramUnits(): number { return this.programCourses.reduce((s,c) => s + (c.credits||0), 0); }

  // Step 3 — File handlers
  onFileChange(event: Event, target: 'tor'|'goodMoral'|'psa'|'form138'|'pic'): void {
    const file = (event.target as HTMLInputElement).files?.[0]; if (!file) return;
    if (target==='tor')         { this.torFile=file;          this.torFileName=file.name; }
    if (target==='goodMoral')   { this.goodMoralFile=file;    this.goodMoralFileName=file.name; }
    if (target==='psa')         { this.psaFile=file;          this.psaFileName=file.name; }
    if (target==='form138')     { this.form138File=file;      this.form138FileName=file.name; }
    if (target==='pic')         { this.picFile=file;          this.picFileName=file.name; }
    this.cdr.detectChanges();
  }

  proceedFromDocuments(): void {
    if (this.isSHS) {
      if (!this.form138File)   { this.enrollError='Report Card (Form 138) is required.';         this.cdr.detectChanges(); return; }
      if (!this.goodMoralFile) { this.enrollError='Certificate of Good Moral is required.';      this.cdr.detectChanges(); return; }
      if (!this.psaFile)       { this.enrollError='PSA Birth Certificate is required.';           this.cdr.detectChanges(); return; }
      if (!this.picFile)       { this.enrollError='2×2 picture is required.';                    this.cdr.detectChanges(); return; }
    } else {
      // College / TVET: only Transferee must upload TOR
      if (this.isTransfereeEnrolling && !this.torFile) {
        this.enrollError = 'TOR / Form 137 is required for Transferee evaluation.';
        this.cdr.detectChanges(); return;
      }
    }
    if (this.isScholar && !this.scholarType) { this.enrollError='Please select a scholarship type.'; this.cdr.detectChanges(); return; }
    if (this.isFullScholarship && this.scholarCodeStatus !== 'valid') {
      this.enrollError = 'Please enter and verify your Full Scholarship claim code from Accounting.';
      this.cdr.detectChanges(); return;
    }
    this.enrollError = '';
    // Warn if fee is still loading
   if (!this.isTransfereeEnrolling && !this.isSHS && !this.isTVET && this.isFeePreviewLoading) {
      this.enrollError = 'Fee assessment is still loading. Please wait a moment.';
      this.cdr.detectChanges(); return;
    }
    if (this.isTransfereeEnrolling) {
      // Transferees: go to TOR review sub-step — create account + send TOR + wait for evaluation
      this.enrollStep = 'tor-review';
      this.saveWizardState();
      this.torReviewPhase = 'idle';
      this.torReviewError = '';
      this.torEvalResult = null;
    } else {
      this.enrollStep = 'account';
      this.saveWizardState();
    }
    this.cdr.detectChanges();
  }

  // ══ TOR REVIEW: create account + send TOR + poll evaluation ═════════════
  sendTorNow(): void {
    const a = this.accountForm;
    if (!a.email || !a.password)           { this.torReviewError = 'Enter your email and password below first.'; this.cdr.detectChanges(); return; }
    if (a.password !== a.confirmPassword)  { this.torReviewError = 'Passwords do not match.';                   this.cdr.detectChanges(); return; }
    const _pwErrTor = this.validatePassword(a.password);
    if (_pwErrTor)                         { this.torReviewError = _pwErrTor;                                   this.cdr.detectChanges(); return; }
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
        // FIX TVET-UNIFIED-01: Both TVET and College transferees use register_student_tvet
        // for TVET (saves tvet_type) and register_transferee for College.
        // Backend seeds ₱20k flat rate for TVET automatically.
        const torAction = this.isTVET ? 'register_student_tvet' : 'register_transferee';
        const torPayload: any = {
          user_id: r1.user_id,
          firstName: this.regForm.firstName,   lastName: this.regForm.lastName,
          middleName: this.regForm.middleName, suffix: this.regForm.suffix,
          email: a.email, phone: this.regForm.contactNumber,
          dateOfBirth: this.regForm.dateOfBirth, address: this.regForm.homeAddress,
          guardianName: this.regForm.guardianName, guardianAddress: this.regForm.guardianAddress,
          guardianContact: this.regForm.guardianContact,
          emergencyContact: this.regForm.guardianName, emergencyPhone: this.regForm.guardianContact,
          program: this.selectedProgramName, studentType: this.regForm.studentType,
          studentCategory: this.studentTypeCategory, paymentMethod: this.paymentMethod, paymentPlan: this.paymentPlan,
          isScholar: this.isScholar ? 1 : 0, scholarType: this.scholarType,
          scholarGrantor: this.scholarGrantor, scholarshipAmount: this.scholarshipAmount,
          lrnNo: this.regForm.lrnNo, sex: this.regForm.sex, religion: this.regForm.religion,
          citizenship: this.regForm.citizenship, placeOfBirth: this.regForm.placeOfBirth,
          motherTongue: this.regForm.motherTongue, lastSchoolAttended: this.regForm.lastSchoolAttended,
          strand: this.regForm.strand, age: this.regForm.age,
          guardianEmail: this.regForm.guardianEmail, guardianRelationship: this.regForm.guardianRelationship,
          psaBirthCertNo: this.regForm.psaBirthCertNo, isIndigenous: this.regForm.isIndigenous,
          hasSpecialNeeds: this.regForm.hasSpecialNeeds, specialNeedsDetails: this.regForm.specialNeedsDetails,
          hasAssistiveTech: this.regForm.hasAssistiveTech, assistiveTechDetails: this.regForm.assistiveTechDetails,
          learningDelivery: this.regForm.learningDelivery,
          semester: this.fullSemester,
          yearLevel: this.regForm.yearLevel,
        };
        // TVET-only extra field
        if (this.isTVET) { torPayload['tvetType'] = this.tvetSelectedType; }
        // STEP 2 — register student profile (action varies by category)
        this.http.post<any>(`${this.apiUrl}?action=${torAction}`, torPayload).subscribe({
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
              this.http.post<any>(environment.registrarApi + '?action=upload_tor_file', fd)
                .subscribe({ next: () => afterUpload(), error: () => afterUpload() });
            } else {
              this.http.post<any>(environment.registrarApi + '?action=submit_tor', { student_id: sid })
                .subscribe({ next: () => afterUpload(), error: () => afterUpload() });
            }
          },
          error: () => { this.torReviewPhase='idle'; this.torReviewError='Registration failed.'; this.cdr.detectChanges(); }
        });
      },
      error: () => { this.torReviewPhase='idle'; this.torReviewError='Cannot connect to server. Check XAMPP is running.'; this.cdr.detectChanges(); }
    });
  }

  showRegistrationConfirmation(studentNumber: string, name: string, program: string, nextStep: 'payment' | 'tor' | 'free'): void {
    this.confirmedStudentNumber = studentNumber;
    this.confirmedName          = name;
    this.confirmedProgram       = program;
    this.confirmedNextStep      = nextStep;
    this.confirmedAutoLoginSec  = 5;
    this.isSubmitting           = false;
    // If subject approval is pending, stay on 'account' step so the waiting
    // message box renders inside Step 5 — don't switch to 'confirmed'.
    if (this.subjectSubmissionStatus === 'waiting' ||
        this.subjectSubmissionStatus === 'approved' ||
        this.subjectSubmissionStatus === 'rejected') {
      // Account created — ensure session is stored so navigation works after approval.
      this.accountAlreadyCreated = true;
      // If register_student returned a token but it wasn't stored yet, store it now.
      if (!this.auth.getToken()) {
        // Token not stored — do a silent login to get it
        const a = this.accountForm;
        this.http.post<any>(`${this.authUrl}?action=login`, {
          email: a.email, password: a.password, portal: 'student'
        }).subscribe({
          next: (lr) => {
            if (lr.success) {
              this.auth.storeSession(lr.token, lr.user, 'student');
              console.log('[ENROLL] Silent login after subject submit — token stored');
            }
          },
          error: () => console.warn('[ENROLL] Silent login failed — user must log in manually')
        });
      }
      this.cdr.detectChanges();
      return;
    }
    this.enrollStep = 'confirmed';
    this.cdr.detectChanges();

    // Start countdown — auto-proceeds after 5s
    if (this.confirmCountdown) clearInterval(this.confirmCountdown);
    this.confirmCountdown = setInterval(() => {
      this.confirmedAutoLoginSec--;
      this.cdr.detectChanges();
      if (this.confirmedAutoLoginSec <= 0) {
        clearInterval(this.confirmCountdown);
        this.proceedAfterConfirmation();
      }
    }, 1000);
  }

  proceedAfterConfirmation(): void {
    if (this.confirmCountdown) clearInterval(this.confirmCountdown);
    const a = this.accountForm;

    // FIX AUTO-LOGIN: If enrollment STEP 2 already returned a token and we
    // stored the session, skip the auth.php call entirely.
    if (this.auth.getToken()) {
      console.log('[ENROLL] proceedAfterConfirmation — session already stored, navigating directly');
      this.clearWizardState();
      // FIX PLAN-NULL-01: pass _pp/_pm so loadContext() hint works even when
      // the update_payment_plan DB write hasn't committed yet at this instant.
      this.router.navigate(['/student/enrollment'], {
        queryParams: { _pp: this.paymentPlan, _pm: this.paymentMethod }
      });
      return;
    }

    this.isSubmitting = true;
    this.cdr.detectChanges();
    // Fallback: STEP 2 returned no token — do a normal login call
    this.http.post<any>(`${this.authUrl}?action=login`, { email: a.email, password: a.password, portal: 'student' }).subscribe({
      next: (lr) => {
        if (lr.otp_required) {
          // 2FA triggered — show OTP modal, then continue to enrollment after verify
          this.isSubmitting = false;
          this.otpToken = lr.otp_token ?? '';
          this.otpCode  = lr.otp_code ?? '';
          this.otpInput = ''; this.otpError = '';
          this.otpCountdown = lr.otp_expires_in ?? 300;
          this.showOtpModal = true;
          this._startOtpTimer();
          this.cdr.detectChanges();
          return;
        }
        if (lr.success) { this.auth.storeSession(lr.token, lr.user, 'student'); }
        this.clearWizardState();
        // FIX PLAN-NULL-01: pass _pp/_pm so loadContext() hint survives race window
        this.router.navigate(['/student/enrollment'], {
          queryParams: { _pp: this.paymentPlan, _pm: this.paymentMethod }
        });
      },
      error: () => {
        this.isSubmitting = false;
        this.view = 'login'; this.email = a.email;
        this.successMessage = 'Account created! Please log in to continue.';
        this.cdr.detectChanges();
      }
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
    this.http.get<any>(`${environment.registrarApi}?action=get_tor_evaluation&student_id=${sid}`).subscribe({
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
        // FIX TVET-TRANSFEREE-FLOW-03: TVET transferees have a fixed ₱20k flat fee —
        // do NOT call the unit-based fee_preview endpoint. Build the fee object directly
        // so the tor-review 'done' phase shows the flat rate, not a unit-based breakdown.
        // FIX TVET-UNIFIED-01: TVET transferee uses the same College transferee flow.
        // get_fee_preview returns ₱20k flat rate for TVET transferees (backend handles it).
        // No separate branch needed — same API call, same result shape.
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
    // FIX TVET-TRANSFEREE-SUBJECTS-02: Load program subject list when TOR evaluation
    // result arrives so the "Program Subjects" section in the done/rejected phase
    // shows real subjects instead of "0 total subjects · -1 remaining".
    // programCourses is empty here because setTorResult fires from the TOR poll
    // (checkTorEval) which runs independently of proceedFromInfoTVET — and even
    // when proceedFromInfoTVET's fix loaded it, a page refresh clears it from memory.
    if (!this.programCourses.length && this.selectedProgramName) {
      this.loadProgramCourses();
    }
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

  /**
   * Parses the enrollment period label set by the admin
   * (e.g. "1st Semester AY 2025-2026" or "2nd Semester, AY 2026-2027")
   * and auto-fills regForm.semesterEnroll and regForm.ayYear.
   * Falls back to getCurrentSemester() parsing when the label is absent or unrecognised.
   */
  applyEnrollmentPeriodToForm(): void {
    const label = this.enrollmentPeriod?.label?.trim() ?? '';

    // Match semester term: "1st Semester" or "2nd Semester" (case-insensitive)
    const semMatch = label.match(/\b(1st\s+Semester|2nd\s+Semester)\b/i);
    // Match AY pattern: four-digit year hyphen four-digit year, e.g. 2025-2026
    const ayMatch  = label.match(/\b(\d{4}[-\u2013]\d{4})\b/);

    if (semMatch && ayMatch) {
      const sem = semMatch[1].replace(/\s+/g, ' ');
      this.regForm.semesterEnroll = sem.charAt(0).toUpperCase() + sem.slice(1);
      this.regForm.ayYear         = ayMatch[1].replace('\u2013', '-');
    } else {
      // Label blank or unrecognised — derive from system clock
      const fallback = this.getCurrentSemester();
      const fbSem = fallback.match(/\b(1st Semester|2nd Semester)\b/i);
      const fbAY  = fallback.match(/\b(\d{4}-\d{4})\b/);
      if (fbSem) this.regForm.semesterEnroll = fbSem[1];
      if (fbAY)  this.regForm.ayYear         = fbAY[1];
    }
    this.cdr.detectChanges();
  }

  /**
   * Calculates the student's age from regForm.dateOfBirth and writes it back
   * into regForm.age. Called whenever the Date of Birth field changes.
   */
  computeAgeFromDOB(): void {
    const dob = this.regForm.dateOfBirth;
    if (!dob) {
      this.regForm.age = '';
      delete this.fieldErrors['dateOfBirth'];
      this.cdr.detectChanges(); return;
    }
    const birth = new Date(dob);
    if (isNaN(birth.getTime())) {
      this.regForm.age = '';
      this.fieldErrors['dateOfBirth'] = 'Please enter a valid date.';
      this.cdr.detectChanges(); return;
    }
    // Live check: birthday must be strictly before today
    const todayMidnight = new Date();
    todayMidnight.setHours(0, 0, 0, 0);
    if (birth >= todayMidnight) {
      this.regForm.age = '';
      this.fieldErrors['dateOfBirth'] = 'Date of Birth must be before today. A student cannot be born today or in the future.';
      this.cdr.detectChanges(); return;
    }
    // Valid — clear error and compute age
    delete this.fieldErrors['dateOfBirth'];
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) { age--; }
    // Live age range check: 14–80
    if (age < 14) {
      this.fieldErrors['dateOfBirth'] = 'Student must be at least 14 years old to enroll.';
      this.regForm.age = '';
      this.cdr.detectChanges(); return;
    }
    if (age > 80) {
      this.fieldErrors['dateOfBirth'] = 'Age cannot exceed 80 years old.';
      this.regForm.age = '';
      this.cdr.detectChanges(); return;
    }
    this.regForm.age = String(age);
    this.cdr.detectChanges();
  }

  finishTorReview(): void {
    // Sequential: wait for update_payment_plan to commit to DB, THEN login + navigate.
    // This prevents the race condition where enrollment.ts loaded before the UPDATE finished,
    // leaving students.payment_method as NULL and defaulting to GCash incorrectly.
    const a = this.accountForm;
    this.isSubmitting = true; this.cdr.detectChanges();
    if (isPlatformBrowser(this.platformId)) {
      sessionStorage.setItem('pendingPaymentMethod', this.paymentMethod);
      sessionStorage.setItem('pendingPaymentPlan',   this.paymentPlan);
    }

    // Helper that performs the login + navigation step (extracted so we can
    // call it both after a successful DB update and as a fallback if there is
    // no studentId to update).
    const doLogin = () => {
      this.http.post<any>(`${this.authUrl}?action=login`, { email: a.email, password: a.password, portal: 'student' }).subscribe({
        next: (lr) => {
          if (lr.otp_required) {
            this.isSubmitting = false;
            this.otpToken = lr.otp_token ?? '';
            this.otpCode  = lr.otp_code ?? '';
            this.otpInput = ''; this.otpError = '';
            this.otpCountdown = lr.otp_expires_in ?? 300;
            this.showOtpModal = true;
            this._startOtpTimer();
            this.cdr.detectChanges();
            return;
          }
          if (lr.success && isPlatformBrowser(this.platformId)) {
            this.auth.storeSession(lr.token, lr.user, 'student');
          }
          this.clearWizardState();
          // FIX RACE-NUCLEAR: Pass plan+method as query params — survives navigation
          // regardless of DB write timing or sessionStorage read-order issues.
          this.router.navigate(['/student/enrollment'], {
            queryParams: {
              _pm: this.paymentMethod,   // 'Cash' or 'GCash'
              _pp: this.paymentPlan,     // 'installment' or 'full'
            }
          });
        },
        error: () => {
          this.view = 'login'; this.email = a.email;
          this.successMessage = 'Account created! Please log in.';
          this.cdr.detectChanges();
        }
      });
    };

    // Save payment_plan + payment_method to DB first, then login sequentially.
    const studentId = this.torReviewStudentId;
    if (studentId > 0) {
      this.http.post<any>(`${this.apiUrl}?action=update_payment_plan`, {
        student_id:     studentId,
        payment_plan:   this.paymentPlan,
        payment_method: this.paymentMethod,
      }).subscribe({
        next:  () => doLogin(),  // DB write confirmed — now safe to login
        // FIX FE-PLAN-REVERT-01 (Bug 4): On error, retry the DB write once before
        // proceeding. The old code called doLogin() immediately on error, meaning the
        // student logged in with payment_plan still NULL in DB. Then loadContext() found
        // NULL, returned needsPlanSelection:true, and showed the plan selector again —
        // wiping the installment breakdown the student already saw in the wizard.
        // Retry gives the DB write a second chance; sessionStorage + query param hints
        // act as a safety net even if the retry also fails.
        error: () => {
          // Retry once
          this.http.post<any>(`${this.apiUrl}?action=update_payment_plan`, {
            student_id:     studentId,
            payment_plan:   this.paymentPlan,
            payment_method: this.paymentMethod,
          }).subscribe({ next: () => doLogin(), error: () => doLogin() });
        },
      });
    } else {
      // No studentId — nothing to persist; go straight to login
      doLogin();
    }
  }

  // ══════════════════════════════════════════════════════════
  // SHS ENROLLMENT SUBMIT — separate function, does NOT modify College flow
  // ══════════════════════════════════════════════════════════
  submitEnrollmentSHS(): void {
    if (this.enrollmentSubmitted) return;  // prevent double-submit
    const a = this.accountForm;
    this.accountErrors = {};
    if (!a.email?.trim())                    { this.setFieldError(this.accountErrors, 'email',    'Email is required.'); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(a.email.trim())) { this.setFieldError(this.accountErrors, 'email', 'Enter a valid email address.'); return; }
    if (!a.password)                         { this.setFieldError(this.accountErrors, 'password', 'Password is required.'); return; }
    const _pwErrSHS = this.validatePassword(a.password);
    if (_pwErrSHS)                           { this.setFieldError(this.accountErrors, 'password', _pwErrSHS); return; }
    if (!a.confirmPassword)                  { this.setFieldError(this.accountErrors, 'confirmPassword', 'Please confirm your password.'); return; }
    if (a.password !== a.confirmPassword)    { this.setFieldError(this.accountErrors, 'confirmPassword', 'Passwords do not match.'); return; }

    this.enrollmentSubmitted = true;
    this.isSubmitting = true; this.enrollError = ''; this.cdr.detectChanges();

    // STEP 1 — Create user account
    this.http.post<any>(`${this.authUrl}?action=register`, {
      email: a.email, password: a.password, role: 'student',
      first_name: this.regForm.firstName, last_name: this.regForm.lastName
    }).subscribe({
      next: (res) => {
        if (!res.success && !res.user_id) {
          this.isSubmitting = false;
          this.enrollmentSubmitted = false; // allow retry
          if (res.code === 'EMAIL_EXISTS') {
            this.accountErrors['email'] = 'This email is already registered. Please use a different email or log in.';
            this.enrollError = '';
          } else if (res.code === 'NAME_EXISTS') {
            this.enrollError = res.message || 'A student with this name and date of birth already exists. Please log in instead.';
            this.enrollStep = 'info'; // send back to Personal Info to review
          } else {
            this.enrollError = res.message || 'Failed to create account.';
          }
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
          guardianEmail: this.regForm.guardianEmail, guardianRelationship: this.regForm.guardianRelationship,
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
          scholarClaimCode: this.scholarClaimCode,
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
                this.http.post<any>(`${this.authUrl}?action=login`, { email: a.email, password: a.password, portal: 'student' }).subscribe({
                  next: (lr) => {
                    if (lr.success && isPlatformBrowser(this.platformId)) {
                      this.auth.storeSession(lr.token, lr.user, 'student');
                    }
                    this.clearWizardState();
                    // FIX PLAN-NULL-01: pass _pp/_pm so loadContext() hint survives race window
                    this.router.navigate(['/student/enrollment'], {
                      queryParams: { _pp: this.paymentPlan, _pm: this.paymentMethod }
                    });
                  },
                  error: () => { this.view = 'login'; this.email = a.email; this.successMessage = 'Account created! Please log in.'; this.cdr.detectChanges(); }
                });
              };
              // Upload Form 138 then login
              const doAfterProof = () => {
                if (this.form138File && studentId) {
                  const fd = new FormData();
                  fd.append('student_id', String(studentId));
                  fd.append('document_type', 'form138');
                  fd.append('file', this.form138File);
                  this.http.post<any>(environment.registrarApi + '?action=upload_document', fd)
                    .subscribe({ next: () => doLogin(), error: () => doLogin() });
                } else { doLogin(); }
              };
              doAfterProof();
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
      error: (err: any) => {
        this.isSubmitting = false;
        this.enrollError = err?.error?.message || err?.error?.errors?.[0] || 'Cannot connect to server. Check XAMPP is running.';
        this.cdr.detectChanges();
      }
    });
  }

  // ══════════════════════════════════════════════════════════
  // TVET ENROLLMENT SUBMIT — separate function, does NOT modify College flow
  // ══════════════════════════════════════════════════════════
  submitEnrollmentTVET(): void {
    // FIX TVET-WIZARD-FE-04: Guard against double-fire on refresh/double-click,
    // same as submitEnrollment() uses enrollmentSubmitted.
    if (this.enrollmentSubmitted) return;
    // FIX TVET-TRANSFEREE-FLOW-05: TVET transferees must NOT reach this path.
    // They are handled by sendTorNow() via the tor-review step, same as College.
    // This guard prevents accidental double-registration if the wizard state is wrong.
    if (this.isTransfereeEnrolling) {
      this.enrollError = 'Please use the TOR review step to complete registration.';
      this.cdr.detectChanges(); return;
    }

    const a = this.accountForm;
    this.accountErrors = {};
    if (!a.email?.trim())                   { this.setFieldError(this.accountErrors, 'email',    'Email is required.'); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(a.email.trim())) { this.setFieldError(this.accountErrors, 'email', 'Enter a valid email address.'); return; }
    if (!a.password)                        { this.setFieldError(this.accountErrors, 'password', 'Password is required.'); return; }
    const _pwErrTVET = this.validatePassword(a.password);
    if (_pwErrTVET)                         { this.setFieldError(this.accountErrors, 'password', _pwErrTVET); return; }
    if (!a.confirmPassword)                 { this.setFieldError(this.accountErrors, 'confirmPassword', 'Please confirm your password.'); return; }
    if (a.password !== a.confirmPassword)   { this.setFieldError(this.accountErrors, 'confirmPassword', 'Passwords do not match.'); return; }

    this.enrollmentSubmitted = true;  // FIX TVET-WIZARD-FE-04: lock against double-fire
    this.isSubmitting = true; this.enrollError = ''; this.cdr.detectChanges();

    // STEP 1 — Create user account
    this.http.post<any>(`${this.authUrl}?action=register`, {
      email: a.email, password: a.password, role: 'student',
      first_name: this.regForm.firstName, last_name: this.regForm.lastName
    }).subscribe({
      next: (res) => {
        if (!res.success && !res.user_id) {
          this.isSubmitting = false;
          this.enrollmentSubmitted = false; // allow retry
          if (res.code === 'EMAIL_EXISTS') {
            this.accountErrors['email'] = 'This email is already registered. Please use a different email or log in.';
            this.enrollError = '';
          } else if (res.code === 'NAME_EXISTS') {
            this.enrollError = res.message || 'A student with this name and date of birth already exists. Please log in instead.';
            this.enrollStep = 'info'; // send back to Personal Info to review
          } else {
            this.enrollError = res.message || 'Failed to create account.';
          }
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
          guardianEmail: this.regForm.guardianEmail, guardianRelationship: this.regForm.guardianRelationship,
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
          scholarClaimCode: this.scholarClaimCode,
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
            const tvetStudentId  = sRes.student_id || sRes.studentId;
            const isTransfereeTVET = this.regForm.studentType === 'Transferee';
            const fullNameTVET     = `${this.regForm.firstName} ${this.regForm.lastName}`.trim();
            // nextStep: transferee → 'tor' (wait for TOR eval); new/old → 'free' (₱0 wizard)
            const tvetNextStep: 'payment' | 'tor' | 'free' = isTransfereeTVET ? 'tor' : 'free';

            // FIX TVET-WIZARD-FE-02: Show the same confirmation screen as College/SHS.
            // Previously skipped — navigated directly without countdown or next-step hint.
            const doTvetLogin = () => {
              this.showRegistrationConfirmation(
                sRes.student_number ?? sRes.studentNumber ?? '',
                fullNameTVET,
                this.selectedProgramName,
                tvetNextStep
              );
            };

            // FIX TVET-WIZARD-FE-03: Upload TOR for TVET transferees before proceeding —
            // identical to the College transferee flow in submitEnrollment().
            // Without this, the Registrar never receives the TOR file and the
            // tor_evaluations row stays empty, blocking the entire transferee workflow.
            if (isTransfereeTVET && tvetStudentId) {
              if (this.torFile) {
                const fdTvet = new FormData();
                fdTvet.append('student_id', String(tvetStudentId));
                fdTvet.append('tor_file', this.torFile);
                this.http.post<any>(environment.registrarApi + '?action=upload_tor_file', fdTvet)
                  .subscribe({ next: () => doTvetLogin(), error: () => doTvetLogin() });
              } else {
                this.http.post<any>(environment.registrarApi + '?action=submit_tor', { student_id: tvetStudentId })
                  .subscribe({ next: () => doTvetLogin(), error: () => doTvetLogin() });
              }
            } else {
              doTvetLogin();
            }
          },
          error: (err) => {
            this.isSubmitting = false;
            this.enrollmentSubmitted = false;  // FIX TVET-WIZARD-FE-04: allow retry on failure
            this.enrollError = err?.error?.message || 'TVET registration failed.';
            this.cdr.detectChanges();
          }
        });
      },
      error: (err: any) => {
        this.isSubmitting = false;
        this.enrollmentSubmitted = false;  // FIX TVET-WIZARD-FE-04: allow retry on failure
        this.enrollError = err?.error?.message || err?.error?.errors?.[0] || 'Cannot connect to server. Check XAMPP is running.';
        this.cdr.detectChanges();
      }
    });
  }

  // ══════════════════════════════════════════════════════════
  // FORGOT / RESET PASSWORD
  // ══════════════════════════════════════════════════════════

  sendForgotOtp(isResend = false): void {
    const email = this.fpEmail.trim();
    this.forgotErrors = {};
    if (!email) { this.forgotErrors['fpEmail'] = 'Email address is required.'; this.cdr.detectChanges(); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { this.forgotErrors['fpEmail'] = 'Enter a valid email address.'; this.cdr.detectChanges(); return; }
    this.fpLoading = true; this.fpError = ''; this.fpSuccess = ''; this.cdr.detectChanges();
    this.http.post<any>(`${this.authUrl}?action=forgot_password`, { email }).subscribe({
      next: (res) => {
        this.fpLoading = false;
        if (res.success) {
          this.fpSuccess = res.message || 'If that email exists, a reset code has been sent.';
          this.view = 'reset';
          this._startFpResendCountdown();
        } else {
          this.fpError = res.message || 'Request failed. Please try again.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.fpLoading = false;
        this.fpError   = 'Connection error. Make sure XAMPP is running.';
        this.cdr.detectChanges();
      },
    });
  }

  submitResetPassword(): void {
    // Client-side per-field validation
    this.forgotErrors = {};
    if (!this.fpOtp.trim())           { this.forgotErrors['fpOtp'] = 'Reset code is required.';                    this.cdr.detectChanges(); return; }
    if (!this.fpNewPassword)          { this.forgotErrors['fpNewPassword'] = 'New password is required.';          this.cdr.detectChanges(); return; }
    if (this.fpNewPassword.length < 6){ this.forgotErrors['fpNewPassword'] = 'Password must be at least 6 characters.'; this.cdr.detectChanges(); return; }
    if (!this.fpConfirmPassword)      { this.forgotErrors['fpConfirmPassword'] = 'Please confirm your password.';  this.cdr.detectChanges(); return; }
    if (this.fpNewPassword !== this.fpConfirmPassword) { this.forgotErrors['fpConfirmPassword'] = 'Passwords do not match.'; this.cdr.detectChanges(); return; }

    this.fpLoading = true;
    this.fpError   = '';
    this.cdr.detectChanges();

    const payload = {
      email:            this.fpEmail.trim(),
      otp:              this.fpOtp.trim(),
      new_password:     this.fpNewPassword,
      confirm_password: this.fpConfirmPassword,
    };

    this.http.post<any>(`${this.authUrl}?action=reset_password`, payload).subscribe({
      next: (res) => {
        this.fpLoading = false;
        if (res.success) {
          this.fpError   = '';
          this.fpSuccess = res.message || 'Password reset successful!';
          this.cdr.detectChanges();
          setTimeout(() => {
            this.view      = 'login';
            this.fpEmail   = '';
            this.fpOtp     = '';
            this.fpNewPassword     = '';
            this.fpConfirmPassword = '';
            this.fpSuccess = '';
            this.cdr.detectChanges();
          }, 1500);
        } else {
          this.fpError = res.message || 'Reset failed. Please try again.';
          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.fpLoading = false;
        this.fpError   = 'Server connection error. Make sure XAMPP is running.';
        this.cdr.detectChanges();
      },
    });
  }

  private _startFpResendCountdown(seconds = 60): void {
    this.fpResendCountdown = seconds;
    if (this.fpResendTimer) clearInterval(this.fpResendTimer);
    this.fpResendTimer = setInterval(() => {
      this.fpResendCountdown--;
      this.cdr.detectChanges();
      if (this.fpResendCountdown <= 0) clearInterval(this.fpResendTimer);
    }, 1000);
  }

  // ══════════════════════════════════════════════════════════
  // VALIDATION helpers for SHS / TVET specific steps
  // ══════════════════════════════════════════════════════════
  // ── Step 2 entry point — SHS ─────────────────────────────────────────────
  proceedFromInfoSHS(): void {
    if (!this._validateStep2({ requireLrn: true, requireStrand: true })) return;
    this.enrollError = '';
    this.enrollStep = this.isTransfereeEnrolling ? 'documents' : 'subjects';
    this.saveWizardState();
    if (!this.isTransfereeEnrolling) { this.loadSubjectSelection(); } else { this.loadSHSFee(); }
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

  // ── Step 2 entry point — TVET ────────────────────────────────────────────
  proceedFromInfoTVET(): void {
    if (!this._validateStep2({ requireLrn: true, isTransferee: this.isTransfereeEnrolling })) return;
    this.enrollError = '';
    if (this.isTransfereeEnrolling) {
      this.enrollStep = 'documents';
      this.saveWizardState();
      this.loadProgramCourses();
    } else {
      this.enrollStep = 'subjects';
      this.saveWizardState();
      this.loadSubjectSelection();
    }
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
    if (this.isFullScholarship && this.scholarCodeStatus !== 'valid') {
      this.enrollError = 'Please enter and verify your Full Scholarship claim code from Accounting.';
      this.cdr.detectChanges(); return;
    }
    // SHS Transferee: check fee is loaded before proceeding
    if (this.isTransfereeEnrolling && this.isSHSFeeLoading) {
      this.enrollError = 'Fee assessment is still loading. Please wait.'; this.cdr.detectChanges(); return;
    }
    this.enrollError = '';
    this.enrollStep = 'account';
    this.saveWizardState();
    this.cdr.detectChanges();
  }

  proceedFromDocumentsTVET(): void {
    if (this.isScholar && !this.scholarType) { this.enrollError = 'Please select a scholarship type.'; this.cdr.detectChanges(); return; }
    if (this.isFullScholarship && this.scholarCodeStatus !== 'valid') {
      this.enrollError = 'Please enter and verify your Full Scholarship claim code from Accounting.';
      this.cdr.detectChanges(); return;
    }
    // FIX TVET-TRANSFEREE-FLOW-01: TVET transferees must go through the same
    // tor-review step as College transferees (create account → upload TOR →
    // wait for Registrar evaluation → see ₱20k flat fee → proceed to account/payment).
    // Previously always went to 'account', skipping TOR evaluation entirely.
    if (this.isTransfereeEnrolling) {
      if (!this.torFile) {
        this.enrollError = 'TOR / Form 137 is required for Transferee evaluation.';
        this.cdr.detectChanges(); return;
      }
      this.enrollError = '';
      this.enrollStep = 'tor-review';
      this.torReviewPhase = 'idle';
      this.torReviewError = '';
      this.torEvalResult = null;
      this.saveWizardState();
      this.cdr.detectChanges();
      return;
    }
    this.enrollError = '';
    this.enrollStep = 'account';
    this.saveWizardState();
    this.cdr.detectChanges();
  }

  // ── Password strength validator (mirrors auth.php rules exactly) ──────────
  private validatePassword(password: string): string {
    if (password.length < 8)
      return 'Password must be at least 8 characters.';
    if (!/[A-Z]/.test(password))
      return 'Password must contain at least one uppercase letter.';
    if (!/[a-z]/.test(password))
      return 'Password must contain at least one lowercase letter.';
    if (!/[0-9]/.test(password))
      return 'Password must contain at least one number.';
    if (!/[!@#$%^&*()\-_=+\[\]{};':",./<>?`~\\|]/.test(password))
      return 'Password must contain at least one special character (e.g. !@#$%).';
    return '';
  }

  // Step 4 — Submit
  submitEnrollment(): void {
    // FIX REFRESH-REGISTER-01: Bail out immediately if this call was already
    // made in this page lifecycle. Covers both double-click and refresh-restore.
    if (this.enrollmentSubmitted) return;

    const a = this.accountForm;
    this.accountErrors = {};
    if (!a.email?.trim())                   { this.setFieldError(this.accountErrors, 'email',           'Email is required.');                return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(a.email.trim())) { this.setFieldError(this.accountErrors, 'email', 'Enter a valid email address.'); return; }
    if (!a.password)                        { this.setFieldError(this.accountErrors, 'password',        'Password is required.');             return; }
    const _pwErr1 = this.validatePassword(a.password);
    if (_pwErr1)                            { this.setFieldError(this.accountErrors, 'password',        _pwErr1);                            return; }
    if (!a.confirmPassword)                 { this.setFieldError(this.accountErrors, 'confirmPassword', 'Please confirm your password.');     return; }
    if (a.password !== a.confirmPassword)   { this.setFieldError(this.accountErrors, 'confirmPassword', 'Passwords do not match.');           return; }

    this.enrollmentSubmitted = true;  // FIX REFRESH-REGISTER-01: lock against double-fire
    this.isSubmitting = true; this.enrollError = ''; this.cdr.detectChanges();

    // ── STEP 1: Create user account ──────────────────────────
    console.log('[ENROLL] STEP 1 — Calling auth.php?action=register', { email: a.email });
    this.http.post<any>(`${this.authUrl}?action=register`, {
      email: a.email, password: a.password, role: 'student',
      first_name: this.regForm.firstName, last_name: this.regForm.lastName
    }).subscribe({
      next: (res) => {
        console.log('[ENROLL] STEP 1 response:', res);

        // FIX: If email already exists, show clear field error — don't let them proceed
        if (!res.success && !res.user_id) {
          this.isSubmitting = false;
          this.enrollmentSubmitted = false; // allow retry with corrected email
          if (res.code === 'EMAIL_EXISTS') {
            this.accountErrors['email'] = 'This email is already registered. Please use a different email or log in.';
            this.enrollError = '';
          } else if (res.code === 'NAME_EXISTS') {
            this.enrollError = res.message || 'A student with this name and date of birth already exists. Please log in instead.';
            this.enrollStep = 'info'; // send back to Personal Info to review
          } else {
            this.enrollError = res.message || 'Failed to create account.';
          }
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
          guardianEmail: this.regForm.guardianEmail,
          guardianAddress: this.regForm.guardianAddress,
          guardianRelationship: this.regForm.guardianRelationship,
          program: this.selectedProgramName,
          studentType: this.regForm.studentType,
          studentCategory: this.studentTypeCategory,
          paymentMethod: this.paymentMethod,
          paymentPlan: this.paymentPlan,
          isScholar: this.isScholar ? 1 : 0,
          scholarType: this.scholarType, scholarGrantor: this.scholarGrantor,
          scholarshipAmount: this.scholarshipAmount,
          scholarClaimCode: this.scholarClaimCode,
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
              this.enrollmentSubmitted = false;  // FIX REFRESH-REGISTER-01: allow retry
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

            // FIX AUTO-LOGIN: If enrollment.php returned a token directly,
            // store the session immediately — no need for a separate auth.php call.
            if (sRes.token && sRes.role) {
              console.log('[ENROLL] STEP 3 — token received from STEP 2, storing session directly');
              const autoUser = {
                id:         userId,
                email:      a.email,
                role:       'student' as const,
                first_name: this.regForm.firstName,
                last_name:  this.regForm.lastName,
              };
              this.auth.storeSession(sRes.token, autoUser, 'student');
            }

            // ── STEP 2b: If Transferee, auto-submit TOR for registrar evaluation ──
            const isTransferee = this.regForm.studentType === 'Transferee';
            const studentId    = sRes.student_id || sRes.studentId;

            // ── STEP 2c: Submit subject selection AFTER getting student_id ─────
            // doAutoLogin must be declared FIRST so it can be called from inside
            // the async subscribe callback below (const is not hoisted).
            const doAutoLogin = () => {
              const fullName = `${this.regForm.firstName} ${this.regForm.lastName}`.trim();
              const nextStep = (this.isSHS || this.isTVET) && !this.isTransfereeEnrolling
                ? 'free'
                : this.isTransfereeEnrolling ? 'tor' : 'payment';
              this.showRegistrationConfirmation(
                sRes.student_number ?? sRes.studentNumber ?? '',
                fullName,
                this.selectedProgramName,
                nextStep
              );
            };

            const savedSelection = (() => {
              try {
                const raw = typeof sessionStorage !== 'undefined'
                  ? sessionStorage.getItem('pendingSubjectSelection') : null;
                return raw ? JSON.parse(raw) : null;
              } catch { return null; }
            })();

            // Collect all course IDs — prefer in-memory selectedSubjects, fall back to session
            const inMemoryIds = this.selectedSubjects
              .map(s => (s as any).id)
              .filter((id: number) => id > 0);
            const sessionIds = (savedSelection || [])
              .map((s: any) => s.id)
              .filter((id: number) => id > 0);
            const courseIds = inMemoryIds.length > 0 ? inMemoryIds : sessionIds;

            const hasSubjectSelection = !isTransferee && studentId
              && (this.subjectSubmissionStatus === 'pending-account' || courseIds.length > 0);

            if (hasSubjectSelection && courseIds.length > 0) {
              const payload: any = { student_id: studentId, notes: '', course_ids: courseIds };
              // Also send course_codes as fallback in case backend needs to re-resolve
              const courseCodes = this.selectedSubjects.map(s => s.code).filter(Boolean);
              if (courseCodes.length > 0) payload.course_codes = courseCodes;
              console.log('[ENROLL] STEP 2c — Submitting subject selection for student', studentId, payload);
              this.isSubmitting = false;
              this.http.post<any>(`${this.apiUrl}?action=submit_subject_selection`, payload).subscribe({
                next: (ssRes) => {
                  console.log('[ENROLL] Subject selection submitted:', ssRes);
                  if (ssRes.success) {
                    this.subjectSubmittedStudentId = studentId;
                    this.subjectSubmissionStatus = 'waiting';
                    this.enrollStep = 'subjects';
                    sessionStorage.removeItem('pendingSubjectSelection');
                    this._startSubjectApprovalPoll(studentId);
                    doAutoLogin();
                  } else {
                    console.warn('[ENROLL] Subject selection submit failed:', ssRes.message);
                    doAutoLogin();
                  }
                },
                error: (e) => {
                  console.warn('[ENROLL] Subject selection submit error (non-fatal):', e);
                  doAutoLogin();
                }
              });
              return;
            } else if (hasSubjectSelection && courseIds.length === 0) {
              // IDs were all 0 — send course codes as fallback for backend to resolve
              const courseCodes = [
                ...this.selectedSubjects.map(s => s.code),
                ...((savedSelection || []).map((s: any) => s.code))
              ].filter((v, i, a) => v && a.indexOf(v) === i);

              if (courseCodes.length > 0) {
                const payload = { student_id: studentId, notes: '', course_codes: courseCodes };
                console.log('[ENROLL] STEP 2c — Submitting via course_codes fallback', payload);
                this.isSubmitting = false;
                this.http.post<any>(`${this.apiUrl}?action=submit_subject_selection`, payload).subscribe({
                  next: (ssRes) => {
                    if (ssRes.success) {
                      this.subjectSubmittedStudentId = studentId;
                      this.subjectSubmissionStatus = 'waiting';
                      this.enrollStep = 'subjects';
                      sessionStorage.removeItem('pendingSubjectSelection');
                      this._startSubjectApprovalPoll(studentId);
                      doAutoLogin();
                    } else {
                      console.warn('[ENROLL] course_codes submit failed:', ssRes.message);
                      doAutoLogin();
                    }
                  },
                  error: () => doAutoLogin()
                });
                return;
              } else {
                console.warn('[ENROLL] No course IDs or codes — skipping subject submission.');
              }
            }

            // Handle TOR upload / auto-login
            const doAfterScholarProof = () => {
              if (isTransferee && studentId) {
                console.log('[ENROLL] Transferee — uploading TOR file, student_id:', studentId);
                if (this.torFile) {
                  const formData = new FormData();
                  formData.append('student_id', String(studentId));
                  formData.append('tor_file', this.torFile);
                  this.http.post<any>(
                    `${environment.registrarApi}?action=upload_tor_file`, formData
                  ).subscribe({
                    next: (torRes) => { console.log('[ENROLL] TOR uploaded:', torRes); doAutoLogin(); },
                    error: () => { console.warn('[ENROLL] TOR upload failed — submitting without file'); doAutoLogin(); }
                  });
                } else {
                  // No file but still create the tor_evaluation record
                  this.http.post<any>(`${environment.registrarApi}?action=submit_tor`,
                    { student_id: studentId }
                  ).subscribe({
                    next: (torRes) => { console.log('[ENROLL] TOR submitted (no file):', torRes); doAutoLogin(); },
                    error: () => { doAutoLogin(); }
                  });
                }
              } else {
                doAutoLogin();
              }
            };
            doAfterScholarProof();
          },
          error: (err) => {
            console.error('[ENROLL] STEP 2 HTTP error:', err);
            console.error('[ENROLL] STEP 2 error body:', err?.error);
            this.enrollmentSubmitted = false;  // FIX REFRESH-REGISTER-01: allow retry on failure
            this.isSubmitting = false;
            this.enrollError = err?.error?.message || 'Registration failed.';
            this.cdr.detectChanges();
          }
        });
      },
      error: (err) => {
        console.error('[ENROLL] STEP 1 HTTP error:', err);
        this.enrollmentSubmitted = false;  // FIX REFRESH-REGISTER-01: allow retry on failure
        this.isSubmitting = false;
        this.enrollError = err?.error?.message || err?.error?.errors?.[0] || 'Cannot connect to server. Check XAMPP is running.';
        this.cdr.detectChanges();
      }
    });
  }
}