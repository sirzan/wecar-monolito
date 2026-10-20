export interface ExternalCar {
    id: string;
    title: string;
    price: number | null;
    year: number | null;
    km: number | null;
    location: string | null;
    imageUrl: string | null;
    gallery?: string[];
    sourceUrl: string;
    platformId: string;
    platformName: string;
    platformColor: string;
    platformInitials: string;
}

export interface ExternalPlatform {
    id: string;
    name: string;
    color: string;
    initials: string;
}

export interface ExternalSearchStatus {
    enabled: boolean;
    subscribed: boolean;
    membership_ends_at: string | null;
    token_balance: number;
    searches: { used: number; remaining: number; limit: number };
    views: { used: number; remaining: number; limit: number };
    has_coupon: boolean;
    platforms: ExternalPlatform[];
}

export interface ExternalSearchResponse {
    results: ExternalCar[];
    platformsSearched: string[];
    error?: string;
    limitReached?: boolean;
    searchCount?: number | null;
    tokenUsed?: boolean;
}

export interface ExternalResolveResponse {
    allowed: boolean;
    sourceUrl?: string;
    remaining: number;
    limitReached?: boolean;
    dead?: boolean;
}
