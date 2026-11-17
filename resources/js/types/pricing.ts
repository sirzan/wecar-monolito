export interface PriceComparables {
    samples: number;
    min: number | null;
    p25: number | null;
    median: number | null;
    p75: number | null;
    max: number | null;
    confidence: 'high' | 'medium' | 'low' | 'none';
    scope: 'version' | 'model';
    year_scope?: 'exact' | 'range';
    km?: number | null;
}

export interface MarketPrice {
    samples: number;
    source_count: number;
    currency?: string;
    min: number | null;
    p25: number | null;
    suggested: number | null;
    p75: number | null;
    max: number | null;
    buy_price?: number | null;
    sell_price?: number | null;
    confidence: 'high' | 'medium' | 'low' | 'none';
    source: string | null;
    source_type?: 'libro_azul' | 'market_mx' | 'none';
    source_label?: string;
    match_level?: 'version' | 'model' | 'none';
    sources?: string[];
    km?: number | null;
    expected_km?: number | null;
    km_adjusted?: boolean;
    error?: string;
    cached?: boolean;
}
