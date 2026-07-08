<?php

return [
    'prompts' => [
        'group_keywords' => [
            'name' => 'Group Keywords',
            'description' => 'Groups SEO keywords into semantic clusters.',
            'system_prompt' => 'You are an expert SEO Architect and Search Analyst. Analyze search queries for a website and group them into logical, semantic topics (clusters) based on SERP overlap potential. Choose one primary keyword representing the highest volume, group secondary keywords, map user intent (informational, commercial, transactional, navigational, local, support), recommend a content layout, and suggest a clear next step.',
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
                                'group_intent' => [
                                    'type' => 'string',
                                    'enum' => ['informational', 'commercial', 'transactional', 'navigational', 'local', 'support', 'mixed', 'unknown']
                                ],
                                'content_type' => [
                                    'type' => 'string',
                                    'enum' => ['blog_article', 'buying_guide', 'category_page_improvement', 'product_page_improvement', 'faq_block', 'comparison_page', 'landing_page', 'support_article', 'no_content_needed']
                                ],
                                'recommended_action' => [
                                    'type' => 'string',
                                    'enum' => ['create_new_page', 'improve_existing_page', 'rewrite_meta', 'add_faq', 'merge_with_existing_content', 'no_action']
                                ],
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
            'system_prompt' => 'You are a Senior SEO Content Strategist and Information Architect. Generate a comprehensive SEO content brief. Design an optimized title (under 60 chars) and meta description (150-160 chars) containing the primary keyword, map out a logical header structure (H2/H3), identify target FAQs, and outline concrete editorial guidelines on semantic density, tone, and formatting.',
            'user_prompt' => "Create a brief for this topic:\nPrimary Keyword: {primary_keyword}\nSecondary Keywords: {secondary_keywords}\nIntent: {intent}\n\nSite Context: {site_context}",
            'output_format' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'meta_title' => ['type' => 'string'],
                    'meta_description' => ['type' => 'string'],
                    'h1' => ['type' => 'string'],
                    'search_intent' => [
                        'type' => 'string',
                        'enum' => ['informational', 'commercial', 'transactional', 'navigational', 'local', 'support', 'mixed', 'unknown']
                    ],
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
            'system_prompt' => 'You are an expert SEO Copywriter and Subject Matter Expert. Write a comprehensive, engaging, and helpful article in clean semantic HTML (body tags only: <h2>, <h3>, <p>, <ul>, <li>, <strong>). Integrate primary and secondary keywords naturally without stuffing. Structure with short paragraphs and bold callouts for readability. Always include a dedicated FAQ section at the end.',
            'user_prompt' => "Here is the content brief:\n{brief}\n\nPlease write the full article in {language} in HTML format under these strict requirements:\n- Target keyword repeat density: Approximately {density}% of the total word count.\n- Target article length: Approximately {length} words.\n- Additional context/editorial hint: {hint}",
            'output_format' => null
        ],
        'review_content' => [
            'name' => 'Review Content Quality',
            'description' => 'Analyzes a draft against Google\'s Helpful Content guidelines.',
            'system_prompt' => 'You are an Editor-in-Chief and Google Search Quality Rater. Evaluate the draft against Google\'s Helpful Content Guidelines, E-E-A-T criteria, intent satisfaction, and keyword usage. Provide a quality score out of 100, a list of actionable improvements, and a boolean approval decision indicating if it is ready for publishing.',
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
