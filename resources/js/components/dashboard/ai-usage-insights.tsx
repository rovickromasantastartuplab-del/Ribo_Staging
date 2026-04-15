import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
  BarChart3, 
  Activity, 
  Zap, 
  Target, 
  Coins, 
  Bot, 
  TrendingUp, 
  PieChart as PieChartIcon,
  Users as UsersIcon,
  Percent
} from 'lucide-react';
import { 
  AreaChart, 
  Area, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer, 
  PieChart, 
  Pie, 
  Cell, 
  Legend 
} from 'recharts';
import { useTranslation } from 'react-i18next';
import { formatCurrency } from '@/utils/currency';

interface AiUsageData {
  stats: {
    totalTokens: number;
    totalRequests: number;
    totalCost: number;
    successRate: number;
  };
  charts: {
    dailyTrends: Array<{
      date: string;
      displayDate: string;
      tokens: number;
      requests: number;
      cost: number;
    }>;
    modelDistribution: Array<{
      name: string;
      value: number;
      color: string;
    }>;
  };
  topCompanies: Array<{
    name: string;
    usage: string;
    cost: number;
  }>;
}

export function AiUsageInsights({ data }: { data: AiUsageData }) {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState('tokens');

  const stats = data?.stats || { totalTokens: 0, totalRequests: 0, totalCost: 0, successRate: 0 };
  const dailyTrends = data?.charts?.dailyTrends || [];
  const modelDistribution = data?.charts?.modelDistribution || [];
  const topCompanies = data?.topCompanies || [];

  const formatTokens = (val: number) => {
    if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
    if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
    return val.toString();
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Bot className="h-6 w-6 text-primary" />
        <h2 className="text-2xl font-bold tracking-tight">{t('AI Intelligence Hub')}</h2>
      </div>

      {/* KPI Row */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-muted-foreground">{t('Tokens Processed')}</p>
                <h3 className="mt-2 text-2xl font-bold">{formatTokens(stats.totalTokens)}</h3>
              </div>
              <div className="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                <Zap className="h-5 w-5 text-blue-600 dark:text-blue-400" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-muted-foreground">{t('Total Requests')}</p>
                <h3 className="mt-2 text-2xl font-bold">{stats.totalRequests.toLocaleString()}</h3>
              </div>
              <div className="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                <Activity className="h-5 w-5 text-purple-600 dark:text-purple-400" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-muted-foreground">{t('Success Rate')}</p>
                <h3 className="mt-2 text-2xl font-bold">{stats.successRate}%</h3>
              </div>
              <div className="rounded-full bg-emerald-100 p-3 dark:bg-emerald-900">
                <Percent className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-muted-foreground">{t('Estimated Cost')}</p>
                <h3 className="mt-2 text-2xl font-bold">{formatCurrency(stats.totalCost)}</h3>
              </div>
              <div className="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                <Coins className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Main Chart Section */}
      <Card className="col-span-4">
        <CardHeader className="flex flex-row items-center justify-between space-y-0">
          <div>
            <CardTitle className="text-base font-semibold">
              {t('Usage Trends')}
            </CardTitle>
            <CardDescription>
              {t('AI resource consumption over the last 30 days')}
            </CardDescription>
          </div>
          <Tabs value={activeTab} onValueChange={setActiveTab} className="w-[400px] flex justify-end">
            <TabsList>
              <TabsTrigger value="tokens">{t('Tokens')}</TabsTrigger>
              <TabsTrigger value="requests">{t('Requests')}</TabsTrigger>
              <TabsTrigger value="cost">{t('Cost')}</TabsTrigger>
            </TabsList>
          </Tabs>
        </CardHeader>
        <CardContent className="pl-2">
          <div className="h-[350px] w-full">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={dailyTrends} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorUsage" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.1}/>
                    <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <XAxis 
                  dataKey="displayDate" 
                  stroke="#888888" 
                  fontSize={12} 
                  tickLine={false} 
                  axisLine={false} 
                />
                <YAxis 
                  stroke="#888888" 
                  fontSize={12} 
                  tickLine={false} 
                  axisLine={false} 
                  tickFormatter={(value) => activeTab === 'cost' ? `$${value}` : formatTokens(value)}
                />
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--muted))" />
                <Tooltip 
                  contentStyle={{ backgroundColor: 'hsl(var(--background))', borderColor: 'hsl(var(--border))', borderRadius: '8px' }}
                  itemStyle={{ color: 'hsl(var(--primary))' }}
                />
                <Area 
                  type="monotone" 
                  dataKey={activeTab} 
                  stroke="hsl(var(--primary))" 
                  fillOpacity={1} 
                  fill="url(#colorUsage)" 
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </CardContent>
      </Card>

      {/* Bottom Row: Model Distribution & Top Users */}
      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <PieChartIcon className="h-5 w-5" />
              {t('Model Distribution')}
            </CardTitle>
            <CardDescription>{t('Usage share across different AI models')}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-[300px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={modelDistribution}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={80}
                    paddingAngle={5}
                    dataKey="value"
                  >
                    {modelDistribution.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <UsersIcon className="h-5 w-5" />
              {t('Top 5 Companies by Usage')}
            </CardTitle>
            <CardDescription>{t('Highest AI consumers this month')}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {topCompanies.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground italic">
                  {t('No usage data recorded yet')}
                </div>
              ) : (
                topCompanies.map((company, index) => (
                  <div key={company.name} className="flex items-center justify-between p-4 rounded-lg bg-muted/50 transition-all hover:bg-muted">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-sm">
                        #{index + 1}
                      </div>
                      <div>
                        <p className="font-semibold text-sm">{company.name}</p>
                        <p className="text-xs text-muted-foreground">{company.usage}</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="font-mono text-sm">{formatCurrency(company.cost)}</p>
                      <p className="text-[10px] uppercase text-muted-foreground font-bold">{t('estimated cost')}</p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
