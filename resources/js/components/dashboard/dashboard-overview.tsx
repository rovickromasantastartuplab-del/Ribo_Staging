import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Building2,
  CreditCard,
  BarChart3,
  MessageSquare,
  CalendarDays,
  Eye,
  TrendingUp,
  ArrowRight,
  Sparkles,
  Users,
  DollarSign,
  Target,
  Briefcase
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';

interface DashboardOverviewProps {
  userType: 'superadmin' | 'company';
  stats: any;
}

export function DashboardOverview({ userType, stats }: DashboardOverviewProps) {
  const { t } = useTranslation();

  const superAdminFeatures = [
    {
      title: t('Company Management'),
      description: t('Manage all registered companies and their subscriptions'),
      icon: Building2,
      color: 'blue',
      href: route('companies.index'),
      count: stats?.totalCompanies || 0
    },
    {
      title: t('Subscription Management'),
      description: t('Monitor and manage all system subscriptions'),
      icon: CreditCard,
      color: 'green',
      href: route('dashboard'),
      count: stats?.totalSubscriptions || 0
    },
    {
      title: t('Revenue Tracking'),
      description: t('Monitor total system revenue and growth'),
      icon: DollarSign,
      color: 'orange',
      href: route('dashboard'),
      count: `$${(stats?.totalRevenue || 0).toLocaleString()}`
    },
    {
      title: t('System Growth'),
      description: t('Monitor system performance and growth'),
      icon: TrendingUp,
      color: 'purple',
      href: route('dashboard'),
      count: `+${stats?.monthlyGrowth || 0}%`
    }
  ];

  const companyFeatures = [
    {
      title: t('Lead Management'),
      description: t('Track and manage your sales leads'),
      icon: Target,
      color: 'green',
      href: route('leads.index'),
      count: stats?.totalLeads || 0
    },
    {
      title: t('Sales Tracking'),
      description: t('Monitor your sales performance'),
      icon: TrendingUp,
      color: 'purple',
      href: route('sales-orders.index'),
      count: stats?.totalSales || 0
    },
    {
      title: t('Customer Base'),
      description: t('Manage your customer relationships'),
      icon: Building2,
      color: 'orange',
      href: route('accounts.index'),
      count: stats?.totalCustomers || 0
    },
    {
      title: t('Project Portfolio'),
      description: t('Track your active projects'),
      icon: Briefcase,
      color: 'blue',
      href: route('projects.index'),
      count: stats?.totalProjects || 0
    }
  ];

  const features = userType === 'superadmin' ? superAdminFeatures : companyFeatures;

  const getColorClasses = (color: string) => {
    const colors = {
      blue: 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400',
      green: 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400',
      purple: 'bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-400',
      orange: 'bg-orange-100 text-orange-600 dark:bg-orange-900 dark:text-orange-400',
    };
    return colors[color as keyof typeof colors] || colors.blue;
  };

  return (
    <Card className="border-2 border-dashed border-primary/20">
      <CardHeader className="text-center">
        <div className="flex items-center justify-center gap-2 mb-2">
          <Sparkles className="h-6 w-6 text-primary" />
          <CardTitle className="text-2xl">
            {t('Features')}
          </CardTitle>
        </div>
        <p className="text-muted-foreground">
          {userType === 'superadmin'
            ? t('Comprehensive system management and oversight tools')
            : t('Everything you need to manage your digital business presence')
          }
        </p>
      </CardHeader>
      <CardContent>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {features.map((feature) => {
            const IconComponent = feature.icon;
            return (
              <div key={feature.title} className="group relative">
                <Card className="h-full transition-all duration-200 hover:shadow-lg hover:scale-105 cursor-pointer">
                  <CardContent className="p-6">
                    <div className="flex items-start justify-between mb-4">
                      <div className={`rounded-full p-3 ${getColorClasses(feature.color)}`}>
                        <IconComponent className="h-6 w-6" />
                      </div>
                      <Badge variant="secondary" className="text-xs">
                        {feature.count}
                      </Badge>
                    </div>
                    <h3 className="font-semibold mb-2 group-hover:text-primary transition-colors">
                      {feature.title}
                    </h3>
                    <p className="text-sm text-muted-foreground mb-4">
                      {feature.description}
                    </p>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="w-full justify-between group-hover:bg-primary/10"
                      asChild
                    >
                      <Link href={feature.href}>
                        {t('Explore')}
                        <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                      </Link>
                    </Button>
                  </CardContent>
                </Card>
              </div>
            );
          })}
        </div>

        {/* Additional Info */}
        <div className="mt-8 text-center">
          <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 text-primary">
            <TrendingUp className="h-4 w-4" />
            <span className="text-sm font-medium">
              {userType === 'superadmin'
                ? t('System growing at {{growth}}% monthly', { growth: stats?.monthlyGrowth || 0 })
                : t('Your business growing at {{growth}}% monthly', { growth: stats?.monthlyGrowth || 0 })
              }
            </span>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}