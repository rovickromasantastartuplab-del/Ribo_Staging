import React from 'react';
import { useTranslation } from 'react-i18next';
import { Trash2, Edit, Check, X, Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';

interface ActivityItemProps {
    activity: any;
    index: number;
    totalItems: number;
    isCompany?: boolean;
    auth?: any;
    onDelete?: (activity: any) => void;
    onEditComment?: (activityId: number, text: string) => void;
    editingCommentId?: number | null;
    editCommentText?: string;
    setEditingComment?: (id: number | null) => void;
    setEditCommentText?: (text: string) => void;
    updateCommentRoute?: string;
    updateCommentParams?: any;
}

export const ActivityItem: React.FC<ActivityItemProps> = ({
    activity,
    index,
    totalItems,
    isCompany,
    auth,
    onDelete,
    editingCommentId,
    editCommentText,
    setEditingComment,
    setEditCommentText,
    updateCommentRoute,
    updateCommentParams
}) => {
    const { t } = useTranslation();

    const formatRelativeTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInMinutes = Math.floor((now.getTime() - date.getTime()) / (1000 * 60));

        if (diffInMinutes < 1) return t('Just now');
        if (diffInMinutes < 60) return t('{{count}} {{unit}} ago', { count: diffInMinutes, unit: diffInMinutes === 1 ? t('minute') : t('minutes') });

        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) return t('{{count}} {{unit}} ago', { count: diffInHours, unit: diffInHours === 1 ? t('hour') : t('hours') });

        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays < 7) return t('{{count}} {{unit}} ago', { count: diffInDays, unit: diffInDays === 1 ? t('day') : t('days') });

        return window.appSettings?.formatDateTime?.(dateString, false) || new Date(dateString).toLocaleDateString();
    };

    const getIcon = () => {
        if (activity.activity_type === 'email_sent' || activity.activity_type === 'email_received') {
            return <Mail className="h-4 w-4 text-indigo-500" />;
        }
        return null;
    };

    return (
        <div className="flex gap-4">
            <div className="flex flex-col items-center">
                <div className="flex-shrink-0 w-8 h-8 rounded-full overflow-hidden border-2 border-gray-200 bg-white flex items-center justify-center">
                    {activity.user ? (
                        <img
                            src={activity.user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(activity.user.name || 'User')}&background=e5e7eb&color=374151&size=32`}
                            alt={activity.user.name || 'User'}
                            className="w-full h-full object-cover"
                            onError={(e) => {
                                const target = e.target as HTMLImageElement;
                                const fallback = `https://ui-avatars.com/api/?name=${encodeURIComponent(activity.user.name || 'User')}&background=e5e7eb&color=374151&size=32`;
                                if (target.src !== fallback) {
                                    target.src = fallback;
                                }
                            }}
                        />
                    ) : (
                        <div className="bg-gray-100 w-full h-full flex items-center justify-center">
                            {getIcon() || <div className="w-2 h-2 bg-gray-400 rounded-full" />}
                        </div>
                    )}
                </div>
                {index < totalItems - 1 && <div className="w-px h-8 bg-gray-200 mt-2" />}
            </div>
            <div className="flex-1 min-w-0 pb-2">
                <div className="flex items-center justify-between mb-1">
                    <span className="text-xs text-gray-400">
                        {activity.user?.name || (activity.activity_type?.startsWith('email') ? t('Email') : t('System'))}
                    </span>
                    <span className="text-xs text-gray-500 font-medium">
                        {formatRelativeTime(activity.created_at)}
                    </span>
                </div>
                <div className={`bg-white border rounded-lg p-3 shadow-sm ${activity.activity_type.startsWith('email') ? 'border-indigo-100 bg-indigo-50/10' : ''}`}>
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-medium text-gray-900" dangerouslySetInnerHTML={{
                            __html: activity.title?.replace(
                                new RegExp(`^(${activity.user?.name || t('System')})`, 'g'),
                                '<span class="font-bold text-base">$1</span>'
                            ) || ''
                        }} />
                        <div className="flex items-center gap-2">
                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${
                                activity.activity_type.startsWith('email') 
                                ? 'bg-indigo-50 text-indigo-700 ring-indigo-600/20' 
                                : 'bg-gray-50 text-gray-700 ring-gray-600/20'
                            }`}>
                                {activity.activity_type.replace('_', ' ').charAt(0).toUpperCase() + activity.activity_type.replace('_', ' ').slice(1)}
                            </span>
                            {isCompany && onDelete && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-6 w-6 p-0 text-red-500 hover:text-red-700"
                                    onClick={() => onDelete(activity)}
                                >
                                    <Trash2 className="h-3 w-3" />
                                </Button>
                            )}
                        </div>
                    </div>
                    {activity.description && (
                        <div className="mb-2 text-sm text-gray-600" dangerouslySetInnerHTML={{
                            __html: activity.description
                        }} />
                    )}
                </div>
            </div>
        </div>
    );
};
