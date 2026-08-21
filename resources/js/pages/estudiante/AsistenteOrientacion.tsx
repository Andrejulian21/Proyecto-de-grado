import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { apiFetch } from '@/lib/utils';
import {
    ArrowLeft,
    Send,
    Bot,
    User,
    Lightbulb,
    FileText,
    BookOpen,
    Loader2,
    AlertTriangle,
    Users,
} from 'lucide-react';

interface Message {
    id: string;
    role: 'assistant' | 'user';
    content: string;
    timestamp: string;
}

interface DirectorRecomendado {
    id: number;
    nombre: string;
    justificacion: string;
    afinidad?: number | null;
}

interface ResultadoAsistente {
    mensaje: string;
    resumen_conversacion: string;
    idea_refinada: string;
    lineas_investigacion: string[];
    tecnologias_recomendadas: string[];
    metodologias_sugeridas: string[];
    directores_recomendados: DirectorRecomendado[];
    riesgos: string[];
    proximos_pasos: string[];
}

interface Suggestion {
    id: string;
    icon: typeof Lightbulb;
    title: string;
    description: string;
    prompt: string;
    bgClass: string;
    iconColor: string;
}

const WELCOME_MESSAGE: Message = {
    id: 'welcome',
    role: 'assistant',
    content:
        '¡Hola! Soy tu asistente académico para proyectos de grado de Ingeniería de Sistemas.\n\nPuedo ayudarte a:\n\n• Refinar la idea de tu proyecto\n• Sugerir líneas de investigación, tecnologías y metodologías\n• Recomendar Directores según perfiles reales del sistema\n\nCuéntame tu idea o en qué necesitas orientación.',
    timestamp: '',
};

const SUGGESTIONS: Suggestion[] = [
    {
        id: 's1',
        icon: FileText,
        title: 'Definir mi idea',
        description: 'Ayuda para clarificar el problema, alcance y aporte del proyecto.',
        prompt: 'Quiero definir mejor la idea de mi proyecto de grado. ¿Qué información necesitas de mí?',
        bgClass: 'bg-[#fed7aa]',
        iconColor: 'text-[#c2410c]',
    },
    {
        id: 's2',
        icon: BookOpen,
        title: 'Metodología',
        description: 'Orientación sobre enfoques y métodos adecuados a tu idea.',
        prompt: '¿Qué metodología de investigación o desarrollo me recomiendas según mi idea de proyecto?',
        bgClass: 'bg-[#dcfce7]',
        iconColor: 'text-[#16a34a]',
    },
    {
        id: 's3',
        icon: Users,
        title: 'Recomendar Director',
        description: 'Sugerencia de Directores con base en perfiles académicos reales.',
        prompt: 'Con la información que te he dado, ¿qué Directores me recomiendas y por qué?',
        bgClass: 'bg-[#e0e7ff]',
        iconColor: 'text-[#4f46e5]',
    },
];

