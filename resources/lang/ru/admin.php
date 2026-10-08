<?php

return [
    'navigation' => [
        'back_to_site' => 'Вернуться на сайт',
        'groups' => [
            'signals' => 'Сигналы',
            'billing' => 'Биллинг',
            'access' => 'Доступ',
            'content' => 'Контент',
        ],
        'resources' => [
            'signals' => 'Сигналы',
            'signal_categories' => 'Категории сигналов',
            'users' => 'Пользователи',
            'faq_items' => 'FAQ',
        ],
    ],
    'dashboard' => [
        'stats' => [
            'users_total' => 'Зарегистрировано пользователей',
            'users_paid_conversion' => 'Конверсия в оплату: :percent%',
            'paid_users' => 'Пользователи с оплатой',
            'api_users_30d' => 'API-пользователи (30д): :count',
            'revenue' => 'Выручка по валютам',
            'active_api_tokens' => 'Активных API токенов: :count',
        ],
        'api_usage' => [
            'heading' => 'Использование API пользователями',
            'description' => 'Недавно активные API токены и их владельцы.',
            'columns' => [
                'user' => 'Пользователь',
                'email' => 'Email',
                'token' => 'Токен',
                'last_used_at' => 'Последнее использование',
                'last_used_ip' => 'IP',
                'expires_at' => 'Истекает',
                'active' => 'Активен',
            ],
            'filters' => [
                'active_only' => 'Только активные',
                'used_30d' => 'Использовались за 30 дней',
            ],
        ],
    ],
    'resources' => [
        'signal' => [
            'label' => 'Сигнал',
            'plural' => 'Сигналы',
            'fields' => [
                'category' => 'Категория',
                'strategy_key' => 'Ключ стратегии',
                'exchange' => 'Биржа',
                'symbol' => 'Символ',
                'timeframe' => 'Таймфрейм',
                'direction' => 'Направление',
                'entry_at' => 'Время входа',
                'entry_price' => 'Цена входа',
                'stop_loss' => 'Стоп-лосс',
                'take_profit' => 'Тейк-профит',
                'reward_risk' => 'Профит/риск',
                'status' => 'Статус',
                'published_at' => 'Опубликован',
                'expires_at' => 'Истекает',
                'notes' => 'Заметки',
            ],
            'options' => [
                'direction' => [
                    'long' => 'Лонг',
                    'short' => 'Шорт',
                ],
                'status' => [
                    'draft' => 'Черновик',
                    'published' => 'Опубликован',
                    'closed' => 'Закрыт',
                    'archived' => 'Архив',
                ],
            ],
        ],
        'signal_category' => [
            'label' => 'Категория сигнала',
            'plural' => 'Категории сигналов',
            'fields' => [
                'slug' => 'Слаг',
                'name' => 'Название',
                'sort_order' => 'Порядок',
                'updated_at' => 'Обновлено',
            ],
        ],
        'signal_update' => [
            'label' => 'Обновление сигнала',
            'plural' => 'Обновления сигналов',
            'fields' => [
                'type' => 'Тип',
                'title' => 'Заголовок',
                'body' => 'Текст',
                'published_at' => 'Опубликовано',
            ],
            'options' => [
                'type' => [
                    'published' => 'Публикация',
                    'entry' => 'Вход',
                    'level_update' => 'Обновление уровней',
                    'canceled' => 'Отмена',
                    'closed' => 'Закрытие',
                    'note' => 'Заметка',
                ],
            ],
        ],
        'user' => [
            'label' => 'Пользователь',
            'plural' => 'Пользователи',
            'fields' => [
                'name' => 'Имя',
                'email' => 'Email',
                'email_verified_at' => 'Почта подтверждена',
                'banned_at' => 'Заблокирован до',
                'banned' => 'Заблокирован',
                'roles' => 'Роли',
                'password' => 'Пароль',
                'verified' => 'Подтвержден',
                'created_at' => 'Создан',
            ],
            'actions' => [
                'ban' => 'Заблокировать',
                'unban' => 'Разблокировать',
                'ban_selected' => 'Заблокировать выбранных',
                'unban_selected' => 'Разблокировать выбранных',
            ],
        ],
        'faq_item' => [
            'label' => 'Пункт FAQ',
            'plural' => 'FAQ',
            'fields' => [
                'translations' => 'Переводы',
                'question' => 'Вопрос',
                'question_for' => 'Вопрос (:locale)',
                'answer_for' => 'Ответ (:locale)',
                'sort_order' => 'Порядок',
                'is_active' => 'Активен',
                'updated_at' => 'Обновлено',
            ],
        ],
    ],
];
