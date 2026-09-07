export type CdnflyRecord = Record<string, unknown>;

export type CdnflyListData = {
    data?: unknown;
    items?: unknown;
    list?: unknown;
    rows?: unknown;
    records?: unknown;
    total?: number;
    meta?: unknown;
    [key: string]: unknown;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};
