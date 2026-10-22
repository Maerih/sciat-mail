<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

/** Starter templates shared by every client (workspace_id = null). Safe to re-run. */
class TemplateSeeder extends Seeder
{
    public const TEMPLATES = [
        'newsletter' => 'Newsletter',
        'promotion' => 'Promotion — discount code',
        'announcement' => 'Announcement',
        'simple-letter' => 'Simple personal letter',
        'event' => 'Event invitation',
    ];

    /** SCIAT AFRICA cybersecurity set (category "cybersecurity"). */
    public const CYBERSECURITY = [
        'sciat-cyber-news' => 'SCIAT Cyber News — monthly newsletter',
        'sciat-welcome-cyber-news' => 'SCIAT Cyber News — welcome new subscriber',
        'sciat-threat-alert' => 'Threat advisory — urgent alert',
        'sciat-security-tip' => 'Security tip of the week',
        'sciat-phishing-awareness' => 'Phishing awareness — how to spot a scam',
        'sciat-blog-digest' => 'Blog digest — latest articles',
        'sciat-research-report' => 'Research report / whitepaper download',
        'sciat-product-xdr' => 'Product spotlight — SCIAT XDR',
        'sciat-products-overview' => 'Product portfolio — the SCIAT Security Platform',
        'sciat-service-pentest' => 'Service spotlight — penetration testing',
        'sciat-services-overview' => 'Services overview — full-spectrum security',
        'sciat-incident-response' => 'Incident response — 24/7 retainer',
        'sciat-managed-plans' => 'Managed security plans — careBasic, carePlus, carePro',
        'sciat-offer-assessment' => 'Special offer — complimentary threat-surface review',
        'sciat-training-invite' => 'SCIAT Academy — training invitation',
        'sciat-webinar-invite' => 'Webinar / briefing invitation',
        'sciat-industry-banking' => 'Industry brief — banking & fintech',
        'sciat-client-onboarding' => 'Client onboarding — welcome & next steps',
        'sciat-re-engagement' => 'Re-engagement — still interested?',
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $file => $name) {
            Template::updateOrCreate(
                ['workspace_id' => null, 'name' => $name],
                ['category' => 'starter', 'html' => file_get_contents(database_path("templates/{$file}.html"))]
            );
        }
        foreach (self::CYBERSECURITY as $file => $name) {
            Template::updateOrCreate(
                ['workspace_id' => null, 'name' => $name],
                ['category' => 'cybersecurity', 'html' => file_get_contents(database_path("templates/{$file}.html"))]
            );
        }
    }
}
