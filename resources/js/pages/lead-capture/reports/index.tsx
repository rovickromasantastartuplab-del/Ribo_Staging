import { PageTemplate } from '@/components/page-template';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent } from '@/components/ui/card';
import { FileText, CheckCircle2, Layers, UserPlus, Copy } from 'lucide-react';

export default function LeadCaptureReports() {
  const { t } = useTranslation();
  const { stats, leadsPerForm = [], leadsPerCampaign = [] } = usePage().props as any;

  const cards = [
    { label: t('Total Forms'), value: stats.total_forms, icon: FileText, color: 'text-blue-600' },
    { label: t('Active Forms'), value: stats.active_forms, icon: CheckCircle2, color: 'text-green-600' },
    { label: t('Total Submissions'), value: stats.total_submissions, icon: Layers, color: 'text-indigo-600' },
    { label: t('New Leads'), value: stats.new_leads, icon: UserPlus, color: 'text-emerald-600' },
    { label: t('Duplicate Matches'), value: stats.duplicate_matches, icon: Copy, color: 'text-amber-600' },
  ];

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Lead Capture') },
    { title: t('Reports') },
  ];

  return (
    <PageTemplate title={t('Lead Capture Reports')} url="/lead-capture/reports" breadcrumbs={breadcrumbs}>
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        {cards.map((c) => (
          <Card key={c.label}>
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-500">{c.label}</p>
                  <p className="text-2xl font-bold">{c.value ?? 0}</p>
                </div>
                <c.icon className={`h-8 w-8 ${c.color}`} />
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid md:grid-cols-2 gap-4 mt-4">
        <Card>
          <CardContent className="p-4">
            <h3 className="font-semibold mb-3">{t('Leads per Form')}</h3>
            {leadsPerForm.length === 0 ? (
              <p className="text-sm text-gray-500">{t('No data yet.')}</p>
            ) : (
              <div className="space-y-2">
                {leadsPerForm.map((f: any) => (
                  <div key={f.name} className="flex justify-between text-sm border-b py-1">
                    <span>{f.name}</span>
                    <span className="text-gray-500">{f.total} ({f.new_leads} {t('new')})</span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <h3 className="font-semibold mb-3">{t('Leads per Campaign')}</h3>
            {leadsPerCampaign.length === 0 ? (
              <p className="text-sm text-gray-500">{t('No data yet.')}</p>
            ) : (
              <div className="space-y-2">
                {leadsPerCampaign.map((c: any) => (
                  <div key={c.name} className="flex justify-between text-sm border-b py-1">
                    <span>{c.name}</span>
                    <span className="text-gray-500">{c.total}</span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </PageTemplate>
  );
}
