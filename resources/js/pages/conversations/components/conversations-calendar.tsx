import React, { useState, useEffect, useImperativeHandle, forwardRef } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';

export interface ConversationsCalendarHandle {
    refresh: () => void;
}

interface ConversationsCalendarProps {
    onSelectThread: (threadId: number) => void;
    t: any;
}

export const ConversationsCalendar = forwardRef<ConversationsCalendarHandle, ConversationsCalendarProps>(
    function ConversationsCalendar({ onSelectThread, t }, ref) {
    const [events, setEvents] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);

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

    // Expose refresh to parent via ref
    useImperativeHandle(ref, () => ({
        refresh: fetchEvents,
    }));

    const handleEventClick = (info: any) => {
        info.jsEvent.preventDefault();
        const threadId = info.event.id;
        if (threadId) {
            onSelectThread(Number(threadId));
        }
    };

    return (
        <div className="flex flex-col h-full bg-background overflow-hidden">
            {/* Header */}
            <div className="flex justify-between items-center px-4 py-3 border-b shrink-0">
                <div>
                    <h2 className="text-sm font-semibold tracking-tight">{t('Follow-up Calendar')}</h2>
                    <p className="text-[11px] text-muted-foreground">{t('Upcoming follow-ups')}</p>
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
                    height="auto"
                    eventDisplay="block"
                    dayMaxEvents={3}
                    eventContent={(eventInfo) => {
                        const isClosed = eventInfo.event.extendedProps.status === 'Closed' || eventInfo.event.extendedProps.status === 'Archive';
                        return (
                            <div className={`overflow-hidden cursor-pointer hover:opacity-80 rounded text-[10px] px-1 py-0.5 ${isClosed ? 'bg-gray-100 text-gray-500 line-through' : 'bg-primary/10 text-primary font-medium'}`}>
                                <div className="truncate">
                                    {eventInfo.event.title}
                                </div>
                            </div>
                        );
                    }}
                />
            </div>
        </div>
    );
});
