# Limaudio Task Manager API — Документация

Простой PHP API для управления задачами c JWT-аутентификацией, загрузкой файлов и уведомлениями в Telegram.

## Быстрый старт

1) Требования
- PHP 8.1+
- Herd/Apache/Nginx, корневая папка `public/`
- SQLite (встроен в PHP)

2) Установка
- Опционально: `composer install` (для автозагрузки). Без Composer работает встроенный автозагрузчик.
- Убедитесь, что сайт указывает на `public/`.
- Перезапустите сервер после редактирования `.env`.

3) Переменные окружения `.env`
- AUTH_SECRET=секрет_для_JWT (обязательно)
- TELEGRAM_BOT_TOKEN=токен_бота (опционально)
- TELEGRAM_CHAT_ID=ID_общего_чата (опционально, как запасной вариант)

Файл `.env` расположен в корне проекта.

## Аутентификация
- Тип: Bearer JWT
- Получить токен: `POST /auth/login`
- Использовать: заголовок `Authorization: Bearer <jwt>`
- Срок жизни токена: 12 часов (можно изменить в `Jwt::sign`)

### Вход (логин)
- `POST /auth/login`
- Тело JSON: { "email": "user@example.com", "password": "Secret123!" }
- Ответ 200:
  {
    "token": "...",
    "user": { "id": 1, "name": "...", "email": "...", "telegram_id": "...", "roles": ["Администратор"] }
  }
- Ошибки: 401 (invalid credentials), 422 (не хватает полей)

## Роли
- Доступные роли: `Администратор`, `Менеджер по продажам`
- Создание пользователей доступно только роли `Администратор`.

## Пользователи

### Создать пользователя
- `POST /users` (требуется Bearer JWT администратора)
- Тело JSON:
  {
    "name": "Имя",
    "email": "email@example.com",
    "password": "Secret123!",
    "roles": ["Администратор"],
    "telegram_id": "123456789"   // опционально, личные уведомления
  }
- Ответ 201: объект пользователя (без пароля), включая `roles` и `telegram_id`
- Ошибки: 403 (нужна роль Администратор), 409 (email существует), 422 (валидация)

### Список пользователей
- `GET /users`
- Параметры: `limit` (по умолч. 50), `offset` (по умолч. 0)
- Ответ 200: { items: [...], limit, offset }

### Пользователь по id
- `GET /users/{id}`
- Ответ 200: объект пользователя
- Ошибка: 404

### Удалить пользователя
- `DELETE /users/{id}` (требуется Bearer JWT администратора)
- Ответ 200: `{ "deleted": <id> }`
- Ошибка: 404 (если пользователь не найден)

## Справочник «Направления»
- Предзаполнено: «Строительство», «Дистрибуция», «Партнерская программа»
- Таблица: `directions`
- При необходимости можно добавить CRUD (по запросу).

## Загрузка файлов

### Загрузить файл
- `POST /upload` (требуется Bearer JWT)
- Формат: `multipart/form-data`, поле `file`
- Ответ 201:
  { "file_name": "original.ext", "file_url": "/uploads/<hash>.ext", "hash": "..." }
- Хранилище: `public/uploads/`

## Задачи
Статусы:
- «Новая» — по умолчанию
- «Ответственный назначен» — если при создании указан `assigned_user_id`

Модель:
- title (обязательно)
- description (текст)
- direction_id (ссылка на `directions`)
- due_at (ISO datetime, например: 2025-09-10T12:00:00Z)
- assigned_user_id (id пользователя)
- links (массив ссылок)
- files (массив объектов { file_name, file_url })
- comments (отдельная таблица)

### Создать задачу
- `POST /task` (требуется Bearer JWT)
- Тело JSON (пример):
  {
    "title": "Тема задачи",
    "description": "Описание",
    "direction_id": 1,
    "due_at": "2025-09-10T12:00:00Z",
    "assigned_user_id": 2,
    "links": ["https://example.com"],
    "files": [ { "file_name": "spec.pdf", "file_url": "/uploads/abcd1234.pdf" } ]
  }
- Ответ 201: объект задачи с `links`, `files`, `comments` (пустой массив)
- Ошибки: 401/403 (аутентификация), 422 (валидация)
- Уведомление в Telegram: личное исполнителю (если есть `telegram_id`), иначе — в общий чат.

### Список задач
- `GET /task`
- Параметры: `limit`, `offset`
- Ответ 200: { items: [...], limit, offset }

### Получить задачу
- `GET /task/{id}`
- Ответ 200: полная задача (+ ссылки, файлы, комментарии)
- Ошибка: 404

### Добавить комментарий к задаче
- `POST /task/{id}/comments` (требуется Bearer JWT)
- Тело JSON: { "text": "Комментарий" }
- Ответ 201: { id, task_id, user_id, text, created_at }
- Уведомление в Telegram: личное исполнителю (если есть `telegram_id`), иначе — в общий чат.

