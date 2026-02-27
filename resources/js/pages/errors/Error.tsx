import { Head, Link } from '@inertiajs/react';
import { ShieldAlert, AlertCircle, ServerCrash, FileQuestion } from 'lucide-react';
import React from 'react';

// Common error status configurations
const errorMap: Record<number, { title: string; description: string; icon: React.ReactNode; color: string }> = {
    403: {
        title: 'Access Denied',
        description: 'You do not have the required permissions to view this page. If you believe this is a mistake, please contact your administrator.',
        icon: <ShieldAlert className="w-24 h-24 text-red-500 mb-6" />,
        color: 'text-red-500',
    },
    404: {
        title: 'Page Not Found',
        description: 'Sorry, the page you are looking for could not be found. It might have been removed, renamed, or did not exist in the first place.',
        icon: <FileQuestion className="w-24 h-24 text-gray-500 mb-6" />,
        color: 'text-gray-500',
    },
    500: {
        title: 'Server Error',
        description: 'Whoops, something went wrong on our servers. Our development team has been notified and we are looking into the issue.',
        icon: <ServerCrash className="w-24 h-24 text-orange-500 mb-6" />,
        color: 'text-orange-500',
    },
    503: {
        title: 'Service Unavailable',
        description: 'We are currently undergoing scheduled maintenance. We should be back up shortly.',
        icon: <AlertCircle className="w-24 h-24 text-blue-500 mb-6" />,
        color: 'text-blue-500',
    },
};

export default function ErrorPage({ status, message }: { status: number; message?: string }) {
    // Determine configuration based on status, or fallback to 500
    const config = errorMap[status] || errorMap[500];

    const finalTitle = status === 419 ? 'Page Expired' : config.title;
    const finalDescription = status === 419 ? 'Your session has expired. Please refresh and try again.' : config.description;

    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 dark:bg-slate-900 py-12 px-4 sm:px-6 lg:px-8 font-sans">
            <Head title={`${status} - ${config.title}`} />

            <div className="max-w-md w-full space-y-8 text-center bg-white dark:bg-slate-800 p-10 rounded-2xl shadow-xl border border-gray-100 dark:border-slate-700">
                <div className="flex justify-center">
                    {config.icon}
                </div>

                <div>
                    <h2 className="mt-2 text-6xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                        {status}
                    </h2>
                    <h3 className={`mt-4 text-2xl font-bold ${config.color}`}>
                        {finalTitle}
                    </h3>

                    <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        {/* Display custom message if provided, otherwise the fallback description */}
                        {message !== 'Internal Server Error' && message !== 'USER DOES NOT HAVE THE RIGHT PERMISSIONS.' ? message : finalDescription}
                    </p>
                </div>

                <div className="mt-8 flex justify-center gap-4">
                    <button
                        onClick={() => window.history.back()}
                        className="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors"
                    >
                        Go Back
                    </button>

                    <Link
                        href="/"
                        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                    >
                        Go Home
                    </Link>
                </div>
            </div>

            <div className="mt-12 text-center text-xs text-gray-400 dark:text-slate-500">
                &copy; {new Date().getFullYear()} Ribo CRM. All rights reserved.
            </div>
        </div>
    );
}
