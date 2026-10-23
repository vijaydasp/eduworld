<?php

return [
    'company_name' => ['label' => 'Company name', 'default' => 'Edu World', 'rule' => 'string|max:120'],
    'address' => ['label' => 'Office address', 'default' => 'Segaya, Manama, Kingdom of Bahrain — Behind / Near Max & Megamart', 'rule' => 'string|max:500'],
    'email' => ['label' => 'Contact email', 'default' => 'info@eduworld.bh', 'rule' => 'email|max:190'],
    'phone' => ['label' => 'Primary phone', 'default' => '+973 33640001', 'rule' => 'string|max:40'],
    'phone_secondary' => ['label' => 'Secondary phone', 'default' => '+973 17264002', 'rule' => 'string|max:40'],
    'whatsapp' => ['label' => 'WhatsApp number (country code included)', 'default' => '97333640001', 'rule' => 'regex:/^\+?[0-9]{8,15}$/'],
    'facebook_url' => ['label' => 'Facebook URL', 'default' => '', 'rule' => 'url:https|max:500'],
    'instagram_url' => ['label' => 'Instagram URL', 'default' => '', 'rule' => 'url:https|max:500'],
    'youtube_url' => ['label' => 'YouTube URL', 'default' => '', 'rule' => 'url:https|max:500'],
    'map_url' => ['label' => 'Google Maps embed URL', 'default' => 'https://www.google.com/maps?q=Segaya%2C%20Manama%2C%20Bahrain&output=embed', 'rule' => 'url:https|max:1000'],
    'default_seo_title' => ['label' => 'Default SEO title', 'default' => 'Edu World Bahrain | Study Abroad, Job Assistance & Visa Services', 'rule' => 'string|max:150'],
    'default_meta_description' => ['label' => 'Default meta description', 'default' => 'Edu World Bahrain provides study abroad assistance, international job assistance, visa services, supporting documents and attestation services. Apply online today.', 'rule' => 'string|max:300'],
    'business_hours' => ['label' => 'Business hours', 'default' => 'Contact our team to arrange your visit.', 'rule' => 'string|max:500'],
    'currency' => ['label' => 'Currency', 'default' => 'BHD', 'rule' => 'in:BHD'],
    'application_notification_email' => ['label' => 'Application notification email', 'default' => '', 'rule' => 'email|max:190'],
];