function formatTime(iso?: string | null): string {
    if (!iso) {
        return new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return date.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
}

function ListBlock({ title, items }: { title: string; items: string[] }) {
    if (!items.length) return null;
    return (
        <div>
            <p className="text-xs font-semibold text-[#1c1917] mb-1.5">{title}</p>
            <ul className="list-disc pl-4 space-y-1">
                {items.map((item, index) => (
                    <li key={`${title}-${index}`} className="text-[11px] text-[#44403c]">
                        {item}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function AsistenteOrientacion() {
    const navigate = useNavigate();
    const [messages, setMessages] = useState<Message[]>([WELCOME_MESSAGE]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(true);
    const [sending, setSending] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [aiUnavailable, setAiUnavailable] = useState(false);
    const [resultado, setResultado] = useState<ResultadoAsistente | null>(null);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, sending]);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setLoadError(null);
            try {
                const res = await apiFetch('/api/estudiante/asistente/conversacion');
                const payload = await res.json().catch(() => ({}));

                if (!res.ok) {
                    throw new Error(payload?.error ?? 'No se pudo cargar la conversación.');
                }

                const apiMessages = (payload?.data?.mensajes ?? []) as Array<{
                    id: number;
                    role: string;
                    content: string;
                    created_at?: string | null;
                }>;

                if (!cancelled) {
                    if (apiMessages.length === 0) {
                        setMessages([{ ...WELCOME_MESSAGE, timestamp: formatTime() }]);
                    } else {
                        setMessages(
                            apiMessages
                                .filter((m) => m.role === 'user' || m.role === 'assistant')
                                .map((m) => ({
                                    id: String(m.id),
                                    role: m.role as 'user' | 'assistant',
                                    content: m.content,
                                    timestamp: formatTime(m.created_at),
                                })),
                        );
                    }

                    const lastResult = payload?.data?.resultado as ResultadoAsistente | null;
                    if (lastResult) {
                        setResultado(lastResult);
                    }
                }
            } catch {
                if (!cancelled) {
                    setLoadError('No se pudo cargar el asistente. Inténtalo de nuevo.');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        void load();
        return () => {
            cancelled = true;
        };
    }, []);

    async function sendMessage(text: string) {
        const trimmed = text.trim();
        if (!trimmed || sending) return;

        setSending(true);
        setActionError(null);
        setAiUnavailable(false);

        const optimistic: Message = {
            id: `local-${Date.now()}`,
            role: 'user',
            content: trimmed,
            timestamp: formatTime(),
        };
        setMessages((prev) => [...prev, optimistic]);
        setInput('');

        try {
            const res = await apiFetch('/api/estudiante/asistente/mensajes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mensaje: trimmed }),
            });

            const payload = await res.json().catch(() => ({}));

            if (res.status === 503 || payload?.code === 'ai_unavailable') {
                setAiUnavailable(true);
                setActionError(
                    payload?.error ??
                        'El servicio de Inteligencia Artificial no se encuentra disponible temporalmente.',
                );
                return;
            }

            if (!res.ok) {
                setActionError(
                    payload?.error ?? 'No fue posible obtener una respuesta del asistente.',
                );
                return;
            }

            const assistant = payload?.data?.mensaje_asistente;
            const structured = payload?.data?.resultado as ResultadoAsistente | undefined;

            if (assistant?.content) {
                setMessages((prev) => [
                    ...prev,
                    {
                        id: String(assistant.id ?? `assistant-${Date.now()}`),
                        role: 'assistant',
                        content: assistant.content,
                        timestamp: formatTime(assistant.created_at),
                    },
                ]);
            }

            if (structured) {
                setResultado(structured);
            }
        } catch {
            setActionError('No fue posible contactar al asistente. Inténtalo de nuevo.');
        } finally {
            setSending(false);
        }
    }

    function handleSend() {
        void sendMessage(input);
    }

    function handleSuggestion(prompt: string) {
        setInput(prompt);
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="IA"
                title="Asistente de Orientación"
                subtitle="Asistente académico especializado para tu proyecto de grado"
                actions={
                    <button
                        onClick={() => navigate('/dashboard/estudiante')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {(aiUnavailable || actionError || loadError) && (
                <div
                    className="flex items-start gap-3 rounded-xl border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#991b1b]"
                    role="alert"
                >
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        {aiUnavailable
                            ? 'El servicio de Inteligencia Artificial no se encuentra disponible temporalmente. Inténtalo más tarde.'
                            : (actionError ?? loadError)}
                    </p>
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-4">
                <div className="lg:col-span-3">
                    <div className="flex h-[600px] flex-col rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="flex-1 space-y-4 overflow-y-auto p-4">
                            {loading ? (
                                <div className="flex h-full items-center justify-center text-sm text-[#78716c]">
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Cargando conversación…
                                </div>
                            ) : (
                                messages.map((msg) => (
                                    <div
                                        key={msg.id}
                                        className={`flex gap-3 ${msg.role === 'user' ? 'flex-row-reverse' : ''}`}
                                    >
                                        <div
                                            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-xl ${
                                                msg.role === 'assistant' ? 'bg-[#fed7aa]' : 'bg-[#4f46e5]'
                                            }`}
                                        >
                                            {msg.role === 'assistant' ? (
                                                <Bot className="h-4 w-4 text-[#c2410c]" />
                                            ) : (
                                                <User className="h-4 w-4 text-white" />
                                            )}
                                        </div>
                                        <div
                                            className={`max-w-[80%] rounded-xl px-4 py-3 text-sm ${
                                                msg.role === 'assistant'
                                                    ? 'bg-[#fafaf9] text-[#1c1917]'
                                                    : 'bg-[#c2410c] text-white'
                                            }`}
                                        >
                                            <pre className="m-0 whitespace-pre-wrap font-sans text-sm">
                                                {msg.content}
                                            </pre>
                                            {msg.timestamp && (
                                                <p
                                                    className={`mt-1.5 text-xs ${
                                                        msg.role === 'assistant'
                                                            ? 'text-[#78716c]'
                                                            : 'text-white/70'
                                                    }`}
                                                >
                                                    {msg.timestamp}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                            {sending && (
                                <div className="flex items-center gap-2 text-xs text-[#78716c]">
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    El asistente está elaborando una respuesta…
                                </div>
                            )}
                            <div ref={messagesEndRef} />
                        </div>

                        <div className="border-t border-[#e5e5e5] p-4">
                            <div className="flex items-center gap-2">
                                <input
                                    type="text"
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') handleSend();
                                    }}
                                    placeholder="Escribe tu pregunta o idea de proyecto…"
                                    disabled={loading || sending}
                                    className="min-h-[40px] flex-1 rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:opacity-60"
                                />
                                <button
                                    onClick={handleSend}
                                    disabled={!input.trim() || sending || loading}
                                    className="inline-flex h-10 w-10 min-h-[40px] items-center justify-center rounded-lg bg-[#c2410c] text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                                    aria-label="Enviar mensaje"
                                >
                                    {sending ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Send className="h-4 w-4" />
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="lg:col-span-1">
                    <div className="sticky top-20 flex flex-col gap-4">
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <h3 className="mb-4 text-sm font-bold text-[#1c1917]">Temas recomendados</h3>
                            <div className="flex flex-col gap-3">
                                {SUGGESTIONS.map((s) => (
                                    <button
                                        key={s.id}
                                        type="button"
                                        onClick={() => handleSuggestion(s.prompt)}
                                        className="group flex items-start gap-3 rounded-lg border border-[#e5e5e5] p-3 text-left transition-all hover:border-[#c2410c] active:scale-[0.98]"
                                    >
                                        <div
                                            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${s.bgClass}`}
                                        >
                                            <s.icon className={`h-4 w-4 ${s.iconColor}`} />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-xs font-semibold text-[#1c1917] transition-colors group-hover:text-[#c2410c]">
                                                {s.title}
                                            </p>
                                            <p className="mt-0.5 text-[10px] text-[#78716c]">
                                                {s.description}
                                            </p>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <h3 className="mb-3 text-sm font-bold text-[#1c1917]">Orientación actual</h3>
                            {!resultado ? (
                                <p className="text-xs text-[#78716c]">
                                    Cuando el asistente responda, aquí verás la idea refinada, recomendaciones
                                    y Directores sugeridos.
                                </p>
                            ) : (
                                <div className="flex flex-col gap-3">
                                    {resultado.idea_refinada && (
                                        <div>
                                            <p className="mb-1 text-xs font-semibold text-[#1c1917]">
                                                Idea refinada
                                            </p>
                                            <p className="text-[11px] text-[#44403c]">
                                                {resultado.idea_refinada}
                                            </p>
                                        </div>
                                    )}
                                    <ListBlock
                                        title="Líneas de investigación"
                                        items={resultado.lineas_investigacion ?? []}
                                    />
                                    <ListBlock
                                        title="Tecnologías"
                                        items={resultado.tecnologias_recomendadas ?? []}
                                    />
                                    <ListBlock
                                        title="Metodologías"
                                        items={resultado.metodologias_sugeridas ?? []}
                                    />
                                    {(resultado.directores_recomendados ?? []).length > 0 && (
                                        <div>
                                            <p className="mb-1.5 text-xs font-semibold text-[#1c1917]">
                                                Directores recomendados
                                            </p>
                                            <div className="flex flex-col gap-2">
                                                {resultado.directores_recomendados.map((d) => (
                                                    <div
                                                        key={d.id}
                                                        className="rounded-lg border border-[#e5e5e5] p-2"
                                                    >
                                                        <p className="text-[11px] font-semibold text-[#1c1917]">
                                                            {d.nombre}
                                                        </p>
                                                        <p className="mt-0.5 text-[10px] text-[#78716c]">
                                                            {d.justificacion}
                                                        </p>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    <ListBlock title="Riesgos" items={resultado.riesgos ?? []} />
                                    <ListBlock
                                        title="Próximos pasos"
                                        items={resultado.proximos_pasos ?? []}
                                    />
                                </div>
                            )}
                        </div>

                        <div className="rounded-xl border border-[#fef3c7] bg-[#fef3c7] p-4">
                            <div className="flex items-start gap-2">
                                <Lightbulb className="mt-0.5 h-4 w-4 shrink-0 text-[#d97706]" />
                                <div>
                                    <p className="text-xs font-semibold text-[#78350f]">
                                        Consejo
                                    </p>
                                    <p className="mt-1 text-xs text-[#78350f]">
                                        Describe el problema, el contexto y las tecnologías que te interesan
                                        para obtener recomendaciones de Directores más precisas.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
