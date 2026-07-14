import { useState, useRef, useEffect, useCallback } from 'react';
import { useStudentSearch, type StudentUser } from '@/hooks/useStudentSearch';
import { X, Search, Loader2, ChevronDown } from 'lucide-react';

export interface StudentAutocompleteProps {
    value: StudentUser[];
    onChange: (selected: StudentUser[]) => void;
    error?: string;
    max?: number;
}

export function StudentAutocomplete({
    value,
    onChange,
    error,
    max = 3,
}: StudentAutocompleteProps) {
    const [inputValue, setInputValue] = useState('');
    const [open, setOpen] = useState(false);
    const { results, loading, search } = useStudentSearch();
    const wrapperRef = useRef<HTMLDivElement>(null);

    // Filter already-selected students from results
    const filteredResults = results.filter(
        (r) => !value.some((v) => v.id === r.id),
    );

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleInputChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const val = e.target.value;
            setInputValue(val);
            search(val);
            setOpen(true);
        },
        [search],
    );

    const handleSelect = useCallback(
        (student: StudentUser) => {
            if (value.length >= max) return;
            onChange([...value, student]);
            setInputValue('');
            setOpen(false);
        },
        [value, onChange, max],
    );

    const handleRemove = useCallback(
        (studentId: number) => {
            onChange(value.filter((s) => s.id !== studentId));
        },
        [value, onChange],
    );

    const handleFocus = useCallback(() => {
        if (inputValue.trim()) setOpen(true);
    }, [inputValue]);

    return (
        <div ref={wrapperRef} className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-[#1c1917]">
                Estudiantes <span className="text-[#dc2626]">*</span>
            </label>

            {/* Selected chips */}
            {value.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {value.map((student) => (
                        <span
                            key={student.id}
                            className="inline-flex items-center gap-1 rounded-full bg-[#fed7aa] px-2.5 py-1 text-xs font-semibold text-[#9a330a]"
                        >
                            {student.name}
                            <button
                                type="button"
                                onClick={() => handleRemove(student.id)}
                                className="ml-0.5 rounded-full p-0.5 transition-colors hover:bg-[#fdba74]"
                                aria-label={`Eliminar ${student.name}`}
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </span>
                    ))}
                    {value.length < max && (
                        <span className="text-xs text-[#78716c] self-center">
                            ({max - value.length} restante{(max - value.length) !== 1 ? 's' : ''})
                        </span>
                    )}
                </div>
            )}

            {/* Search input */}
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                <input
                    type="text"
                    value={inputValue}
                    onChange={handleInputChange}
                    onFocus={handleFocus}
                    placeholder={
                        value.length >= max
                            ? 'Máximo de estudiantes alcanzado'
                            : 'Buscar estudiante por nombre o email...'
                    }
                    disabled={value.length >= max}
                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:cursor-not-allowed disabled:opacity-60"
                    aria-label="Buscar estudiante"
                    aria-invalid={!!error}
                    role="combobox"
                    aria-expanded={open}
                    aria-autocomplete="list"
                />
                {loading && (
                    <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-[#78716c]" />
                )}
            </div>

            {/* Dropdown */}
            {open && inputValue.trim() && (
                <div className="z-50 max-h-48 overflow-y-auto rounded-lg border border-[#e5e5e5] bg-white shadow-[0_4px_12px_rgba(28,25,23,0.1)]">
                    {loading ? (
                        <div className="flex items-center justify-center py-6">
                            <Loader2 className="h-5 w-5 animate-spin text-[#78716c]" />
                        </div>
                    ) : filteredResults.length === 0 ? (
                        <div className="py-6 text-center text-sm text-[#57534e]">
                            Sin resultados
                        </div>
                    ) : (
                        <ul className="py-1" role="listbox">
                            {filteredResults.map((student) => (
                                <li key={student.id}>
                                    <button
                                        type="button"
                                        onClick={() => handleSelect(student)}
                                        className="flex w-full items-center gap-3 px-3 py-2 text-left text-sm transition-colors hover:bg-[#f5f5f4]"
                                        role="option"
                                        aria-selected={value.some((v) => v.id === student.id)}
                                    >
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[#f5f5f4] text-xs font-bold text-[#57534e]">
                                            {student.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div className="flex flex-col">
                                            <span className="font-medium text-[#1c1917]">
                                                {student.name}
                                            </span>
                                            <span className="text-xs text-[#78716c]">
                                                {student.email}
                                            </span>
                                        </div>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {error && (
                <span className="text-xs font-medium text-[#dc2626]" role="alert">
                    {error}
                </span>
            )}
        </div>
    );
}
