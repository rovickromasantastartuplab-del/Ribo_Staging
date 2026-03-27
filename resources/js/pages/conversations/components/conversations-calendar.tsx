import React, { useState, useEffect, useImperativeHandle, forwardRef } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import axios from 'axios';
import { RefreshCw, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';

export interface ConversationsCalendarHandle {
    refresh: () => void;
}

interface ConversationsCalendarProps {
    onSelectThread: (threadId: number) => void;
    t: any;
}

// CSS injected to override FullCalendar's default blue event & more-link styles
const calendarStyles = `
.fc .fc-daygrid-event { background: transparent !important; border: none !important; }
.fc .fc-event-main { padding: 0 !important; }
.fc .fc-daygrid-more-link { font-size: 10px; color: hsl(var(--primary)); font-weight: 600; }
.fc .fc-more-popover { z-index: 10 !important; max-width: 220px !important; }
.fc .fc-popover { z-index: 10 !important; }
`;

export const ConversationsCalendar = forwardRef<ConversationsCalendarHandle, ConversationsCalendarProps>(
    function ConversationsCalendar({ onSelectThread, t }, ref) {
    const [events, setEvents] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);

    // Inline "more" sheet — shown instead of the floating popover
    const [moreDayEvents, setMoreDayEvents] = useState<any[]>([]);
    const [moreDayTitle, setMoreDayTitle] = useState('');

    const fetchEvents = async () => {
        setLoading(true);
        try {
            const response = await axios.get(route('api.conversations.calendar_events'));
            setEvents(response.data);
        } catch (error) {
            console.error('Failed to fetch calendar events', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchEvents();
    }, []);

    useImperativeHandle(ref, () => ({ refresh: fetchEvents }));

    const handleEventClick = (info: any) => {
        info.jsEvent.preventDefault();
        const threadId = info.event.id;
        if (threadId) onSelectThread(Number(threadId));
    };

    // Replace the floating popover with our own inline panel
    const handleMoreLinkClick = (info: any) => {
        info.jsEvent.preventDefault();
        const dayEvts = info.allSegs.map((seg: any) => seg.event);
        setMoreDayTitle(info.date.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' }));
        setMoreDayEvents(dayEvts);
        return 'stop'; // prevent FullCalendar from opening its popover
    };

    return (
        <div className="flex flex-col h-full bg-background overflow-hidden relative">
            {/* Inject override styles */}
            <style>{calendarStyles}</style>

            {/* Header */}
            <div className="flex justify-between items-center px-4 py-3 border-b shrink-0">
                <div>
                    <h2 className="text-sm font-semibold tracking-tight">{t('Follow-up Calendar')}</h2>
                    <p className="text-xs text-muted-foreground">{t('Upcoming follow-ups')}</p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={fetchEvents}
                    disabled={loading}
                    className="gap-1.5 h-7 text-xs"
                >
                    <RefreshCw className={`h-3 w-3 ${loading ? 'animate-spin text-primary' : ''}`} />
                    {t('Refresh')}
                </Button>
            </div>

            {/* Calendar body */}
            <div className="flex-1 min-h-0 overflow-y-auto p-3">
                <FullCalendar
                    plugins={[dayGridPlugin, interactionPlugin]}
                    initialView="dayGridMonth"
                    headerToolbar={{
                        left: 'prev,next today',
                        center: 'title',
                        right: '',
                    }}
                    events={events}
                    eventClick={handleEventClick}
                    moreLinkClick={handleMoreLinkClick}
                    height="auto"
                    eventDisplay="block"
                    dayMaxEvents={2}
                    eventContent={(eventInfo) => {
                        const isClosed = eventInfo.event.extendedProps.status === 'Closed' || eventInfo.event.extendedProps.status === 'Archive';
                        return (
                            <div
                                className="w-full overflow-hidden cursor-pointer rounded text-[10px] px-1 py-0.5"
                                style={{
                                    backgroundColor: isClosed ? '#f3f4f6' : 'hsl(220 13% 91%)',
                                    color: isClosed ? '#9ca3af' : 'hsl(221 83% 43%)',
                                    textDecoration: isClosed ? 'line-through' : 'none',
                                    fontWeight: 600,
                                }}
                            >
                                <div className="truncate">{eventInfo.event.title}</div>
                            </div>
                        );
                    }}
                />
            </div>

            {/* Inline "more" panel — replaces the floating popover */}
            {moreDayEvents.length > 0 && (
                <div className="absolute inset-0 z-20 bg-background/95 backdrop-blur-sm flex flex-col">
                    <div className="flex items-center justify-between px-4 py-3 border-b shrink-0">
                        <div>
                            <p className="text-xs font-semibold">{moreDayTitle}</p>
                            <p className="text-[10px] text-muted-foreground">{moreDayEvents.length} {t('follow-ups')}</p>
                        </div>
                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setMoreDayEvents([])}>
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                    <ScrollArea className="flex-1 p-3">
                        <div className="space-y-1.5">
                            {moreDayEvents.map((evt: any) => {
                                const isClosed = evt.extendedProps?.status === 'Closed' || evt.extendedProps?.status === 'Archive';
                                return (
                                    <button
                                        key={evt.id}
                                        onClick={() => {
                                            setMoreDayEvents([]);
                                            onSelectThread(Number(evt.id));
                                        }}
                                        className="w-full text-left rounded px-3 py-2 text-xs hover:bg-muted transition-colors border"
                                        style={{ textDecoration: isClosed ? 'line-through' : 'none', opacity: isClosed ? 0.6 : 1 }}
                                    >
                                        <span className="font-medium text-foreground truncate block">{evt.title}</span>
                                        <span className="text-[10px] text-muted-foreground">{evt.extendedProps?.status}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </ScrollArea>
                </div>
            )}
        </div>
    );
});
