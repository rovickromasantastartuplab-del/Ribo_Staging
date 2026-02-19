export interface WeddingSupplierContact {
    id: number;
    supplier_id: number;
    name: string;
    position: string | null;
    phone: string | null;
    email: string | null;
    created_at: string;
    updated_at: string;
}

export interface WeddingSupplierCategory {
    id: number;
    name: string;
    created_at?: string;
    updated_at?: string;
}

export interface WeddingSupplier {
    id: number;
    name: string;
    category_id: number;
    email: string | null;
    phone: string | null;
    telephone: string | null;
    website: string | null;
    address: string | null;
    facebook: string | null;
    tiktok: string | null;
    available_contact_time: string | null;
    created_at: string;
    updated_at: string;
    category?: {
        id: number;
        name: string;
    };
    contacts?: WeddingSupplierContact[];
}
