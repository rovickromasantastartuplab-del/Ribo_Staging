<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\UserEmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $languages = json_decode(file_get_contents(resource_path('lang/language.json')), true);
        $langCodes = collect($languages)->pluck('code')->toArray();

        $templates = [
            // User Created
            [
                'name' => 'User Created',
                'from' => 'Support Team',
                'translations' => [
                    'en' => [
                        'subject' => 'Welcome to our platform - {user_name}',
                        'content' => '<p>Hello {user_name},</p><p>Your account has been successfully created.</p><p><strong>Login Details:</strong></p><ul><li>Website: {app_url}</li><li>Email: {user_email}</li><li>Password: {user_password}</li><li>Account Type: {user_type}</li></ul><p>Please keep this information secure.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Bienvenido a nuestra plataforma - {user_name}',
                        'content' => '<p>Hola {user_name},</p><p>Su cuenta ha sido creada exitosamente.</p><p><strong>Detalles de acceso:</strong></p><ul><li>Sitio web: {app_url}</li><li>Email: {user_email}</li><li>Contraseña: {user_password}</li><li>Tipo de cuenta: {user_type}</li></ul><p>Por favor mantenga esta información segura.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'مرحباً بك في منصتنا - {user_name}',
                        'content' => '<p>مرحباً {user_name}،</p><p>تم إنشاء حسابك بنجاح.</p><p><strong>تفاصيل تسجيل الدخول:</strong></p><ul><li>الموقع: {app_url}</li><li>البريد الإلكتروني: {user_email}</li><li>كلمة المرور: {user_password}</li><li>نوع الحساب: {user_type}</li></ul><p>يرجى الاحتفاظ بهذه المعلومات آمنة.</p><p style="text-align: right;">مع أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Velkommen til vores platform - {user_name}',
                        'content' => '<p>Hej {user_name},</p><p>Din konto er blevet oprettet med succes.</p><p><strong>Login detaljer:</strong></p><ul><li>Hjemmeside: {app_url}</li><li>Email: {user_email}</li><li>Adgangskode: {user_password}</li><li>Kontotype: {user_type}</li></ul><p>Hold venligst disse oplysninger sikre.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Willkommen auf unserer Plattform - {user_name}',
                        'content' => '<p>Hallo {user_name},</p><p>Ihr Konto wurde erfolgreich erstellt.</p><p><strong>Anmeldedaten:</strong></p><ul><li>Website: {app_url}</li><li>E-Mail: {user_email}</li><li>Passwort: {user_password}</li><li>Kontotyp: {user_type}</li></ul><p>Bitte bewahren Sie diese Informationen sicher auf.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Bienvenue sur notre plateforme - {user_name}',
                        'content' => '<p>Bonjour {user_name},</p><p>Votre compte a été créé avec succès.</p><p><strong>Détails de connexion:</strong></p><ul><li>Site web: {app_url}</li><li>Email: {user_email}</li><li>Mot de passe: {user_password}</li><li>Type de compte: {user_type}</li></ul><p>Veuillez garder ces informations en sécurité.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'ברוכים הבאים לפלטפורמה שלנו - {user_name}',
                        'content' => '<p>שלום {user_name},</p><p>החשבון שלך נוצר בהצלחה.</p><p><strong>פרטי התחברות:</strong></p><ul><li>אתר: {app_url}</li><li>אימייל: {user_email}</li><li>סיסמה: {user_password}</li><li>סוג חשבון: {user_type}</li></ul><p>אנא שמרו על המידע הזה בבטחה.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Benvenuto sulla nostra piattaforma - {user_name}',
                        'content' => '<p>Ciao {user_name},</p><p>Il tuo account è stato creato con successo.</p><p><strong>Dettagli di accesso:</strong></p><ul><li>Sito web: {app_url}</li><li>Email: {user_email}</li><li>Password: {user_password}</li><li>Tipo di account: {user_type}</li></ul><p>Si prega di mantenere queste informazioni al sicuro.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => 'プラットフォームへようこそ - {user_name}',
                        'content' => '<p>こんにちは {user_name}さん、</p><p>アカウントが正常に作成されました。</p><p><strong>ログイン詳細:</strong></p><ul><li>ウェブサイト: {app_url}</li><li>メール: {user_email}</li><li>パスワード: {user_password}</li><li>アカウントタイプ: {user_type}</li></ul><p>この情報を安全に保管してください。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Welkom op ons platform - {user_name}',
                        'content' => '<p>Hallo {user_name},</p><p>Uw account is succesvol aangemaakt.</p><p><strong>Inloggegevens:</strong></p><ul><li>Website: {app_url}</li><li>Email: {user_email}</li><li>Wachtwoord: {user_password}</li><li>Accounttype: {user_type}</li></ul><p>Houd deze informatie veilig.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Witamy na naszej platformie - {user_name}',
                        'content' => '<p>Witaj {user_name},</p><p>Twoje konto zostało pomyślnie utworzone.</p><p><strong>Szczegóły logowania:</strong></p><ul><li>Strona internetowa: {app_url}</li><li>Email: {user_email}</li><li>Hasło: {user_password}</li><li>Typ konta: {user_type}</li></ul><p>Prosimy o bezpieczne przechowywanie tych informacji.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Bem-vindo à nossa plataforma - {user_name}',
                        'content' => '<p>Olá {user_name},</p><p>A sua conta foi criada com sucesso.</p><p><strong>Detalhes de login:</strong></p><ul><li>Website: {app_url}</li><li>Email: {user_email}</li><li>Palavra-passe: {user_password}</li><li>Tipo de conta: {user_type}</li></ul><p>Por favor, mantenha esta informação segura.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Bem-vindo à nossa plataforma - {user_name}',
                        'content' => '<p>Olá {user_name},</p><p>Sua conta foi criada com sucesso.</p><p><strong>Detalhes de login:</strong></p><ul><li>Website: {app_url}</li><li>Email: {user_email}</li><li>Senha: {user_password}</li><li>Tipo de conta: {user_type}</li></ul><p>Por favor, mantenha essas informações seguras.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Добро пожаловать на нашу платформу - {user_name}',
                        'content' => '<p>Привет {user_name},</p><p>Ваш аккаунт был успешно создан.</p><p><strong>Данные для входа:</strong></p><ul><li>Веб-сайт: {app_url}</li><li>Email: {user_email}</li><li>Пароль: {user_password}</li><li>Тип аккаунта: {user_type}</li></ul><p>Пожалуйста, храните эту информацию в безопасности.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Platformumuza hoş geldiniz - {user_name}',
                        'content' => '<p>Merhaba {user_name},</p><p>Hesabınız başarıyla oluşturuldu.</p><p><strong>Giriş Detayları:</strong></p><ul><li>Website: {app_url}</li><li>Email: {user_email}</li><li>Şifre: {user_password}</li><li>Hesap Türü: {user_type}</li></ul><p>Lütfen bu bilgileri güvenli tutun.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '欢迎来到我们的平台 - {user_name}',
                        'content' => '<p>你好 {user_name}，</p><p>您的账户已成功创建。</p><p><strong>登录详情：</strong></p><ul><li>网站：{app_url}</li><li>邮箱：{user_email}</li><li>密码：{user_password}</li><li>账户类型：{user_type}</li></ul><p>请妥善保管这些信息。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ]
                ]
            ],
            // Lead Assigned
            [
                'name' => 'Lead Assigned',
                'from' => 'Sales Team',
                'translations' => [
                    'en' => [
                        'subject' => 'New Lead Assigned to You - {lead_name}',
                        'content' => '<p>Hello {assigned_user_name},</p><p>A new lead has been assigned to you. Please review the details below and follow up accordingly.</p><p><strong>Lead Details:</strong></p><ul><li>Name: {lead_name}</li><li>Email: {lead_email}</li><li>Phone: {lead_phone}</li><li>Company: {lead_company}</li></ul><p>Please contact this lead as soon as possible to maximize conversion opportunities.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Nuevo Lead Asignado - {lead_name}',
                        'content' => '<p>Hola {assigned_user_name},</p><p>Se le ha asignado un nuevo lead. Por favor revise los detalles a continuación y haga el seguimiento correspondiente.</p><p><strong>Detalles del Lead:</strong></p><ul><li>Nombre: {lead_name}</li><li>Email: {lead_email}</li><li>Teléfono: {lead_phone}</li><li>Empresa: {lead_company}</li></ul><p>Por favor contacte a este lead lo antes posible para maximizar las oportunidades de conversión.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'تم تعيين عميل محتمل جديد - {lead_name}',
                        'content' => '<p>مرحباً {assigned_user_name}،</p><p>تم تعيين عميل محتمل جديد لك. يرجى مراجعة التفاصيل أدناه والمتابعة وفقاً لذلك.</p><p><strong>تفاصيل العميل المحتمل:</strong></p><ul><li>الاسم: {lead_name}</li><li>البريد الإلكتروني: {lead_email}</li><li>الهاتف: {lead_phone}</li><li>الشركة: {lead_company}</li></ul><p>يرجى التواصل مع هذا العميل المحتمل في أقرب وقت ممكن لتعظيم فرص التحويل.</p><p style="text-align: right;">مع أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Nyt Lead Tildelt - {lead_name}',
                        'content' => '<p>Hej {assigned_user_name},</p><p>Et nyt lead er blevet tildelt til dig. Gennemgå venligst detaljerne nedenfor og følg op i overensstemmelse hermed.</p><p><strong>Lead Detaljer:</strong></p><ul><li>Navn: {lead_name}</li><li>Email: {lead_email}</li><li>Telefon: {lead_phone}</li><li>Virksomhed: {lead_company}</li></ul><p>Kontakt venligst dette lead så hurtigt som muligt for at maksimere konverteringsmuligheder.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Neuer Lead zugewiesen - {lead_name}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Ein neuer Lead wurde Ihnen zugewiesen. Bitte überprüfen Sie die Details unten und folgen Sie entsprechend nach.</p><p><strong>Lead Details:</strong></p><ul><li>Name: {lead_name}</li><li>E-Mail: {lead_email}</li><li>Telefon: {lead_phone}</li><li>Unternehmen: {lead_company}</li></ul><p>Bitte kontaktieren Sie diesen Lead so schnell wie möglich, um die Konvertierungsmöglichkeiten zu maximieren.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Nouveau Lead Assigné - {lead_name}',
                        'content' => '<p>Bonjour {assigned_user_name},</p><p>Un nouveau lead vous a été assigné. Veuillez examiner les détails ci-dessous et faire le suivi en conséquence.</p><p><strong>Détails du Lead:</strong></p><ul><li>Nom: {lead_name}</li><li>Email: {lead_email}</li><li>Téléphone: {lead_phone}</li><li>Entreprise: {lead_company}</li></ul><p>Veuillez contacter ce lead dès que possible pour maximiser les opportunités de conversion.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'ליד חדש הוקצה - {lead_name}',
                        'content' => '<p>שלום {assigned_user_name},</p><p>ליד חדש הוקצה לך. אנא עיין בפרטים להלן ועשה מעקב בהתאם.</p><p><strong>פרטי הליד:</strong></p><ul><li>שם: {lead_name}</li><li>אימייל: {lead_email}</li><li>טלפון: {lead_phone}</li><li>חברה: {lead_company}</li></ul><p>אנא צור קשר עם הליד הזה בהקדם האפשרי כדי למקסם את הזדמנויות ההמרה.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Nuovo Lead Assegnato - {lead_name}',
                        'content' => '<p>Ciao {assigned_user_name},</p><p>Un nuovo lead ti è stato assegnato. Si prega di rivedere i dettagli qui sotto e seguire di conseguenza.</p><p><strong>Dettagli Lead:</strong></p><ul><li>Nome: {lead_name}</li><li>Email: {lead_email}</li><li>Telefono: {lead_phone}</li><li>Azienda: {lead_company}</li></ul><p>Si prega di contattare questo lead il prima possibile per massimizzare le opportunità di conversione.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => '新しいリードが割り当てられました - {lead_name}',
                        'content' => '<p>こんにちは {assigned_user_name}さん、</p><p>新しいリードがあなたに割り当てられました。以下の詳細を確認し、適切にフォローアップしてください。</p><p><strong>リード詳細:</strong></p><ul><li>名前: {lead_name}</li><li>メール: {lead_email}</li><li>電話: {lead_phone}</li><li>会社: {lead_company}</li></ul><p>コンバージョンの機会を最大化するために、できるだけ早くこのリードに連絡してください。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Nieuwe Lead Toegewezen - {lead_name}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Een nieuwe lead is aan je toegewezen. Bekijk de details hieronder en volg dienovereenkomstig op.</p><p><strong>Lead Details:</strong></p><ul><li>Naam: {lead_name}</li><li>Email: {lead_email}</li><li>Telefoon: {lead_phone}</li><li>Bedrijf: {lead_company}</li></ul><p>Neem zo snel mogelijk contact op met deze lead om conversiekansen te maximaliseren.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Nowy Lead Przypisany - {lead_name}',
                        'content' => '<p>Witaj {assigned_user_name},</p><p>Nowy lead został Ci przypisany. Przejrzyj szczegóły poniżej i podejmij odpowiednie działania.</p><p><strong>Szczegóły Lead:</strong></p><ul><li>Nazwa: {lead_name}</li><li>Email: {lead_email}</li><li>Telefon: {lead_phone}</li><li>Firma: {lead_company}</li></ul><p>Skontaktuj się z tym leadem jak najszybciej, aby zmaksymalizować możliwości konwersji.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Novo Lead Atribuído - {lead_name}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>Um novo lead foi atribuído a si. Por favor, reveja os detalhes abaixo e faça o seguimento em conformidade.</p><p><strong>Detalhes do Lead:</strong></p><ul><li>Nome: {lead_name}</li><li>Email: {lead_email}</li><li>Telefone: {lead_phone}</li><li>Empresa: {lead_company}</li></ul><p>Por favor, contacte este lead o mais rapidamente possível para maximizar as oportunidades de conversão.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Novo Lead Atribuído - {lead_name}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>Um novo lead foi atribuído a você. Por favor, revise os detalhes abaixo e faça o acompanhamento adequadamente.</p><p><strong>Detalhes do Lead:</strong></p><ul><li>Nome: {lead_name}</li><li>Email: {lead_email}</li><li>Telefone: {lead_phone}</li><li>Empresa: {lead_company}</li></ul><p>Por favor, entre em contato com este lead o mais rápido possível para maximizar as oportunidades de conversão.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Новый лид назначен - {lead_name}',
                        'content' => '<p>Привет {assigned_user_name},</p><p>Вам назначен новый лид. Пожалуйста, просмотрите детали ниже и проведите соответствующие действия.</p><p><strong>Детали лида:</strong></p><ul><li>Имя: {lead_name}</li><li>Email: {lead_email}</li><li>Телефон: {lead_phone}</li><li>Компания: {lead_company}</li></ul><p>Пожалуйста, свяжитесь с этим лидом как можно скорее, чтобы максимизировать возможности конверсии.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Yeni Müşteri Adayı Atandı - {lead_name}',
                        'content' => '<p>Merhaba {assigned_user_name},</p><p>Size yeni bir müşteri adayı atandı. Lütfen aşağıdaki detayları inceleyin ve buna göre takip edin.</p><p><strong>Müşteri Adayı Detayları:</strong></p><ul><li>Ad: {lead_name}</li><li>Email: {lead_email}</li><li>Telefon: {lead_phone}</li><li>Şirket: {lead_company}</li></ul><p>Dönüşüm fırsatlarını maksimize etmek için lütfen bu müşteri adayıyla mümkün olan en kısa sürede iletişime geçin.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '新的潜在客户已分配 - {lead_name}',
                        'content' => '<p>你好 {assigned_user_name}，</p><p>一个新的潜在客户已分配给您。请查看以下详细信息并相应进行跟进。</p><p><strong>潜在客户详情：</strong></p><ul><li>姓名：{lead_name}</li><li>邮箱：{lead_email}</li><li>电话：{lead_phone}</li><li>公司：{lead_company}</li></ul><p>请尽快与这个潜在客户联系，以最大化转化机会。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ]
                ]
            ],
            // Lead Moved
            [
                'name' => 'Lead Moved',
                'from' => 'Sales Team',
                'translations' => [
                    'en' => [
                        'subject' => 'Lead Moved - {lead_name}',
                        'content' => '<p>Hello {assigned_user_name},</p><p>The lead <strong>{lead_name}</strong> has been moved from <strong>{old_lead_stage}</strong> to <strong>{new_lead_stage}</strong>. Please review the details below and follow up accordingly.</p><p><strong>Lead Details:</strong></p><ul><li>Name: {lead_name}</li><li>Email: {lead_email}</li><li>Phone: {lead_phone}</li><li>Company: {lead_company}</li></ul><p>Thank you for your continued hard work and dedication.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Lead Movido - {lead_name}',
                        'content' => '<p>Hola {assigned_user_name},</p><p>El lead <strong>{lead_name}</strong> ha sido movido de <strong>{old_lead_stage}</strong> a <strong>{new_lead_stage}</strong>. Por favor, revisa los detalles a continuación y haz el seguimiento correspondiente.</p><p><strong>Detalles del Lead:</strong></p><ul><li>Nombre: {lead_name}</li><li>Correo electrónico: {lead_email}</li><li>Teléfono: {lead_phone}</li><li>Empresa: {lead_company}</li></ul><p>Gracias por tu continuo esfuerzo y dedicación.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'تم نقل العميل المحتمل - {lead_name}',
                        'content' => '<p>مرحباً {assigned_user_name}،</p><p>تم نقل العميل المحتمل <strong>{lead_name}</strong> من <strong>{old_lead_stage}</strong> إلى <strong>{new_lead_stage}</strong>. يرجى مراجعة التفاصيل أدناه والمتابعة وفقاً لذلك.</p><p><strong>تفاصيل العميل المحتمل:</strong></p><ul><li>الاسم: {lead_name}</li><li>البريد الإلكتروني: {lead_email}</li><li>الهاتف: {lead_phone}</li><li>الشركة: {lead_company}</li></ul><p>شكراً لك على جهدك وتفانيك المستمر.</p><p style="text-align: right;">مع أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Lead Flyttet - {lead_name}',
                        'content' => '<p>Hej {assigned_user_name},</p><p>Lead <strong>{lead_name}</strong> er blevet flyttet fra <strong>{old_lead_stage}</strong> til <strong>{new_lead_stage}</strong>. Gennemgå venligst detaljerne nedenfor og følg op i overensstemmelse hermed.</p><p><strong>Lead Detaljer:</strong></p><ul><li>Navn: {lead_name}</li><li>Email: {lead_email}</li><li>Telefon: {lead_phone}</li><li>Virksomhed: {lead_company}</li></ul><p>Tak for dit fortsatte hårde arbejde og dedikation.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Lead verschoben - {lead_name}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Der Lead <strong>{lead_name}</strong> wurde von <strong>{old_lead_stage}</strong> zu <strong>{new_lead_stage}</strong> verschoben. Bitte überprüfen Sie die Details unten und folgen Sie entsprechend nach.</p><p><strong>Lead Details:</strong></p><ul><li>Name: {lead_name}</li><li>E-Mail: {lead_email}</li><li>Telefon: {lead_phone}</li><li>Unternehmen: {lead_company}</li></ul><p>Vielen Dank für Ihre kontinuierliche harte Arbeit und Ihr Engagement.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Lead Déplacé - {lead_name}',
                        'content' => '<p>Bonjour {assigned_user_name},</p><p>Le lead <strong>{lead_name}</strong> a été déplacé de <strong>{old_lead_stage}</strong> vers <strong>{new_lead_stage}</strong>. Veuillez examiner les détails ci-dessous et faire le suivi en conséquence.</p><p><strong>Détails du Lead:</strong></p><ul><li>Nom: {lead_name}</li><li>Email: {lead_email}</li><li>Téléphone: {lead_phone}</li><li>Entreprise: {lead_company}</li></ul><p>Merci pour votre travail acharné et votre dévouement continus.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'ליד הועבר - {lead_name}',
                        'content' => '<p>שלום {assigned_user_name},</p><p>הליד <strong>{lead_name}</strong> הועבר מ-<strong>{old_lead_stage}</strong> ל-<strong>{new_lead_stage}</strong>. אנא עיין בפרטים להלן ועשה מעקב בהתאם.</p><p><strong>פרטי הליד:</strong></p><ul><li>שם: {lead_name}</li><li>אימייל: {lead_email}</li><li>טלפון: {lead_phone}</li><li>חברה: {lead_company}</li></ul><p>תודה על העבודה הקשה והמסירות המתמשכת.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Lead Spostato - {lead_name}',
                        'content' => '<p>Ciao {assigned_user_name},</p><p>Il lead <strong>{lead_name}</strong> è stato spostato da <strong>{old_lead_stage}</strong> a <strong>{new_lead_stage}</strong>. Si prega di rivedere i dettagli qui sotto e seguire di conseguenza.</p><p><strong>Dettagli Lead:</strong></p><ul><li>Nome: {lead_name}</li><li>Email: {lead_email}</li><li>Telefono: {lead_phone}</li><li>Azienda: {lead_company}</li></ul><p>Grazie per il tuo continuo duro lavoro e dedizione.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => 'リードが移動されました - {lead_name}',
                        'content' => '<p>こんにちは {assigned_user_name}さん、</p><p>リード <strong>{lead_name}</strong> が <strong>{old_lead_stage}</strong> から <strong>{new_lead_stage}</strong> に移動されました。以下の詳細を確認し、適切にフォローアップしてください。</p><p><strong>リード詳細:</strong></p><ul><li>名前: {lead_name}</li><li>メール: {lead_email}</li><li>電話: {lead_phone}</li><li>会社: {lead_company}</li></ul><p>継続的な努力と献身に感謝します。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Lead Verplaatst - {lead_name}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>De lead <strong>{lead_name}</strong> is verplaatst van <strong>{old_lead_stage}</strong> naar <strong>{new_lead_stage}</strong>. Bekijk de details hieronder en volg dienovereenkomstig op.</p><p><strong>Lead Details:</strong></p><ul><li>Naam: {lead_name}</li><li>Email: {lead_email}</li><li>Telefoon: {lead_phone}</li><li>Bedrijf: {lead_company}</li></ul><p>Bedankt voor je voortdurende harde werk en toewijding.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Lead Przeniesiony - {lead_name}',
                        'content' => '<p>Witaj {assigned_user_name},</p><p>Lead <strong>{lead_name}</strong> został przeniesiony z <strong>{old_lead_stage}</strong> do <strong>{new_lead_stage}</strong>. Przejrzyj szczegóły poniżej i podejmij odpowiednie działania.</p><p><strong>Szczegóły Lead:</strong></p><ul><li>Nazwa: {lead_name}</li><li>Email: {lead_email}</li><li>Telefon: {lead_phone}</li><li>Firma: {lead_company}</li></ul><p>Dziękuję za Twoją ciągłą ciężką pracę i zaangażowanie.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Lead Movido - {lead_name}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>O lead <strong>{lead_name}</strong> foi movido de <strong>{old_lead_stage}</strong> para <strong>{new_lead_stage}</strong>. Por favor, reveja os detalhes abaixo e faça o seguimento em conformidade.</p><p><strong>Detalhes do Lead:</strong></p><ul><li>Nome: {lead_name}</li><li>Email: {lead_email}</li><li>Telefone: {lead_phone}</li><li>Empresa: {lead_company}</li></ul><p>Obrigado pelo seu trabalho árduo e dedicação contínuos.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Lead Movido - {lead_name}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>O lead <strong>{lead_name}</strong> foi movido de <strong>{old_lead_stage}</strong> para <strong>{new_lead_stage}</strong>. Por favor, revise os detalhes abaixo e faça o acompanhamento adequadamente.</p><p><strong>Detalhes do Lead:</strong></p><ul><li>Nome: {lead_name}</li><li>Email: {lead_email}</li><li>Telefone: {lead_phone}</li><li>Empresa: {lead_company}</li></ul><p>Obrigado pelo seu trabalho árduo e dedicação contínuos.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Лид перемещен - {lead_name}',
                        'content' => '<p>Привет {assigned_user_name},</p><p>Лид <strong>{lead_name}</strong> был перемещен с <strong>{old_lead_stage}</strong> на <strong>{new_lead_stage}</strong>. Пожалуйста, просмотрите детали ниже и проведите соответствующие действия.</p><p><strong>Детали лида:</strong></p><ul><li>Имя: {lead_name}</li><li>Email: {lead_email}</li><li>Телефон: {lead_phone}</li><li>Компания: {lead_company}</li></ul><p>Спасибо за вашу постоянную усердную работу и преданность.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Müşteri Adayı Taşındı - {lead_name}',
                        'content' => '<p>Merhaba {assigned_user_name},</p><p>Müşteri adayı <strong>{lead_name}</strong>, <strong>{old_lead_stage}</strong> aşamasından <strong>{new_lead_stage}</strong> aşamasına taşındı. Lütfen aşağıdaki detayları inceleyin ve buna göre takip edin.</p><p><strong>Müşteri Adayı Detayları:</strong></p><ul><li>Ad: {lead_name}</li><li>Email: {lead_email}</li><li>Telefon: {lead_phone}</li><li>Şirket: {lead_company}</li></ul><p>Sürekli sert çalışmanız ve bağlılığınız için teşekkür ederiz.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '潜在客户已移动 - {lead_name}',
                        'content' => '<p>你好 {assigned_user_name}，</p><p>潜在客户 <strong>{lead_name}</strong> 已从 <strong>{old_lead_stage}</strong> 移动到 <strong>{new_lead_stage}</strong>。请查看以下详细信息并相应进行跟进。</p><p><strong>潜在客户详情：</strong></p><ul><li>姓名：{lead_name}</li><li>邮箱：{lead_email}</li><li>电话：{lead_phone}</li><li>公司：{lead_company}</li></ul><p>感谢您的持续努力和奉献。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ]
                ]
            ],
            // Quote Created
            [
                'name' => 'Quote Created',
                'from' => 'Sales Team',
                'translations' => [
                    'en' => [
                        'subject' => 'New Quote Created - {quote_name}',
                        'content' => '<p>Hello {billing_contact_name},</p><p>A new quote has been created for you. Please review the details below.</p><p><strong>Quote Details:</strong></p><ul><li>Quote Number: {quote_number}</li><li>Quote Name: {quote_name}</li><li>Account: {account_name}</li><li>Total Amount: {quote_total}</li><li>Valid Until: {quote_valid_until}</li><li>Status: {quote_status}</li></ul><p><strong>Assigned Sales Representative:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Please contact your sales representative if you have any questions about this quote.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Nueva Cotización Creada - {quote_name}',
                        'content' => '<p>Hola {billing_contact_name},</p><p>Se ha creado una nueva cotización para usted. Por favor revise los detalles a continuación.</p><p><strong>Detalles de la Cotización:</strong></p><ul><li>Número de Cotización: {quote_number}</li><li>Nombre de Cotización: {quote_name}</li><li>Cuenta: {account_name}</li><li>Monto Total: {quote_total}</li><li>Válida Hasta: {quote_valid_until}</li><li>Estado: {quote_status}</li></ul><p><strong>Representante de Ventas Asignado:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Por favor contacte a su representante de ventas si tiene alguna pregunta sobre esta cotización.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'تم إنشاء عرض أسعار جديد - {quote_name}',
                        'content' => '<p>مرحباً {billing_contact_name}،</p><p>تم إنشاء عرض أسعار جديد لك. يرجى مراجعة التفاصيل أدناه.</p><p><strong>تفاصيل عرض الأسعار:</strong></p><ul><li>رقم عرض الأسعار: {quote_number}</li><li>اسم عرض الأسعار: {quote_name}</li><li>الحساب: {account_name}</li><li>المبلغ الإجمالي: {quote_total}</li><li>صالح حتى: {quote_valid_until}</li><li>الحالة: {quote_status}</li></ul><p><strong>مندوب المبيعات المعين:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>يرجى التواصل مع مندوب المبيعات إذا كان لديك أي أسئلة حول هذا العرض.</p><p style="text-align: right;">مع أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Nyt Tilbud Oprettet - {quote_name}',
                        'content' => '<p>Hej {billing_contact_name},</p><p>Et nyt tilbud er blevet oprettet til dig. Gennemgå venligst detaljerne nedenfor.</p><p><strong>Tilbud Detaljer:</strong></p><ul><li>Tilbudsnummer: {quote_number}</li><li>Tilbudsnavn: {quote_name}</li><li>Konto: {account_name}</li><li>Samlet beløb: {quote_total}</li><li>Gyldig indtil: {quote_valid_until}</li><li>Status: {quote_status}</li></ul><p><strong>Tildelt Salgsrepræsentant:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Kontakt venligst din salgsrepræsentant, hvis du har spørgsmål om dette tilbud.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Neues Angebot erstellt - {quote_name}',
                        'content' => '<p>Hallo {billing_contact_name},</p><p>Ein neues Angebot wurde für Sie erstellt. Bitte überprüfen Sie die Details unten.</p><p><strong>Angebot Details:</strong></p><ul><li>Angebotsnummer: {quote_number}</li><li>Angebotsname: {quote_name}</li><li>Konto: {account_name}</li><li>Gesamtbetrag: {quote_total}</li><li>Gültig bis: {quote_valid_until}</li><li>Status: {quote_status}</li></ul><p><strong>Zugewiesener Vertriebsmitarbeiter:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Bitte kontaktieren Sie Ihren Vertriebsmitarbeiter, wenn Sie Fragen zu diesem Angebot haben.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Nouveau Devis Créé - {quote_name}',
                        'content' => '<p>Bonjour {billing_contact_name},</p><p>Un nouveau devis a été créé pour vous. Veuillez examiner les détails ci-dessous.</p><p><strong>Détails du Devis:</strong></p><ul><li>Numéro de devis: {quote_number}</li><li>Nom du devis: {quote_name}</li><li>Compte: {account_name}</li><li>Montant total: {quote_total}</li><li>Valide jusqu\'au: {quote_valid_until}</li><li>Statut: {quote_status}</li></ul><p><strong>Représentant Commercial Assigné:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Veuillez contacter votre représentant commercial si vous avez des questions sur ce devis.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'הצעת מחיר חדשה נוצרה - {quote_name}',
                        'content' => '<p>שלום {billing_contact_name},</p><p>הצעת מחיר חדשה נוצרה עבורך. אנא עיין בפרטים להלן.</p><p><strong>פרטי הצעת המחיר:</strong></p><ul><li>מספר הצעת מחיר: {quote_number}</li><li>שם הצעת מחיר: {quote_name}</li><li>חשבון: {account_name}</li><li>סכום כולל: {quote_total}</li><li>תקף עד: {quote_valid_until}</li><li>סטטוס: {quote_status}</li></ul><p><strong>נציג מכירות מוקצה:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>אנא צור קשר עם נציג המכירות שלך אם יש לך שאלות על הצעת מחיר זו.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Nuovo Preventivo Creato - {quote_name}',
                        'content' => '<p>Ciao {billing_contact_name},</p><p>Un nuovo preventivo è stato creato per te. Si prega di rivedere i dettagli qui sotto.</p><p><strong>Dettagli Preventivo:</strong></p><ul><li>Numero preventivo: {quote_number}</li><li>Nome preventivo: {quote_name}</li><li>Account: {account_name}</li><li>Importo totale: {quote_total}</li><li>Valido fino al: {quote_valid_until}</li><li>Stato: {quote_status}</li></ul><p><strong>Rappresentante Vendite Assegnato:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Si prega di contattare il rappresentante vendite per qualsiasi domanda su questo preventivo.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => '新しい見積もりが作成されました - {quote_name}',
                        'content' => '<p>こんにちは {billing_contact_name}さん、</p><p>新しい見積もりが作成されました。以下の詳細をご確認ください。</p><p><strong>見積もり詳細:</strong></p><ul><li>見積もり番号: {quote_number}</li><li>見積もり名: {quote_name}</li><li>アカウント: {account_name}</li><li>合計金額: {quote_total}</li><li>有効期限: {quote_valid_until}</li><li>ステータス: {quote_status}</li></ul><p><strong>担当営業担当者:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>この見積もりについてご質問がございましたら、営業担当者にお問い合わせください。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Nieuwe Offerte Aangemaakt - {quote_name}',
                        'content' => '<p>Hallo {billing_contact_name},</p><p>Een nieuwe offerte is voor je aangemaakt. Bekijk de details hieronder.</p><p><strong>Offerte Details:</strong></p><ul><li>Offertenummer: {quote_number}</li><li>Offertenaam: {quote_name}</li><li>Account: {account_name}</li><li>Totaalbedrag: {quote_total}</li><li>Geldig tot: {quote_valid_until}</li><li>Status: {quote_status}</li></ul><p><strong>Toegewezen Vertegenwoordiger:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Neem contact op met je vertegenwoordiger als je vragen hebt over deze offerte.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Nowa Oferta Utworzona - {quote_name}',
                        'content' => '<p>Witaj {billing_contact_name},</p><p>Nowa oferta została dla Ciebie utworzona. Przejrzyj szczegóły poniżej.</p><p><strong>Szczegóły Oferty:</strong></p><ul><li>Numer oferty: {quote_number}</li><li>Nazwa oferty: {quote_name}</li><li>Konto: {account_name}</li><li>Łączna kwota: {quote_total}</li><li>Ważna do: {quote_valid_until}</li><li>Status: {quote_status}</li></ul><p><strong>Przypisany Przedstawiciel Handlowy:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Skontaktuj się z przedstawicielem handlowym, jeśli masz pytania dotyczące tej oferty.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Nova Cotação Criada - {quote_name}',
                        'content' => '<p>Olá {billing_contact_name},</p><p>Uma nova cotação foi criada para si. Por favor, reveja os detalhes abaixo.</p><p><strong>Detalhes da Cotação:</strong></p><ul><li>Número da cotação: {quote_number}</li><li>Nome da cotação: {quote_name}</li><li>Conta: {account_name}</li><li>Valor total: {quote_total}</li><li>Válida até: {quote_valid_until}</li><li>Estado: {quote_status}</li></ul><p><strong>Representante de Vendas Atribuído:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Por favor, contacte o seu representante de vendas se tiver questões sobre esta cotação.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Nova Cotação Criada - {quote_name}',
                        'content' => '<p>Olá {billing_contact_name},</p><p>Uma nova cotação foi criada para você. Por favor, revise os detalhes abaixo.</p><p><strong>Detalhes da Cotação:</strong></p><ul><li>Número da cotação: {quote_number}</li><li>Nome da cotação: {quote_name}</li><li>Conta: {account_name}</li><li>Valor total: {quote_total}</li><li>Válida até: {quote_valid_until}</li><li>Status: {quote_status}</li></ul><p><strong>Representante de Vendas Designado:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Por favor, entre em contato com seu representante de vendas se tiver dúvidas sobre esta cotação.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Создано новое предложение - {quote_name}',
                        'content' => '<p>Привет {billing_contact_name},</p><p>Для вас создано новое предложение. Пожалуйста, просмотрите детали ниже.</p><p><strong>Детали предложения:</strong></p><ul><li>Номер предложения: {quote_number}</li><li>Название предложения: {quote_name}</li><li>Аккаунт: {account_name}</li><li>Общая сумма: {quote_total}</li><li>Действительно до: {quote_valid_until}</li><li>Статус: {quote_status}</li></ul><p><strong>Назначенный представитель по продажам:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Пожалуйста, свяжитесь с вашим представителем по продажам, если у вас есть вопросы по этому предложению.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Yeni Teklif Oluşturuldu - {quote_name}',
                        'content' => '<p>Merhaba {billing_contact_name},</p><p>Sizin için yeni bir teklif oluşturuldu. Lütfen aşağıdaki detayları inceleyin.</p><p><strong>Teklif Detayları:</strong></p><ul><li>Teklif numarası: {quote_number}</li><li>Teklif adı: {quote_name}</li><li>Hesap: {account_name}</li><li>Toplam tutar: {quote_total}</li><li>Geçerlilik tarihi: {quote_valid_until}</li><li>Durum: {quote_status}</li></ul><p><strong>Atanan Satış Temsilcisi:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Bu teklifle ilgili sorularınız varsa lütfen satış temsilcinizle iletişime geçin.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '新报价已创建 - {quote_name}',
                        'content' => '<p>你好 {billing_contact_name}，</p><p>已为您创建了新的报价。请查看以下详细信息。</p><p><strong>报价详情：</strong></p><ul><li>报价编号：{quote_number}</li><li>报价名称：{quote_name}</li><li>账户：{account_name}</li><li>总金额：{quote_total}</li><li>有效期至：{quote_valid_until}</li><li>状态：{quote_status}</li></ul><p><strong>指定销售代表：</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>如果您对此报价有任何疑问，请联系您的销售代表。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ]
                ]
            ],
            // Quote Status Changed
            [
                'name' => 'Quote Status Changed',
                'from' => 'Sales Team',
                'translations' => [
                    'en' => [
                        'subject' => 'Quote Status Updated - {quote_name}',
                        'content' => '<p>Hello {billing_contact_name},</p><p>The status of your quote has been updated from <strong>{old_quote_status}</strong> to <strong>{new_quote_status}</strong>.</p><p><strong>Quote Details:</strong></p><ul><li>Quote Number: {quote_number}</li><li>Quote Name: {quote_name}</li><li>Account: {account_name}</li><li>Total Amount: {quote_total}</li><li>Valid Until: {quote_valid_until}</li><li>Current Status: {new_quote_status}</li></ul><p><strong>Assigned Sales Representative:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Please contact your sales representative if you have any questions about this status change.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Estado de Cotización Actualizado - {quote_name}',
                        'content' => '<p>Hola {billing_contact_name},</p><p>El estado de su cotización ha sido actualizado de <strong>{old_quote_status}</strong> a <strong>{new_quote_status}</strong>.</p><p><strong>Detalles de la Cotización:</strong></p><ul><li>Número de Cotización: {quote_number}</li><li>Nombre de Cotización: {quote_name}</li><li>Cuenta: {account_name}</li><li>Monto Total: {quote_total}</li><li>Válida Hasta: {quote_valid_until}</li><li>Estado Actual: {new_quote_status}</li></ul><p><strong>Representante de Ventas Asignado:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Por favor contacte a su representante de ventas si tiene alguna pregunta sobre este cambio de estado.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'تم تحديث حالة عرض الأسعار - {quote_name}',
                        'content' => '<p>مرحباً {billing_contact_name}،</p><p>تم تحديث حالة عرض الأسعار الخاص بك من <strong>{old_quote_status}</strong> إلى <strong>{new_quote_status}</strong>.</p><p><strong>تفاصيل عرض الأسعار:</strong></p><ul><li>رقم عرض الأسعار: {quote_number}</li><li>اسم عرض الأسعار: {quote_name}</li><li>الحساب: {account_name}</li><li>المبلغ الإجمالي: {quote_total}</li><li>صالح حتى: {quote_valid_until}</li><li>الحالة الحالية: {new_quote_status}</li></ul><p><strong>مندوب المبيعات المعين:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>يرجى التواصل مع مندوب المبيعات إذا كان لديك أي أسئلة حول هذا التغيير في الحالة.</p><p style="text-align: right;">مع أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Tilbudsstatus Opdateret - {quote_name}',
                        'content' => '<p>Hej {billing_contact_name},</p><p>Status på dit tilbud er blevet opdateret fra <strong>{old_quote_status}</strong> til <strong>{new_quote_status}</strong>.</p><p><strong>Tilbud Detaljer:</strong></p><ul><li>Tilbudsnummer: {quote_number}</li><li>Tilbudsnavn: {quote_name}</li><li>Konto: {account_name}</li><li>Samlet beløb: {quote_total}</li><li>Gyldig indtil: {quote_valid_until}</li><li>Nuværende status: {new_quote_status}</li></ul><p><strong>Tildelt Salgsrepræsentant:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Kontakt venligst din salgsrepræsentant, hvis du har spørgsmål om denne statusændring.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Angebotsstatus Aktualisiert - {quote_name}',
                        'content' => '<p>Hallo {billing_contact_name},</p><p>Der Status Ihres Angebots wurde von <strong>{old_quote_status}</strong> auf <strong>{new_quote_status}</strong> aktualisiert.</p><p><strong>Angebot Details:</strong></p><ul><li>Angebotsnummer: {quote_number}</li><li>Angebotsname: {quote_name}</li><li>Konto: {account_name}</li><li>Gesamtbetrag: {quote_total}</li><li>Gültig bis: {quote_valid_until}</li><li>Aktueller Status: {new_quote_status}</li></ul><p><strong>Zugewiesener Vertriebsmitarbeiter:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Bitte kontaktieren Sie Ihren Vertriebsmitarbeiter, wenn Sie Fragen zu dieser Statusänderung haben.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Statut du Devis Mis à Jour - {quote_name}',
                        'content' => '<p>Bonjour {billing_contact_name},</p><p>Le statut de votre devis a été mis à jour de <strong>{old_quote_status}</strong> vers <strong>{new_quote_status}</strong>.</p><p><strong>Détails du Devis:</strong></p><ul><li>Numéro de devis: {quote_number}</li><li>Nom du devis: {quote_name}</li><li>Compte: {account_name}</li><li>Montant total: {quote_total}</li><li>Valide jusqu\'au: {quote_valid_until}</li><li>Statut actuel: {new_quote_status}</li></ul><p><strong>Représentant Commercial Assigné:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Veuillez contacter votre représentant commercial si vous avez des questions sur ce changement de statut.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'סטטוס הצעת המחיר עודכן - {quote_name}',
                        'content' => '<p>שלום {billing_contact_name},</p><p>סטטוס הצעת המחיר שלך עודכן מ-<strong>{old_quote_status}</strong> ל-<strong>{new_quote_status}</strong>.</p><p><strong>פרטי הצעת המחיר:</strong></p><ul><li>מספר הצעת מחיר: {quote_number}</li><li>שם הצעת מחיר: {quote_name}</li><li>חשבון: {account_name}</li><li>סכום כולל: {quote_total}</li><li>תקף עד: {quote_valid_until}</li><li>סטטוס נוכחי: {new_quote_status}</li></ul><p><strong>נציג מכירות מוקצה:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>אנא צור קשר עם נציג המכירות שלך אם יש לך שאלות על שינוי סטטוס זה.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Stato Preventivo Aggiornato - {quote_name}',
                        'content' => '<p>Ciao {billing_contact_name},</p><p>Lo stato del tuo preventivo è stato aggiornato da <strong>{old_quote_status}</strong> a <strong>{new_quote_status}</strong>.</p><p><strong>Dettagli Preventivo:</strong></p><ul><li>Numero preventivo: {quote_number}</li><li>Nome preventivo: {quote_name}</li><li>Account: {account_name}</li><li>Importo totale: {quote_total}</li><li>Valido fino al: {quote_valid_until}</li><li>Stato attuale: {new_quote_status}</li></ul><p><strong>Rappresentante Vendite Assegnato:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Si prega di contattare il rappresentante vendite per qualsiasi domanda su questo cambio di stato.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => '見積もりステータスが更新されました - {quote_name}',
                        'content' => '<p>こんにちは {billing_contact_name}さん、</p><p>見積もりのステータスが <strong>{old_quote_status}</strong> から <strong>{new_quote_status}</strong> に更新されました。</p><p><strong>見積もり詳細:</strong></p><ul><li>見積もり番号: {quote_number}</li><li>見積もり名: {quote_name}</li><li>アカウント: {account_name}</li><li>合計金額: {quote_total}</li><li>有効期限: {quote_valid_until}</li><li>現在のステータス: {new_quote_status}</li></ul><p><strong>担当営業担当者:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>このステータス変更についてご質問がございましたら、営業担当者にお問い合わせください。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Offerte Status Bijgewerkt - {quote_name}',
                        'content' => '<p>Hallo {billing_contact_name},</p><p>De status van je offerte is bijgewerkt van <strong>{old_quote_status}</strong> naar <strong>{new_quote_status}</strong>.</p><p><strong>Offerte Details:</strong></p><ul><li>Offertenummer: {quote_number}</li><li>Offertenaam: {quote_name}</li><li>Account: {account_name}</li><li>Totaalbedrag: {quote_total}</li><li>Geldig tot: {quote_valid_until}</li><li>Huidige status: {new_quote_status}</li></ul><p><strong>Toegewezen Vertegenwoordiger:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Neem contact op met je vertegenwoordiger als je vragen hebt over deze statuswijziging.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Status Oferty Zaktualizowany - {quote_name}',
                        'content' => '<p>Witaj {billing_contact_name},</p><p>Status Twojej oferty został zaktualizowany z <strong>{old_quote_status}</strong> na <strong>{new_quote_status}</strong>.</p><p><strong>Szczegóły Oferty:</strong></p><ul><li>Numer oferty: {quote_number}</li><li>Nazwa oferty: {quote_name}</li><li>Konto: {account_name}</li><li>Łączna kwota: {quote_total}</li><li>Ważna do: {quote_valid_until}</li><li>Aktualny status: {new_quote_status}</li></ul><p><strong>Przypisany Przedstawiciel Handlowy:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Skontaktuj się z przedstawicielem handlowym, jeśli masz pytania dotyczące tej zmiany statusu.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Estado da Cotação Atualizado - {quote_name}',
                        'content' => '<p>Olá {billing_contact_name},</p><p>O estado da sua cotação foi atualizado de <strong>{old_quote_status}</strong> para <strong>{new_quote_status}</strong>.</p><p><strong>Detalhes da Cotação:</strong></p><ul><li>Número da cotação: {quote_number}</li><li>Nome da cotação: {quote_name}</li><li>Conta: {account_name}</li><li>Valor total: {quote_total}</li><li>Válida até: {quote_valid_until}</li><li>Estado atual: {new_quote_status}</li></ul><p><strong>Representante de Vendas Atribuído:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Por favor, contacte o seu representante de vendas se tiver questões sobre esta mudança de estado.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Status da Cotação Atualizado - {quote_name}',
                        'content' => '<p>Olá {billing_contact_name},</p><p>O status da sua cotação foi atualizado de <strong>{old_quote_status}</strong> para <strong>{new_quote_status}</strong>.</p><p><strong>Detalhes da Cotação:</strong></p><ul><li>Número da cotação: {quote_number}</li><li>Nome da cotação: {quote_name}</li><li>Conta: {account_name}</li><li>Valor total: {quote_total}</li><li>Válida até: {quote_valid_until}</li><li>Status atual: {new_quote_status}</li></ul><p><strong>Representante de Vendas Designado:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Por favor, entre em contato com seu representante de vendas se tiver dúvidas sobre esta mudança de status.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Статус предложения обновлен - {quote_name}',
                        'content' => '<p>Привет {billing_contact_name},</p><p>Статус вашего предложения был обновлен с <strong>{old_quote_status}</strong> на <strong>{new_quote_status}</strong>.</p><p><strong>Детали предложения:</strong></p><ul><li>Номер предложения: {quote_number}</li><li>Название предложения: {quote_name}</li><li>Аккаунт: {account_name}</li><li>Общая сумма: {quote_total}</li><li>Действительно до: {quote_valid_until}</li><li>Текущий статус: {new_quote_status}</li></ul><p><strong>Назначенный представитель по продажам:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Пожалуйста, свяжитесь с вашим представителем по продажам, если у вас есть вопросы по этому изменению статуса.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Teklif Durumu Güncellendi - {quote_name}',
                        'content' => '<p>Merhaba {billing_contact_name},</p><p>Teklifinizin durumu <strong>{old_quote_status}</strong> durumundan <strong>{new_quote_status}</strong> durumuna güncellendi.</p><p><strong>Teklif Detayları:</strong></p><ul><li>Teklif numarası: {quote_number}</li><li>Teklif adı: {quote_name}</li><li>Hesap: {account_name}</li><li>Toplam tutar: {quote_total}</li><li>Geçerlilik tarihi: {quote_valid_until}</li><li>Mevcut durum: {new_quote_status}</li></ul><p><strong>Atanan Satış Temsilcisi:</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>Bu durum değişikliği hakkında sorularınız varsa lütfen satış temsilcinizle iletişime geçin.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '报价状态已更新 - {quote_name}',
                        'content' => '<p>你好 {billing_contact_name}，</p><p>您的报价状态已从 <strong>{old_quote_status}</strong> 更新为 <strong>{new_quote_status}</strong>。</p><p><strong>报价详情：</strong></p><ul><li>报价编号：{quote_number}</li><li>报价名称：{quote_name}</li><li>账户：{account_name}</li><li>总金额：{quote_total}</li><li>有效期至：{quote_valid_until}</li><li>当前状态：{new_quote_status}</li></ul><p><strong>指定销售代表：</strong></p><p>{assigned_user_name} - {assigned_user_email}</p><p>如果您对此状态变更有任何疑问，请联系您的销售代表。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ]
                ]
            ],
            // Task Assigned
            [
                'name' => 'Task Assigned',
                'from' => 'Project Team',
                'translations' => [
                    'en' => [
                        'subject' => 'New Task Assigned to You - {task_title}',
                        'content' => '<p>Hello {assigned_user_name},</p><p>A new task has been assigned to you. Please review the details below and take appropriate action.</p><p><strong>Task Details:</strong></p><ul><li>Task Title: {task_title}</li><li>Project: {project_name}</li><li>Priority: {task_priority}</li><li>Due Date: {task_due_date}</li><li>Status: {task_status}</li><li>Estimated Hours: {task_estimated_hours}</li></ul><p><strong>Description:</strong></p><p>{task_description}</p><p><strong>Assigned By:</strong></p><p>{creator_name} - {creator_email}</p><p>Please log into the system to view full task details and update progress as needed.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Nueva Tarea Asignada - {task_title}',
                        'content' => '<p>Hola {assigned_user_name},</p><p>Se le ha asignado una nueva tarea. Por favor revise los detalles a continuación y tome las medidas apropiadas.</p><p><strong>Detalles de la Tarea:</strong></p><ul><li>Título de la Tarea: {task_title}</li><li>Proyecto: {project_name}</li><li>Prioridad: {task_priority}</li><li>Fecha de Vencimiento: {task_due_date}</li><li>Estado: {task_status}</li><li>Horas Estimadas: {task_estimated_hours}</li></ul><p><strong>Descripción:</strong></p><p>{task_description}</p><p><strong>Asignado Por:</strong></p><p>{creator_name} - {creator_email}</p><p>Por favor inicie sesión en el sistema para ver los detalles completos de la tarea y actualizar el progreso según sea necesario.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'تم تعيين مهمة جديدة لك - {task_title}',
                        'content' => '<p>مرحباً {assigned_user_name}،</p><p>تم تعيين مهمة جديدة لك. يرجى مراجعة التفاصيل أدناه واتخاذ الإجراء المناسب.</p><p><strong>تفاصيل المهمة:</strong></p><ul><li>عنوان المهمة: {task_title}</li><li>المشروع: {project_name}</li><li>الأولوية: {task_priority}</li><li>تاريخ الاستحقاق: {task_due_date}</li><li>الحالة: {task_status}</li><li>الساعات المقدرة: {task_estimated_hours}</li></ul><p><strong>الوصف:</strong></p><p>{task_description}</p><p><strong>معين بواسطة:</strong></p><p>{creator_name} - {creator_email}</p><p>يرجى تسجيل الدخول إلى النظام لعرض تفاصيل المهمة الكاملة وتحديث التقدم حسب الحاجة.</p><p style="text-align: right;">مع أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Ny Opgave Tildelt - {task_title}',
                        'content' => '<p>Hej {assigned_user_name},</p><p>En ny opgave er blevet tildelt til dig. Gennemgå venligst detaljerne nedenfor og tag passende handling.</p><p><strong>Opgave Detaljer:</strong></p><ul><li>Opgave Titel: {task_title}</li><li>Projekt: {project_name}</li><li>Prioritet: {task_priority}</li><li>Forfaldsdato: {task_due_date}</li><li>Status: {task_status}</li><li>Estimerede Timer: {task_estimated_hours}</li></ul><p><strong>Beskrivelse:</strong></p><p>{task_description}</p><p><strong>Tildelt Af:</strong></p><p>{creator_name} - {creator_email}</p><p>Log venligst ind i systemet for at se fulde opgavedetaljer og opdatere fremskridt efter behov.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Neue Aufgabe zugewiesen - {task_title}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Eine neue Aufgabe wurde Ihnen zugewiesen. Bitte überprüfen Sie die Details unten und ergreifen Sie entsprechende Maßnahmen.</p><p><strong>Aufgaben Details:</strong></p><ul><li>Aufgaben Titel: {task_title}</li><li>Projekt: {project_name}</li><li>Priorität: {task_priority}</li><li>Fälligkeitsdatum: {task_due_date}</li><li>Status: {task_status}</li><li>Geschätzte Stunden: {task_estimated_hours}</li></ul><p><strong>Beschreibung:</strong></p><p>{task_description}</p><p><strong>Zugewiesen von:</strong></p><p>{creator_name} - {creator_email}</p><p>Bitte loggen Sie sich in das System ein, um vollständige Aufgabendetails anzuzeigen und den Fortschritt bei Bedarf zu aktualisieren.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Nouvelle Tâche Assignée - {task_title}',
                        'content' => '<p>Bonjour {assigned_user_name},</p><p>Une nouvelle tâche vous a été assignée. Veuillez examiner les détails ci-dessous et prendre les mesures appropriées.</p><p><strong>Détails de la Tâche:</strong></p><ul><li>Titre de la Tâche: {task_title}</li><li>Projet: {project_name}</li><li>Priorité: {task_priority}</li><li>Date d\'échéance: {task_due_date}</li><li>Statut: {task_status}</li><li>Heures Estimées: {task_estimated_hours}</li></ul><p><strong>Description:</strong></p><p>{task_description}</p><p><strong>Assigné Par:</strong></p><p>{creator_name} - {creator_email}</p><p>Veuillez vous connecter au système pour voir les détails complets de la tâche et mettre à jour les progrès si nécessaire.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'משימה חדשה הוקצתה - {task_title}',
                        'content' => '<p>שלום {assigned_user_name},</p><p>משימה חדשה הוקצתה לך. אנא עיין בפרטים להלן ונקט פעולה מתאימה.</p><p><strong>פרטי המשימה:</strong></p><ul><li>כותרת המשימה: {task_title}</li><li>פרויקט: {project_name}</li><li>עדיפות: {task_priority}</li><li>תאריך יעד: {task_due_date}</li><li>סטטוס: {task_status}</li><li>שעות מוערכות: {task_estimated_hours}</li></ul><p><strong>תיאור:</strong></p><p>{task_description}</p><p><strong>הוקצה על ידי:</strong></p><p>{creator_name} - {creator_email}</p><p>אנא התחבר למערכת כדי לראות פרטי משימה מלאים ולעדכן התקדמות לפי הצורך.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Nuovo Compito Assegnato - {task_title}',
                        'content' => '<p>Ciao {assigned_user_name},</p><p>Un nuovo compito ti è stato assegnato. Si prega di rivedere i dettagli qui sotto e prendere le azioni appropriate.</p><p><strong>Dettagli del Compito:</strong></p><ul><li>Titolo del Compito: {task_title}</li><li>Progetto: {project_name}</li><li>Priorità: {task_priority}</li><li>Data di Scadenza: {task_due_date}</li><li>Stato: {task_status}</li><li>Ore Stimate: {task_estimated_hours}</li></ul><p><strong>Descrizione:</strong></p><p>{task_description}</p><p><strong>Assegnato Da:</strong></p><p>{creator_name} - {creator_email}</p><p>Si prega di accedere al sistema per visualizzare i dettagli completi del compito e aggiornare i progressi secondo necessità.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => '新しいタスクが割り当てられました - {task_title}',
                        'content' => '<p>こんにちは {assigned_user_name}さん、</p><p>新しいタスクがあなたに割り当てられました。以下の詳細を確認し、適切なアクションを取ってください。</p><p><strong>タスク詳細:</strong></p><ul><li>タスクタイトル: {task_title}</li><li>プロジェクト: {project_name}</li><li>優先度: {task_priority}</li><li>期限: {task_due_date}</li><li>ステータス: {task_status}</li><li>予想時間: {task_estimated_hours}</li></ul><p><strong>説明:</strong></p><p>{task_description}</p><p><strong>割り当て者:</strong></p><p>{creator_name} - {creator_email}</p><p>システムにログインしてタスクの詳細を確認し、必要に応じて進捗を更新してください。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Nieuwe Taak Toegewezen - {task_title}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Een nieuwe taak is aan je toegewezen. Bekijk de details hieronder en onderneem passende actie.</p><p><strong>Taak Details:</strong></p><ul><li>Taak Titel: {task_title}</li><li>Project: {project_name}</li><li>Prioriteit: {task_priority}</li><li>Vervaldatum: {task_due_date}</li><li>Status: {task_status}</li><li>Geschatte Uren: {task_estimated_hours}</li></ul><p><strong>Beschrijving:</strong></p><p>{task_description}</p><p><strong>Toegewezen Door:</strong></p><p>{creator_name} - {creator_email}</p><p>Log in op het systeem om volledige taakdetails te bekijken en voortgang bij te werken indien nodig.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Nowe Zadanie Przypisane - {task_title}',
                        'content' => '<p>Witaj {assigned_user_name},</p><p>Nowe zadanie zostało Ci przypisane. Przejrzyj szczegóły poniżej i podejmij odpowiednie działania.</p><p><strong>Szczegóły Zadania:</strong></p><ul><li>Tytuł Zadania: {task_title}</li><li>Projekt: {project_name}</li><li>Priorytet: {task_priority}</li><li>Termin: {task_due_date}</li><li>Status: {task_status}</li><li>Szacowane Godziny: {task_estimated_hours}</li></ul><p><strong>Opis:</strong></p><p>{task_description}</p><p><strong>Przypisane Przez:</strong></p><p>{creator_name} - {creator_email}</p><p>Zaloguj się do systemu, aby zobaczyć pełne szczegóły zadania i zaktualizować postęp w razie potrzeby.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Nova Tarefa Atribuída - {task_title}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>Uma nova tarefa foi atribuída a si. Por favor, reveja os detalhes abaixo e tome a ação apropriada.</p><p><strong>Detalhes da Tarefa:</strong></p><ul><li>Título da Tarefa: {task_title}</li><li>Projeto: {project_name}</li><li>Prioridade: {task_priority}</li><li>Data de Vencimento: {task_due_date}</li><li>Estado: {task_status}</li><li>Horas Estimadas: {task_estimated_hours}</li></ul><p><strong>Descrição:</strong></p><p>{task_description}</p><p><strong>Atribuído Por:</strong></p><p>{creator_name} - {creator_email}</p><p>Por favor, faça login no sistema para ver os detalhes completos da tarefa e atualizar o progresso conforme necessário.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Nova Tarefa Atribuída - {task_title}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>Uma nova tarefa foi atribuída a você. Por favor, revise os detalhes abaixo e tome a ação apropriada.</p><p><strong>Detalhes da Tarefa:</strong></p><ul><li>Título da Tarefa: {task_title}</li><li>Projeto: {project_name}</li><li>Prioridade: {task_priority}</li><li>Data de Vencimento: {task_due_date}</li><li>Status: {task_status}</li><li>Horas Estimadas: {task_estimated_hours}</li></ul><p><strong>Descrição:</strong></p><p>{task_description}</p><p><strong>Atribuído Por:</strong></p><p>{creator_name} - {creator_email}</p><p>Por favor, faça login no sistema para ver os detalhes completos da tarefa e atualizar o progresso conforme necessário.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Новая задача назначена - {task_title}',
                        'content' => '<p>Привет {assigned_user_name},</p><p>Вам назначена новая задача. Пожалуйста, просмотрите детали ниже и примите соответствующие меры.</p><p><strong>Детали задачи:</strong></p><ul><li>Название задачи: {task_title}</li><li>Проект: {project_name}</li><li>Приоритет: {task_priority}</li><li>Срок выполнения: {task_due_date}</li><li>Статус: {task_status}</li><li>Оценочные часы: {task_estimated_hours}</li></ul><p><strong>Описание:</strong></p><p>{task_description}</p><p><strong>Назначено:</strong></p><p>{creator_name} - {creator_email}</p><p>Пожалуйста, войдите в систему, чтобы посмотреть полные детали задачи и обновить прогресс по мере необходимости.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Yeni Görev Atandı - {task_title}',
                        'content' => '<p>Merhaba {assigned_user_name},</p><p>Size yeni bir görev atandı. Lütfen aşağıdaki detayları inceleyin ve uygun eylemi gerçekleştirin.</p><p><strong>Görev Detayları:</strong></p><ul><li>Görev Başlığı: {task_title}</li><li>Proje: {project_name}</li><li>Öncelik: {task_priority}</li><li>Teslim Tarihi: {task_due_date}</li><li>Durum: {task_status}</li><li>Tahmini Saatler: {task_estimated_hours}</li></ul><p><strong>Açıklama:</strong></p><p>{task_description}</p><p><strong>Atayan:</strong></p><p>{creator_name} - {creator_email}</p><p>Görevin tam detaylarını görmek ve gerektiğinde ilerlemeyi güncellemek için lütfen sisteme giriş yapın.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '新任务已分配 - {task_title}',
                        'content' => '<p>你好 {assigned_user_name}，</p><p>一个新任务已分配给您。请查看以下详细信息并采取适当行动。</p><p><strong>任务详情：</strong></p><ul><li>任务标题：{task_title}</li><li>项目：{project_name}</li><li>优先级：{task_priority}</li><li>截止日期：{task_due_date}</li><li>状态：{task_status}</li><li>预估小时：{task_estimated_hours}</li></ul><p><strong>描述：</strong></p><p>{task_description}</p><p><strong>分配者：</strong></p><p>{creator_name} - {creator_email}</p><p>请登录系统查看完整的任务详情并根据需要更新进度。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ]
                ]
            ],
            // Meeting Invitation
            [
                'name' => 'Meeting Invitation',
                'from' => 'Meeting Organizer',
                'translations' => [
                    'en' => [
                        'subject' => 'Meeting Invitation: {meeting_title}',
                        'content' => '<p>Hello {attendee_name},</p><p>You are invited to attend the following meeting:</p><p><strong>Meeting Details:</strong></p><ul><li>Title: {meeting_title}</li><li>Date: {meeting_date}</li><li>Time: {meeting_start_time} - {meeting_end_time}</li><li>Location: {meeting_location}</li></ul><p><strong>Description:</strong></p><p>{meeting_description}</p><p>Please confirm your attendance and add this meeting to your calendar.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Invitación a Reunión: {meeting_title}',
                        'content' => '<p>Hola {attendee_name},</p><p>Está invitado a asistir a la siguiente reunión:</p><p><strong>Detalles de la Reunión:</strong></p><ul><li>Título: {meeting_title}</li><li>Fecha: {meeting_date}</li><li>Hora: {meeting_start_time} - {meeting_end_time}</li><li>Ubicación: {meeting_location}</li></ul><p><strong>Descripción:</strong></p><p>{meeting_description}</p><p>Por favor confirme su asistencia y agregue esta reunión a su calendario.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'دعوة لاجتماع: {meeting_title}',
                        'content' => '<p>مرحباً {attendee_name}،</p><p>أنت مدعو لحضور الاجتماع التالي:</p><p><strong>تفاصيل الاجتماع:</strong></p><ul><li>العنوان: {meeting_title}</li><li>التاريخ: {meeting_date}</li><li>الوقت: {meeting_start_time} - {meeting_end_time}</li><li>الموقع: {meeting_location}</li></ul><p><strong>الوصف:</strong></p><p>{meeting_description}</p><p>يرجى تأكيد حضورك وإضافة هذا الاجتماع إلى تقويمك.</p><p style="text-align: right;">مع أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Mødeinvitation: {meeting_title}',
                        'content' => '<p>Hej {attendee_name},</p><p>Du er inviteret til at deltage i følgende møde:</p><p><strong>Møde Detaljer:</strong></p><ul><li>Titel: {meeting_title}</li><li>Dato: {meeting_date}</li><li>Tid: {meeting_start_time} - {meeting_end_time}</li><li>Sted: {meeting_location}</li></ul><p><strong>Beskrivelse:</strong></p><p>{meeting_description}</p><p>Bekræft venligst din deltagelse og tilføj dette møde til din kalender.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Meeting-Einladung: {meeting_title}',
                        'content' => '<p>Hallo {attendee_name},</p><p>Sie sind eingeladen, an folgendem Meeting teilzunehmen:</p><p><strong>Meeting Details:</strong></p><ul><li>Titel: {meeting_title}</li><li>Datum: {meeting_date}</li><li>Zeit: {meeting_start_time} - {meeting_end_time}</li><li>Ort: {meeting_location}</li></ul><p><strong>Beschreibung:</strong></p><p>{meeting_description}</p><p>Bitte bestätigen Sie Ihre Teilnahme und fügen Sie dieses Meeting zu Ihrem Kalender hinzu.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Invitation à la Réunion: {meeting_title}',
                        'content' => '<p>Bonjour {attendee_name},</p><p>Vous êtes invité à assister à la réunion suivante:</p><p><strong>Détails de la Réunion:</strong></p><ul><li>Titre: {meeting_title}</li><li>Date: {meeting_date}</li><li>Heure: {meeting_start_time} - {meeting_end_time}</li><li>Lieu: {meeting_location}</li></ul><p><strong>Description:</strong></p><p>{meeting_description}</p><p>Veuillez confirmer votre présence et ajouter cette réunion à votre calendrier.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'הזמנה לפגישה: {meeting_title}',
                        'content' => '<p>שלום {attendee_name},</p><p>אתה מוזמן להשתתף בפגישה הבאה:</p><p><strong>פרטי הפגישה:</strong></p><ul><li>כותרת: {meeting_title}</li><li>תאריך: {meeting_date}</li><li>שעה: {meeting_start_time} - {meeting_end_time}</li><li>מיקום: {meeting_location}</li></ul><p><strong>תיאור:</strong></p><p>{meeting_description}</p><p>אנא אשר את השתתפותך והוסף את הפגישה ליומן שלך.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Invito alla Riunione: {meeting_title}',
                        'content' => '<p>Ciao {attendee_name},</p><p>Sei invitato a partecipare alla seguente riunione:</p><p><strong>Dettagli della Riunione:</strong></p><ul><li>Titolo: {meeting_title}</li><li>Data: {meeting_date}</li><li>Ora: {meeting_start_time} - {meeting_end_time}</li><li>Luogo: {meeting_location}</li></ul><p><strong>Descrizione:</strong></p><p>{meeting_description}</p><p>Si prega di confermare la partecipazione e aggiungere questa riunione al calendario.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => '会議のご招待: {meeting_title}',
                        'content' => '<p>こんにちは {attendee_name}さん、</p><p>以下の会議にご参加いただきますようご招待いたします:</p><p><strong>会議詳細:</strong></p><ul><li>タイトル: {meeting_title}</li><li>日付: {meeting_date}</li><li>時間: {meeting_start_time} - {meeting_end_time}</li><li>場所: {meeting_location}</li></ul><p><strong>説明:</strong></p><p>{meeting_description}</p><p>参加の確認とカレンダーへの登録をお願いいたします。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Vergaderuitnodiging: {meeting_title}',
                        'content' => '<p>Hallo {attendee_name},</p><p>Je bent uitgenodigd voor de volgende vergadering:</p><p><strong>Vergader Details:</strong></p><ul><li>Titel: {meeting_title}</li><li>Datum: {meeting_date}</li><li>Tijd: {meeting_start_time} - {meeting_end_time}</li><li>Locatie: {meeting_location}</li></ul><p><strong>Beschrijving:</strong></p><p>{meeting_description}</p><p>Bevestig je aanwezigheid en voeg deze vergadering toe aan je agenda.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Zaproszenie na Spotkanie: {meeting_title}',
                        'content' => '<p>Witaj {attendee_name},</p><p>Jesteś zaproszony na następujące spotkanie:</p><p><strong>Szczegóły Spotkania:</strong></p><ul><li>Tytuł: {meeting_title}</li><li>Data: {meeting_date}</li><li>Czas: {meeting_start_time} - {meeting_end_time}</li><li>Miejsce: {meeting_location}</li></ul><p><strong>Opis:</strong></p><p>{meeting_description}</p><p>Potwierdź swoją obecność i dodaj to spotkanie do swojego kalendarza.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Convite para Reunião: {meeting_title}',
                        'content' => '<p>Olá {attendee_name},</p><p>Está convidado a participar na seguinte reunião:</p><p><strong>Detalhes da Reunião:</strong></p><ul><li>Título: {meeting_title}</li><li>Data: {meeting_date}</li><li>Hora: {meeting_start_time} - {meeting_end_time}</li><li>Local: {meeting_location}</li></ul><p><strong>Descrição:</strong></p><p>{meeting_description}</p><p>Por favor, confirme a sua presença e adicione esta reunião ao seu calendário.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Convite para Reunião: {meeting_title}',
                        'content' => '<p>Olá {attendee_name},</p><p>Você está convidado a participar da seguinte reunião:</p><p><strong>Detalhes da Reunião:</strong></p><ul><li>Título: {meeting_title}</li><li>Data: {meeting_date}</li><li>Horário: {meeting_start_time} - {meeting_end_time}</li><li>Local: {meeting_location}</li></ul><p><strong>Descrição:</strong></p><p>{meeting_description}</p><p>Por favor, confirme sua presença e adicione esta reunião ao seu calendário.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Приглашение на собрание: {meeting_title}',
                        'content' => '<p>Привет {attendee_name},</p><p>Вы приглашены на следующее собрание:</p><p><strong>Детали собрания:</strong></p><ul><li>Название: {meeting_title}</li><li>Дата: {meeting_date}</li><li>Время: {meeting_start_time} - {meeting_end_time}</li><li>Место: {meeting_location}</li></ul><p><strong>Описание:</strong></p><p>{meeting_description}</p><p>Пожалуйста, подтвердите свое участие и добавьте это собрание в свой календарь.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Toplantı Davetiyesi: {meeting_title}',
                        'content' => '<p>Merhaba {attendee_name},</p><p>Aşağıdaki toplantıya davetlisiniz:</p><p><strong>Toplantı Detayları:</strong></p><ul><li>Başlık: {meeting_title}</li><li>Tarih: {meeting_date}</li><li>Saat: {meeting_start_time} - {meeting_end_time}</li><li>Yer: {meeting_location}</li></ul><p><strong>Açıklama:</strong></p><p>{meeting_description}</p><p>Lütfen katılımınızı onaylayın ve bu toplantıyı takviminize ekleyin.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '会议邀请: {meeting_title}',
                        'content' => '<p>你好 {attendee_name}，</p><p>邀请您参加以下会议:</p><p><strong>会议详情：</strong></p><ul><li>标题：{meeting_title}</li><li>日期：{meeting_date}</li><li>时间：{meeting_start_time} - {meeting_end_time}</li><li>地点：{meeting_location}</li></ul><p><strong>描述：</strong></p><p>{meeting_description}</p><p>请确认您的参会并将此会议添加到您的日历中。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ]
                ]
            ],
            // Case Created
            [
                'name' => 'Case Created',
                'from' => 'Support Team',
                'translations' => [
                    'en' => [
                        'subject' => 'New Support Case Assigned - {case_subject}',
                        'content' => '<p>Hello {assigned_user_name},</p><p>A new support case has been assigned to you. Please review the details below and take the necessary actions.</p><p><strong>Case Details:</strong></p><ul><li>Subject: {case_subject}</li><li>Priority: {case_priority}</li><li>Status: {case_status}</li><li>Created Date: {case_created_date}</li></ul><p><strong>Description:</strong></p><p>{case_description}</p><p>Thank you for your support.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Nuevo Caso de Soporte Asignado - {case_subject}',
                        'content' => '<p>Hola {assigned_user_name},</p><p>Se le ha asignado un nuevo caso de soporte. Por favor, revise los detalles a continuación y tome las acciones necesarias.</p><p><strong>Detalles del Caso:</strong></p><ul><li>Asunto: {case_subject}</li><li>Prioridad: {case_priority}</li><li>Estado: {case_status}</li><li>Fecha de Creación: {case_created_date}</li></ul><p><strong>Descripción:</strong></p><p>{case_description}</p><p>Gracias por su apoyo.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'تم تعيين حالة دعم جديدة - {case_subject}',
                        'content' => '<p>مرحباً {assigned_user_name}،</p><p>تم تعيين حالة دعم جديدة لك. يرجى مراجعة التفاصيل أدناه واتخاذ الإجراءات اللازمة.</p><p><strong>تفاصيل الحالة:</strong></p><ul><li>الموضوع: {case_subject}</li><li>الأولوية: {case_priority}</li><li>الحالة: {case_status}</li><li>تاريخ الإنشاء: {case_created_date}</li></ul><p><strong>الوصف:</strong></p><p>{case_description}</p><p>شكراً لدعمك.</p><p style="text-align: right;">مع أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Ny Supportsag Tildelt - {case_subject}',
                        'content' => '<p>Hej {assigned_user_name},</p><p>En ny supportsag er blevet tildelt dig. Gennemgå venligst detaljerne nedenfor og tag de nødvendige handlinger.</p><p><strong>Sagsdetaljer:</strong></p><ul><li>Emne: {case_subject}</li><li>Prioritet: {case_priority}</li><li>Status: {case_status}</li><li>Oprettelsesdato: {case_created_date}</li></ul><p><strong>Beskrivelse:</strong></p><p>{case_description}</p><p>Tak for din støtte.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Neuer Support-Fall zugewiesen - {case_subject}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Ihnen wurde ein neuer Support-Fall zugewiesen. Bitte überprüfen Sie die folgenden Details und ergreifen Sie die notwendigen Maßnahmen.</p><p><strong>Falldetails:</strong></p><ul><li>Betreff: {case_subject}</li><li>Priorität: {case_priority}</li><li>Status: {case_status}</li><li>Erstellungsdatum: {case_created_date}</li></ul><p><strong>Beschreibung:</strong></p><p>{case_description}</p><p>Vielen Dank für Ihre Unterstützung.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Nouveau Cas de Support Assigné - {case_subject}',
                        'content' => '<p>Bonjour {assigned_user_name},</p><p>Un nouveau cas de support vous a été assigné. Veuillez consulter les détails ci-dessous et prendre les mesures nécessaires.</p><p><strong>Détails du Cas:</strong></p><ul><li>Sujet: {case_subject}</li><li>Priorité: {case_priority}</li><li>Statut: {case_status}</li><li>Date de création: {case_created_date}</li></ul><p><strong>Description:</strong></p><p>{case_description}</p><p>Merci pour votre soutien.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'הוקצתה לך פנייה חדשה - {case_subject}',
                        'content' => '<p>שלום {assigned_user_name},</p><p>פנייה חדשה הוקצתה לך. אנא עיין בפרטים למטה ונקוט בפעולות הנדרשות.</p><p><strong>פרטי הפנייה:</strong></p><ul><li>נושא: {case_subject}</li><li>עדיפות: {case_priority}</li><li>סטטוס: {case_status}</li><li>תאריך יצירה: {case_created_date}</li></ul><p><strong>תיאור:</strong></p><p>{case_description}</p><p>תודה על התמיכה שלך.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Nuovo Caso di Supporto Assegnato - {case_subject}',
                        'content' => '<p>Ciao {assigned_user_name},</p><p>Ti è stato assegnato un nuovo caso di supporto. Si prega di rivedere i dettagli seguenti e intraprendere le azioni necessarie.</p><p><strong>Dettagli del Caso:</strong></p><ul><li>Oggetto: {case_subject}</li><li>Priorità: {case_priority}</li><li>Stato: {case_status}</li><li>Data di creazione: {case_created_date}</li></ul><p><strong>Descrizione:</strong></p><p>{case_description}</p><p>Grazie per il tuo supporto.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => '新しいサポートケースが割り当てられました - {case_subject}',
                        'content' => '<p>こんにちは {assigned_user_name}さん、</p><p>新しいサポートケースがあなたに割り当てられました。以下の詳細を確認し、必要な対応をお願いします。</p><p><strong>ケース詳細:</strong></p><ul><li>件名: {case_subject}</li><li>優先度: {case_priority}</li><li>ステータス: {case_status}</li><li>作成日: {case_created_date}</li></ul><p><strong>説明:</strong></p><p>{case_description}</p><p>ご協力ありがとうございます。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Nieuwe Supportcase Toegewezen - {case_subject}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Een nieuwe supportcase is aan je toegewezen. Controleer de onderstaande details en onderneem de nodige stappen.</p><p><strong>Case Details:</strong></p><ul><li>Onderwerp: {case_subject}</li><li>Prioriteit: {case_priority}</li><li>Status: {case_status}</li><li>Aanmaakdatum: {case_created_date}</li></ul><p><strong>Beschrijving:</strong></p><p>{case_description}</p><p>Bedankt voor je inzet.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Nowe Zgłoszenie Przypisane - {case_subject}',
                        'content' => '<p>Witaj {assigned_user_name},</p><p>Przypisano Ci nowe zgłoszenie wsparcia. Proszę zapoznaj się ze szczegółami poniżej i podejmij odpowiednie działania.</p><p><strong>Szczegóły Zgłoszenia:</strong></p><ul><li>Temat: {case_subject}</li><li>Priorytet: {case_priority}</li><li>Status: {case_status}</li><li>Data utworzenia: {case_created_date}</li></ul><p><strong>Opis:</strong></p><p>{case_description}</p><p>Dziękujemy za Twoje wsparcie.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Novo Caso de Suporte Atribuído - {case_subject}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>Um novo caso de suporte foi atribuído a si. Por favor, reveja os detalhes abaixo e tome as ações necessárias.</p><p><strong>Detalhes do Caso:</strong></p><ul><li>Assunto: {case_subject}</li><li>Prioridade: {case_priority}</li><li>Status: {case_status}</li><li>Data de criação: {case_created_date}</li></ul><p><strong>Descrição:</strong></p><p>{case_description}</p><p>Obrigado pelo seu apoio.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Novo Caso de Suporte Atribuído - {case_subject}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>Um novo caso de suporte foi atribuído a você. Por favor, revise os detalhes abaixo e tome as ações necessárias.</p><p><strong>Detalhes do Caso:</strong></p><ul><li>Assunto: {case_subject}</li><li>Prioridade: {case_priority}</li><li>Status: {case_status}</li><li>Data de criação: {case_created_date}</li></ul><p><strong>Descrição:</strong></p><p>{case_description}</p><p>Obrigado pelo seu apoio.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Назначен новый случай поддержки - {case_subject}',
                        'content' => '<p>Привет {assigned_user_name},</p><p>Вам назначен новый случай поддержки. Пожалуйста, ознакомьтесь с деталями ниже и примите необходимые меры.</p><p><strong>Детали случая:</strong></p><ul><li>Тема: {case_subject}</li><li>Приоритет: {case_priority}</li><li>Статус: {case_status}</li><li>Дата создания: {case_created_date}</li></ul><p><strong>Описание:</strong></p><p>{case_description}</p><p>Спасибо за вашу поддержку.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Yeni Destek Vakası Atandı - {case_subject}',
                        'content' => '<p>Merhaba {assigned_user_name},</p><p>Size yeni bir destek vakası atandı. Lütfen aşağıdaki ayrıntıları inceleyin ve gerekli adımları atın.</p><p><strong>Vaka Detayları:</strong></p><ul><li>Konu: {case_subject}</li><li>Öncelik: {case_priority}</li><li>Durum: {case_status}</li><li>Oluşturulma tarihi: {case_created_date}</li></ul><p><strong>Açıklama:</strong></p><p>{case_description}</p><p>Destekleriniz için teşekkür ederiz.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '已分配新的支持案例 - {case_subject}',
                        'content' => '<p>你好 {assigned_user_name}，</p><p>一个新的支持案例已分配给你。请查看以下详情并采取必要的措施。</p><p><strong>案例详情：</strong></p><ul><li>主题：{case_subject}</li><li>优先级：{case_priority}</li><li>状态：{case_status}</li><li>创建日期：{case_created_date}</li></ul><p><strong>描述：</strong></p><p>{case_description}</p><p>感谢你的支持。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ]
                ]
            ],
            // Opportunity Created
            [
                'name' => 'Opportunity Created',
                'from' => 'Sales Team',
                'translations' => [
                    'en' => [
                        'subject' => 'New Opportunity Created - {opportunity_name}',
                        'content' => '<p>Hello {assigned_user_name},</p><p>A new sales opportunity has been created and assigned to you. Please review the details below and take appropriate action.</p><p><strong>Opportunity Details:</strong></p><ul><li>Opportunity Name: {opportunity_name}</li><li>Account: {account_name}</li><li>Contact: {contact_name}</li><li>Stage: {opportunity_stage}</li><li>Amount: {opportunity_amount}</li><li>Close Date: {opportunity_close_date}</li></ul><p><strong>Description:</strong></p><p>{opportunity_description}</p><p>Please log into the system to view full opportunity details and begin working on this sales opportunity.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Nueva Oportunidad Creada - {opportunity_name}',
                        'content' => '<p>Hola {assigned_user_name},</p><p>Se ha creado una nueva oportunidad de ventas y se le ha asignado. Por favor revise los detalles a continuación y tome las medidas apropiadas.</p><p><strong>Detalles de la Oportunidad:</strong></p><ul><li>Nombre de la Oportunidad: {opportunity_name}</li><li>Cuenta: {account_name}</li><li>Contacto: {contact_name}</li><li>Etapa: {opportunity_stage}</li><li>Monto: {opportunity_amount}</li><li>Fecha de Cierre: {opportunity_close_date}</li></ul><p><strong>Descripción:</strong></p><p>{opportunity_description}</p><p>Por favor inicie sesión en el sistema para ver los detalles completos de la oportunidad y comenzar a trabajar en esta oportunidad de ventas.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'تم إنشاء فرصة جديدة - {opportunity_name}',
                        'content' => '<p>مرحباً {assigned_user_name}،</p><p>تم إنشاء فرصة مبيعات جديدة وتم تعيينها لك. يرجى مراجعة التفاصيل أدناه واتخاذ الإجراء المناسب.</p><p><strong>تفاصيل الفرصة:</strong></p><ul><li>اسم الفرصة: {opportunity_name}</li><li>الحساب: {account_name}</li><li>جهة الاتصال: {contact_name}</li><li>المرحلة: {opportunity_stage}</li><li>المبلغ: {opportunity_amount}</li><li>تاريخ الإغلاق: {opportunity_close_date}</li></ul><p><strong>الوصف:</strong></p><p>{opportunity_description}</p><p>يرجى تسجيل الدخول إلى النظام لعرض تفاصيل الفرصة الكاملة والبدء في العمل على فرصة المبيعات هذه.</p><p style="text-align: right;">مع أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Ny Mulighed Oprettet - {opportunity_name}',
                        'content' => '<p>Hej {assigned_user_name},</p><p>En ny salgsmulighed er blevet oprettet og tildelt til dig. Gennemgå venligst detaljerne nedenfor og tag passende handling.</p><p><strong>Muligheds Detaljer:</strong></p><ul><li>Muligheds Navn: {opportunity_name}</li><li>Konto: {account_name}</li><li>Kontakt: {contact_name}</li><li>Fase: {opportunity_stage}</li><li>Beløb: {opportunity_amount}</li><li>Lukkedato: {opportunity_close_date}</li></ul><p><strong>Beskrivelse:</strong></p><p>{opportunity_description}</p><p>Log venligst ind i systemet for at se fulde muligheds detaljer og begynde at arbejde på denne salgsmulighed.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Neue Verkaufschance Erstellt - {opportunity_name}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Eine neue Verkaufschance wurde erstellt und Ihnen zugewiesen. Bitte überprüfen Sie die Details unten und ergreifen Sie entsprechende Maßnahmen.</p><p><strong>Verkaufschancen Details:</strong></p><ul><li>Verkaufschancen Name: {opportunity_name}</li><li>Konto: {account_name}</li><li>Kontakt: {contact_name}</li><li>Phase: {opportunity_stage}</li><li>Betrag: {opportunity_amount}</li><li>Abschlussdatum: {opportunity_close_date}</li></ul><p><strong>Beschreibung:</strong></p><p>{opportunity_description}</p><p>Bitte loggen Sie sich in das System ein, um vollständige Verkaufschancen-Details anzuzeigen und mit der Arbeit an dieser Verkaufschance zu beginnen.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Nouvelle Opportunité Créée - {opportunity_name}',
                        'content' => '<p>Bonjour {assigned_user_name},</p><p>Une nouvelle opportunité de vente a été créée et vous a été assignée. Veuillez examiner les détails ci-dessous et prendre les mesures appropriées.</p><p><strong>Détails de l\'Opportunité:</strong></p><ul><li>Nom de l\'Opportunité: {opportunity_name}</li><li>Compte: {account_name}</li><li>Contact: {contact_name}</li><li>Étape: {opportunity_stage}</li><li>Montant: {opportunity_amount}</li><li>Date de Clôture: {opportunity_close_date}</li></ul><p><strong>Description:</strong></p><p>{opportunity_description}</p><p>Veuillez vous connecter au système pour voir les détails complets de l\'opportunité et commencer à travailler sur cette opportunité de vente.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'הזדמנות חדשה נוצרה - {opportunity_name}',
                        'content' => '<p>שלום {assigned_user_name},</p><p>הזדמנות מכירות חדשה נוצרה והוקצתה לך. אנא עיין בפרטים למטה ונקוט בפעולה המתאימה.</p><p><strong>פרטי ההזדמנות:</strong></p><ul><li>שם ההזדמנות: {opportunity_name}</li><li>חשבון: {account_name}</li><li>איש קשר: {contact_name}</li><li>שלב: {opportunity_stage}</li><li>סכום: {opportunity_amount}</li><li>תאריך סגירה: {opportunity_close_date}</li></ul><p><strong>תיאור:</strong></p><p>{opportunity_description}</p><p>אנא התחבר למערכת כדי לראות פרטי הזדמנות מלאים ולהתחיל לעבוד על הזדמנות מכירות זו.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Nuova Opportunità Creata - {opportunity_name}',
                        'content' => '<p>Ciao {assigned_user_name},</p><p>Una nuova opportunità di vendita è stata creata e assegnata a te. Si prega di rivedere i dettagli qui sotto e prendere le azioni appropriate.</p><p><strong>Dettagli dell\'Opportunità:</strong></p><ul><li>Nome dell\'Opportunità: {opportunity_name}</li><li>Account: {account_name}</li><li>Contatto: {contact_name}</li><li>Fase: {opportunity_stage}</li><li>Importo: {opportunity_amount}</li><li>Data di Chiusura: {opportunity_close_date}</li></ul><p><strong>Descrizione:</strong></p><p>{opportunity_description}</p><p>Si prega di accedere al sistema per visualizzare i dettagli completi dell\'opportunità e iniziare a lavorare su questa opportunità di vendita.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => '新しい営業機会が作成されました - {opportunity_name}',
                        'content' => '<p>こんにちは {assigned_user_name}さん、</p><p>新しい営業機会が作成され、あなたに割り当てられました。以下の詳細を確認し、適切な対応を取ってください。</p><p><strong>営業機会の詳細：</strong></p><ul><li>営業機会名：{opportunity_name}</li><li>アカウント：{account_name}</li><li>連絡先：{contact_name}</li><li>ステージ：{opportunity_stage}</li><li>金額：{opportunity_amount}</li><li>クローズ日：{opportunity_close_date}</li></ul><p><strong>説明：</strong></p><p>{opportunity_description}</p><p>システムにログインして営業機会の詳細を確認し、この営業機会の作業を開始してください。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Nieuwe Kans Aangemaakt - {opportunity_name}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Een nieuwe verkoopkans is aangemaakt en aan jou toegewezen. Bekijk de details hieronder en onderneem de juiste actie.</p><p><strong>Kans Details:</strong></p><ul><li>Kans Naam: {opportunity_name}</li><li>Account: {account_name}</li><li>Contact: {contact_name}</li><li>Fase: {opportunity_stage}</li><li>Bedrag: {opportunity_amount}</li><li>Sluitingsdatum: {opportunity_close_date}</li></ul><p><strong>Beschrijving:</strong></p><p>{opportunity_description}</p><p>Log in op het systeem om volledige kansdetails te bekijken en te beginnen met werken aan deze verkoopkans.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Nowa Szansa Utworzona - {opportunity_name}',
                        'content' => '<p>Cześć {assigned_user_name},</p><p>Nowa szansa sprzedażowa została utworzona i przypisana do Ciebie. Przejrzyj szczegóły poniżej i podejmij odpowiednie działania.</p><p><strong>Szczegóły Szansy:</strong></p><ul><li>Nazwa Szansy: {opportunity_name}</li><li>Konto: {account_name}</li><li>Kontakt: {contact_name}</li><li>Etap: {opportunity_stage}</li><li>Kwota: {opportunity_amount}</li><li>Data Zamknięcia: {opportunity_close_date}</li></ul><p><strong>Opis:</strong></p><p>{opportunity_description}</p><p>Zaloguj się do systemu, aby zobaczyć pełne szczegóły szansy i rozpocząć pracę nad tą szansą sprzedażową.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Nova Oportunidade Criada - {opportunity_name}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>Uma nova oportunidade de vendas foi criada e atribuída a si. Por favor reveja os detalhes abaixo e tome as medidas apropriadas.</p><p><strong>Detalhes da Oportunidade:</strong></p><ul><li>Nome da Oportunidade: {opportunity_name}</li><li>Conta: {account_name}</li><li>Contacto: {contact_name}</li><li>Fase: {opportunity_stage}</li><li>Montante: {opportunity_amount}</li><li>Data de Fecho: {opportunity_close_date}</li></ul><p><strong>Descrição:</strong></p><p>{opportunity_description}</p><p>Por favor faça login no sistema para ver os detalhes completos da oportunidade e começar a trabalhar nesta oportunidade de vendas.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Nova Oportunidade Criada - {opportunity_name}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>Uma nova oportunidade de vendas foi criada e atribuída a você. Por favor revise os detalhes abaixo e tome as medidas apropriadas.</p><p><strong>Detalhes da Oportunidade:</strong></p><ul><li>Nome da Oportunidade: {opportunity_name}</li><li>Conta: {account_name}</li><li>Contato: {contact_name}</li><li>Estágio: {opportunity_stage}</li><li>Valor: {opportunity_amount}</li><li>Data de Fechamento: {opportunity_close_date}</li></ul><p><strong>Descrição:</strong></p><p>{opportunity_description}</p><p>Por favor faça login no sistema para ver os detalhes completos da oportunidade e começar a trabalhar nesta oportunidade de vendas.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Создана новая возможность - {opportunity_name}',
                        'content' => '<p>Привет {assigned_user_name},</p><p>Новая возможность продаж была создана и назначена вам. Пожалуйста, просмотрите детали ниже и предпримите соответствующие действия.</p><p><strong>Детали Возможности:</strong></p><ul><li>Название Возможности: {opportunity_name}</li><li>Аккаунт: {account_name}</li><li>Контакт: {contact_name}</li><li>Этап: {opportunity_stage}</li><li>Сумма: {opportunity_amount}</li><li>Дата Закрытия: {opportunity_close_date}</li></ul><p><strong>Описание:</strong></p><p>{opportunity_description}</p><p>Пожалуйста, войдите в систему, чтобы просмотреть полные детали возможности и начать работу над этой возможностью продаж.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Yeni Fırsat Oluşturuldu - {opportunity_name}',
                        'content' => '<p>Merhaba {assigned_user_name},</p><p>Yeni bir satış fırsatı oluşturuldu ve size atandı. Lütfen aşağıdaki detayları inceleyin ve uygun eylemi gerçekleştirin.</p><p><strong>Fırsat Detayları:</strong></p><ul><li>Fırsat Adı: {opportunity_name}</li><li>Hesap: {account_name}</li><li>İletişim: {contact_name}</li><li>Aşama: {opportunity_stage}</li><li>Tutar: {opportunity_amount}</li><li>Kapanış Tarihi: {opportunity_close_date}</li></ul><p><strong>Açıklama:</strong></p><p>{opportunity_description}</p><p>Lütfen tam fırsat detaylarını görüntülemek ve bu satış fırsatı üzerinde çalışmaya başlamak için sisteme giriş yapın.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '新机会已创建 - {opportunity_name}',
                        'content' => '<p>你好 {assigned_user_name}，</p><p>一个新的销售机会已创建并分配给您。请查看以下详细信息并采取适当行动。</p><p><strong>机会详情：</strong></p><ul><li>机会名称：{opportunity_name}</li><li>客户：{account_name}</li><li>联系人：{contact_name}</li><li>阶段：{opportunity_stage}</li><li>金额：{opportunity_amount}</li><li>关闭日期：{opportunity_close_date}</li></ul><p><strong>描述：</strong></p><p>{opportunity_description}</p><p>请登录系统查看完整的机会详情并开始处理这个销售机会。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ]
                ]
            ],
            // Opportunity Status Changed
            [
                'name' => 'Opportunity Status Changed',
                'from' => 'Sales Team',
                'translations' => [
                    'en' => [
                        'subject' => 'Opportunity Stage Updated - {opportunity_name}',
                        'content' => '<p>Hello {assigned_user_name},</p><p>The stage of your opportunity has been updated from <strong>{old_opportunity_stage}</strong> to <strong>{new_opportunity_stage}</strong>.</p><p><strong>Opportunity Details:</strong></p><ul><li>Opportunity Name: {opportunity_name}</li><li>Account: {account_name}</li><li>Contact: {contact_name}</li><li>Current Stage: {new_opportunity_stage}</li><li>Amount: {opportunity_amount}</li><li>Close Date: {opportunity_close_date}</li></ul><p><strong>Description:</strong></p><p>{opportunity_description}</p><p>Please continue working on this opportunity and update the progress as needed.</p><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                    ],
                    'es' => [
                        'subject' => 'Etapa de Oportunidad Actualizada - {opportunity_name}',
                        'content' => '<p>Hola {assigned_user_name},</p><p>La etapa de su oportunidad ha sido actualizada de <strong>{old_opportunity_stage}</strong> a <strong>{new_opportunity_stage}</strong>.</p><p><strong>Detalles de la Oportunidad:</strong></p><ul><li>Nombre de la Oportunidad: {opportunity_name}</li><li>Cuenta: {account_name}</li><li>Contacto: {contact_name}</li><li>Etapa Actual: {new_opportunity_stage}</li><li>Monto: {opportunity_amount}</li><li>Fecha de Cierre: {opportunity_close_date}</li></ul><p><strong>Descripción:</strong></p><p>{opportunity_description}</p><p>Por favor continúe trabajando en esta oportunidad y actualice el progreso según sea necesario.</p><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                    ],
                    'ar' => [
                        'subject' => 'تم تحديث مرحلة الفرصة - {opportunity_name}',
                        'content' => '<p>مرحباً {assigned_user_name}،</p><p>تم تحديث مرحلة فرصتك من <strong>{old_opportunity_stage}</strong> إلى <strong>{new_opportunity_stage}</strong>.</p><p><strong>تفاصيل الفرصة:</strong></p><ul><li>اسم الفرصة: {opportunity_name}</li><li>الحساب: {account_name}</li><li>جهة الاتصال: {contact_name}</li><li>المرحلة الحالية: {new_opportunity_stage}</li><li>المبلغ: {opportunity_amount}</li><li>تاريخ الإغلاق: {opportunity_close_date}</li></ul><p><strong>الوصف:</strong></p><p>{opportunity_description}</p><p>يرجى الاستمرار في العمل على هذه الفرصة وتحديث التقدم حسب الحاجة.</p><p style="text-align: right;">أطيب التحيات،<br>{company_name}</p>'
                    ],
                    'da' => [
                        'subject' => 'Muligheds Fase Opdateret - {opportunity_name}',
                        'content' => '<p>Hej {assigned_user_name},</p><p>Fasen af din mulighed er blevet opdateret fra <strong>{old_opportunity_stage}</strong> til <strong>{new_opportunity_stage}</strong>.</p><p><strong>Muligheds Detaljer:</strong></p><ul><li>Muligheds Navn: {opportunity_name}</li><li>Konto: {account_name}</li><li>Kontakt: {contact_name}</li><li>Nuværende Fase: {new_opportunity_stage}</li><li>Beløb: {opportunity_amount}</li><li>Lukkedato: {opportunity_close_date}</li></ul><p><strong>Beskrivelse:</strong></p><p>{opportunity_description}</p><p>Fortsæt venligst med at arbejde på denne mulighed og opdater fremskridt efter behov.</p><p style="text-align: right;">Med venlig hilsen,<br>{company_name}</p>'
                    ],
                    'de' => [
                        'subject' => 'Verkaufschancen Phase Aktualisiert - {opportunity_name}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>Die Phase Ihrer Verkaufschance wurde von <strong>{old_opportunity_stage}</strong> auf <strong>{new_opportunity_stage}</strong> aktualisiert.</p><p><strong>Verkaufschancen Details:</strong></p><ul><li>Verkaufschancen Name: {opportunity_name}</li><li>Konto: {account_name}</li><li>Kontakt: {contact_name}</li><li>Aktuelle Phase: {new_opportunity_stage}</li><li>Betrag: {opportunity_amount}</li><li>Abschlussdatum: {opportunity_close_date}</li></ul><p><strong>Beschreibung:</strong></p><p>{opportunity_description}</p><p>Bitte arbeiten Sie weiter an dieser Verkaufschance und aktualisieren Sie den Fortschritt nach Bedarf.</p><p style="text-align: right;">Mit freundlichen Grüßen,<br>{company_name}</p>'
                    ],
                    'fr' => [
                        'subject' => 'Étape d\'Opportunité Mise à Jour - {opportunity_name}',
                        'content' => '<p>Bonjour {assigned_user_name},</p><p>L\'étape de votre opportunité a été mise à jour de <strong>{old_opportunity_stage}</strong> à <strong>{new_opportunity_stage}</strong>.</p><p><strong>Détails de l\'Opportunité:</strong></p><ul><li>Nom de l\'Opportunité: {opportunity_name}</li><li>Compte: {account_name}</li><li>Contact: {contact_name}</li><li>Étape Actuelle: {new_opportunity_stage}</li><li>Montant: {opportunity_amount}</li><li>Date de Clôture: {opportunity_close_date}</li></ul><p><strong>Description:</strong></p><p>{opportunity_description}</p><p>Veuillez continuer à travailler sur cette opportunité et mettre à jour les progrès selon les besoins.</p><p style="text-align: right;">Cordialement,<br>{company_name}</p>'
                    ],
                    'he' => [
                        'subject' => 'שלב ההזדמנות עודכן - {opportunity_name}',
                        'content' => '<p>שלום {assigned_user_name},</p><p>שלב ההזדמנות שלך עודכן מ<strong>{old_opportunity_stage}</strong> ל<strong>{new_opportunity_stage}</strong>.</p><p><strong>פרטי ההזדמנות:</strong></p><ul><li>שם ההזדמנות: {opportunity_name}</li><li>חשבון: {account_name}</li><li>איש קשר: {contact_name}</li><li>שלב נוכחי: {new_opportunity_stage}</li><li>סכום: {opportunity_amount}</li><li>תאריך סגירה: {opportunity_close_date}</li></ul><p><strong>תיאור:</strong></p><p>{opportunity_description}</p><p>אנא המשך לעבוד על הזדמנות זו ועדכן את ההתקדמות לפי הצורך.</p><p style="text-align: right;">בברכה,<br>{company_name}</p>'
                    ],
                    'it' => [
                        'subject' => 'Fase Opportunità Aggiornata - {opportunity_name}',
                        'content' => '<p>Ciao {assigned_user_name},</p><p>La fase della tua opportunità è stata aggiornata da <strong>{old_opportunity_stage}</strong> a <strong>{new_opportunity_stage}</strong>.</p><p><strong>Dettagli dell\'Opportunità:</strong></p><ul><li>Nome dell\'Opportunità: {opportunity_name}</li><li>Account: {account_name}</li><li>Contatto: {contact_name}</li><li>Fase Attuale: {new_opportunity_stage}</li><li>Importo: {opportunity_amount}</li><li>Data di Chiusura: {opportunity_close_date}</li></ul><p><strong>Descrizione:</strong></p><p>{opportunity_description}</p><p>Si prega di continuare a lavorare su questa opportunità e aggiornare i progressi secondo necessità.</p><p style="text-align: right;">Cordiali saluti,<br>{company_name}</p>'
                    ],
                    'ja' => [
                        'subject' => '営業機会のステージが更新されました - {opportunity_name}',
                        'content' => '<p>こんにちは {assigned_user_name}さん、</p><p>あなたの営業機会のステージが<strong>{old_opportunity_stage}</strong>から<strong>{new_opportunity_stage}</strong>に更新されました。</p><p><strong>営業機会の詳細：</strong></p><ul><li>営業機会名：{opportunity_name}</li><li>アカウント：{account_name}</li><li>連絡先：{contact_name}</li><li>現在のステージ：{new_opportunity_stage}</li><li>金額：{opportunity_amount}</li><li>クローズ日：{opportunity_close_date}</li></ul><p><strong>説明：</strong></p><p>{opportunity_description}</p><p>この営業機会の作業を継続し、必要に応じて進捗を更新してください。</p><p style="text-align: right;">よろしくお願いします、<br>{company_name}</p>'
                    ],
                    'nl' => [
                        'subject' => 'Kans Fase Bijgewerkt - {opportunity_name}',
                        'content' => '<p>Hallo {assigned_user_name},</p><p>De fase van jouw kans is bijgewerkt van <strong>{old_opportunity_stage}</strong> naar <strong>{new_opportunity_stage}</strong>.</p><p><strong>Kans Details:</strong></p><ul><li>Kans Naam: {opportunity_name}</li><li>Account: {account_name}</li><li>Contact: {contact_name}</li><li>Huidige Fase: {new_opportunity_stage}</li><li>Bedrag: {opportunity_amount}</li><li>Sluitingsdatum: {opportunity_close_date}</li></ul><p><strong>Beschrijving:</strong></p><p>{opportunity_description}</p><p>Ga door met werken aan deze kans en werk de voortgang bij indien nodig.</p><p style="text-align: right;">Met vriendelijke groet,<br>{company_name}</p>'
                    ],
                    'pl' => [
                        'subject' => 'Etap Szansy Zaktualizowany - {opportunity_name}',
                        'content' => '<p>Cześć {assigned_user_name},</p><p>Etap Twojej szansy został zaktualizowany z <strong>{old_opportunity_stage}</strong> na <strong>{new_opportunity_stage}</strong>.</p><p><strong>Szczegóły Szansy:</strong></p><ul><li>Nazwa Szansy: {opportunity_name}</li><li>Konto: {account_name}</li><li>Kontakt: {contact_name}</li><li>Obecny Etap: {new_opportunity_stage}</li><li>Kwota: {opportunity_amount}</li><li>Data Zamknięcia: {opportunity_close_date}</li></ul><p><strong>Opis:</strong></p><p>{opportunity_description}</p><p>Kontynuuj pracę nad tą szansą i aktualizuj postęp w razie potrzeby.</p><p style="text-align: right;">Z poważaniem,<br>{company_name}</p>'
                    ],
                    'pt' => [
                        'subject' => 'Fase da Oportunidade Actualizada - {opportunity_name}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>A fase da sua oportunidade foi actualizada de <strong>{old_opportunity_stage}</strong> para <strong>{new_opportunity_stage}</strong>.</p><p><strong>Detalhes da Oportunidade:</strong></p><ul><li>Nome da Oportunidade: {opportunity_name}</li><li>Conta: {account_name}</li><li>Contacto: {contact_name}</li><li>Fase Actual: {new_opportunity_stage}</li><li>Montante: {opportunity_amount}</li><li>Data de Fecho: {opportunity_close_date}</li></ul><p><strong>Descrição:</strong></p><p>{opportunity_description}</p><p>Por favor continue a trabalhar nesta oportunidade e actualize o progresso conforme necessário.</p><p style="text-align: right;">Cumprimentos,<br>{company_name}</p>'
                    ],
                    'pt-BR' => [
                        'subject' => 'Estágio da Oportunidade Atualizado - {opportunity_name}',
                        'content' => '<p>Olá {assigned_user_name},</p><p>O estágio da sua oportunidade foi atualizado de <strong>{old_opportunity_stage}</strong> para <strong>{new_opportunity_stage}</strong>.</p><p><strong>Detalhes da Oportunidade:</strong></p><ul><li>Nome da Oportunidade: {opportunity_name}</li><li>Conta: {account_name}</li><li>Contato: {contact_name}</li><li>Estágio Atual: {new_opportunity_stage}</li><li>Valor: {opportunity_amount}</li><li>Data de Fechamento: {opportunity_close_date}</li></ul><p><strong>Descrição:</strong></p><p>{opportunity_description}</p><p>Por favor continue trabalhando nesta oportunidade e atualize o progresso conforme necessário.</p><p style="text-align: right;">Atenciosamente,<br>{company_name}</p>'
                    ],
                    'ru' => [
                        'subject' => 'Этап возможности обновлен - {opportunity_name}',
                        'content' => '<p>Привет {assigned_user_name},</p><p>Этап вашей возможности был обновлен с <strong>{old_opportunity_stage}</strong> на <strong>{new_opportunity_stage}</strong>.</p><p><strong>Детали Возможности:</strong></p><ul><li>Название Возможности: {opportunity_name}</li><li>Аккаунт: {account_name}</li><li>Контакт: {contact_name}</li><li>Текущий Этап: {new_opportunity_stage}</li><li>Сумма: {opportunity_amount}</li><li>Дата Закрытия: {opportunity_close_date}</li></ul><p><strong>Описание:</strong></p><p>{opportunity_description}</p><p>Пожалуйста, продолжайте работу над этой возможностью и обновляйте прогресс по мере необходимости.</p><p style="text-align: right;">С уважением,<br>{company_name}</p>'
                    ],
                    'tr' => [
                        'subject' => 'Fırsat Aşaması Güncellendi - {opportunity_name}',
                        'content' => '<p>Merhaba {assigned_user_name},</p><p>Fırsatınızın aşaması <strong>{old_opportunity_stage}</strong> seviyesinden <strong>{new_opportunity_stage}</strong> seviyesine güncellendi.</p><p><strong>Fırsat Detayları:</strong></p><ul><li>Fırsat Adı: {opportunity_name}</li><li>Hesap: {account_name}</li><li>İletişim: {contact_name}</li><li>Mevcut Aşama: {new_opportunity_stage}</li><li>Tutar: {opportunity_amount}</li><li>Kapanış Tarihi: {opportunity_close_date}</li></ul><p><strong>Açıklama:</strong></p><p>{opportunity_description}</p><p>Lütfen bu fırsat üzerinde çalışmaya devam edin ve gerektiğinde ilerlemeyi güncelleyin.</p><p style="text-align: right;">Saygılarımızla,<br>{company_name}</p>'
                    ],
                    'zh' => [
                        'subject' => '机会阶段已更新 - {opportunity_name}',
                        'content' => '<p>你好 {assigned_user_name}，</p><p>您的机会阶段已从<strong>{old_opportunity_stage}</strong>更新为<strong>{new_opportunity_stage}</strong>。</p><p><strong>机会详情：</strong></p><ul><li>机会名称：{opportunity_name}</li><li>客户：{account_name}</li><li>联系人：{contact_name}</li><li>当前阶段：{new_opportunity_stage}</li><li>金额：{opportunity_amount}</li><li>关闭日期：{opportunity_close_date}</li></ul><p><strong>描述：</strong></p><p>{opportunity_description}</p><p>请继续处理这个机会并根据需要更新进度。</p><p style="text-align: right;">此致敬礼，<br>{company_name}</p>'
                    ],
                ]
                ],
        ];

        foreach ($templates as $templateData) {
            $existingTemplate = EmailTemplate::where('name', $templateData['name'])->first();

            if ($existingTemplate) {
                continue;
            }

            $template = EmailTemplate::create([
                'name' => $templateData['name'],
                'from' => $templateData['from'],
                'user_id' => 1
            ]);

            foreach ($langCodes as $langCode) {
                $translation = $templateData['translations'][$langCode] ?? $templateData['translations']['en'];

                EmailTemplateLang::create([
                    'parent_id' => $template->id,
                    'lang' => $langCode,
                    'subject' => $translation['subject'],
                    'content' => $translation['content']
                ]);
            }

            UserEmailTemplate::create([
                'template_id' => $template->id,
                'user_id' => 1,
                'is_active' => true
            ]);
        }
    }
}
