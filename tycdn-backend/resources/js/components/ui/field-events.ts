/** Preserve existing form change handlers while controls use the UI primitives. */
export function fieldEvent(
    type: 'input' | 'change',
    values: { value: string; checked?: boolean },
    reset?: (value: string) => void,
): Event {
    const event = new Event(type, { bubbles: true });
    const target = {
        get value() {
            return values.value;
        },
        set value(value: string) {
            values.value = value;
            reset?.(value);
        },
        checked: values.checked,
    };
    Object.defineProperties(event, {
        target: { value: target },
        currentTarget: { value: target },
    });

    return event;
}
/** A submit can invalidate several fields; open only the first field's popup. */
export function isFirstInvalidField(event: Event): boolean {
    const field = event.target as HTMLInputElement;

    return (
        !field.form ||
        field.form.querySelector(
            'input:invalid, select:invalid, textarea:invalid',
        ) === field
    );
}
