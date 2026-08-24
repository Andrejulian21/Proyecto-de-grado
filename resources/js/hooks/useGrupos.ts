import { useContext } from 'react';
import { GruposContext, type GruposContextValue } from '@/contexts/GruposContext';

export interface Grupo {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active?: boolean;
    created_at?: string;
}

export function useGrupos(): GruposContextValue {
    const ctx = useContext(GruposContext);
    if (!ctx) throw new Error('useGrupos must be used within a GruposProvider');
    return ctx;
}