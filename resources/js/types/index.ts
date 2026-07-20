import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: AuthUser | null;
    roles: string[];
    permissions: string[];
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    branch_id: number | null;
    department_id: number | null;
    team_id: number | null;
    avatar?: string;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
}

export interface AppNotification {
    id: number;
    type: string;
    level: 'info' | 'success' | 'warning' | 'critical';
    title: string;
    body: string | null;
    action_url: string | null;
    read: boolean;
    created_at: string;
}

export interface SharedData {
    name: string;
    quote?: { message: string; author: string };
    auth: Auth;
    flash: { success: string | null; error: string | null };
    notifications?: { unread: number; recent: AppNotification[] };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface Branch {
    id: number;
    code: string;
    name: string;
    slug: string;
    address: string | null;
    city: string | null;
    state: string | null;
    pin_code: string | null;
    phone: string | null;
    email: string | null;
    gst_number: string | null;
    latitude: string | null;
    longitude: string | null;
    is_active: boolean;
    sort_order: number;
    users_count?: number;
}

export interface Department {
    id: number;
    code: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    users_count?: number;
}

export interface Team {
    id: number;
    code: string;
    name: string;
    branch_id: number;
    department_id: number;
    team_leader_id: number | null;
    is_active: boolean;
    branch?: Pick<Branch, 'id' | 'name'>;
    department?: Pick<Department, 'id' | 'name'>;
    team_leader?: { id: number; name: string } | null;
    members_count?: number;
}

export interface EmployeeProfile {
    id: number;
    user_id: number;
    employee_code: string;
    designation: string | null;
    date_of_joining: string | null;
    dob: string | null;
    gender: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    pin_code: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    blood_group: string | null;
    id_proof_type: string | null;
    id_proof_number: string | null;
    reports_to: number | null;
}

export interface Employee {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    branch_id: number | null;
    department_id: number | null;
    team_id: number | null;
    is_active: boolean;
    force_password_change: boolean;
    last_login_at: string | null;
    branch?: Pick<Branch, 'id' | 'name'> | null;
    department?: Pick<Department, 'id' | 'name'> | null;
    team?: Pick<Team, 'id' | 'name'> | null;
    roles?: { id: number; name: string }[];
    employee_profile?: Partial<EmployeeProfile> | null;
}

export interface Role {
    id: number;
    name: string;
    users_count?: number;
    permissions_count?: number;
    meta?: {
        data_scope: string;
        description: string | null;
        is_system: boolean;
    } | null;
}

export interface ActivityLog {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    subject_type: string | null;
    subject_id: number | null;
    causer?: { id: number; name: string } | null;
    properties: Record<string, unknown>;
    created_at: string;
}

export interface LoginHistoryEntry {
    id: number;
    user?: { id: number; name: string; email: string } | null;
    email: string | null;
    ip_address: string | null;
    user_agent: string | null;
    device_uuid: string | null;
    guard: string;
    event: string;
    created_at: string;
}

export interface CrudPermissions {
    create: boolean;
    update: boolean;
    delete: boolean;
}

export type BreadcrumbItemType = BreadcrumbItem;
