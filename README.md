# yii2-widget-file-upload7

Виджет для загрузки файла
Загружает на сервер AvatarCloud или на локальный сервер если сформировано окружение

## Окружение для загрузки файла на локальный сервер




## Установка
## Пример использования

```php
<?= \iAvatar777\widgets\FileUpload7\FileUpload::widget([
    'model'      => $model,
    'attribute'  => 'file',
]) ?>
```



## Параметры

FileUpload7.init(options)

- `controller` - идентификатор контроллера который направлен на \iAvatar777\assets\JqueryUpload1\Upload2Controller
- `selector` - запрос JQuery идентифицирующий элемент (input) загрузки. по умолчанию `'.FileUpload7'`
- `maxSize` - макс размер файла в KB
- `server` - путь к внешнему серверу, по умолчанию '' (Например 'https://cloud1.i-am-avatar.com')
- `allowedExtensions` - массив расширений которые возможны для загрузки. По умолчанию `['jpg', 'jpeg', 'png']`
- `accept` - mime тип который можно загрузить. По умолчанию `'image/*'`
- `data` - Массив данных которые надо передать на действие сохранения файла

## Как работает

Определить сервер для загрузки
