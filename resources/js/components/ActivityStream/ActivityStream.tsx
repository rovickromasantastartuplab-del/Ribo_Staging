import React from 'react';
import { useTranslation } from 'react-i18next';
import { Calendar, RefreshCw, History as HistoryIcon } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { ActivityItem } from './ActivityItem';

interface ActivityStreamProps {
    activities: any[];
    title?: string;
    isCompany?: boolean;
    auth?: any;
    onDeleteActivity?: (activity: any) => void;
    onDeleteAll?: () => void;
    emptyMessage?: string;
    maxHeight?: string;
    showAddComment?: boolean;
    onAddComment?: (comment: string) => void;
    // Props for editing comments (if applicable)
    editingCommentId?: number | null;
    editCommentText?: string;
    setEditingComment?: (id: number | null) => void;
    setEditCommentText?: (text: string) => void;
    updateCommentRoute?: string;
    updateCommentParams?: any;
    hasMore?: boolean;
    onLoadMore?: () => void;
    isLoadingMore?: boolean;
}

export const ActivityStream: React.FC<ActivityStreamProps> = ({
    activities,
    title,
    isCompany,
    auth,
    onDeleteActivity,
    onDeleteAll,
    emptyMessage,
    maxHeight = 'max-h-96',
    editingCommentId,
    editCommentText,
    setEditingComment,
    setEditCommentText,
    updateCommentRoute,
    updateCommentParams,
    hasMore,
    onLoadMore,
    isLoadingMore
}) => {
    const { t } = useTranslation();
    const observerTarget = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        const observer = new IntersectionObserver(
            entries => {
                if (entries[0].isIntersecting && hasMore && !isLoadingMore && onLoadMore) {
                    onLoadMore();
                }
            },
            { threshold: 0.1 }
        );

        if (observerTarget.current) {
            observer.observe(observerTarget.current);
        }

        return () => observer.disconnect();
    }, [hasMore, isLoadingMore, onLoadMore]);

    return (
        <Card className="shadow-sm overflow-hidden border-none">
            {title && (
                <CardHeader className="bg-gray-50 border-b px-8 py-6 flex flex-row items-center justify-between">
                    <CardTitle className="text-lg font-semibold">{title}</CardTitle>
                    {isCompany && onDeleteAll && activities.length > 0 && (
                        <button 
                            onClick={onDeleteAll}
                            className="text-xs text-red-500 hover:text-red-700 font-medium"
                        >
                            {t('Clear History')}
                        </button>
                    )}
                </CardHeader>
            )}
            <CardContent className={`p-8 ${maxHeight} overflow-y-auto`}>
                {activities && activities.length > 0 ? (
                    <div className="space-y-2">
                        {activities.map((activity, index) => (
                            <ActivityItem
                                key={activity.id || index}
                                activity={activity}
                                index={index}
                                totalItems={activities.length}
                                isCompany={isCompany}
                                auth={auth}
                                onDelete={onDeleteActivity}
                                editingCommentId={editingCommentId}
                                editCommentText={editCommentText}
                                setEditingComment={setEditingComment}
                                setEditCommentText={setEditCommentText}
                                updateCommentRoute={updateCommentRoute}
                                updateCommentParams={updateCommentParams}
                            />
                        ))}
                        
                        {/* Sentinel for IntersectionObserver */}
                        <div ref={observerTarget} className="h-4 w-full" />

                        {isLoadingMore && (
                            <div className="pt-4 pb-2 flex justify-center items-center gap-2 text-primary/60 animate-pulse">
                                <RefreshCw className="h-4 w-4 animate-spin" />
                                <span className="text-[10px] font-medium">{t('Loading older activities...')}</span>
                            </div>
                        )}

                        {!hasMore && activities.length > 5 && (
                            <div className="pt-8 pb-4 text-center">
                                <div className="h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent mb-4" />
                                <p className="text-[10px] text-muted-foreground uppercase tracking-widest">{t('End of history')}</p>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="text-center py-12 text-gray-500">
                        <Calendar className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                        <p className="text-sm">{emptyMessage || t('No activities found')}</p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
};
