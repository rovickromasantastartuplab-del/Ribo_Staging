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
                        
                        {hasMore && (
                            <div className="pt-4 pb-2 flex justify-center">
                                <button
                                    onClick={onLoadMore}
                                    disabled={isLoadingMore}
                                    className="px-6 py-2 rounded-full border border-primary/20 bg-primary/5 text-primary text-xs font-semibold hover:bg-primary/10 hover:border-primary/30 transition-all flex items-center gap-2 group disabled:opacity-50"
                                >
                                    {isLoadingMore ? (
                                        <RefreshCw className="h-3 w-3 animate-spin" />
                                    ) : (
                                        <HistoryIcon className="h-3 w-3 group-hover:rotate-12 transition-transform" />
                                    )}
                                    {t('Load older activities')}
                                </button>
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
