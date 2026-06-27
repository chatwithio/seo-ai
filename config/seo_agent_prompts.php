<?php

return [
    'prompts' => [
        'group_keywords' => [
            'name' => 'Group Keywords',
            'description' => 'Groups SEO keywords into semantic clusters.',
            'system_prompt' => 'You are an expert SEO architect. Group these keywords by search intent, semantics, and target page type. Respond with JSON.',
            'user_prompt' => "Here are the keywords for the site {site_url}:\n\n{keywords}\n\nPlease group them into topics.",
            'output_format' => [
                'type' => 'object',
                'properties' => [
                    'groups' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'group_name' => ['type' => 'string'],
                                'primary_keyword' => ['type' => 'string'],
                                'secondary_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'group_intent' => ['type' => 'string'],
                                'content_type' => ['type' => 'string'],
                                'recommended_action' => ['type' => 'string'],
                                'ai_summary' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ]
        ],
        'generate_brief' => [
            'name' => 'Generate Content Brief',
            'description' => 'Generates an SEO content brief from a keyword group.',
            'system_prompt' => 'You are a Senior SEO Content Strategist. Create a detailed content brief based on the provided keywords. Include H1, Meta, Outline, and FAQs. Respond with JSON.',
            'user_prompt' => "Create a brief for this topic:\nPrimary Keyword: {primary_keyword}\nSecondary Keywords: {secondary_keywords}\nIntent: {intent}\n\nSite Context: {site_context}",
            'output_format' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'meta_title' => ['type' => 'string'],
                    'meta_description' => ['type' => 'string'],
                    'h1' => ['type' => 'string'],
                    'search_intent' => ['type' => 'string'],
                    'outline' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'faq_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'must_answer_questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'seo_notes' => ['type' => 'string']
                ]
            ]
        ],
        'generate_draft' => [
            'name' => 'Generate Content Draft',
            'description' => 'Generates a full HTML article from a brief.',
            'system_prompt' => 'You are an expert SEO Content Writer. Write a comprehensive, highly engaging, and helpful article based on the brief. Format the output in proper semantic HTML (only the body content).',
            'user_prompt' => "Here is the content brief:\n{brief}\n\nPlease write the full article in HTML format.",
            'output_format' => null
        ],
        'review_content' => [
            'name' => 'Review Content Quality',
            'description' => 'Analyzes a draft against Google\'s Helpful Content guidelines.',
            'system_prompt' => 'You are an Editor in Chief specializing in Google Helpful Content guidelines. Review the following draft. Provide a score out of 100 and a list of improvements. Respond with JSON.',
            'user_prompt' => "Brief:\n{brief}\n\nDraft:\n{draft}\n\nPlease review it.",
            'output_format' => [
                'type' => 'object',
                'properties' => [
                    'score' => ['type' => 'number'],
                    'improvements' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'is_approved' => ['type' => 'boolean']
                ]
            ]
        ]
    ]
];
