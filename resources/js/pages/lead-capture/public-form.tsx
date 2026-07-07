import { useEffect, useRef } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';

const FIELD_LABELS: Record<string, string> = {
  name: 'Name',
  email: 'Email',
  phone: 'Phone',
  company: 'Company',
  position: 'Position',
  website: 'Website',
  address: 'Address',
  notes: 'Message',
};

const FIELD_PLACEHOLDERS: Record<string, string> = {
  name: 'Jane Doe',
  email: 'you@example.com',
  phone: '+1 555 000 1234',
  company: 'Acme Inc.',
  position: 'Marketing Manager',
  website: 'https://example.com',
  address: 'Street, City, Country',
  notes: 'How can we help you?',
};

const grecaptcha = () => (window as any).grecaptcha;

// Blend a hex color toward white/black to derive soft accents from the configured theme.
const shade = (hex: string, percent: number) => {
  const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '');
  if (!m) return hex;
  const mix = (c: number) =>
    Math.round(percent < 0 ? c * (1 + percent) : c + (255 - c) * percent);
  const to = (c: number) => c.toString(16).padStart(2, '0');
  return `#${to(mix(parseInt(m[1], 16)))}${to(mix(parseInt(m[2], 16)))}${to(mix(parseInt(m[3], 16)))}`;
};

export default function PublicForm() {
  const { form, recaptcha } = usePage().props as any;
  const recaptchaRef = useRef<HTMLDivElement>(null);
  const widgetId = useRef<number | null>(null);

  const initial: Record<string, string> = {};
  form.fields.forEach((f: any) => { initial[f.field] = ''; });
  const { data, setData, post, processing, errors, transform } = useForm<Record<string, string>>(initial);

  const theme = form.theme || {};
  const primary = theme.primary_color || '#2563eb';
  const background = theme.background_color || '#f3f4f6';

  // Load + render reCAPTCHA v2 widget when enabled.
  useEffect(() => {
    if (!recaptcha?.enabled || !recaptcha?.site_key) return;
    const renderWidget = () => {
      if (grecaptcha() && recaptchaRef.current && widgetId.current === null) {
        try {
          widgetId.current = grecaptcha().render(recaptchaRef.current, { sitekey: recaptcha.site_key });
        } catch { /* already rendered */ }
      }
    };
    if (!document.getElementById('recaptcha-api')) {
      const s = document.createElement('script');
      s.id = 'recaptcha-api';
      s.src = 'https://www.google.com/recaptcha/api.js?render=explicit';
      s.async = true;
      s.defer = true;
      s.onload = () => setTimeout(renderWidget, 300);
      document.head.appendChild(s);
    } else {
      setTimeout(renderWidget, 300);
    }
  }, [recaptcha]);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const token = recaptcha?.enabled && grecaptcha() && widgetId.current !== null
      ? grecaptcha().getResponse(widgetId.current)
      : '';
    transform((d) => ({ ...d, recaptcha_token: token }));
    post(form.action_url, {
      onError: () => {
        if (grecaptcha() && widgetId.current !== null) grecaptcha().reset(widgetId.current);
      },
    });
  };

  return (
    <div
      className="min-h-screen flex items-center justify-center p-4 sm:p-6"
      style={{
        background: `radial-gradient(1200px 600px at 50% -10%, ${shade(primary, 0.82)}, ${background} 60%)`,
      }}
    >
      <Head title={form.name} />
      <div
        className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5"
      >
        {/* Accent header bar reflecting the configured primary color */}
        <div
          className="h-2 w-full"
          style={{ background: `linear-gradient(90deg, ${primary}, ${shade(primary, 0.35)})` }}
        />

        <div className="p-8 sm:p-10">
          <div className="mb-6 text-center sm:text-left">
            {form.logo_url ? (
              <img
                src={form.logo_url}
                alt={form.company_name || form.name}
                className="mb-4 h-12 object-contain mx-auto sm:mx-0"
              />
            ) : form.company_name ? (
              <span
                className="mb-3 inline-block rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                style={{ backgroundColor: shade(primary, 0.88), color: shade(primary, -0.25) }}
              >
                {form.company_name}
              </span>
            ) : null}
            {form.logo_url && form.company_name && (
              <p className="text-sm font-medium text-gray-500">{form.company_name}</p>
            )}
            <h1 className="text-2xl font-bold tracking-tight text-gray-900">{form.name}</h1>
            {form.description && <p className="mt-2 text-sm leading-relaxed text-gray-600">{form.description}</p>}
          </div>

          <form onSubmit={submit} className="space-y-5">
            {form.fields.map((f: any) => {
              const label = FIELD_LABELS[f.field] || f.field;
              const required = f.field === 'name' || f.required;
              const isTextarea = f.field === 'notes' || f.field === 'address';
              const common = {
                id: f.field,
                value: data[f.field] || '',
                required,
                placeholder: FIELD_PLACEHOLDERS[f.field] || '',
                onChange: (e: any) => setData(f.field as any, e.target.value),
                className:
                  'mt-1.5 w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 transition focus:outline-none focus:ring-2 focus:ring-offset-0 ' +
                  (errors[f.field] ? 'border-red-400 focus:ring-red-300' : 'border-gray-300'),
                style: (errors[f.field] ? {} : { '--tw-ring-color': shade(primary, 0.35) }) as any,
              };
              return (
                <div key={f.field}>
                  <label htmlFor={f.field} className="block text-sm font-medium text-gray-700">
                    {label}{required && <span style={{ color: primary }}> *</span>}
                  </label>
                  {isTextarea
                    ? <textarea rows={4} {...common} />
                    : <input type={f.field === 'email' ? 'email' : 'text'} {...common} />}
                  {errors[f.field] && <p className="mt-1 text-xs text-red-500">{errors[f.field]}</p>}
                </div>
              );
            })}

            {recaptcha?.enabled && <div ref={recaptchaRef} className="my-1" />}
            {errors.recaptcha_token && <p className="text-xs text-red-500">{errors.recaptcha_token}</p>}

            <button
              type="submit"
              disabled={processing}
              className="flex w-full items-center justify-center gap-2 rounded-lg py-3 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
              style={{ backgroundColor: primary, '--tw-ring-color': shade(primary, 0.35) } as any}
            >
              {processing && (
                <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
              )}
              {processing ? 'Submitting...' : form.submit_button_text}
            </button>
          </form>

          {form.powered_by && (
            <p className="mt-8 text-center text-xs text-gray-400">Powered by {form.powered_by}</p>
          )}
        </div>
      </div>
    </div>
  );
}
