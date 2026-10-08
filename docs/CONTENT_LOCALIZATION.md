# Content localization / Локализация

Terminal UI strings live in `resources/lang/{locale}/ui.php` and `custom.php`; administrator strings live in `admin.php`. Update RU and EN together. Keep locale keys consistent with views.

Тексты терминала находятся в `resources/lang/{locale}/ui.php` и `custom.php`, админки — в `admin.php`. Обновляйте RU и EN вместе и сохраняйте соответствие ключей шаблонам.

Signal-category names may be translated by slug through `content.php`. Database values serve as the fallback. Project documentation has English `README.md` and Russian `README.ru.md` versions with a language switch.
