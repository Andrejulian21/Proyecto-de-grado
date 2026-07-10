import { useState, useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { ArrowLeft, Send, Bot, User, Lightbulb, FileText, BookOpen, Loader2 } from 'lucide-react';

interface Message {
    id: string;
    role: 'assistant' | 'user';
    content: string;
    timestamp: string;
}

interface Suggestion {
    id: string;
    icon: typeof Lightbulb;
    title: string;
    description: string;
    bgClass: string;
    iconColor: string;
}

const INITIAL_MESSAGES: Message[] = [
    { id: 'welcome', role: 'assistant', content: '¡Hola! Soy tu asistente de orientación para proyectos de grado. Puedo ayudarte con:\n\n• Estructura y contenido de tu documento\n• Metodología de investigación\n• Normas de formato y estilo\n• Recomendaciones para tu sustentación\n\n¿En qué puedo ayudarte hoy?', timestamp: '10:30' },
    { id: 'q1', role: 'user', content: '¿Cómo puedo mejorar la sección de metodología de mi proyecto?', timestamp: '10:31' },
    { id: 'r1', role: 'assistant', content: 'Excelente pregunta. Para mejorar la sección de metodología:\n\n1. **Define claramente el tipo de investigación** (aplicada, descriptiva, correlacional, etc.)\n2. **Describe el enfoque** (cuantitativo, cualitativo o mixto)\n3. **Justifica por qué seleccionaste ese enfoque** para tu proyecto\n4. **Incluye el ciclo de vida** del desarrollo (SCRUM, RUP, etc.)\n5. **Detalla los instrumentos** de recolección de datos\n\n¿Te gustaría que profundice en alguno de estos puntos?', timestamp: '10:32' },
    { id: 'q2', role: 'user', content: '¿Cuántas referencias debería incluir en el marco teórico?', timestamp: '10:33' },
    { id: 'r2', role: 'assistant', content: 'Para un proyecto de grado de ingeniería de sistemas, se recomienda:\n\n• **Mínimo 15-20 referencias** para el marco teórico\n• **Al menos 70% de los últimos 5 años** (salvo fuentes clásicas)\n• **Preferiblemente artículos indexados** (IEEE, ACM, Scopus)\n• **Libros de texto** para conceptos fundamentales\n• **Normas APA 7ª edición** para el formato\n\nRecuerda que la calidad es más importante que la cantidad. Cada referencia debe estar directamente relacionada con tu investigación.', timestamp: '10:34' },
];

const SUGGESTIONS: Suggestion[] = [
    { id: 's1', icon: FileText, title: 'Estructura del documento', description: 'Guía sobre las secciones y el orden recomendado para tu proyecto.', bgClass: 'bg-[#fed7aa]', iconColor: 'text-[#c2410c]' },
    { id: 's2', icon: BookOpen, title: 'Metodología de investigación', description: 'Tipos de investigación, enfoques y métodos de recolección de datos.', bgClass: 'bg-[#dcfce7]', iconColor: 'text-[#16a34a]' },
    { id: 's3', icon: Lightbulb, title: 'Recomendaciones para sustentación', description: 'Consejos para preparar y presentar tu sustentación final.', bgClass: 'bg-[#e0e7ff]', iconColor: 'text-[#4f46e5]' },
];

export default function AsistenteOrientacion() {
    const navigate = useNavigate();
    const [messages] = useState(INITIAL_MESSAGES);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    function handleSend() {
        if (!input.trim() || sending) return;
        setSending(true);
        // Simulate response
        setTimeout(() => {
            setSending(false);
        }, 1000);
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="IA"
                title="Asistente de Orientación"
                subtitle="Chat de asistencia inteligente para tu proyecto de grado"
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

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-4">
                {/* Chat */}
                <div className="lg:col-span-3">
                    <div className="flex flex-col rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)] h-[600px]">
                        {/* Messages */}
                        <div className="flex-1 overflow-y-auto p-4 space-y-4">
                            {messages.map((msg) => (
                                <div
                                    key={msg.id}
                                    className={`flex gap-3 ${msg.role === 'user' ? 'flex-row-reverse' : ''}`}
                                >
                                    <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-xl ${
                                        msg.role === 'assistant' ? 'bg-[#fed7aa]' : 'bg-[#4f46e5]'
                                    }`}>
                                        {msg.role === 'assistant' ? (
                                            <Bot className={`h-4 w-4 text-[#c2410c]`} />
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
                                        <pre className="whitespace-pre-wrap font-sans text-sm m-0">{msg.content}</pre>
                                        <p className={`text-xs mt-1.5 ${msg.role === 'assistant' ? 'text-[#78716c]' : 'text-white/70'}`}>
                                            {msg.timestamp}
                                        </p>
                                    </div>
                                </div>
                            ))}
                            <div ref={messagesEndRef} />
                        </div>

                        {/* Input */}
                        <div className="border-t border-[#e5e5e5] p-4">
                            <div className="flex items-center gap-2">
                                <input
                                    type="text"
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter') handleSend(); }}
                                    placeholder="Escribe tu pregunta aquí..."
                                    className="flex-1 min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                />
                                <button
                                    onClick={handleSend}
                                    disabled={!input.trim() || sending}
                                    className="inline-flex min-h-[40px] w-10 items-center justify-center rounded-lg bg-[#c2410c] text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
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

                {/* Suggestions Panel */}
                <div className="lg:col-span-1">
                    <div className="sticky top-20 flex flex-col gap-4">
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <h3 className="mb-4 text-sm font-bold text-[#1c1917]">Temas Recomendados</h3>
                            <div className="flex flex-col gap-3">
                                {SUGGESTIONS.map((s) => (
                                    <button
                                        key={s.id}
                                        className="group flex items-start gap-3 rounded-lg border border-[#e5e5e5] p-3 text-left transition-all hover:border-[#c2410c] active:scale-[0.98]"
                                    >
                                        <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${s.bgClass}`}>
                                            <s.icon className={`h-4 w-4 ${s.iconColor}`} />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-xs font-semibold text-[#1c1917] group-hover:text-[#c2410c] transition-colors">
                                                {s.title}
                                            </p>
                                            <p className="text-[10px] text-[#78716c] mt-0.5">
                                                {s.description}
                                            </p>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-[#fef3c7] bg-[#fef3c7] p-4">
                            <div className="flex items-start gap-2">
                                <Lightbulb className="mt-0.5 h-4 w-4 shrink-0 text-[#d97706]" />
                                <div>
                                    <p className="text-xs font-semibold text-[#78350f]">
                                        Sugerencia del día
                                    </p>
                                    <p className="text-xs text-[#78350f] mt-1">
                                        Revisa la sección de resultados de tu último avance. El análisis automático detectó que algunos gráficos no tienen descripción textual.
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
