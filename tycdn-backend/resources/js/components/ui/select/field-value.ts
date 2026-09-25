// Encode values so the empty-string filter remains selectable in Reka Select,
// and numeric page sizes retain their type when a selection is committed.
export function encodeSelectValue(value: unknown): string {
    return `${typeof value === 'number' ? 'number' : 'string'}:${String(value ?? '')}`;
}
export function decodeSelectValue(value: string): string | number {
    return value.startsWith('number:')
        ? Number(value.slice(7))
        : value.slice(7);
}
import type { InjectionKey } from 'vue';

export const selectFieldOptions: InjectionKey<(value: unknown) => () => void> =
    Symbol('select-field-options');
