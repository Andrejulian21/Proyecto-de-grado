import { useState, useCallback } from 'react';
import { useGrupos, type Grupo } from '@/hooks/useGrupos';
import { Loader2, Plus, ChevronDown, Check, Calendar } from 'lucide-react';

export interface GroupSelectorProps {
    value: number | null;
    onChange: (groupId: number) => void;
    error?: string;
    /** Optional: when true, show the selected group name as read-only text */
    readonly?: boolean;
    /** Optional: when false, hide the "create group" option (select existing groups only) */
    allowCreate?: boolean;
    /** Optional: called with the newly created group right after a successful create */
    onCreate?: (grupo: Grupo) => void;
}

export function GroupSelector({ value, onChange, error, readonly = false, allowCreate = true, onCreate }: GroupSelectorProps) {
    const { data: grupos, loading, error: fetchError, crear } = useGrupos();
    const [open, setOpen] = useState(false);
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [newName, setNewName] = useState('');
    const [newStartDate, setNewStartDate] = useState('');
    const [newEndDate, setNewEndDate] = useState('');
    const [newIsActive, setNewIsActive] = useState(true);
    const [creating, setCreating] = useState(false);
    const [createError, setCreateError] = useState<string | null>(null);

    const selectedGroup = grupos.find((g) => g.id === value);

    const handleSelect = useCallback(
        (groupId: number) => {
            onChange(groupId);
            setOpen(false);
        },
        [onChange],
    );

    const handleCreateGroup = useCallback(async () => {
        if (!newName.trim()) return;
        setCreating(true);
        setCreateError(null);
        try {
            const nuevo = await crear({
                name: newName.trim(),
                start_date: newStartDate,
                end_date: newEndDate,
                is_active: newIsActive,
            });
            setNewName('');
            setNewStartDate('');
            setNewEndDate('');
            setNewIsActive(true);
            setShowCreateForm(false);
            onChange(nuevo.id);
            onCreate?.(nuevo);
        } catch (err) {
            setCreateError(err instanceof Error ? err.message : 'Error al crear grupo');
        } finally {
            setCreating(false);
        }
    }, [newName, newStartDate, newEndDate, newIsActive, crear, onChange, onCreate]);

    // Read-only mode: just show the selected group name
    if (readonly) {
        return (
            <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-[#1c1917]">Grupo</label>
                <div className="min-h-[40px] rounded-lg border border-[#e5e5e5] bg-[#f5f5f4] px-3 py-2 text-sm text-[#57534e]">
                    {selectedGroup?.name ?? '—'}
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-[#1c1917]">
                Grupo <span className="text-[#dc2626]">*</span>
            </label>

            {/* Custom Select trigger */}
            <div className="relative">
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className="flex w-full min-h-[40px] items-center justify-between rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-left text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    aria-label="Seleccionar grupo"
                    aria-expanded={open}
                >
                    {loading ? (
                        <span className="flex items-center gap-2 text-[#78716c]">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Cargando grupos...
                        </span>
                    ) : selectedGroup ? (
                        <span>{selectedGroup.name}</span>
                    ) : (
                        <span className="text-[#78716c]">Seleccionar grupo...</span>
                    )}
                    <ChevronDown className={`h-4 w-4 text-[#78716c] transition-transform ${open ? 'rotate-180' : ''}`} />
                </button>

                {open && (
                    <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-[#e5e5e5] bg-white shadow-[0_4px_12px_rgba(28,25,23,0.1)]">
                        {fetchError ? (
                            <div className="px-3 py-2 text-sm text-[#dc2626]">{fetchError}</div>
                        ) : grupos.length === 0 ? (
                            <div className="px-3 py-2 text-sm text-[#57534e]">Sin grupos</div>
                        ) : (
                            <ul className="max-h-48 overflow-y-auto py-1">
                                {grupos.map((grupo) => (
                                    <li key={grupo.id}>
                                        <button
                                            type="button"
                                            onClick={() => handleSelect(grupo.id)}
                                            className={`flex w-full items-center justify-between px-3 py-2 text-sm transition-colors hover:bg-[#f5f5f4] ${
                                                value === grupo.id ? 'bg-[#fed7aa] font-semibold text-[#9a330a]' : 'text-[#1c1917]'
                                            }`}
                                        >
                                            <span className="flex items-center gap-2">
                                                {grupo.name}
                                                {grupo.is_active && (
                                                    <span className="rounded-full bg-[#dcfce7] px-2 py-0.5 text-[10px] font-bold text-[#166534]">
                                                        Activo
                                                    </span>
                                                )}
                                            </span>
                                            {value === grupo.id && <Check className="h-4 w-4" />}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {allowCreate && (
                            <div className="border-t border-[#e5e5e5] p-2">
                            {showCreateForm ? (
                                <div className="flex flex-col gap-2">
                                    <input
                                        type="text"
                                        value={newName}
                                        onChange={(e) => setNewName(e.target.value)}
                                        placeholder="Nombre del grupo"
                                        className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm outline-none focus:border-[#c2410c]"
                                    />
                                    <div className="relative">
                                        <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                                        <input
                                            type="date"
                                            value={newStartDate}
                                            onChange={(e) => setNewStartDate(e.target.value)}
                                            className="w-full rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm outline-none focus:border-[#c2410c]"
                                            placeholder="Fecha inicio"
                                        />
                                    </div>
                                    <div className="relative">
                                        <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                                        <input
                                            type="date"
                                            value={newEndDate}
                                            onChange={(e) => setNewEndDate(e.target.value)}
                                            className="w-full rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm outline-none focus:border-[#c2410c]"
                                            placeholder="Fecha fin"
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm text-[#1c1917]">
                                        <input
                                            type="checkbox"
                                            checked={newIsActive}
                                            onChange={(e) => setNewIsActive(e.target.checked)}
                                            className="h-4 w-4 rounded border-[#e5e5e5] text-[#c2410c] accent-[#c2410c] focus:ring-[#c2410c]"
                                        />
                                        Semestre activo
                                    </label>
                                    {createError && (
                                        <span className="text-xs text-[#dc2626]">{createError}</span>
                                    )}
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={handleCreateGroup}
                                            disabled={!newName.trim() || !newStartDate || !newEndDate || creating}
                                            className="inline-flex items-center gap-1 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {creating ? (
                                                <Loader2 className="h-3 w-3 animate-spin" />
                                            ) : (
                                                <Plus className="h-3 w-3" />
                                            )}
                                            Crear
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowCreateForm(false);
                                                setCreateError(null);
                                            }}
                                            className="rounded-lg px-3 py-1.5 text-xs font-semibold text-[#57534e] transition-colors hover:bg-[#f5f5f4]"
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => setShowCreateForm(true)}
                                    className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-semibold text-[#c2410c] transition-colors hover:bg-[#fed7aa]"
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                    Crear grupo
                                </button>
                            )}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {error && (
                <span className="text-xs font-medium text-[#dc2626]" role="alert">
                    {error}
                </span>
            )}
        </div>
    );
}
