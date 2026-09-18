import { cdnflyJsonRows } from './cdnflyResponse';
import type { CdnflyRecord } from './sharedTypes';

export type MatcherCondition = {
    key: string;
    operator: string;
    value: string;
    raw?: CdnflyRecord;
};

// The installed v6 master uses arrays of item/op/value, including value2 for headers.
export function parseCcMatcher(value: unknown): MatcherCondition[] {
    return cdnflyJsonRows(value).map((row) => ({
        key: String(row.item ?? ''),
        operator: String(row.op ?? '='),
        value: String(row.value ?? ''),
        raw: row,
    }));
}

export function buildCcMatcher(conditions: MatcherCondition[]): CdnflyRecord[] {
    return conditions
        .filter((c) => c.key)
        .map((condition) => ({
            ...condition.raw,
            item: condition.key,
            op: condition.operator,
            value: condition.value,
        }));
}
