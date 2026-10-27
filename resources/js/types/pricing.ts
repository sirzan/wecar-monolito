export interface PriceComparables {
    samples: number;
    min: number | null;
    p25: number | null;
    median: number | null;
    p75: number | null;
    max: number | null;
    confidence: 'high' | 'medium' | 'low' | 'none';
    scope: 'version' | 'model';
    km?: number | null;
}

export interface MarketPrice {
    samples: number;
    source_count: number;
    min: number | null;
    p25: number | null;
    suggested: number | null;
    p75: number | null;
    max: number | null;
    confidence: 'high' | 'medium' | 'low' | 'none';
    source: string | null;
    error?: string;
    cached?: boolean;
}
