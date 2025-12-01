export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  avatar?: string | null;
  profile_picture?: string;
  email_verified_at?: string | null;
  created_at?: string;
  updated_at?: string;
  
  // ✅ Add missing fields
  student_id?: string;
  course?: string;
  year_level?: string;
  faculty?: string;
  status?: 'active' | 'graduated' | 'dropped';
  birthday?: string;
  phone?: string;
  address?: string;
  
  // Relationships
  paymentTerms?: PaymentTerm[];
  account?: Account;
  student?: StudentProfile; // ✅ Add this
}

// ✅ Add StudentProfile interface
export interface StudentProfile {
  id: number;
  user_id: number;
  student_id: string;
  last_name: string;
  first_name: string;
  middle_initial?: string;
  email: string;
  course: string;
  year_level: string;
  status: 'enrolled' | 'graduated' | 'inactive';
  total_balance: number;
  birthday?: string;
  phone?: string;
  address?: string;
}

// ✅ ADD NEW INTERFACE
export interface PaymentTerm {
  id: number;
  term_name: string;
  term_order: number;
  amount: number;
  paid_amount: number;
  remaining_balance: number;
  due_date: string | null;
  status: 'pending' | 'paid' | 'partial';
  is_overdue?: boolean;
}

// ✅ ADD ACCOUNT INTERFACE
export interface Account {
  id: number;
  balance: number;
  created_at?: string;
  updated_at?: string;
}

// StudentUser extends User
export interface StudentUser extends User {
  student_id: string;
  course: string;
  year_level: string;

  address?: string;
  phone?: string;
  status?: 'active' | 'graduated' | 'dropped';
}
