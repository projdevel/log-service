# Log Service

Микросервис для сбора логов от агентов и публикации в RabbitMQ

## О проекте

Сервис принимает batch логи в формате JSON, валидирует их и асинхронно отправляет в RabbitMQ. Подходит для сбора логов от множества агентов с высокой нагрузкой.

### Функциональность

- ✅ Прием batch логов через REST API
- ✅ Валидация входящих данных
- ✅ Асинхронная отправка в RabbitMQ
- ✅ Поддержка приоритизации очередей
- ✅ Docker контейнеризация
- ✅ Unit и Integration тесты

## Требования

- **Docker**
- **Git**
- **curl** (для тестирования API)

### 1. Клонировать репозиторий

```bash
cd log-service

```
### 2. Собрать образ
```bash
docker-compose build --no-cache
```
### 3. Запустить контейнер
```bash
docker-compose up -d
```

### 4 Статус контейнеров
```bash
docker-compose ps
```

### 5 Установить зависимости
```bash
mkdir vendor/ && chmod 777 vendor/   <- костыль, но для тестового пойдет
docker-compose exec php composer install
```

### 6 Отправить тестовый запрос
```bash
curl -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{"logs":[{"timestamp":"2026-02-26T10:30:45Z","level":"error","service":"test","message":"Hello Docker"}]}'
```
Ожидаемый ответ
```bash
{
  "status": "accepted",
  "batch_id": "batch_20260311113438_72fdafbb9c13868e",
  "logs_count": 1
}
```
Запрос с несколькими логами

```bash
curl -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-02-26T10:30:45Z",
        "level": "error",
        "service": "auth-service",
        "message": "Error log"
      },
      {
        "timestamp": "2026-02-26T10:30:46Z",
        "level": "info",
        "service": "api-gateway",
        "message": "Info log"
      }
    ]
  }'
  ```
  Ожидаемый ответ:
```bash  
{"status":"accepted","batch_id":"batch_20260311123612_bb4672353cbecdb9","logs_count":2}
  ```
  Невалидный запрос:
```bash  
curl -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-02-26T10:30:45Z",
        "level": "error"
      }
    ]
  }'
  ```
  Ожидаемый ответ:
```bash
  {"status":"error","message":"Validation failed: Log #0: service is required, Log #0: message is required","errors":["Log #0: service is required","Log #0: message is required"]}
```
Проверить количество сообщений в очереди
```bash
docker-compose exec rabbitmq rabbitmqctl list_queues
```

Ожидаемый ответ:
```bash
Timeout: 60.0 seconds ...
Listing queues for vhost / ...
name    messages
logs.ingest     4
```


## Запуск тестов

### Все тесты
```bash
docker-compose exec php php bin/phpunit
```
### Юнит тест
```bash
docker-compose exec php php bin/phpunit tests/Unit/Service/LogValidatorTest.php
```

### Интеграционный тест
```bash
docker-compose exec php php bin/phpunit tests/Integration/Controller/LogIngestionControllerTest.php 
```

### Остановка контейнеров
```bash
docker-compose down
```

# Дополнение
#### Сделал на php8.4, что отличается от версии в ТЗ (php8.2).
#### Также просто собрал базовый проект symfony, потому что тут только API 
#### В корень проекта прикрепил видеофайл с демонстрацией работы
