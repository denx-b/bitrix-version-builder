## Bitrix Version Builder

Библиотека берёт на себя рутинную работу по **генерации базовой структуры** и **сборке обновлений** модулей 1С-Битрикс:
- автоматическое создание структуры модуля;
- автоматическое создание архива новой версии модуля;
- определение изменённых файлов по истории коммитов в git;
- автоматическое декодирование кириллических языковых файлов из UTF-8 в windows-1251;
- автоматическое описание обновления (description. ru) из комментария последнего коммита.

### Использование
Установка библиотеки из composer:
```sh
composer require denx-b/bitrix-version-builder --dev
```

Обязательно в корне модуля должен быть git:
```sh
git init
```

Генерация базовой структуры модуля:
```sh
./vendor/bin/console bitrix:create-module
```

Сборка обновления:
```sh
./vendor/bin/console bitrix:version-build
```

В итоге структура модуля может выглядеть следующим образом:
```php
/*
aspro.max/
  ├─ .versions/
  |   ├─ .last_version.zip
  |   ├─ 1.1.3.zip
  |   └─ 1.1.4.zip
  ├─ install/
  ├─ lang/
  ├─ vendor/
  ├─ composer.json
  ├─ composer.lock
  ├─ include.php
  ├─ options.php
  └─ options_conf.php
*/
```

Команда генерации `bitrix:create-module` после запуска задаст вам несколько вопросов, для генерации класса установки, кода модуля, название, описание модуля и так далее.
Вы можете использовать данную библиотеку в уже существующих модулях со своей структурой и файлами, то есть шаг по генерации структуры можно пропусить и пользоваться только сборкой версий `bitrix:version-build`

<img src="https://dbogdanoff.ru/upload/bitrix-version-builder-1011.jpeg" alt="bitrix:create-module" width="700"/>

### Как работает сборка обновлений?
Архивы версий складываются в директорию .versions:
```php
/*
aspro.max/
  ├─ .versions/
  |   ├─ .last_version.zip
  |   ├─ 1.1.3.zip
  |   └─ 1.1.4.zip
*/
```

Название версии берётся из файла модуля /install/version.php

    <?php
    $arModuleVersion = array(  
        "VERSION" => "1.1.4", // <-- 1.1.4.zip
        "VERSION_DATE" => "2019-12-04 18:52:00"  
    );
В архив обновлений попадают файлы между последним и предыдущим тегами или вообще все файлы (_.last_version.zip_), если тегов менее двух.

<img src="https://dbogdanoff.ru/upload/bitrix-version-builder-1010.jpeg" alt="bitrix:version-build" width="700"/>

[Подробнее](https://github.com/denx-b/bitrix-version-builder/issues/4) о попадании файлов в архив и именовании архива.

### epilog_after.php
Развивайте ваш модуль, комитьте, фокусируйтесь на задаче, а рутинную работу возложите на сборщик! Как будете готовы к публикации новой версии, сново просто выполните команду `./vendor/bin/console bitrix:version-build`
