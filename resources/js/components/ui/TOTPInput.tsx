import { useState, useRef, useCallback, type KeyboardEvent, type ClipboardEvent } from 'react';
import { cn } from '@/lib/utils';

export interface TOTPInputProps {
    onComplete: (code: string) => void;
    disabled?: boolean;
    error?: string;
}

const DIGIT_COUNT = 6;

export function TOTPInput({ onComplete, disabled = false, error }: TOTPInputProps) {
    const [values, setValues] = useState<string[]>(Array(DIGIT_COUNT).fill(''));
    const inputRefs = useRef<(HTMLInputElement | null)[]>(Array(DIGIT_COUNT).fill(null));

    const focusNext = useCallback((idx: number) => {
        if (idx < DIGIT_COUNT - 1) {
            inputRefs.current[idx + 1]?.focus();
        }
    }, []);

    const focusPrev = useCallback((idx: number) => {
        if (idx > 0) {
            inputRefs.current[idx - 1]?.focus();
        }
    }, []);

    const handleChange = useCallback(
        (idx: number, char: string) => {
            if (disabled) return;
            const digit = char.replace(/[^0-9]/g, '').slice(0, 1);
            if (!digit && char !== '') return;

            const next = [...values];
            next[idx] = digit;
            setValues(next);

            if (digit) {
                focusNext(idx);
            }

            // Check completion
            const code = next.join('');
            if (code.length === DIGIT_COUNT && code.split('').every(Boolean)) {
                onComplete(code);
            }
        },
        [disabled, values, focusNext, onComplete],
    );

    const handleKeyDown = useCallback(
        (idx: number, e: KeyboardEvent<HTMLInputElement>) => {
            if (e.key === 'Backspace') {
                if (values[idx] === '') {
                    focusPrev(idx);
                } else {
                    const next = [...values];
                    next[idx] = '';
                    setValues(next);
                }
            } else if (e.key === 'ArrowLeft') {
                focusPrev(idx);
            } else if (e.key === 'ArrowRight') {
                focusNext(idx);
            }
        },
        [values, focusPrev, focusNext],
    );

    const handlePaste = useCallback(
        (e: ClipboardEvent<HTMLInputElement>) => {
            e.preventDefault();
            if (disabled) return;

            const pasted = e.clipboardData
                .getData('text')
                .replace(/[^0-9]/g, '')
                .slice(0, DIGIT_COUNT);

            if (!pasted) return;

            const next = [...values];
            for (let i = 0; i < pasted.length; i++) {
                next[i] = pasted[i];
            }
            setValues(next);

            // Focus after last filled
            const focusIdx = Math.min(pasted.length, DIGIT_COUNT - 1);
            inputRefs.current[focusIdx]?.focus();

            const code = next.join('');
            if (code.length === DIGIT_COUNT && code.split('').every(Boolean)) {
                onComplete(code);
            }
        },
        [disabled, values, onComplete],
    );

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center gap-2 sm:gap-3">
                {values.map((val, idx) => (
                    <input
                        key={idx}
                        ref={(el) => { inputRefs.current[idx] = el; }}
                        type="text"
                        inputMode="numeric"
                        maxLength={1}
                        value={val}
                        onChange={(e) => handleChange(idx, e.target.value)}
                        onKeyDown={(e) => handleKeyDown(idx, e)}
                        onPaste={idx === 0 ? handlePaste : undefined}
                        disabled={disabled}
                        autoFocus={idx === 0}
                        aria-label={`Digito ${idx + 1} del código`}
                        className={cn(
                            'h-12 w-10 rounded-lg border text-center text-lg font-bold text-[#1c1917] outline-none transition-colors sm:h-14 sm:w-12 sm:text-xl',
                            error
                                ? 'border-[#dc2626] bg-[#fee2e2] focus:border-[#dc2626] focus:shadow-[0_0_0_3px_#fee2e2]'
                                : 'border-[#e5e5e5] bg-white focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]',
                            disabled && 'cursor-not-allowed opacity-60',
                        )}
                    />
                ))}
            </div>
            {error && (
                <p className="text-sm font-medium text-[#dc2626]">{error}</p>
            )}
        </div>
    );
}
