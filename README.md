# smstraffic — Bitrix: SMS через SmartDelivery (SMS Traffic BY)

Локальный модуль для **1С-Битрикс**: регистрация отправителя в «Службе сообщений» (`messageservice`, событие `onGetSmsSenders`), отправка SMS по HTTP API **SmartDelivery** (`sds.smstraffic.by`).

## Установка

1. Положите каталог в `local/modules/smstraffic/` (или подключите этот репозиторий как **git submodule** в этот путь).
2. **Настройки → Marketplace → Установленные решения → Модули** — установите модуль `sms traffic` / `smstraffic`.
3. Откройте настройки модуля (`/bitrix/admin/settings.php?mid=smstraffic`), задайте логин и пароль от SmartDelivery, при необходимости — имена отправителя (`originator`), `rus`, маршрут.
4. В настройках **Главного модуля** (вкладка «Почта и СМС») выберите сервис с идентификатором `smstraffic_smartdelivery`.

Не забудьте занести **IP сервера** в whitelist у SMS Traffic для API.

## Git submodule для проекта сайта

В корне репозитория сайта:

```bash
git submodule add https://github.com/dimabresky/smstraffic.git local/modules/smstraffic
git submodule update --init --recursive
```

Если каталог уже существовал без submodule, перенесите файлы во временную папку, выполните `git submodule add`, затем скопируйте файлы в клон и закоммитьте в репозитории модуля.

## Требования

- Битрикс с модулем **messageservice**.
- PHP с расширениями `curl` / сетевыми возможностями `Bitrix\Main\Web\HttpClient`.

## Устройство кода и классы

### Файлы верхнего уровня

| Файл | Назначение |
|------|------------|
| `include.php` | Подключается ядром при `Loader::includeModule('smstraffic')`: регистрирует автозагрузку классов из `lib/` (namespace `Smstraffic`). |
| `options.php` | Админка настроек модуля: логин/пароль SmartDelivery, список originator, `rus`, маршрут, базовые URL API. |
| `default_option.php` | Значения опций по умолчанию (те же ключи, что сохраняет `options.php`). |
| `install/index.php` | Класс модуля `smstraffic` (`\CModule`): установка/удаление, регистрация обработчика события `messageservice` → `onGetSmsSenders`. |

### Поток работы

1. При **установке** модуль регистрируется в `ModuleManager` и на событие **`onGetSmsSenders`** вешается статический метод `\Smstraffic\Handlers::onGetSmsSenders`.
2. Обработчик возвращает массив с одним экземпляром **`Smstraffic\Sender\SmartDelivery`** — это провайдер SMS для модуля «Служба сообщений».
3. При отправке SMS ядро вызывает **`SmartDelivery::sendMessage()`** с полями `MESSAGE_TO`, `MESSAGE_BODY`, `MESSAGE_FROM` и т.д.
4. Класс нормализует номера, готовит тело через **`Bitrix\MessageService\Sender\Base::prepareMessageBodyForSend()`** (родительский метод), собирает POST-параметры API и передаёт их в **`Smstraffic\SmartDelivery\ApiClient::send()`**.
5. **`ApiClient`** добавляет `login`/`password` из настроек, выполняет POST на `…/multi.php` основного хоста; при коде API **1000** или сетевой ошибке повторяет запрос на **резервный** базовый URL. Ответ разбирается как XML (`result`, `code`, `description`).

### `Smstraffic\Handlers`

- **`onGetSmsSenders(): SmartDelivery[]`** — отдаёт список отправителей для ядра (обычно один экземпляр провайдера); идентификатор в настройках сайта: константа **`SmartDelivery::ID`** = `smstraffic_smartdelivery`.

### `Smstraffic\Sender\SmartDelivery` (extends `Bitrix\MessageService\Sender\Base`)

- **`getId()`** — всегда `smstraffic_smartdelivery`.
- **`getName()` / `getShortName()`** — подписи в админке (с локализацией из `lang/`).
- **`canUse()`** — `true`, если подключён `messageservice` и в опциях модуля заданы непустые `login` и `password`.
- **`getFromList()`** — список originator из настройки `originators` (строки через перевод строки или запятую); если пусто — виртуальный пункт `default`.
- **`sendMessage(array $messageFields): SendMessage`** — валидация, вызов API через `ApiClient`, при успехе `setAccepted()`, при ошибке — `addError()` с текстом от API или транспорта.
- Внутренние методы: **`resolveRusMode()`** — параметр `rus` для API (`0` / `1` / `5`, иначе принудительно `5`); **`normalizePhonesForApi()`** — только цифры, несколько номеров через запятую.

### `Smstraffic\SmartDelivery\ApiClient`

- **`setTimeouts(int $socket, int $stream): self`** — таймауты для `Bitrix\Main\Web\HttpClient` (некорректные значения заменяются на значения по умолчанию).
- **`send(array $postFields): array`** — объединение с учётными данными, POST на primary, при необходимости повтор на secondary; возвращает унифицированный массив с ключом **`success`** и при ошибках — `description`, `error`, `code`, `raw`, `http_status`.

### Класс модуля `smstraffic` (`install/index.php`)

- **`DoInstall()`** — `ModuleManager::registerModule`, регистрация обработчика `\Smstraffic\Handlers::onGetSmsSenders`.
- **`DoUninstall()`** — снятие обработчика, `Option::delete` для всех опций модуля, `ModuleManager::unRegisterModule`.

Подробные PHPDoc по методам — в исходниках в `lib/` и в комментариях к `include.php`, `options.php`, `default_option.php`.