## Уведомления в Telegram
- Конфигурация `.env`: TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID
- Персональные уведомления: по `users.telegram_id`
- Фоллбек: в чат TELEGRAM_CHAT_ID

## Cron-скрипт дедлайнов
- Файл: `cron/task_deadlines.php`
- Логика: вычисляет процент оставшегося времени от (due_at - created_at) и отправляет напоминания при ~30%, ~10% и после истечения срока.
- Запуск в Windows Task Scheduler (пример):
  - Program/script: `php`
  - Arguments: `D:\Projects\limaudio-task-manager-api\cron\task_deadlines.php`
  - Периодичность: каждые 5-10 минут

## Безопасность
- Все небезопасные методы (POST/PUT/PATCH/DELETE) защищены Bearer JWT
- Создание пользователей — только для роли Администратор
- Данные паролей: `password_hash`/`password_verify`

## Ошибки и коды ответов
- 200 OK — успешная выборка
- 201 Created — успешно создано
- 204 No Content — preflight ответ
- 401 Unauthorized — нет/неверный токен
- 403 Forbidden — нет прав (например, требуется Администратор)
- 404 Not Found — не найдено
- 409 Conflict — конфликт (дубликат email)
- 422 Unprocessable Entity — валидация

## Примечания
- БД: SQLite файл `storage/database.sqlite` создается автоматически
- Автозагрузка: Composer (при наличии) или встроенный PSR-4
- При необходимости добавлю дополнительные эндпоинты (CRUD по направлениям, `/auth/me`, привязка `telegram_id` текущим пользователем и т.п.)

## Работа с файлами — практические сценарии

Ниже — проверенные способы работы с файлами (Postman и curl на Windows).

### Вариант A: Загрузить и прикрепить позже
1) Загрузка
- POST `/upload` (Bearer JWT)
- Body: form-data → ключ `file` (тип File)
- Ответ: `{ "file_name", "file_url", "hash" }`

2) Прикрепление к задаче
- POST `/task/{id}/files` (Bearer JWT)
- Body: raw → JSON:
  `{ "file_name": "имя_видаемо_пользователю.pdf", "file_url": "/uploads/<hash>.pdf" }`
- Ответ: `{ id, task_id, file_name, file_url }`

### Вариант B: Сразу прикрепить файл к задаче
- POST `/task/{id}/files` (Bearer JWT)
- Body: form-data → ключ `file` (тип File)
- Не задавайте вручную заголовок Content-Type — Postman сделает это сам.
- Ответ: `{ id, task_id, file_name, file_url }`

### Вариант C: Прикрепить на этапе создания задачи
- Поддерживается через ссылочный способ: предварительно загрузите файл (Вариант A/шаг 1),
  затем в `POST /task` передайте массив `files`:
  `"files": [ { "file_name": "spec.pdf", "file_url": "/uploads/abcd1234.pdf" } ]`
- Одновременная отправка multipart (файл) и JSON для `/task` в одном запросе пока не поддерживается.

### Примеры для Postman
- Загрузка: POST `/upload` → Body/form-data: `file` = <File>
- Привязка (multipart): POST `/task/{id}/files` → Body/form-data: `file` = <File>
- Привязка (JSON): POST `/task/{id}/files` → Body/raw(JSON): `{ "file_name": "...", "file_url": "/uploads/..." }`
- Всегда добавляйте Authorization: Bearer `<jwt>`

### Примеры для PowerShell (Windows)
- Загрузка:
  `curl.exe -F "file=@C:\\path\\to\\doc.pdf" -H "Authorization: Bearer <JWT>" https://your-domain/upload`
- Привязка (multipart):
  `curl.exe -F "file=@C:\\path\\to\\doc.pdf" -H "Authorization: Bearer <JWT>" https://your-domain/task/123/files`
- Привязка (JSON):
  `curl.exe -X POST -H "Authorization: Bearer <JWT>" -H "Content-Type: application/json" -d "{\"file_name\":\"doc.pdf\",\"file_url\":\"/uploads/abcd1234.pdf\"}" https://your-domain/task/123/files`

### Частые ошибки и решения
- Сообщение: `file_name and file_url are required` при форм-data —
  означает, что файл не был отправлен как часть multipart. Проверьте, что ключ именно `file` и тип `File`.
- Не задавайте вручную заголовок `Content-Type: multipart/form-data` — без boundary сервер не увидит файл; доверьте это Postman/curl.
- Большие файлы: увеличьте `upload_max_filesize` и `post_max_size` в php.ini, затем перезапустите сервер (Herd).
- Доступ: `file_url` доступен по публичному пути `/uploads/<hash>.<ext>` из `public/uploads/`.
