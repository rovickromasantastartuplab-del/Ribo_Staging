import { Head, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

// Blend a hex color toward white/black to derive soft accents from the configured theme.
const shade = (hex: string, percent: number) => {
  const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '');
  if (!m) return hex;
  const mix = (c: number) =>
    Math.round(percent < 0 ? c * (1 + percent) : c + (255 - c) * percent);
  const to = (c: number) => c.toString(16).padStart(2, '0');
  return `#${to(mix(parseInt(m[1], 16)))}${to(mix(parseInt(m[2], 16)))}${to(mix(parseInt(m[3], 16)))}`;
};

export default function ThankYou() {
  const { form } = usePage().props as any;
  const theme = form.theme || {};
  const primary = theme.primary_color || '#2563eb';
  const background = theme.background_color || '#f3f4f6';

  return (
    <div
      className="min-h-screen flex items-center justify-center p-4 sm:p-6"
      style={{
        background: `radial-gradient(1200px 600px at 50% -10%, ${shade(primary, 0.82)}, ${background} 60%)`,
      }}
    >
      <Head title={`${form.name} — Thank You`} />
      <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
        <div
          className="h-2 w-full"
          style={{ background: `linear-gradient(90deg, ${primary}, ${shade(primary, 0.35)})` }}
        />
        <div className="p-10 text-center">
          {form.logo_url && (
            <img src={form.logo_url} alt={form.company_name || form.name} className="mx-auto mb-4 h-12 object-contain" />
          )}
          <div
            className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full"
            style={{ backgroundColor: shade(primary, 0.85) }}
          >
            <CheckCircle2 className="h-9 w-9" style={{ color: primary }} />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-gray-900">Thank You!</h1>
          <p className="mt-2 text-sm leading-relaxed text-gray-600">{form.thank_you_message}</p>
          {form.powered_by && (
            <p className="mt-8 text-center text-xs text-gray-400">Powered by {form.powered_by}</p>
          )}
        </div>
      </div>
    </div>
  );
}
