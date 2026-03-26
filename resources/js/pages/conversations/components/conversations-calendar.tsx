import React, { useState, useEffect } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import axios from 'axios';
import { Card } from '@/components/ui/card';
import { useTranslation } from 'react-i18next';
import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function ConversationsCalendar({ onSelectThread, t }: { onSelectThread: (threadId: number) => void, t: any }) {
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

    const handleEventClick = (info: any) => {
        info.jsEvent.preventDefault();
        const threadId = info.event.id;
        if (threadId) {
            // Trigger the parent index.tsx's thread selector so it opens on the right side seamlessly
            onSelectThread(Number(threadId));
        }
    };

    return (
        <div className="flex flex-col h-full bg-background overflow-hidden p-6 relative">
            <div className="flex justify-between items-center mb-6">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">{t('Follow-up Calendar')}</h2>
                    <p className="text-sm text-muted-foreground">{t('Track all upcoming conversation follow-ups')}</p>
                </div>
                <Button 
                    variant="outline" 
                    size="sm" 
                    onClick={fetchEvents} 
                    disabled={loading}
                    className="gap-2"
                >
                    <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin text-primary' : ''}`} />
                    {t('Refresh')}
                </Button>
            </div>
            
            <Card className="flex-1 p-4 shadow-sm min-h-0 overflow-y-auto">
                <FullCalendar
                    plugins={[dayGridPlugin, interactionPlugin]}
                    initialView="dayGridMonth"
                    headerToolbar={{
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth'
                    }}
                    events={events}
                    eventClick={handleEventClick}
                    height="100%"
                    contentHeight="auto"
                    eventDisplay="block"
                    dayMaxEvents={3}
                    eventContent={(eventInfo) => {
                        const isClosed = eventInfo.event.extendedProps.status === 'Closed' || eventInfo.event.extendedProps.status === 'Archive';
                        return (
                            <div className={`p-1 overflow-hidden cursor-pointer hover:opacity-80 rounded text-xs ${isClosed ? 'bg-gray-100 text-gray-500 line-through' : 'bg-blue-100 text-blue-800 font-medium'}`}>
                                <div className="truncate px-1">
                                    {eventInfo.event.title}
                                </div>
                            </div>
                        );
                    }}
                />
            </Card>
        </div>
    );
}
