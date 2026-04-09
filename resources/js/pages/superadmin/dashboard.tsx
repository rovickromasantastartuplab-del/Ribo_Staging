import React, { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { RefreshCw, BarChart3, Building2, CreditCard, Users, DollarSign, TrendingUp, Activity, AlertCircle, Receipt } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { DashboardOverview, AiUsageInsights } from '@/components/dashboard';
import { router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';


interface PaymentLog {
  id: number;
  payment_id: string;
  status: string;
  amount: string;
  currency: string;
  company_name: string;
  payer_email: string;
  plan_name: string;
  order_status: string;
  error: string | null;
  created_at: string;
  time_ago: string;
}

interface SuperAdminDashboardData {
  stats: {
    totalCompanies: number;
    totalUsers: number;
    totalSubscriptions: number;
    totalRevenue: number;
    activeCompanies: number;
    inactiveCompanies: number;
    monthlyGrowth: number;
  };
  recentActivity: Array<{
    id: number;
    type: string;
    message: string;
    time: string;
    status: 'success' | 'warning' | 'error';
  }>;
  topPlans: Array<{
    name: string;
    subscribers: number;
    revenue: number;
  }>;
  paymentLogs: PaymentLog[];
  aiUsage: any;
}

interface PageAction {
  label: string;
  icon: React.ReactNode;
  variant: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  onClick: () => void;
}

export default function SuperAdminDashboard({ dashboardData }: { dashboardData: SuperAdminDashboardData }) {
  const { t } = useTranslation();
  const [isRefreshing, setIsRefreshing] = useState(false);

  const handleRefresh = () => {
    setIsRefreshing(true);
    router.reload({ only: ['dashboardData'] });
    setTimeout(() => setIsRefreshing(false), 1000);
  };

  const pageActions: PageAction[] = [
    {
      label: t('Refresh'),
      icon: <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />,
      variant: 'outline',
      onClick: handleRefresh
    }
  ];

  const stats = dashboardData?.stats || {};
  const recentActivity = dashboardData?.recentActivity || [];
  const topPlans = dashboardData?.topPlans || [];
  const paymentLogs = dashboardData?.paymentLogs || [];

  const getStatusBadge = (status: string) => {
    const s = status.toLowerCase();
    if (s.includes('success') || s.includes('completed') || s.includes('approved')) {
      return <Badge className="bg-green-100 text-green-700 hover:bg-green-100">{status}</Badge>;
    }
    if (s.includes('failed') || s.includes('error') || s.includes('rejected') || s.includes('exception')) {
      return <Badge variant="destructive">{status}</Badge>;
    }
    if (s.includes('hmac') || s.includes('invalid')) {
      return <Badge className="bg-red-100 text-red-700 hover:bg-red-100">{status}</Badge>;
    }
    if (s.includes('pending') || s.includes('received') || s.includes('parsed')) {
      return <Badge className="bg-yellow-100 text-yellow-700 hover:bg-yellow-100">{status}</Badge>;
    }
    if (s.includes('ignored')) {
      return <Badge variant="secondary">{status}</Badge>;
    }
    return <Badge variant="outline">{status}</Badge>;
  };

  return (
    <PageTemplate
      title={t('Dashboard')}
      url="{{ route('dashboard') }}"
      actions={pageActions}
    >
      <div className="space-y-6">
        {/* Key Metrics */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('Total Companies')}</p>
                  <h3 className="mt-2 text-xl font-bold">{(stats.totalCompanies || 0).toLocaleString()}</h3>
                </div>
                <div className="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                  <Building2 className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('Total Users')}</p>
                  <h3 className="mt-2 text-xl font-bold">{(stats.totalUsers || 0).toLocaleString()}</h3>
                </div>
                <div className="rounded-full bg-green-100 p-3 dark:bg-green-900">
                  <Users className="h-5 w-5 text-green-600 dark:text-green-400" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('Active Subscriptions')}</p>
                  <h3 className="mt-2 text-xl font-bold">{(stats.totalSubscriptions || 0).toLocaleString()}</h3>
                </div>
                <div className="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                  <CreditCard className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('Monthly Growth')}</p>
                  <h3 className="mt-2 text-xl font-bold">+{stats.monthlyGrowth || 0}%</h3>
                </div>
                <div className="rounded-full bg-emerald-100 p-3 dark:bg-emerald-900">
                  <TrendingUp className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('Total Revenue')}</p>
                  <h3 className="mt-2 text-xl font-bold">{formatCurrency(stats.totalRevenue || 0)}</h3>
                </div>
                <div className="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                  <DollarSign className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* AI Usage Insights */}
        {dashboardData.aiUsage && (
          <AiUsageInsights data={dashboardData.aiUsage} />
        )}

        <div className="grid gap-6 lg:grid-cols-2">
          {/* Recent Activity */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Activity className="h-5 w-5" />
                <span className="text-lg font-semibold">{t('Recent Activity')}</span>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {recentActivity.map((activity) => (
                  <div key={activity.id} className="flex items-start gap-3 p-3 rounded-lg bg-muted/50">
                    <div className={`w-2 h-2 rounded-full mt-2 ${activity.status === 'success' ? 'bg-green-500' :
                      activity.status === 'warning' ? 'bg-yellow-500' : 'bg-red-500'
                      }`} />
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium">{activity.message}</p>
                      <p className="text-xs text-muted-foreground">{activity.time}</p>
                    </div>
                    <Badge variant={activity.status === 'success' ? 'default' : 'secondary'}>
                      {activity.type}
                    </Badge>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Top Plans */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <BarChart3 className="h-5 w-5" />
                <span className="text-lg font-semibold">{t('Top Performing Plans')}</span>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {topPlans.map((plan, index) => (
                  <div key={plan.name} className="flex items-center justify-between p-3 rounded-lg bg-muted/50">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                        <span className="text-sm font-bold text-primary">#{index + 1}</span>
                      </div>
                      <div>
                        <p className="font-medium">{plan.name}</p>
                        <p className="text-sm text-muted-foreground">{plan.subscribers} subscribers</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="font-semibold">{formatCurrency(plan.revenue)}</p>
                      <p className="text-xs text-muted-foreground">revenue</p>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Payment Transaction Log */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Receipt className="h-5 w-5" />
              <span className="text-lg font-semibold">{t('Payment Transaction Log')}</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {paymentLogs.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <Receipt className="h-10 w-10 mx-auto mb-3 opacity-30" />
                <p>{t('No payment transactions recorded yet')}</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left">
                      <th className="pb-3 pr-4 font-medium text-muted-foreground">{t('Company')}</th>
                      <th className="pb-3 pr-4 font-medium text-muted-foreground">{t('Email')}</th>
                      <th className="pb-3 pr-4 font-medium text-muted-foreground">{t('Plan')}</th>
                      <th className="pb-3 pr-4 font-medium text-muted-foreground">{t('Amount')}</th>
                      <th className="pb-3 pr-4 font-medium text-muted-foreground">{t('Status')}</th>
                      <th className="pb-3 pr-4 font-medium text-muted-foreground">{t('Payment ID')}</th>
                      <th className="pb-3 font-medium text-muted-foreground">{t('Time')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {paymentLogs.map((log) => (
                      <tr key={log.id} className="border-b last:border-0 hover:bg-muted/30 transition-colors">
                        <td className="py-3 pr-4 font-medium">{log.company_name}</td>
                        <td className="py-3 pr-4 text-muted-foreground text-xs">{log.payer_email}</td>
                        <td className="py-3 pr-4">
                          {log.plan_name !== '-' ? (
                            <Badge variant="outline">{log.plan_name}</Badge>
                          ) : '-'}
                        </td>
                        <td className="py-3 pr-4 font-medium">
                          {log.amount !== '-' ? `${log.currency} ${log.amount}` : '-'}
                        </td>
                        <td className="py-3 pr-4">{getStatusBadge(log.status)}</td>
                        <td className="py-3 pr-4">
                          <span className="font-mono text-xs bg-muted px-2 py-1 rounded">
                            {log.payment_id.length > 16 ? log.payment_id.substring(0, 16) + '...' : log.payment_id}
                          </span>
                        </td>
                        <td className="py-3 text-muted-foreground text-xs whitespace-nowrap">{log.time_ago}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Feature Overview */}
        <DashboardOverview userType="superadmin" stats={stats} />
      </div>
    </PageTemplate>
  );
}