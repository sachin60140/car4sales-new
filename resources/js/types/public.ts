export interface PublicSite {
    name: string;
    tagline: string;
    phone: string;
    whatsapp: string;
    email: string;
    otp_required: boolean;
}

export interface VehicleCardData {
    id: number;
    slug: string;
    title: string;
    make: string;
    model: string;
    variant: string | null;
    manufacturing_year: number | null;
    fuel_type: string | null;
    transmission: string | null;
    odometer_km: number | null;
    ownership_serial: number | null;
    color: string | null;
    body_type: string | null;
    asking_price: string | null;
    branch: { name: string; city: string | null } | null;
    availability: string;
    is_featured: boolean;
    thumbnail: string | null;
}

export interface VehicleDetailData extends VehicleCardData {
    registration_year: number | null;
    registration_state: string | null;
    insurance_status: string | null;
    inspection_grade: string | null;
    description: string | null;
    key_features: string[];
    gallery: { type: string; url: string }[];
    branch: { name: string; city: string | null; phone?: string | null; address?: string | null } | null;
}

export interface FinanceEstimate {
    loan_amount: number;
    down_payment: number;
    emi: number;
    tenure_months: number;
    interest_rate: number;
    total_payable: number;
}
