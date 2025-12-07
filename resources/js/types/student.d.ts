export interface Student {
  id: number;
  account_id: string; // ✅ PRIMARY IDENTIFIER
  student_id: string;
  name: string;
  full_name: string;
  email: string;
  course: string;
  year_level: string;
  status: 'enrolled' | 'graduated' | 'inactive';
  birthday?: string;
  phone?: string;
  address?: string;
  total_balance: number;
  remaining_balance: number;
  created_at?: string;
  updated_at?: string;
  
  // Relationships
  user?: {
    id: number;
    email: string;
    status: string;
    role: string;
  };
}

export interface StudentListItem {
  id: number;
  account_id: string; // ✅ PRIMARY
  student_id: string;
  name: string;
  email: string;
  course: string;
  year_level: string;
  status: string;
  total_balance?: number;
  remaining_balance?: number;
}

export interface StudentFormData {
  last_name: string;
  first_name: string;
  middle_initial?: string;
  email: string;
  birthday: string;
  phone: string;
  address: string;
  year_level: string;
  student_id?: string;
  program_id?: number;
  course?: string;
  semester?: string;
  school_year?: string;
  auto_generate_assessment?: boolean;
}