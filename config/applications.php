<?php

$text = fn (string $label, bool $required = false) => ['label' => $label, 'type' => 'text', 'required' => $required];
$select = fn (string $label, array $options) => ['label' => $label, 'type' => 'select', 'options' => $options];
$area = fn (string $label, bool $required = false) => ['label' => $label, 'type' => 'textarea', 'required' => $required];
$number = fn (string $label, int $min = 0, int $max = 9999) => ['label' => $label, 'type' => 'number', 'min' => $min, 'max' => $max];

return [
    'admin_email' => env('ADMIN_EMAIL'),
    'max_document_kb' => 10240,
    'statuses' => ['new' => 'New', 'under_review' => 'Under Review', 'documents_required' => 'Documents Required', 'processing' => 'Processing', 'completed' => 'Completed', 'closed' => 'Closed'],
    'payment_statuses' => ['pending' => 'Pending', 'paid' => 'Paid', 'partially_paid' => 'Partially Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'],
    'services' => [
        'study_abroad' => [
            'label' => 'Study Abroad', 'relation' => 'studyDetails',
            'fields' => [
                'highest_qualification' => $text('Highest qualification'),
                'year_completed' => $number('Year completed', 1950, 2100),
                'institution' => $text('Current / previous institution'),
                'preferred_course' => $text('Preferred course', true),
                'preferred_study_level' => $select('Preferred study level', ['Foundation', 'Diploma', 'Bachelor’s degree', 'Master’s degree', 'Doctorate', 'MBBS', 'Other']),
                'preferred_countries' => $text('Preferred country / countries'),
                'approximate_budget' => $text('Approximate budget (include currency)'),
                'preferred_intake' => $text('Preferred intake'),
                'english_test' => $select('English test', ['IELTS', 'PTE', 'TOEFL', 'None']),
            ],
            'uploads' => ['passport' => 'Passport', 'academic_documents' => 'Academic documents'],
        ],
        'job_assistance' => [
            'label' => 'Job Assistance', 'relation' => 'jobDetails',
            'fields' => [
                'current_occupation' => $text('Current occupation'),
                'job_category' => $select('Job category', ['Healthcare & Nursing', 'Care Assistance', 'Logistics & Warehouse', 'Truck Drivers', 'Manufacturing & Factory', 'Construction', 'Hospitality', 'Skilled Technicians', 'General Workers', 'Other']) + ['required' => true],
                'years_experience' => $number('Years of experience', 0, 80),
                'current_employer' => $text('Current employer'),
                'gcc_residency' => $text('Current GCC residency (if applicable)'),
                'driving_license' => $select('Driving license', ['Yes', 'No']),
                'license_category' => $text('License category'),
                'preferred_country' => $text('Preferred country'),
                'english_level' => $select('English level', ['Beginner', 'Intermediate', 'Advanced', 'Fluent']),
                'other_languages' => $text('Other languages'),
            ],
            'uploads' => ['cv' => 'CV', 'passport' => 'Passport', 'residence_permit' => 'Residence permit', 'licenses_certificates' => 'Licenses / certificates'],
        ],
        'visa_services' => [
            'label' => 'Visa Services', 'relation' => 'visaDetails',
            'fields' => [
                'destination_country' => $text('Destination country', true),
                'visa_type' => $select('Visa type', ['Tourist / Visit', 'Business', 'Student', 'Employment / Work', 'Family / Dependent', 'Other']) + ['required' => true],
                'purpose_of_travel' => $area('Purpose of travel'),
                'proposed_travel_date' => ['label' => 'Proposed travel date', 'type' => 'date'],
                'duration' => $text('Duration of stay'),
                'current_residency' => $text('Current residency'),
                'previous_visa_history' => $area('Previous visa history'),
            ],
            'uploads' => ['passport' => 'Passport', 'residence_permit' => 'Residence permit', 'additional_documents' => 'Additional documents'],
        ],
        'supporting_documents' => [
            'label' => 'Supporting Documents', 'relation' => 'supportingDocumentDetails',
            'fields' => [
                'services_requested' => ['label' => 'Documents / services required', 'type' => 'checkboxes', 'required' => true, 'options' => ['Flight Itinerary', 'Hotel Reservation Assistance', 'Travel Insurance', 'Cover Letter', 'Travel Itinerary', 'Application Form Assistance', 'Other']],
                'destination' => $text('Destination'),
                'travel_start_date' => ['label' => 'Travel start date', 'type' => 'date'],
                'travel_end_date' => ['label' => 'Travel end date', 'type' => 'date'],
                'number_of_travelers' => $number('Number of travelers', 1, 500),
                'remarks' => $area('Remarks'),
            ], 'uploads' => [],
        ],
        'attestation' => [
            'label' => 'Attestation Services', 'relation' => 'attestationDetails',
            'fields' => [
                'document_type' => $select('Document type', ['Degree certificate', 'Diploma', 'School certificate', 'Transcript', 'Birth certificate', 'Marriage certificate', 'Commercial document', 'Other']) + ['required' => true],
                'issuing_country' => $text('Issuing country', true),
                'destination_country' => $text('Destination country', true),
                'purpose' => $area('Purpose'),
                'number_of_documents' => $number('Number of documents', 1, 500),
            ], 'uploads' => ['attestation_documents' => 'Documents for attestation'],
        ],
        'employer_request' => [
            'label' => 'Employer / Manpower Request', 'relation' => 'employerRequest',
            'fields' => [
                'company_name' => $text('Company name', true),
                'company_country' => $text('Company country', true),
                'industry' => $text('Industry'),
                'roles_required' => $area('Roles / skills required', true),
                'number_of_candidates' => $number('Number of candidates', 1, 10000),
                'work_location' => $text('Work location'),
                'preferred_start_date' => ['label' => 'Preferred start date', 'type' => 'date'],
                'requirements' => $area('Additional requirements'),
            ], 'uploads' => ['job_description' => 'Job description / requirements'],
        ],
        'other' => ['label' => 'Other', 'relation' => null, 'fields' => ['remarks' => $area('How can we help?', true)], 'uploads' => []],
    ],
];
