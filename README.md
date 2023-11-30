# Пример использования
```php
<?php
use Tryhardy\BitrixFilter\ElementsFilter;

$filter = ElementsFilter::getInstance();
$filter->add('IBLOCK_ID', 1);
$filter->add('ACTIVE', 'Y');
$filter->add('PROPERTY_TAGS', ['tag1', 'tag2']);

//Добавить фильтр по разделу без подразделов
$filter->add('SECTION_ID|IBLOCK_SECTION.ID|IBLOCK_SECTION_ID', 1);
//Добавить фильтр по разделу с подразделами
$filter->addSection('SECTION_ID|IBLOCK_SECTION.ID|IBLOCK_SECTION_ID', 1, true);
```