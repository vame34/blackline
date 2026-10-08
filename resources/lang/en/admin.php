<?php

return [
    'navigation' => [
        'back_to_site' => 'Back to website',
        'groups' => [
            'signals' => 'Signals',
            'billing' => 'Billing',
            'access' => 'Access',
            'content' => 'Content',
        ],
        'resources' => [
            'signals' => 'Signals',
            'signal_categories' => 'Signal categories',
            'users' => 'Users',
            'faq_items' => 'FAQ',
        ],
    ],
    'dashboard' => [
        'stats' => [
            'users_total' => 'Registered users',
            'users_paid_conversion' => 'Paid conversion: :percent%',
            'paid_users' => 'Users with payment',
            'api_users_30d' => 'API users (30d): :count',
            'revenue' => 'Revenue by currency',
            'active_api_tokens' => 'Active API tokens: :count',
        ],
        'api_usage' => [
            'heading' => 'API usage by users',
            'description' => 'Recently active API tokens and their owners.',
            'columns' => [
                'user' => 'User',
                'email' => 'Email',
                'token' => 'Token',
                'last_used_at' => 'Last used',
                'last_used_ip' => 'IP',
                'expires_at' => 'Expires',
                'active' => 'Active',
            ],
            'filters' => [
                'active_only' => 'Active only',
                'used_30d' => 'Used in last 30 days',
            ],
        ],
    ],
    'resources' => [
        'signal' => [
            'label' => 'Signal',
            'plural' => 'Signals',
            'fields' => [
                'category' => 'Category',
                'strategy_key' => 'Strategy key',
                'exchange' => 'Exchange',
                'symbol' => 'Symbol',
                'timeframe' => 'Timeframe',
                'direction' => 'Direction',
                'entry_at' => 'Entry time',
                'entry_price' => 'Entry price',
                'stop_loss' => 'Stop loss',
                'take_profit' => 'Take profit',
                'reward_risk' => 'Reward/risk',
                'status' => 'Status',
                'published_at' => 'Published at',
                'expires_at' => 'Expires at',
                'notes' => 'Notes',
            ],
            'options' => [
                'direction' => [
                    'long' => 'Long',
                    'short' => 'Short',
                ],
                'status' => [
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'closed' => 'Closed',
                    'archived' => 'Archived',
                ],
            ],
        ],
        'signal_category' => [
            'label' => 'Signal category',
            'plural' => 'Signal categories',
            'fields' => [
                'slug' => 'Slug',
                'name' => 'Name',
                'sort_order' => 'Sort order',
                'updated_at' => 'Updated',
            ],
        ],
        'signal_update' => [
            'label' => 'Signal update',
            'plural' => 'Signal updates',
            'fields' => [
                'type' => 'Type',
                'title' => 'Title',
                'body' => 'Body',
                'published_at' => 'Published at',
            ],
            'options' => [
                'type' => [
                    'published' => 'Published',
                    'entry' => 'Entry',
                    'level_update' => 'Level update',
                    'canceled' => 'Canceled',
                    'closed' => 'Closed',
                    'note' => 'Note',
                ],
            ],
        ],
        'user' => [
            'label' => 'User',
            'plural' => 'Users',
            'fields' => [
                'name' => 'Name',
                'email' => 'Email',
                'email_verified_at' => 'Email verified at',
                'banned_at' => 'Banned at',
                'banned' => 'Banned',
                'roles' => 'Roles',
                'password' => 'Password',
                'verified' => 'Verified',
                'created_at' => 'Created',
            ],
            'actions' => [
                'ban' => 'Ban',
                'unban' => 'Unban',
                'ban_selected' => 'Ban selected',
                'unban_selected' => 'Unban selected',
            ],
        ],
        'faq_item' => [
            'label' => 'FAQ item',
            'plural' => 'FAQ',
            'fields' => [
                'translations' => 'Translations',
                'question' => 'Question',
                'question_for' => 'Question (:locale)',
                'answer_for' => 'Answer (:locale)',
                'sort_order' => 'Sort order',
                'is_active' => 'Active',
                'updated_at' => 'Updated',
            ],
        ],
    ],
];
