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
