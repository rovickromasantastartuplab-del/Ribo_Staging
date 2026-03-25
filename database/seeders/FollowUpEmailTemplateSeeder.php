<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class FollowUpEmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = [
            'name' => 'Conversation Follow Up',
            'from' => 'Support Team',
            'translations' => [
                'en' => [
                    'subject' => 'Follow-up Required: {thread_subject}',
                    'content' => '<p>Hello {assigned_user_name},</p><p>A conversation thread requires your attention for follow-up.</p><p><strong>Subject:</strong> {thread_subject}</p><div style="text-align: center; margin: 30px 0;"><a href="{view_link}" style="background-color: {primary_color}; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">View Conversation</a></div><p style="text-align: right;">Best regards,<br>{company_name}</p>'
                ],
                // Add basic translations mirroring the English one to ensure it populates
                'es' => [
                    'subject' => 'Seguimiento Requerido: {thread_subject}',
                    'content' => '<p>Hola {assigned_user_name},</p><p>Un hilo de conversación requiere su atención para seguimiento.</p><p><strong>Asunto:</strong> {thread_subject}</p><div style="text-align: center; margin: 30px 0;"><a href="{view_link}" style="background-color: {primary_color}; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">Ver Conversación</a></div><p style="text-align: right;">Saludos cordiales,<br>{company_name}</p>'
                ]
            ]
        ];

        $emailTemplate = EmailTemplate::firstOrCreate(
            ['name' => $template['name']],
            ['from' => $template['from']]
        );

        foreach ($template['translations'] as $langCode => $translation) {
            $emailTemplate->emailTemplateLangs()->updateOrCreate(
                ['lang' => $langCode],
                [
                    'subject' => $translation['subject'],
                    'content' => $translation['content']
                ]
            );
        }
    }
}
